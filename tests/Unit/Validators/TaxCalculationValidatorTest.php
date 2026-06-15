<?php

use FactuTica\FactuticaCR\Exceptions\InvalidReceiptException;
use FactuTica\FactuticaCR\Services\Validators\TaxCalculationValidator;
/**
 * Builds a minimal line with a single tax for the validator.
 */
function taxLineData(array $taxOverrides = [], array $lineOverrides = []): array
{
    $tax = array_merge([
        'Codigo'          => '01',
        'CodigoTarifaIVA' => '08',
        'Tarifa'          => 13,
        'Monto'           => 1300,
    ], $taxOverrides);

    $line = array_merge([
        'NumeroLinea'   => 1,
        'Cantidad'      => 1,
        'PrecioUnitario'=> 10000,
        'MontoTotal'    => 10000,
        'SubTotal'      => 10000,
        'BaseImponible' => 10000,
        'Impuesto'      => [$tax],
    ], $lineOverrides);

    return [
        'DetalleServicio' => [
            'LineaDetalle' => [$line],
        ],
    ];
}

// ── IVA (codigo 01) ─────────────────────────────────────────────────

it('passes when IVA code 01 monto is correct', function () {
    // BaseImponible(10000) x 13% = 1300
    $data = taxLineData([
        'Codigo'          => '01',
        'CodigoTarifaIVA' => '08',
        'Tarifa'          => 13,
        'Monto'           => 1300,
    ], [
        'BaseImponible' => 10000,
    ]);

    app(TaxCalculationValidator::class)->validate($data);

    expect(true)->toBeTrue();
});

it('throws when IVA code 01 monto is incorrect', function () {
    // BaseImponible(10000) x 13% = 1300, not 999
    $data = taxLineData([
        'Codigo'          => '01',
        'CodigoTarifaIVA' => '08',
        'Tarifa'          => 13,
        'Monto'           => 999,
    ], [
        'BaseImponible' => 10000,
    ]);

    app(TaxCalculationValidator::class)->validate($data);
})->throws(InvalidReceiptException::class);

// ── Selectivo (codigo 02) ────────────────────────────────────────────

it('passes when Selectivo code 02 monto is correct', function () {
    // SubTotal(5000) x 10% = 500
    $data = taxLineData([
        'Codigo' => '02',
        'Tarifa' => 10,
        'Monto'  => 500,
    ], [
        'SubTotal' => 5000,
    ]);

    app(TaxCalculationValidator::class)->validate($data);

    expect(true)->toBeTrue();
});

// ── Combustible (codigo 03) ──────────────────────────────────────────

it('passes when Combustible code 03 monto is correct', function () {
    // CantidadUnidadMedida(50) x ImpuestoUnidad(12) = 600
    $data = taxLineData([
        'Codigo' => '03',
        'Tarifa' => 0,
        'Monto'  => 600,
        'DatosImpuestoEspecifico' => [
            'CantidadUnidadMedida' => 50,
            'ImpuestoUnidad'       => 12,
        ],
    ]);

    app(TaxCalculationValidator::class)->validate($data);

    expect(true)->toBeTrue();
});

it('throws when Combustible code 03 is missing DatosImpuestoEspecifico', function () {
    $data = taxLineData([
        'Codigo' => '03',
        'Tarifa' => 0,
        'Monto'  => 0,
        // No DatosImpuestoEspecifico — calculation yields 0 so Monto matches, but DatosEspecificos check fires
    ]);

    app(TaxCalculationValidator::class)->validate($data);
})->throws(InvalidReceiptException::class, 'DatosImpuestoEspecifico es requerido');

// ── Alcoholico (codigo 04) ──────────────────────────────────────────

it('passes when Alcoholico code 04 monto is correct', function () {
    // Cantidad(10) x Proporcion(0.5) x ImpuestoUnidad(20) = 100
    $data = taxLineData([
        'Codigo' => '04',
        'Tarifa' => 0,
        'Monto'  => 100,
        'DatosImpuestoEspecifico' => [
            'Proporcion'     => 0.5,
            'ImpuestoUnidad' => 20,
        ],
    ], [
        'Cantidad' => 10,
    ]);

    app(TaxCalculationValidator::class)->validate($data);

    expect(true)->toBeTrue();
});

// ── Bebidas sin alcohol (codigo 05) ──────────────────────────────────

it('passes when Bebidas code 05 monto is correct', function () {
    // Cantidad(6) x CantidadUnidadMedida(330) x (ImpuestoUnidad(10) / VolumenUnidadConsumo(1000)) = 19.80
    $data = taxLineData([
        'Codigo' => '05',
        'Tarifa' => 0,
        'Monto'  => 19.80,
        'DatosImpuestoEspecifico' => [
            'CantidadUnidadMedida'  => 330,
            'ImpuestoUnidad'        => 10,
            'VolumenUnidadConsumo'  => 1000,
        ],
    ], [
        'Cantidad' => 6,
    ]);

    app(TaxCalculationValidator::class)->validate($data);

    expect(true)->toBeTrue();
});

// ── Tabaco (codigo 06) ──────────────────────────────────────────────

it('passes when Tabaco code 06 monto is correct', function () {
    // Cantidad(3) x CantidadUnidadMedida(20) x ImpuestoUnidad(5) = 300
    $data = taxLineData([
        'Codigo' => '06',
        'Tarifa' => 0,
        'Monto'  => 300,
        'DatosImpuestoEspecifico' => [
            'CantidadUnidadMedida' => 20,
            'ImpuestoUnidad'       => 5,
        ],
    ], [
        'Cantidad' => 3,
    ]);

    app(TaxCalculationValidator::class)->validate($data);

    expect(true)->toBeTrue();
});

