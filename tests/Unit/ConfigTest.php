<?php

it('loads invoicing config', function () {
    expect(config('invoicing-cr.invoicing.ambiente'))->toBe('sandbox');
    expect(config('invoicing-cr.invoicing.provider'))->toBe('hacienda');
    expect(config('invoicing-cr.invoicing.emisor.nombre'))->toBe('Test Emisor S.A.');
});

it('loads hacienda config with endpoints', function () {
    expect(config('invoicing-cr.hacienda.endpoints.sandbox.idp'))->toContain('rut-stag');
    expect(config('invoicing-cr.hacienda.endpoints.production.idp'))->toContain('/rut/');
    expect(config('invoicing-cr.hacienda.endpoints.sandbox.recepcion'))->toContain('api-sandbox');
    expect(config('invoicing-cr.hacienda.endpoints.production.recepcion'))->not->toContain('sandbox');
});

it('loads catalogues config', function () {
    $units = config('invoicing-cr.catalogues.measurement_units');
    expect($units)->toBeArray();
    expect($units['Unid'])->toBe('Unidad');
    expect($units['Kg'])->toBe('Kilogramo');

    $currencies = config('invoicing-cr.catalogues.currency_codes');
    expect($currencies)->toBeArray();
    expect($currencies['CRC'])->toBe('Colón costarricense');
    expect($currencies['USD'])->toBe('Dólar Americano');
});