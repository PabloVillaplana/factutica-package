<?php

namespace FactuTica\FactuticaCR\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use FactuTica\FactuticaCR\Contracts\Sendable;
use FactuTica\FactuticaCR\Models\SentReceipt;
use FactuTica\FactuticaCR\Providers\ProviderFactoryService;
use FactuTica\FactuticaCR\Services\Hacienda\XmlSignerService;
use FactuTica\FactuticaCR\Services\XmlGenerator\MensajeReceptorXmlGeneratorService;
use FactuTica\FactuticaCR\Traits\SendsToProvider;

class SendSentReceiptToProviderJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SendsToProvider, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var int[] */
    public array $backoff = [30, 60, 90];

    /**
     * @param  array  $data  Datos validados del request de recepción.
     */
    public function __construct(
        public SentReceipt $receipt,
        public array $data,
    ) {}

    public function uniqueId(): string
    {
        return 'sent-receipt-'.$this->receipt->id;
    }

    protected function getSendable(): Sendable
    {
        return $this->receipt;
    }

    protected function getJobName(): string
    {
        return 'SendSentReceiptToProviderJob';
    }

    public function handle(
        ProviderFactoryService $factory,
        MensajeReceptorXmlGeneratorService $xmlGenerator,
        XmlSignerService $signer,
    ): void {
        $sentReceipt = $this->receipt;
        $data = $this->data;

        // Mapear reception_status a código de mensaje y tipo de consecutivo
        [$messageCode, $typeCode] = match ($data['reception_status']) {
            'accepted'            => ['1', '05'],
            'partially_accepted'  => ['2', '06'],
            'rejected'            => ['3', '07'],
        };

        // Generar consecutivo del receptor para este tipo de mensaje
        $establishment = (int) ($data['establishment'] ?? config('invoicing-cr.invoicing.defaults.establishment', 1));
        $terminal = (int) ($data['terminal'] ?? config('invoicing-cr.invoicing.defaults.terminal', 1));

        $consecutive = $this->getNextReceptionConsecutive($typeCode, $establishment, $terminal);

        // Construir clave consecutiva del receptor (20 dígitos)
        $consecutiveKey = str_pad((string) $establishment, 3, '0', STR_PAD_LEFT)
            .str_pad((string) $terminal, 5, '0', STR_PAD_LEFT)
            .$typeCode
            .str_pad((string) $consecutive, 10, '0', STR_PAD_LEFT);

        // Datos del receptor (nosotros)
        $receptorId = config('invoicing-cr.invoicing.emisor.cedula');
        $receptorTipo = config('invoicing-cr.invoicing.emisor.tipo_cedula', '01');

        // Construir datos para MensajeReceptor XML
        $mensajeData = [
            'Clave'                        => $data['clave'],
            'NumeroCedulaEmisor'           => $data['issuer_number'],
            'FechaEmisionDoc'              => $data['emission_date'],
            'Mensaje'                      => $messageCode,
            'DetalleMensaje'               => $data['reception_message'] ?? null,
            'MontoTotalImpuesto'           => isset($data['tax_amount']) ? (string) $data['tax_amount'] : null,
            'CodigoActividad'              => $data['economic_activity_code'] ?? null,
            'CondicionImpuesto'            => $data['tax_condition_code'] ?? null,
            'MontoTotalImpuestoAcreditar'  => isset($data['tax_credited']) ? (string) $data['tax_credited'] : null,
            'MontoTotalDeGastoAplicable'   => isset($data['total_expense']) ? (string) $data['total_expense'] : null,
            'TotalFactura'                 => (string) $data['total_voucher'],
            'NumeroCedulaReceptor'         => $receptorId,
            'NumeroConsecutivoReceptor'    => $consecutiveKey,
        ];

        // Generar y firmar XML
        $xml = $xmlGenerator->generate($mensajeData);
        $signedXml = $signer->sign($xml);

        // Construir payload para Hacienda
        $haciendaPayload = [
            'clave'              => $data['clave'],
            'fecha'              => $data['emission_date'],
            'emisor'             => [
                'tipoIdentificacion'   => $data['issuer_identification_type'],
                'numeroIdentificacion' => $data['issuer_number'],
            ],
            'receptor'           => [
                'tipoIdentificacion'   => $receptorTipo,
                'numeroIdentificacion' => $receptorId,
            ],
            'comprobanteXml'     => base64_encode($signedXml),
            'callbackUrl'        => url(config('invoicing-cr.invoicing.callback_url')),
            'consecutivoReceptor' => $consecutiveKey,
        ];

        // Enviar
        $provider = $factory->make();
        $response = $provider->send($sentReceipt, $haciendaPayload);

        $sentReceipt->markAsSent(
            uiKey: (strlen($response->clave) === \FactuTica\FactuticaCR\Constants::CLAVE_LENGTH) ? $response->clave : $data['clave'],
            signedXml: $signedXml,
        );

        // Actualizar datos de recepción
        $sentReceipt->update([
            'consecutive_number' => $consecutiveKey,
        ]);

        Log::info('SendSentReceiptToProviderJob: enviado', [
            'id'               => $sentReceipt->getUiKey(),
            'receipt_type'     => $sentReceipt->getReceiptType()->value,
            'ui_key'           => $response->clave,
            'consecutive_key'  => $consecutiveKey,
        ]);
    }

    /**
     * Obtiene el siguiente consecutivo para mensajes de recepción (tipos 05/06/07).
     * Usa la misma tabla de consecutivos pero con claves 'MSG-05', 'MSG-06', 'MSG-07'.
     */
    private function getNextReceptionConsecutive(string $typeCode, int $establishment, int $terminal): int
    {
        return DB::transaction(function () use ($typeCode, $establishment, $terminal) {
            $key = "MSG-{$typeCode}";

            $record = \FactuTica\FactuticaCR\Models\ReceiptConsecutive::lockForUpdate()->firstOrCreate(
                ['receipt_type' => $key, 'establishment' => $establishment, 'terminal' => $terminal],
                ['last_number' => 0]
            );

            $record->increment('last_number');
            $record->refresh();

            return $record->last_number;
        });
    }
}
