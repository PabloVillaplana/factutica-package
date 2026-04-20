<?php

use FactuTica\FactuticaCR\Exceptions\InvalidReceiptException;
use FactuTica\FactuticaCR\Services\Validators\DetailLineValidator;

function validLineData(array $overrides = []): array
{
    return [
        'DetalleServicio' => [
            'LineaDetalle' => [array_merge([
                'NumeroLinea' => 1,
                'CodigoCABYS' => '8410101000100',
                'Cantidad' => '2',
                'UnidadMedida' => 'Sp',
                'Detalle' => 'Servicio test',
                'PrecioUnitario' => '5000.00',
                'MontoTotal' => '10000.00',
                'SubTotal' => '10000.00',
                'BaseImponible' => '10000.00',
                'Impuesto' => [['Codigo' => '01', 'CodigoTarifaIVA' => '08', 'Tarifa' => '13.00', 'Monto' => '1300.00']],
                'ImpuestoNeto' => '1300.00',
                'MontoTotalLinea' => '11300.00',
            ], $overrides)],
        ],
    ];
}

it('passes with valid data', function () {
    $validator = app(DetailLineValidator::class);

    $validator->validate(validLineData());
})->throwsNoExceptions();

it('throws when MontoTotal is incorrect', function () {
    $validator = app(DetailLineValidator::class);
    $data = validLineData(['MontoTotal' => '9999.00']);

    expect(fn () => $validator->validate($data))
        ->toThrow(InvalidReceiptException::class);
});

it('throws when SubTotal is incorrect', function () {
    $validator = app(DetailLineValidator::class);
    $data = validLineData([
        'Descuento' => [['MontoDescuento' => '1000.00', 'NaturalezaDescuento' => 'Promo']],
        'SubTotal' => '8000.00',
        'BaseImponible' => '8000.00',
        'Impuesto' => [['Codigo' => '01', 'CodigoTarifaIVA' => '08', 'Tarifa' => '13.00', 'Monto' => '1170.00']],
        'ImpuestoNeto' => '1170.00',
        'MontoTotalLinea' => '9170.00',
    ]);

    expect(fn () => $validator->validate($data))
        ->toThrow(InvalidReceiptException::class);
});

it('passes SubTotal with multiple discounts summing correctly', function () {
    $validator = app(DetailLineValidator::class);
    $data = validLineData([
        'Descuento' => [
            ['MontoDescuento' => '500.00', 'NaturalezaDescuento' => 'Promo 1'],
            ['MontoDescuento' => '500.00', 'NaturalezaDescuento' => 'Promo 2'],
        ],
        'SubTotal' => '9000.00',
        'BaseImponible' => '9000.00',
        'Impuesto' => [['Codigo' => '01', 'CodigoTarifaIVA' => '08', 'Tarifa' => '13.00', 'Monto' => '1170.00']],
        'ImpuestoNeto' => '1170.00',
        'MontoTotalLinea' => '10170.00',
    ]);

    $validator->validate($data);
})->throwsNoExceptions();

it('throws when discount exceeds MontoTotal', function () {
    $validator = app(DetailLineValidator::class);
    $data = validLineData([
        'Descuento' => [['MontoDescuento' => '15000.00', 'NaturalezaDescuento' => 'Big discount']],
    ]);

    expect(fn () => $validator->validate($data))
        ->toThrow(InvalidReceiptException::class);
});

it('throws when BaseImponible is incorrect', function () {
    $validator = app(DetailLineValidator::class);
    $data = validLineData(['BaseImponible' => '7777.00']);

    expect(fn () => $validator->validate($data))
        ->toThrow(InvalidReceiptException::class);
});

it('skips BaseImponible validation when IVACobradoFabrica is 01', function () {
    $validator = app(DetailLineValidator::class);
    $data = validLineData([
        'IVACobradoFabrica' => '01',
        'BaseImponible' => '99999.00',
    ]);

    $validator->validate($data);
})->throwsNoExceptions();

it('throws when ImpuestoNeto is incorrect', function () {
    $validator = app(DetailLineValidator::class);
    $data = validLineData(['ImpuestoNeto' => '999.00']);

    expect(fn () => $validator->validate($data))
        ->toThrow(InvalidReceiptException::class);
});

it('throws when MontoTotalLinea is incorrect', function () {
    $validator = app(DetailLineValidator::class);
    $data = validLineData(['MontoTotalLinea' => '50000.00']);

    expect(fn () => $validator->validate($data))
        ->toThrow(InvalidReceiptException::class);
});

it('throws when MontoExportacion is out of bounds (negative)', function () {
    $validator = app(DetailLineValidator::class);
    $data = validLineData(['MontoExportacion' => '-1.00']);

    expect(fn () => $validator->validate($data))
        ->toThrow(InvalidReceiptException::class);
});

it('throws when MontoExportacion exceeds MontoTotal', function () {
    $validator = app(DetailLineValidator::class);
    $data = validLineData(['MontoExportacion' => '20000.00']);

    expect(fn () => $validator->validate($data))
        ->toThrow(InvalidReceiptException::class);
});

it('throws when UnidadMedida is invalid', function () {
    $validator = app(DetailLineValidator::class);
    $data = validLineData(['UnidadMedida' => 'INVALID']);

    expect(fn () => $validator->validate($data))
        ->toThrow(InvalidReceiptException::class);
});

it('throws when VIN is required for transport CABYS but missing', function () {
    $validator = app(DetailLineValidator::class);
    $data = validLineData(['CodigoCABYS' => '6401010100100']);

    expect(fn () => $validator->validate($data))
        ->toThrow(InvalidReceiptException::class);
});

it('passes when LineaDetalle is empty', function () {
    $validator = app(DetailLineValidator::class);
    $data = ['DetalleServicio' => ['LineaDetalle' => []]];

    $validator->validate($data);
})->throwsNoExceptions();

it('passes for a line without taxes', function () {
    $validator = app(DetailLineValidator::class);
    $data = [
        'DetalleServicio' => [
            'LineaDetalle' => [[
                'NumeroLinea' => 1,
                'CodigoCABYS' => '8410101000100',
                'Cantidad' => '3',
                'UnidadMedida' => 'Sp',
                'Detalle' => 'Servicio exento',
                'PrecioUnitario' => '1000.00',
                'MontoTotal' => '3000.00',
                'SubTotal' => '3000.00',
                'BaseImponible' => '3000.00',
                'ImpuestoNeto' => '0',
                'MontoTotalLinea' => '3000.00',
            ]],
        ],
    ];

    $validator->validate($data);
})->throwsNoExceptions();