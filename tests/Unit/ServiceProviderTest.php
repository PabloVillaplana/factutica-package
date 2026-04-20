<?php

use FactuTica\FactuticaCR\Contracts\InvoicingInterface;
use FactuTica\FactuticaCR\Facades\Facturacion;
use FactuTica\FactuticaCR\Services\Hacienda\CertificateLoaderService;
use FactuTica\FactuticaCR\Services\Hacienda\Idp\HaciendaIdpService;
use FactuTica\FactuticaCR\Services\Hacienda\XmlSignerService;
use FactuTica\FactuticaCR\Services\InvoicingService;
use FactuTica\FactuticaCR\Providers\ProviderFactoryService;

it('registers all singletons in the container', function () {
    expect(app(CertificateLoaderService::class))->toBeInstanceOf(CertificateLoaderService::class);
    expect(app(HaciendaIdpService::class))->toBeInstanceOf(HaciendaIdpService::class);
    expect(app(XmlSignerService::class))->toBeInstanceOf(XmlSignerService::class);
    expect(app(ProviderFactoryService::class))->toBeInstanceOf(ProviderFactoryService::class);
    expect(app(InvoicingService::class))->toBeInstanceOf(InvoicingService::class);
});

it('returns same singleton instance', function () {
    $first = app(CertificateLoaderService::class);
    $second = app(CertificateLoaderService::class);

    expect($first)->toBe($second);
});

it('resolves invoicing interface to invoicing service', function () {
    expect(app(InvoicingInterface::class))->toBeInstanceOf(InvoicingService::class);
});

it('registers aliases correctly', function () {
    expect(app('invoicing'))->toBeInstanceOf(InvoicingService::class);
    expect(app('invoicing.signer'))->toBeInstanceOf(XmlSignerService::class);
    expect(app('invoicing.idp'))->toBeInstanceOf(HaciendaIdpService::class);
});

it('resolves facade accessor', function () {
    expect(Facturacion::getFacadeRoot())->toBeInstanceOf(InvoicingService::class);
});