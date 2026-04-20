<?php

use FactuTica\FactuticaCR\Exceptions\InvalidReceiptException;
use FactuTica\FactuticaCR\Services\Validators\CalculationValidatorService;
/**
 * Builds complete valid data with 1 service line (CABYS 8xxx, gravado, IVA 13%)
 * and matching ResumenFactura + TotalDesgloseImpuesto.
 */
function orchestratorData(array $overrides = []): array
{
    $base = [
        'DetalleServicio' => [
            'LineaDetalle' => [
                [
                    'NumeroLinea'       => 1,
                    'CodigoCABYS'       => '8410010000100',
                    'Cantidad'          => 2,
                    'UnidadMedida'      => 'Sp',
                    'PrecioUnitario'    => 5000,
                    'MontoTotal'        => 10000,
                    'SubTotal'          => 10000,
                    'BaseImponible'     => 10000,
                    'ImpuestoNeto'      => 1300,
                    'MontoTotalLinea'   => 11300,
                    'ImpuestoAsumidoEmisorFabrica' => 0,
                    'Impuesto'          => [
                        [
                            'Codigo'            => '01',
                            'CodigoTarifaIVA'   => '08',
                            'Tarifa'            => 13,
                            'Monto'             => 1300,
                            'ImpuestoNeto'      => 1300,
                            'ImpuestoAsumidoEmisorFabrica' => 0,
                        ],
                    ],
                ],
            ],
        ],
        'ResumenFactura' => [
            'CodigoTipoMoneda' => [
                'CodigoMoneda' => 'CRC',
                'TipoCambio'   => 1,
            ],
            'TotalServGravados'         => 10000,
            'TotalServExentos'          => 0,
            'TotalServExonerado'        => 0,
            'TotalServNoSujeto'         => 0,
            'TotalMercanciasGravadas'   => 0,
            'TotalMercanciasExentas'    => 0,
            'TotalMercExonerada'        => 0,
            'TotalMercNoSujeta'         => 0,
            'TotalGravado'              => 10000,
            'TotalExento'               => 0,
            'TotalExonerado'            => 0,
            'TotalNoSujeto'             => 0,
            'TotalVenta'                => 10000,
            'TotalDescuentos'           => 0,
            'TotalVentaNeta'            => 10000,
            'TotalImpuesto'             => 1300,
            'TotalImpAsumEmisorFabrica' => 0,
            'TotalIVADevuelto'          => 0,
            'TotalOtrosCargos'          => 0,
            'TotalComprobante'          => 11300,
        ],
        'TotalDesgloseImpuesto' => [
            [
                'Codigo'      => '01',
                'Tarifa'      => 13,
                'MontoTotal'  => 1300,
                'MontoNeto'   => 1300,
            ],
        ],
    ];

    return array_replace_recursive($base, $overrides);
}

// ── CalculationValidatorService ──────────────────────────────────────

