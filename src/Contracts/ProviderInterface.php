<?php

namespace FactuTica\FactuticaCR\Contracts;

use FactuTica\FactuticaCR\Providers\ProviderResponse;

interface ProviderInterface
{
    /**
     * Envía un comprobante al provider.
     */
    public function send(Sendable $receipt, array $data): ProviderResponse;

    /**
     * Consulta el estado de un comprobante.
     */
    public function getStatus(string $key): array;

    /**
     * Nombre del provider.
     */
    public function getName(): string;
}