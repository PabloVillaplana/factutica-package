<?php

use FactuTica\FactuticaCR\Exceptions\InvalidReceiptException;
use FactuTica\FactuticaCR\Services\Validators\AssortmentValidator;

function assortmentData(array $surtidoOverrides = [], array $lineOverrides = []): array
{
    $surtidoItem = array_merge([
        'CodigoCABYSSurtido' => '6803000000000',
        'CantidadSurtido' => '2',
        'UnidadMedidaSurtido' => 'Sp',
        'DetalleSurtido' => 'Producto combo',
        'PrecioUnitarioSurtido' => '5000.00',
        'MontoTotalSurtido' => '10000.00',
        'SubTotalSurtido' => '10000.00',
        'BaseImponibleSurtido' => '10000.00',
        'ImpuestoSurtido' => [[
            'CodigoImpuestoSurtido' => '01',
            'CodigoTarifaIVASurtido' => '08',
            'TarifaSurtido' => '13.00',
            'MontoImpuestoSurtido' => '1300.00',
        ]],
    ], $surtidoOverrides);

    $line = array_merge([
        'NumeroLinea' => 1,
        'CodigoCABYS' => '6803000000000',
        'Cantidad' => '1',
        'UnidadMedida' => 'Sp',
        'Detalle' => 'Combo',
        'PrecioUnitario' => '10000.00',
        'MontoTotal' => '10000.00',
        'SubTotal' => '10000.00',
        'BaseImponible' => '10000.00',
        'ImpuestoAsumidoEmisorFabrica' => '0',
        'ImpuestoNeto' => '1300.00',
        'MontoTotalLinea' => '11300.00',
        'DetalleSurtido' => [
            'LineaDetalleSurtido' => [$surtidoItem],
        ],
    ], $lineOverrides);

    return [
        'DetalleServicio' => [
            'LineaDetalle' => [$line],
        ],
    ];
}

// ── Valid data ───────────────────────────────────────────────────────

it('passes with valid assortment data', function () {
    $validator = app(AssortmentValidator::class);
    $validator->validate(assortmentData());
})->throwsNoExceptions();

it('skips validation when no DetalleSurtido present', function () {
    $validator = app(AssortmentValidator::class);
    $validator->validate([
        'DetalleServicio' => [
            'LineaDetalle' => [[
                'NumeroLinea' => 1,
                'CodigoCABYS' => '6803000000000',
            ]],
        ],
    ]);
})->throwsNoExceptions();

// ── MontoTotalSurtido ────────────────────────────────────────────────

it('throws when MontoTotalSurtido is incorrect', function () {
    $validator = app(AssortmentValidator::class);
    $validator->validate(assortmentData([
        'MontoTotalSurtido' => '9000.00', // should be 10000 (2 × 5000)
    ]));
})->throws(InvalidReceiptException::class, 'MontoTotalSurtido');

// ── DescuentoSurtido bounds ─────────────────────────────────────────

it('throws when discount exceeds MontoTotalSurtido', function () {
    $validator = app(AssortmentValidator::class);
    $validator->validate(assortmentData([
        'DescuentoSurtido' => [[
            'MontoDescuentoSurtido' => '15000.00',
            'CodigoDescuentoSurtido' => '04',
        ]],
    ]));
})->throws(InvalidReceiptException::class, 'fuera de rango');

it('throws when discount is negative', function () {
    $validator = app(AssortmentValidator::class);
    $validator->validate(assortmentData([
        'DescuentoSurtido' => [[
            'MontoDescuentoSurtido' => '-100.00',
            'CodigoDescuentoSurtido' => '04',
        ]],
    ]));
})->throws(InvalidReceiptException::class, 'fuera de rango');

// ── SubTotalSurtido ─────────────────────────────────────────────────

it('throws when SubTotalSurtido is incorrect', function () {
    $validator = app(AssortmentValidator::class);
    $validator->validate(assortmentData([
        'DescuentoSurtido' => [[
            'MontoDescuentoSurtido' => '1000.00',
            'CodigoDescuentoSurtido' => '04',
        ]],
        'SubTotalSurtido' => '10000.00', // should be 9000 (10000 - 1000)
    ]));
})->throws(InvalidReceiptException::class, 'SubTotalSurtido');

