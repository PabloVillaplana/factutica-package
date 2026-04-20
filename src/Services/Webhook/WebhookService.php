<?php

namespace FactuTica\FactuticaCR\Services\Webhook;

use DOMDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use FactuTica\FactuticaCR\Enums\HaciendaStatus;
use FactuTica\FactuticaCR\Enums\ReceiptStatus;
use FactuTica\FactuticaCR\Events\ReceiptAccepted;
use FactuTica\FactuticaCR\Events\ReceiptRejected;
use FactuTica\FactuticaCR\Exceptions\HaciendaException;
use FactuTica\FactuticaCR\Models\HaciendaResponse;
use FactuTica\FactuticaCR\Models\Receipt;

class WebhookService
{
    public function __construct(
        private readonly WebhookVerifierService $verifier,
    ) {}

    /**
     * Procesa la respuesta de Hacienda para un comprobante.
     *
     * Flujo: buscar receipt → verificar autenticidad → persistir respuesta → actualizar status
     *
     * @return array{receipt_id: int, ui_key: string, status: string}
     *
     * @throws HaciendaException
     */
    public function process(string $clave, ?string $indEstado, ?string $respuestaXml, ?string $fecha): array
    {
        $receipt = Receipt::where('ui_key', $clave)->first();

        if (! $receipt) {
            throw new HaciendaException("Comprobante no encontrado para clave: {$clave}");
        }

        // Verificar autenticidad del webhook
        $verification = $this->verifier->verify(
            ['clave' => $clave, 'respuesta-xml' => $respuestaXml],
            $receipt,
        );

        if (! $verification['valid']) {
            Log::warning('WebhookService: verificación fallida', [
                'receipt_id' => $receipt->id,
                'reason'     => $verification['reason'],
            ]);

            throw new HaciendaException("Webhook rechazado: {$verification['reason']}");
        }

        // Idempotencia: si ya fue procesado, no re-procesar
        if ($receipt->hacienda_status !== HaciendaStatus::Pending) {
            Log::info('WebhookService: webhook duplicado ignorado', [
                'receipt_id' => $receipt->id,
                'ui_key'     => $receipt->ui_key,
                'status'     => $receipt->hacienda_status->value,
            ]);

            return [
                'receipt_id' => $receipt->id,
                'ui_key'     => $receipt->ui_key,
                'status'     => $receipt->hacienda_status->value,
            ];
        }

        $status = $this->mapStatus($indEstado);
        $message = $this->extractMessage($respuestaXml);

        DB::transaction(function () use ($receipt, $status, $respuestaXml, $message, $fecha) {
            HaciendaResponse::updateOrCreate(
                ['receipt_id' => $receipt->id],
                [
                    'receipt_key'               => $receipt->ui_key,
                    'hacienda_status'           => $status,
                    'response_xml'     => $respuestaXml,
                    'response_message' => $message,
                    'responded_at'     => $fecha,
                ],
            );

            $receipt->update([
                'hacienda_status' => $status,
                'receipt_status'  => $status === HaciendaStatus::Accepted
                    ? ReceiptStatus::Accepted
                    : ReceiptStatus::Rejected,
            ]);
        });

        // Disparar events DESPUES del commit exitoso con datos frescos
        $receipt->refresh();

        if ($status === HaciendaStatus::Accepted) {
            ReceiptAccepted::dispatch($receipt, $message);
        } else {
            ReceiptRejected::dispatch($receipt, $message);
        }

        Log::info('WebhookService: respuesta procesada', [
            'receipt_id' => $receipt->id,
            'ui_key'     => $receipt->ui_key,
            'status'     => $status->value,
        ]);

        return [
            'receipt_id' => $receipt->id,
            'ui_key'     => $receipt->ui_key,
            'status'     => $status->value,
        ];
    }

    private function mapStatus(?string $status): HaciendaStatus
    {
        return match (strtolower($status ?? '')) {
            'aceptado'  => HaciendaStatus::Accepted,
            'rechazado' => HaciendaStatus::Rejected,
            default     => HaciendaStatus::Pending,
        };
    }

    private function extractMessage(?string $base64Xml): ?string
    {
        if (! $base64Xml) {
            return null;
        }

        $xml = base64_decode($base64Xml, true);

        if ($xml === false) {
            return null;
        }

        $doc = new DOMDocument();

        if (! @$doc->loadXML($xml)) {
            return null;
        }

        $nodes = $doc->getElementsByTagName('DetalleMensaje');

        return $nodes->length > 0 ? $nodes->item(0)->nodeValue : null;
    }
}