<?php

namespace FactuTica\FactuticaCR\Tests\Integration;

use FactuTica\FactuticaCR\Tests\TestCase;

/**
 * Base para tests de integración que se conectan al sandbox de Hacienda.
 *
 * Lee las credenciales del .env del proyecto host.
 * Si no están disponibles, los tests se marcan como skipped.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Leer .env del proyecto host (package-dev)
        $envPath = realpath(__DIR__.'/../../../../../.env');

        if (! $envPath || ! file_exists($envPath)) {
            return;
        }

        $env = $this->parseEnvFile($envPath);

        $app['config']->set('invoicing-cr.invoicing.ambiente', $env['INVOICING_CR_AMBIENTE'] ?? 'sandbox');
        $app['config']->set('invoicing-cr.invoicing.provider', $env['INVOICING_CR_PROVIDER'] ?? 'hacienda');

        $app['config']->set('invoicing-cr.hacienda.idp.username', $env['INVOICING_CR_IDP_USERNAME'] ?? null);
        $app['config']->set('invoicing-cr.hacienda.idp.password', $env['INVOICING_CR_IDP_PASSWORD'] ?? null);

        // Resolver ruta del certificado como absoluta al storage del proyecto host
        $certPath = $env['INVOICING_CR_CERTIFICADO_PATH'] ?? null;
        if ($certPath && ! str_starts_with($certPath, '/')) {
            $hostStorage = realpath(__DIR__.'/../../../../../storage');
            $certPath = $hostStorage.'/'.$certPath;
        }

        $app['config']->set('invoicing-cr.hacienda.certificado.path', $certPath);
        $app['config']->set('invoicing-cr.hacienda.certificado.pin', $env['INVOICING_CR_CERTIFICADO_PIN'] ?? null);
    }

    /**
     * Parsea un archivo .env de forma básica.
     */
    private function parseEnvFile(string $path): array
    {
        $env = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $env[trim($key)] = trim(trim($value), '"\'');
        }

        return $env;
    }

    /**
     * Resuelve storage_path relativo al proyecto host.
     */
    protected function resolveHostStoragePath(string $relativePath): string
    {
        return realpath(__DIR__.'/../../../../storage/'.$relativePath) ?: '';
    }
}