it('passes SubTotalSurtido with discount correctly calculated', function () {
    $validator = app(AssortmentValidator::class);
    $validator->validate(assortmentData([
        'DescuentoSurtido' => [[
            'MontoDescuentoSurtido' => '1000.00',
            'CodigoDescuentoSurtido' => '04',
        ]],
        'SubTotalSurtido' => '9000.00',
        'BaseImponibleSurtido' => '9000.00',
        'ImpuestoSurtido' => [[
            'CodigoImpuestoSurtido' => '01',
            'CodigoTarifaIVASurtido' => '08',
            'TarifaSurtido' => '13.00',
            'MontoImpuestoSurtido' => '1170.00', // 9000 × 0.13
        ]],
    ]));
})->throwsNoExceptions();

// ── BaseImponibleSurtido ────────────────────────────────────────────

it('throws when BaseImponibleSurtido is incorrect', function () {
    $validator = app(AssortmentValidator::class);
    $validator->validate(assortmentData([
        'BaseImponibleSurtido' => '5000.00', // should be 10000
    ]));
})->throws(InvalidReceiptException::class, 'BaseImponibleSurtido');

// ── ImpuestoSurtido formulas ────────────────────────────────────────

it('throws when IVA MontoImpuestoSurtido is incorrect', function () {
    $validator = app(AssortmentValidator::class);
    $validator->validate(assortmentData([
        'ImpuestoSurtido' => [[
            'CodigoImpuestoSurtido' => '01',
            'CodigoTarifaIVASurtido' => '08',
            'TarifaSurtido' => '13.00',
            'MontoImpuestoSurtido' => '999.00', // should be 1300 (10000 × 0.13)
        ]],
    ]));
})->throws(InvalidReceiptException::class, 'MontoImpuestoSurtido');

it('passes with correct selectivo code 02 calculation', function () {
    $validator = app(AssortmentValidator::class);
    $validator->validate(assortmentData([
        'BaseImponibleSurtido' => '11000.00', // SubTotal(10000) + selectivo(1000)
        'ImpuestoSurtido' => [[
            'CodigoImpuestoSurtido' => '02',
            'TarifaSurtido' => '10.00',
            'MontoImpuestoSurtido' => '1000.00', // SubTotal(10000) × 10%
        ]],
    ]));
})->throwsNoExceptions();

it('passes with correct alcoholico code 04 calculation', function () {
    $validator = app(AssortmentValidator::class);
    $validator->validate(assortmentData([
        'BaseImponibleSurtido' => '10100.00', // SubTotal(10000) + alcoholico(100)
        'ImpuestoSurtido' => [[
            'CodigoImpuestoSurtido' => '04',
            'DatosImpuestoEspecificoSurtido' => [
                'ProporcionSurtido' => '0.50',
                'ImpuestoUnidadSurtido' => '100.00',
            ],
            'MontoImpuestoSurtido' => '100.00', // Cantidad(2) × Proporcion(0.5) × ImpUnidad(100)
        ]],
    ]));
})->throwsNoExceptions();

it('passes with correct tabaco code 06 calculation', function () {
    $validator = app(AssortmentValidator::class);
    $validator->validate(assortmentData([
        'ImpuestoSurtido' => [[
            'CodigoImpuestoSurtido' => '06',
            'DatosImpuestoEspecificoSurtido' => [
                'CantidadUnidadMedidaSurtido' => '10',
                'ImpuestoUnidadSurtido' => '5.00',
            ],
            'MontoImpuestoSurtido' => '100.00', // Cantidad(2) × CantUM(10) × ImpUnidad(5)
        ]],
    ]));
})->throwsNoExceptions();

// ── DatosImpuestoEspecificoSurtido required ─────────────────────────

it('throws when DatosImpuestoEspecifico missing for code 04', function () {
    $validator = app(AssortmentValidator::class);
    $validator->validate(assortmentData([
        'BaseImponibleSurtido' => '10100.00', // adjusted for additive tax
        'ImpuestoSurtido' => [[
            'CodigoImpuestoSurtido' => '04',
            'MontoImpuestoSurtido' => '100.00',
        ]],
    ]));
})->throws(InvalidReceiptException::class, 'DatosImpuestoEspecificoSurtido requerido');

