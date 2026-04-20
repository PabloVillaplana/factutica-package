<?php

namespace FactuTica\FactuticaCR\Providers;

use FactuTica\FactuticaCR\Contracts\ProviderInterface;
use FactuTica\FactuticaCR\Exceptions\HaciendaException;
use FactuTica\FactuticaCR\Providers\HaciendaProvider;

class ProviderFactoryService
{
    /** @var array<string, class-string<ProviderInterface>> */
    private array $providers = [
        'hacienda' => HaciendaProvider::class,
    ];

    public function __construct(
        private readonly \Illuminate\Contracts\Foundation\Application $app,
    ) {}

    /**
     * Resuelve el provider configurado o el especificado por nombre.
     *
     * @throws HaciendaException
     */
    public function make(?string $name = null): ProviderInterface
    {
        $name = $name ?? config('invoicing-cr.invoicing.provider', 'hacienda');

        if (! isset($this->providers[$name])) {
            throw new HaciendaException("Provider de facturación no soportado: [{$name}]");
        }

        return $this->app->make($this->providers[$name]);
    }

    /**
     * Registra un provider personalizado.
     */
    public function register(string $name, string $class): void
    {
        $this->providers[$name] = $class;
    }
}