<?php

use FactuTica\FactuticaCR\Exceptions\InvalidReceiptException;
use FactuTica\FactuticaCR\Services\Validators\InvoiceSummaryValidator;
/**
 * Builds valid data with 1 service line (CABYS 8xxx, gravado, IVA 13%) and matching ResumenFactura.
 *
 * Line: 2 units x 5000 = 10000, IVA 13% = 1300, ImpuestoNeto = 1300, MontoTotalLinea = 11300
 */
function summaryData(array $overrides = []): array
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
    ];

    return array_replace_recursive($base, $overrides);
}

// ── InvoiceSummaryValidator ──────────────────────────────────────────

describe('InvoiceSummaryValidator', function () {

    it('passes with valid service gravado data', function () {
        $validator = app(InvoiceSummaryValidator::class);

        $validator->validate(summaryData());

        expect(true)->toBeTrue();
    });

    it('throws when TotalServGravados is incorrect', function () {
        $validator = app(InvoiceSummaryValidator::class);

        $data = summaryData([
            'ResumenFactura' => [
                'TotalServGravados' => 9000,
            ],
        ]);

        $validator->validate($data);
    })->throws(InvalidReceiptException::class, 'TotalServGravados');

    it('classifies merchandise line into TotalMercanciasGravadas', function () {
        $validator = app(InvoiceSummaryValidator::class);

        $data = summaryData([
            'DetalleServicio' => [
                'LineaDetalle' => [
                    [
                        'NumeroLinea'       => 1,
                        'CodigoCABYS'       => '0410010000100',
                        'Cantidad'          => 2,
                        'UnidadMedida'      => 'Unid',
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
                'TotalServGravados'       => 0,
                'TotalMercanciasGravadas' => 10000,
            ],
        ]);

        $validator->validate($data);

        expect(true)->toBeTrue();
    });

    it('throws when TotalGravado is incorrect', function () {
        $validator = app(InvoiceSummaryValidator::class);

        $data = summaryData([
            'ResumenFactura' => [
                'TotalGravado' => 5000,
            ],
        ]);

        $validator->validate($data);
    })->throws(InvalidReceiptException::class, 'TotalGravado');

    it('throws when TotalVenta is incorrect', function () {
        $validator = app(InvoiceSummaryValidator::class);

        $data = summaryData([
            'ResumenFactura' => [
                'TotalVenta' => 8000,
            ],
        ]);

        $validator->validate($data);
    })->throws(InvalidReceiptException::class, 'TotalVenta');

    it('throws when TotalVentaNeta is incorrect', function () {
        $validator = app(InvoiceSummaryValidator::class);

        $data = summaryData([
            'ResumenFactura' => [
                'TotalVentaNeta' => 7000,
            ],
        ]);

        $validator->validate($data);
    })->throws(InvalidReceiptException::class, 'TotalVentaNeta');

    it('throws when TotalImpuesto is incorrect', function () {
        $validator = app(InvoiceSummaryValidator::class);

        $data = summaryData([
            'ResumenFactura' => [
                'TotalImpuesto' => 999,
            ],
        ]);

        $validator->validate($data);
    })->throws(InvalidReceiptException::class, 'TotalImpuesto');

    it('throws when TotalComprobante is incorrect', function () {
        $validator = app(InvoiceSummaryValidator::class);

        $data = summaryData([
            'ResumenFactura' => [
                'TotalComprobante' => 9999,
            ],
        ]);

        $validator->validate($data);
    })->throws(InvalidReceiptException::class, 'TotalComprobante');

    it('throws when TipoCambio is not 1 for CRC', function () {
        $validator = app(InvoiceSummaryValidator::class);

        $data = summaryData([
            'ResumenFactura' => [
                'CodigoTipoMoneda' => [
                    'CodigoMoneda' => 'CRC',
                    'TipoCambio'   => 570,
                ],
            ],
        ]);

        $validator->validate($data);
    })->throws(InvalidReceiptException::class, 'TipoCambio debe ser 1');

    it('passes when TipoCambio differs for USD', function () {
        $validator = app(InvoiceSummaryValidator::class);

        $data = summaryData([
            'ResumenFactura' => [
                'CodigoTipoMoneda' => [
                    'CodigoMoneda' => 'USD',
                    'TipoCambio'   => 515,
                ],
            ],
        ]);

        $validator->validate($data);

        expect(true)->toBeTrue();
    });

    it('throws when TotalDescuentos does not match line discounts', function () {
        $validator = app(InvoiceSummaryValidator::class);

        $data = summaryData([
            'DetalleServicio' => [
                'LineaDetalle' => [
                    [
                        'NumeroLinea'       => 1,
                        'CodigoCABYS'       => '8410010000100',
                        'Cantidad'          => 2,
                        'UnidadMedida'      => 'Sp',
                        'PrecioUnitario'    => 5000,
                        'MontoTotal'        => 10000,
                        'SubTotal'          => 9000,
                        'BaseImponible'     => 9000,
                        'ImpuestoNeto'      => 1170,
                        'MontoTotalLinea'   => 10170,
                        'ImpuestoAsumidoEmisorFabrica' => 0,
                        'Descuento'         => [
                            ['MontoDescuento' => 1000, 'NaturalezaDescuento' => 'Promo'],
                        ],
                        'Impuesto'          => [
                            [
                                'Codigo'            => '01',
                                'CodigoTarifaIVA'   => '08',
                                'Tarifa'            => 13,
                                'Monto'             => 1170,
                                'ImpuestoNeto'      => 1170,
                                'ImpuestoAsumidoEmisorFabrica' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            'ResumenFactura' => [
                'TotalServGravados'  => 10000,
                'TotalGravado'       => 10000,
                'TotalVenta'         => 10000,
                'TotalDescuentos'    => 500,
                'TotalVentaNeta'     => 9500,
                'TotalImpuesto'      => 1170,
                'TotalComprobante'   => 10670,
            ],
        ]);

        $validator->validate($data);
    })->throws(InvalidReceiptException::class, 'TotalDescuentos');

    it('classifies exento line correctly with CodigoTarifaIVA 10', function () {
        $validator = app(InvoiceSummaryValidator::class);

        $data = summaryData([
            'DetalleServicio' => [
                'LineaDetalle' => [
                    [
                        'NumeroLinea'       => 1,
                        'CodigoCABYS'       => '8410010000100',
                        'Cantidad'          => 1,
                        'UnidadMedida'      => 'Sp',
                        'PrecioUnitario'    => 5000,
                        'MontoTotal'        => 5000,
                        'SubTotal'          => 5000,
                        'BaseImponible'     => 5000,
                        'ImpuestoNeto'      => 0,
                        'MontoTotalLinea'   => 5000,
                        'ImpuestoAsumidoEmisorFabrica' => 0,
                        'Impuesto'          => [
                            [
                                'Codigo'            => '01',
                                'CodigoTarifaIVA'   => '10',
                                'Tarifa'            => 0,
                                'Monto'             => 0,
                                'ImpuestoNeto'      => 0,
                                'ImpuestoAsumidoEmisorFabrica' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            'ResumenFactura' => [
                'TotalServGravados'  => 0,
                'TotalServExentos'   => 5000,
                'TotalGravado'       => 0,
                'TotalExento'        => 5000,
                'TotalVenta'         => 5000,
                'TotalDescuentos'    => 0,
                'TotalVentaNeta'     => 5000,
                'TotalImpuesto'      => 0,
                'TotalComprobante'   => 5000,
            ],
        ]);

        $validator->validate($data);

        expect(true)->toBeTrue();
    });

    it('classifies no sujeto line correctly with CodigoTarifaIVA 01', function () {
        $validator = app(InvoiceSummaryValidator::class);

        $data = summaryData([
            'DetalleServicio' => [
                'LineaDetalle' => [
                    [
                        'NumeroLinea'       => 1,
                        'CodigoCABYS'       => '8410010000100',
                        'Cantidad'          => 1,
                        'UnidadMedida'      => 'Sp',
                        'PrecioUnitario'    => 5000,
                        'MontoTotal'        => 5000,
                        'SubTotal'          => 5000,
                        'BaseImponible'     => 5000,
                        'ImpuestoNeto'      => 0,
                        'MontoTotalLinea'   => 5000,
                        'ImpuestoAsumidoEmisorFabrica' => 0,
                        'Impuesto'          => [
                            [
                                'Codigo'            => '01',
                                'CodigoTarifaIVA'   => '01',
                                'Tarifa'            => 0,
                                'Monto'             => 0,
                                'ImpuestoNeto'      => 0,
                                'ImpuestoAsumidoEmisorFabrica' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            'ResumenFactura' => [
                'TotalServGravados'  => 0,
                'TotalServNoSujeto'  => 5000,
                'TotalGravado'       => 0,
                'TotalNoSujeto'      => 5000,
                'TotalVenta'         => 5000,
                'TotalDescuentos'    => 0,
                'TotalVentaNeta'     => 5000,
                'TotalImpuesto'      => 0,
                'TotalComprobante'   => 5000,
            ],
        ]);

        $validator->validate($data);

        expect(true)->toBeTrue();
    });

    it('passes with empty lines and only ResumenFactura', function () {
        $validator = app(InvoiceSummaryValidator::class);

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
});