<?php

namespace FactuTica\FactuticaCR\Tests;

use FactuTica\FactuticaCR\InvoicingServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            InvoicingServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Facturacion' => \FactuTica\FactuticaCR\Facades\Facturacion::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('invoicing-cr.invoicing.ambiente', 'sandbox');
        $app['config']->set('invoicing-cr.invoicing.provider', 'hacienda');
        $app['config']->set('invoicing-cr.invoicing.emisor.nombre', 'Test Emisor S.A.');
        $app['config']->set('invoicing-cr.invoicing.emisor.cedula', '3101123456');
        $app['config']->set('invoicing-cr.invoicing.emisor.tipo_cedula', '02');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}