// ── Cemento (codigo 12) ─────────────────────────────────────────────

it('throws when Cemento code 12 tarifa is not 5', function () {
    $data = taxLineData([
        'Codigo' => '12',
        'Tarifa' => 10,
        'Monto'  => 500,
    ], [
        'SubTotal' => 5000,
    ]);

    app(TaxCalculationValidator::class)->validate($data);
})->throws(InvalidReceiptException::class, 'Tarifa para código 12 (cemento) debe ser 5');

// ── Tariff consistency ──────────────────────────────────────────────

it('throws when CodigoTarifaIVA 08 has wrong tarifa', function () {
    // CodigoTarifaIVA 08 expects 13%, sending 8
    $data = taxLineData([
        'Codigo'          => '01',
        'CodigoTarifaIVA' => '08',
        'Tarifa'          => 8,
        'Monto'           => 800,
    ], [
        'BaseImponible' => 10000,
    ]);

    app(TaxCalculationValidator::class)->validate($data);
})->throws(InvalidReceiptException::class, 'no coincide con CodigoTarifaIVA');

it('throws when CodigoTarifaIVA 01 has wrong tarifa', function () {
    // CodigoTarifaIVA 01 expects 0%, sending 13
    $data = taxLineData([
        'Codigo'          => '01',
        'CodigoTarifaIVA' => '01',
        'Tarifa'          => 13,
        'Monto'           => 1300,
    ], [
        'BaseImponible' => 10000,
    ]);

    app(TaxCalculationValidator::class)->validate($data);
})->throws(InvalidReceiptException::class, 'no coincide con CodigoTarifaIVA');

// ── Exoneracion ─────────────────────────────────────────────────────

it('throws when MontoExoneracion is incorrect', function () {
    // TarifaExonerada 4% x BaseImponible 10000 = 400, not 999
    $data = taxLineData([
        'Codigo'          => '01',
        'CodigoTarifaIVA' => '08',
        'Tarifa'          => 13,
        'Monto'           => 1300,
        'Exoneracion'     => [
            'TarifaExonerada'   => 4,
            'MontoExoneracion'  => 999,
        ],
    ], [
        'BaseImponible' => 10000,
    ]);

    app(TaxCalculationValidator::class)->validate($data);
})->throws(InvalidReceiptException::class, 'MontoExoneracion');

it('throws when TarifaExonerada exceeds Tarifa for CodigoTarifaIVA 04', function () {
    // CodigoTarifaIVA 04 expects Tarifa=4, TarifaExonerada=5 exceeds it
    $data = taxLineData([
        'Codigo'          => '01',
        'CodigoTarifaIVA' => '04',
        'Tarifa'          => 4,
        'Monto'           => 400,
        'Exoneracion'     => [
            'TarifaExonerada'   => 5,
            'MontoExoneracion'  => 500,
        ],
    ], [
        'BaseImponible' => 10000,
    ]);

    app(TaxCalculationValidator::class)->validate($data);
})->throws(InvalidReceiptException::class, 'TarifaExonerada');

it('passes when MontoExoneracion is correct (partial exoneration)', function () {
    // TarifaExonerada 12% x BaseImponible 10000 = 1200
    $data = taxLineData([
        'Codigo'          => '01',
        'CodigoTarifaIVA' => '08',
        'Tarifa'          => 13,
        'Monto'           => 1300,
        'Exoneracion'     => [
            'TarifaExonerada'   => 12,
            'MontoExoneracion'  => 1200,
        ],
    ], [
        'BaseImponible' => 10000,
    ]);

    app(TaxCalculationValidator::class)->validate($data);

    expect(true)->toBeTrue();
});

it('skips the exoneration formula for lines with DetalleSurtido', function () {
    // 156 does not satisfy the per-line formula (12% x 10000 = 1200), but
    // assortment lines only require MontoExoneracion <= tax Monto
    $data = taxLineData([
        'Codigo'          => '01',
        'CodigoTarifaIVA' => '08',
        'Tarifa'          => 13,
        'Monto'           => 1300,
        'Exoneracion'     => [
            'TarifaExonerada'   => 12,
            'MontoExoneracion'  => 156,
        ],
    ], [
        'BaseImponible'  => 10000,
        'DetalleSurtido' => [['CodigoCABYSSurtido' => '8399000000000']],
    ]);

    app(TaxCalculationValidator::class)->validate($data);

    expect(true)->toBeTrue();
});

it('throws when MontoExoneracion exceeds tax Monto on a DetalleSurtido line', function () {
    $data = taxLineData([
        'Codigo'          => '01',
        'CodigoTarifaIVA' => '08',
        'Tarifa'          => 13,
        'Monto'           => 1300,
        'Exoneracion'     => [
            'TarifaExonerada'   => 12,
            'MontoExoneracion'  => 1500,
        ],
    ], [
        'BaseImponible'  => 10000,
        'DetalleSurtido' => [['CodigoCABYSSurtido' => '8399000000000']],
    ]);

    app(TaxCalculationValidator::class)->validate($data);
})->throws(InvalidReceiptException::class, 'no puede exceder el Monto del impuesto');

// ── DatosImpuestoEspecifico required for codes 04,05,06 ─────────────

it('throws when DatosImpuestoEspecifico is missing for specific codes', function (string $codigo) {
    $data = taxLineData([
        'Codigo' => $codigo,
        'Tarifa' => 0,
        'Monto'  => 0,
        // No DatosImpuestoEspecifico
    ]);

    app(TaxCalculationValidator::class)->validate($data);
})->with(['04', '05', '06'])->throws(InvalidReceiptException::class, 'DatosImpuestoEspecifico es requerido');