<?php

namespace FactuTica\FactuticaCR\Services;

use FactuTica\FactuticaCR\Constants;
use FactuTica\FactuticaCR\Contracts\Sendable;
use FactuTica\FactuticaCR\Enums\ReceiptType;
use FactuTica\FactuticaCR\Events\ReceiptSent;
use FactuTica\FactuticaCR\Models\Receipt;
use FactuTica\FactuticaCR\Providers\ProviderFactoryService;
use FactuTica\FactuticaCR\Providers\ProviderResponse;
use FactuTica\FactuticaCR\Services\Hacienda\XmlSignerService;
use FactuTica\FactuticaCR\Services\XmlGenerator\XmlGeneratorService;

/**
 * Pipeline: genera XML → firma → construye payload Hacienda → envía.
 *
 * Usado por InvoicingService (sync) y SendReceiptToProviderJob (async).
 * Centraliza la lógica para evitar duplicación.
 */
class XmlPipelineService
{
    public function __construct(
        private readonly XmlGeneratorService $xmlGenerator,
        private readonly XmlSignerService $xmlSigner,
        private readonly ProviderFactoryService $providerFactory,
    ) {}

    /**
     * Genera XML, firma, construye payload, envía a Hacienda, y actualiza el receipt.
     */
    public function generateSignAndSend(Sendable $receipt, ReceiptType $type, array $data): ProviderResponse
    {
        $xml = $this->xmlGenerator->generate($type, $data);
        $signedXml = $this->xmlSigner->sign($xml);

        $provider = $this->providerFactory->make();
        $haciendaPayload = $this->buildHaciendaPayload($data, $signedXml);
        $response = $provider->send($receipt, $haciendaPayload);

        $receipt->markAsSent(
            uiKey: (strlen($response->clave) === Constants::CLAVE_LENGTH) ? $response->clave : $receipt->getKey(),
            signedXml: $signedXml,
        );

        if ($receipt instanceof Receipt) {
            ReceiptSent::dispatch($receipt, $response);
        }

        return $response;
    }

    /**
     * Construye el payload que Hacienda espera en el endpoint de recepción.
     */
    private function buildHaciendaPayload(array $data, string $signedXml): array
    {
        $payload = [
            'clave'          => $data['Clave'],
            'fecha'          => $data['FechaEmision'],
            'emisor'         => [
                'tipoIdentificacion'   => $data['Emisor']['Identificacion']['Tipo'],
                'numeroIdentificacion' => $data['Emisor']['Identificacion']['Numero'],
            ],
            'comprobanteXml' => base64_encode($signedXml),
            'callbackUrl'    => url(config('invoicing-cr.invoicing.callback_url')),
        ];

        if (! empty($data['Receptor']['Identificacion'])) {
            $payload['receptor'] = [
                'tipoIdentificacion'   => $data['Receptor']['Identificacion']['Tipo'],
                'numeroIdentificacion' => $data['Receptor']['Identificacion']['Numero'],
            ];
        }

        return $payload;
    }
}
