<?php

namespace FactuTica\FactuticaCR;

use Illuminate\Support\ServiceProvider;
use FactuTica\FactuticaCR\Contracts\InvoicingInterface;
use FactuTica\FactuticaCR\Contracts\ProviderInterface;
use FactuTica\FactuticaCR\Providers\HaciendaProvider;
use FactuTica\FactuticaCR\Providers\ProviderFactoryService;
use FactuTica\FactuticaCR\Services\Hacienda\CertificateLoaderService;
use FactuTica\FactuticaCR\Services\Hacienda\Idp\HaciendaIdpService;
use FactuTica\FactuticaCR\Services\Hacienda\XmlSignerService;
use FactuTica\FactuticaCR\Services\InvoicingService;
use FactuTica\FactuticaCR\Services\KeyGeneratorService;
use FactuTica\FactuticaCR\Services\PayloadTransformerService;
use FactuTica\FactuticaCR\Services\ReceiptBuilderService;
use FactuTica\FactuticaCR\Services\XmlPipelineService;
use FactuTica\FactuticaCR\Services\Validators\AssortmentValidator;
use FactuTica\FactuticaCR\Services\Validators\CalculationValidatorService;
use FactuTica\FactuticaCR\Services\Validators\DetailLineValidator;
use FactuTica\FactuticaCR\Services\Validators\InvoiceSummaryValidator;
use FactuTica\FactuticaCR\Services\Validators\TaxBreakdownValidator;
use FactuTica\FactuticaCR\Services\Validators\TaxCalculationValidator;
use FactuTica\FactuticaCR\Services\XmlGenerator\MensajeReceptorXmlGeneratorService;
use FactuTica\FactuticaCR\Services\XmlGenerator\XmlGeneratorService;

class InvoicingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Configs
        $this->mergeConfigFrom(__DIR__.'/../config/invoicing.php', 'invoicing-cr.invoicing');
        $this->mergeConfigFrom(__DIR__.'/../config/hacienda.php', 'invoicing-cr.hacienda');
        $this->mergeConfigFrom(__DIR__.'/../config/catalogues.php', 'invoicing-cr.catalogues');

        // Servicios de Hacienda
        $this->app->singleton(CertificateLoaderService::class);
        $this->app->singleton(HaciendaIdpService::class);
        $this->app->singleton(XmlGeneratorService::class);
        $this->app->singleton(MensajeReceptorXmlGeneratorService::class);
        $this->app->singleton(KeyGeneratorService::class);
        $this->app->singleton(PayloadTransformerService::class);
        $this->app->singleton(XmlPipelineService::class);

        $this->app->singleton(XmlSignerService::class, function ($app) {
            return new XmlSignerService($app->make(CertificateLoaderService::class));
        });

        // Provider factory y provider por defecto
        $this->app->singleton(ProviderFactoryService::class);

        $this->app->singleton(HaciendaProvider::class, function ($app) {
            return new HaciendaProvider($app->make(HaciendaIdpService::class));
        });

        $this->app->bind(ProviderInterface::class, function ($app) {
            return $app->make(ProviderFactoryService::class)->make();
        });

        // Validadores
        $this->app->singleton(DetailLineValidator::class);
        $this->app->singleton(TaxCalculationValidator::class);
        $this->app->singleton(InvoiceSummaryValidator::class);
        $this->app->singleton(TaxBreakdownValidator::class);
        $this->app->singleton(AssortmentValidator::class);
        $this->app->singleton(CalculationValidatorService::class, function ($app) {
            return new CalculationValidatorService(
                $app->make(DetailLineValidator::class),
                $app->make(TaxCalculationValidator::class),
                $app->make(InvoiceSummaryValidator::class),
                $app->make(TaxBreakdownValidator::class),
                $app->make(AssortmentValidator::class),
            );
        });

        // Receipt builder
        $this->app->singleton(ReceiptBuilderService::class, function ($app) {
            return new ReceiptBuilderService($app->make(KeyGeneratorService::class));
        });

        // Servicio principal
        $this->app->singleton(InvoicingService::class, function ($app) {
            return new InvoicingService(
                $app->make(ProviderFactoryService::class),
                $app->make(ReceiptBuilderService::class),
                $app->make(XmlPipelineService::class),
                $app->make(CalculationValidatorService::class),
            );
        });

        $this->app->bind(InvoicingInterface::class, InvoicingService::class);

        // Aliases
        $this->app->alias(InvoicingService::class, 'invoicing');
        $this->app->alias(XmlSignerService::class, 'invoicing.signer');
        $this->app->alias(HaciendaIdpService::class, 'invoicing.idp');
        $this->app->alias(XmlGeneratorService::class, 'invoicing.xml');
    }

    public function boot(): void
    {
        // Comandos
        if ($this->app->runningInConsole()) {
            $this->commands([
                \FactuTica\FactuticaCR\Console\SetConsecutiveCommand::class,
                \FactuTica\FactuticaCR\Console\CheckCertificateCommand::class,
                \FactuTica\FactuticaCR\Console\SyncCabysCommand::class,
                \FactuTica\FactuticaCR\Console\SyncEconomicActivitiesCommand::class,
            ]);
        }

        // Rutas (desactivables con INVOICING_CR_REGISTER_ROUTES=false)
        if (config('invoicing-cr.invoicing.register_routes', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }

        // Publicar configuración
        $this->publishes([
            __DIR__.'/../config/invoicing.php'  => config_path('invoicing-cr/invoicing.php'),
            __DIR__.'/../config/hacienda.php'   => config_path('invoicing-cr/hacienda.php'),
            __DIR__.'/../config/catalogues.php' => config_path('invoicing-cr/catalogues.php'),
        ], 'invoicing-config');

        // Cargar migraciones
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Publicar migraciones
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'invoicing-migrations');
    }
}