describe('CalculationValidatorService', function () {

    it('passes with complete valid data', function () {
        $validator = app(CalculationValidatorService::class);

        $validator->validate(orchestratorData());

        expect(true)->toBeTrue();
    });

    it('skips line validators when no LineaDetalle', function () {
        $validator = app(CalculationValidatorService::class);

        $data = [
            'ResumenFactura' => [
                'CodigoTipoMoneda' => [
                    'CodigoMoneda' => 'CRC',
                    'TipoCambio'   => 1,
                ],
                'TotalGravado'       => 0,
                'TotalExento'        => 0,
                'TotalExonerado'     => 0,
                'TotalNoSujeto'      => 0,
                'TotalVenta'         => 0,
                'TotalDescuentos'    => 0,
                'TotalVentaNeta'     => 0,
                'TotalImpuesto'      => 0,
                'TotalComprobante'   => 0,
            ],
        ];

        $validator->validate($data);

        expect(true)->toBeTrue();
    });

    it('skips summary when no ResumenFactura', function () {
        $validator = app(CalculationValidatorService::class);

        $data = [
            'DetalleServicio' => [
                'LineaDetalle' => [
                    [
                        'NumeroLinea'       => 1,
                        'CodigoCABYS'       => '8410010000100',
                        'Cantidad'          => 2,
                        'UnidadMedida'      => 'Sp',
                        'PrecioUnitario'    => 5000,
                        'MontoTotal'        => 10000,
                        'SubTotal'          => 10000,
                        'BaseImponible'     => 10000,
                        'ImpuestoNeto'      => 1300,
                        'MontoTotalLinea'   => 11300,
                        'ImpuestoAsumidoEmisorFabrica' => 0,
                        'Impuesto'          => [
                            [
                                'Codigo'            => '01',
                                'CodigoTarifaIVA'   => '08',
                                'Tarifa'            => 13,
                                'Monto'             => 1300,
                                'ImpuestoNeto'      => 1300,
                                'ImpuestoAsumidoEmisorFabrica' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            'TotalDesgloseImpuesto' => [
                [
                    'Codigo'      => '01',
                    'Tarifa'      => 13,
                    'MontoTotal'  => 1300,
                    'MontoNeto'   => 1300,
                ],
            ],
        ];

        $validator->validate($data);

        expect(true)->toBeTrue();
    });

    it('propagates DetailLineValidator exception on bad MontoTotal', function () {
        $validator = app(CalculationValidatorService::class);

        $data = orchestratorData([
            'DetalleServicio' => [
                'LineaDetalle' => [
                    [
                        'NumeroLinea'       => 1,
                        'CodigoCABYS'       => '8410010000100',
                        'Cantidad'          => 2,
                        'UnidadMedida'      => 'Sp',
                        'PrecioUnitario'    => 5000,
                        'MontoTotal'        => 9000,
                        'SubTotal'          => 10000,
                        'BaseImponible'     => 10000,
                        'ImpuestoNeto'      => 1300,
                        'MontoTotalLinea'   => 11300,
                        'ImpuestoAsumidoEmisorFabrica' => 0,
                        'Impuesto'          => [
                            [
                                'Codigo'            => '01',
                                'CodigoTarifaIVA'   => '08',
                                'Tarifa'            => 13,
                                'Monto'             => 1300,
                                'ImpuestoNeto'      => 1300,
                                'ImpuestoAsumidoEmisorFabrica' => 0,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $validator->validate($data);
    })->throws(InvalidReceiptException::class);

    it('propagates TaxCalculationValidator exception on bad tax monto', function () {
        $validator = app(CalculationValidatorService::class);

        $data = orchestratorData([
            'DetalleServicio' => [
                'LineaDetalle' => [
                    [
                        'NumeroLinea'       => 1,
                        'CodigoCABYS'       => '8410010000100',
                        'Cantidad'          => 2,
                        'UnidadMedida'      => 'Sp',
                        'PrecioUnitario'    => 5000,
                        'MontoTotal'        => 10000,
                        'SubTotal'          => 10000,
                        'BaseImponible'     => 10000,
                        'ImpuestoNeto'      => 1300,
                        'MontoTotalLinea'   => 11300,
                        'ImpuestoAsumidoEmisorFabrica' => 0,
                        'Impuesto'          => [
                            [
                                'Codigo'            => '01',
                                'CodigoTarifaIVA'   => '08',
                                'Tarifa'            => 13,
                                'Monto'             => 999,
                                'ImpuestoNeto'      => 1300,
                                'ImpuestoAsumidoEmisorFabrica' => 0,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $validator->validate($data);
    })->throws(InvalidReceiptException::class);

    it('propagates InvoiceSummaryValidator exception on bad TotalComprobante', function () {
        $validator = app(CalculationValidatorService::class);

        $data = orchestratorData([
            'ResumenFactura' => [
                'TotalComprobante' => 99999,
            ],
        ]);

        $validator->validate($data);
    })->throws(InvalidReceiptException::class);

    it('passes with empty data', function () {
        $validator = app(CalculationValidatorService::class);

        $validator->validate([]);

        expect(true)->toBeTrue();
    });
});