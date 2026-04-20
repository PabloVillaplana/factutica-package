<?php

use FactuTica\FactuticaCR\Contracts\ProviderInterface;
use FactuTica\FactuticaCR\Exceptions\HaciendaException;
use FactuTica\FactuticaCR\Providers\HaciendaProvider;
use FactuTica\FactuticaCR\Providers\ProviderFactoryService;

it('resolves hacienda provider by default', function () {
    config()->set('invoicing-cr.invoicing.provider', 'hacienda');

    $factory = app(ProviderFactoryService::class);
    $provider = $factory->make();

    expect($provider)->toBeInstanceOf(HaciendaProvider::class);
    expect($provider->getName())->toBe('hacienda');
});

it('throws on unsupported provider', function () {
    $factory = app(ProviderFactoryService::class);
    $factory->make('nonexistent');
})->throws(HaciendaException::class, 'no soportado');

it('allows registering custom providers', function () {
    $factory = app(ProviderFactoryService::class);

    $factory->register('custom', HaciendaProvider::class);
    $provider = $factory->make('custom');

    expect($provider)->toBeInstanceOf(HaciendaProvider::class);
});

it('resolves provider interface from container', function () {
    config()->set('invoicing-cr.invoicing.provider', 'hacienda');

    $provider = app(ProviderInterface::class);

    expect($provider)->toBeInstanceOf(HaciendaProvider::class);
});