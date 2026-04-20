<?php

namespace FactuTica\FactuticaCR\Providers;

use Illuminate\Support\Facades\Http;
use FactuTica\FactuticaCR\Contracts\ProviderInterface;
use FactuTica\FactuticaCR\Contracts\Sendable;
use FactuTica\FactuticaCR\Exceptions\HaciendaException;
use FactuTica\FactuticaCR\Services\Hacienda\Idp\HaciendaIdpService;

/**
 * Provider que envía comprobantes directamente a la API de Hacienda.
 */
class HaciendaProvider implements ProviderInterface
{
    public function __construct(
        private readonly HaciendaIdpService $idpService,
    ) {}

    /**
     * @throws HaciendaException
     */
    public function send(Sendable $receipt, array $data): ProviderResponse
    {
        $token = $this->idpService->getAccessToken();
        $endpoint = $this->getRecepcionEndpoint();

        $response = Http::timeout(15)->withToken($token)
            ->post($endpoint, $data);

        if ($response->failed()) {
            throw new HaciendaException(
                "Error al enviar comprobante a Hacienda: {$response->body()}",
                $response->status()
            );
        }

        try {
            $body = $response->json() ?? [];
        } catch (\Throwable) {
            $body = [];
        }

        return new ProviderResponse(
            clave: $body['clave'] ?? '',
            fecha: $body['fecha'] ?? now()->toIso8601String(),
            httpStatus: $response->status(),
            signedXml: $body['data'] ?? null,
        );
    }

    /**
     * @throws HaciendaException
     */
    public function getStatus(string $key): array
    {
        $token = $this->idpService->getAccessToken();
        $endpoint = rtrim($this->getRecepcionEndpoint(), '/').'/'.$key;

        $response = Http::timeout(15)->withToken($token)->get($endpoint);

        if ($response->failed()) {
            throw new HaciendaException(
                "Error al consultar estado en Hacienda: {$response->body()}",
                $response->status()
            );
        }

        return $response->json() ?? [];
    }

    public function getName(): string
    {
        return 'hacienda';
    }

    private function getRecepcionEndpoint(): string
    {
        $ambiente = config('invoicing-cr.invoicing.ambiente', 'sandbox');

        return config("invoicing-cr.hacienda.endpoints.{$ambiente}.recepcion");
    }
}