it('throws when DatosImpuestoEspecifico missing for code 05', function () {
    $validator = app(AssortmentValidator::class);
    $validator->validate(assortmentData([
        'BaseImponibleSurtido' => '10100.00', // adjusted for additive tax
        'ImpuestoSurtido' => [[
            'CodigoImpuestoSurtido' => '05',
            'MontoImpuestoSurtido' => '100.00',
        ]],
    ]));
})->throws(InvalidReceiptException::class, 'DatosImpuestoEspecificoSurtido requerido');

it('throws when DatosImpuestoEspecifico missing for code 06', function () {
    $validator = app(AssortmentValidator::class);
    $validator->validate(assortmentData([
        'ImpuestoSurtido' => [[
            'CodigoImpuestoSurtido' => '06',
            'MontoImpuestoSurtido' => '100.00',
        ]],
    ]));
})->throws(InvalidReceiptException::class, 'DatosImpuestoEspecificoSurtido requerido');

// ── TarifaSurtido / CodigoTarifaIVASurtido ──────────────────────────

it('throws when TarifaSurtido missing for code 01', function () {
    $validator = app(AssortmentValidator::class);
    $validator->validate(assortmentData([
        'ImpuestoSurtido' => [[
            'CodigoImpuestoSurtido' => '01',
            'CodigoTarifaIVASurtido' => '08',
            'MontoImpuestoSurtido' => '1300.00',
        ]],
    ]));
})->throws(InvalidReceiptException::class, 'TarifaSurtido requerida');

it('throws when CodigoTarifaIVASurtido missing for code 01', function () {
    $validator = app(AssortmentValidator::class);
    $validator->validate(assortmentData([
        'ImpuestoSurtido' => [[
            'CodigoImpuestoSurtido' => '01',
            'TarifaSurtido' => '13.00',
            'MontoImpuestoSurtido' => '1300.00',
        ]],
    ]));
})->throws(InvalidReceiptException::class, 'CodigoTarifaIVASurtido requerido');

it('throws when TarifaSurtido does not match CodigoTarifaIVASurtido', function () {
    $validator = app(AssortmentValidator::class);
    $validator->validate(assortmentData([
        'ImpuestoSurtido' => [[
            'CodigoImpuestoSurtido' => '01',
            'CodigoTarifaIVASurtido' => '08', // expects 13%
            'TarifaSurtido' => '8.00',        // wrong
            'MontoImpuestoSurtido' => '800.00',
        ]],
    ]));
})->throws(InvalidReceiptException::class, 'TarifaSurtido');

// ── Special discount (regalía) uses MontoTotalSurtido for IVA base ──

it('uses MontoTotalSurtido as IVA base when special discount present', function () {
    $validator = app(AssortmentValidator::class);
    // Descuento código 01 (regalía) → IVA base = MontoTotalSurtido, not BaseImponible
    $validator->validate(assortmentData([
        'DescuentoSurtido' => [[
            'MontoDescuentoSurtido' => '2000.00',
            'CodigoDescuentoSurtido' => '01', // regalía
        ]],
        'SubTotalSurtido' => '8000.00',
        'BaseImponibleSurtido' => '8000.00',
        'ImpuestoSurtido' => [[
            'CodigoImpuestoSurtido' => '01',
            'CodigoTarifaIVASurtido' => '08',
            'TarifaSurtido' => '13.00',
            'MontoImpuestoSurtido' => '1300.00', // MontoTotal(10000) × 13%, NOT BaseImponible(8000)
        ]],
    ]));
})->throwsNoExceptions();

// ── Invalid UnidadMedidaSurtido ─────────────────────────────────────

it('throws when UnidadMedidaSurtido is invalid', function () {
    $validator = app(AssortmentValidator::class);
    $validator->validate(assortmentData([
        'UnidadMedidaSurtido' => 'INVALID_UNIT',
    ]));
})->throws(InvalidReceiptException::class, 'UnidadMedidaSurtido');