<?php

namespace FactuTica\FactuticaCR\Providers;

class ProviderResponse
{
    public function __construct(
        public readonly string $clave,
        public readonly string $fecha,
        public readonly int $httpStatus,
        public readonly ?string $signedXml = null,
    ) {}
}