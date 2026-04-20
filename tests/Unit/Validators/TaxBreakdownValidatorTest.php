<?php

use FactuTica\FactuticaCR\Exceptions\InvalidReceiptException;
use FactuTica\FactuticaCR\Services\Validators\TaxBreakdownValidator;
// ── Passes with correct breakdown ────────────────────────────────────

it('passes with a correct single-code breakdown', function () {
    $data = [
        'DetalleServicio' => [
            'LineaDetalle' => [
                [
                    'NumeroLinea' => 1,
                    'Impuesto' => [
                        [
                            'Codigo'          => '01',
                            'CodigoTarifaIVA' => '08',
                            'Monto'           => 1300,
                        ],
                    ],
                ],
            ],
        ],
        'ResumenFactura' => [
            'TotalDesgloseImpuesto' => [
                [
                    'Codigo'              => '01',
                    'CodigoTarifaIVA'     => '08',
                    'TotalMontoImpuesto'  => 1300,
                ],
            ],
        ],
    ];

    app(TaxBreakdownValidator::class)->validate($data);

    expect(true)->toBeTrue();
});

// ── Fails when TotalMontoImpuesto doesn't match ─────────────────────

it('throws when TotalMontoImpuesto does not match sum of lines', function () {
    $data = [
        'DetalleServicio' => [
            'LineaDetalle' => [
                [
                    'NumeroLinea' => 1,
                    'Impuesto' => [
                        [
                            'Codigo'          => '01',
                            'CodigoTarifaIVA' => '08',
                            'Monto'           => 1300,
                        ],
                    ],
                ],
            ],
        ],
        'ResumenFactura' => [
            'TotalDesgloseImpuesto' => [
                [
                    'Codigo'              => '01',
                    'CodigoTarifaIVA'     => '08',
                    'TotalMontoImpuesto'  => 1500,
                ],
            ],
        ],
    ];

    app(TaxBreakdownValidator::class)->validate($data);
})->throws(InvalidReceiptException::class, 'TotalMontoImpuesto');

// ── Missing code in breakdown ────────────────────────────────────────

it('throws when a tax code from lines is missing in breakdown', function () {
    $data = [
        'DetalleServicio' => [
            'LineaDetalle' => [
                [
                    'NumeroLinea' => 1,
                    'Impuesto' => [
                        [
                            'Codigo'          => '01',
                            'CodigoTarifaIVA' => '08',
                            'Monto'           => 1300,
                        ],
                        [
                            'Codigo'          => '02',
                            'CodigoTarifaIVA' => '',
                            'Monto'           => 500,
                        ],
                    ],
                ],
            ],
        ],
        'ResumenFactura' => [
            'TotalDesgloseImpuesto' => [
                [
                    'Codigo'              => '01',
                    'CodigoTarifaIVA'     => '08',
                    'TotalMontoImpuesto'  => 1300,
                ],
                // Missing code 02
            ],
        ],
    ];

    app(TaxBreakdownValidator::class)->validate($data);
})->throws(InvalidReceiptException::class, 'Falta TotalDesgloseImpuesto');

// ── Multiple codes grouped correctly ─────────────────────────────────

it('passes with multiple codes grouped correctly', function () {
    $data = [
        'DetalleServicio' => [
            'LineaDetalle' => [
                [
                    'NumeroLinea' => 1,
                    'Impuesto' => [
                        [
                            'Codigo'          => '01',
                            'CodigoTarifaIVA' => '08',
                            'Monto'           => 1300,
                        ],
                    ],
                ],
                [
                    'NumeroLinea' => 2,
                    'Impuesto' => [
                        [
                            'Codigo'          => '01',
                            'CodigoTarifaIVA' => '08',
                            'Monto'           => 650,
                        ],
                        [
                            'Codigo'          => '02',
                            'CodigoTarifaIVA' => '',
                            'Monto'           => 200,
                        ],
                    ],
                ],
            ],
        ],
        'ResumenFactura' => [
            'TotalDesgloseImpuesto' => [
                [
                    'Codigo'              => '01',
                    'CodigoTarifaIVA'     => '08',
                    'TotalMontoImpuesto'  => 1950,
                ],
                [
                    'Codigo'              => '02',
                    'CodigoTarifaIVA'     => '',
                    'TotalMontoImpuesto'  => 200,
                ],
            ],
        ],
    ];

    app(TaxBreakdownValidator::class)->validate($data);

    expect(true)->toBeTrue();
});

// ── Skips when TotalDesgloseImpuesto not declared ────────────────────

it('passes when TotalDesgloseImpuesto is not declared', function () {
    $data = [
        'DetalleServicio' => [
            'LineaDetalle' => [
                [
                    'NumeroLinea' => 1,
                    'Impuesto' => [
                        [
                            'Codigo'          => '01',
                            'CodigoTarifaIVA' => '08',
                            'Monto'           => 1300,
                        ],
                    ],
                ],
            ],
        ],
        'ResumenFactura' => [
            // No TotalDesgloseImpuesto
        ],
    ];

    app(TaxBreakdownValidator::class)->validate($data);

    expect(true)->toBeTrue();
});

// ── Skips when no lines ──────────────────────────────────────────────

it('passes when LineaDetalle is empty', function () {
    $data = [
        'DetalleServicio' => [
            'LineaDetalle' => [],
        ],
        'ResumenFactura' => [
            'TotalDesgloseImpuesto' => [
                [
                    'Codigo'              => '01',
                    'CodigoTarifaIVA'     => '08',
                    'TotalMontoImpuesto'  => 1300,
                ],
            ],
        ],
    ];

    app(TaxBreakdownValidator::class)->validate($data);

    expect(true)->toBeTrue();
});

// ── Exoneracion subtracted from total ────────────────────────────────

it('subtracts MontoExoneracion from the breakdown total', function () {
    // Monto 1300 - MontoExoneracion 500 = 800 net
    $data = [
        'DetalleServicio' => [
            'LineaDetalle' => [
                [
                    'NumeroLinea' => 1,
                    'Impuesto' => [
                        [
                            'Codigo'          => '01',
                            'CodigoTarifaIVA' => '08',
                            'Monto'           => 1300,
                            'Exoneracion'     => [
                                'MontoExoneracion' => 500,
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'ResumenFactura' => [
            'TotalDesgloseImpuesto' => [
                [
                    'Codigo'              => '01',
                    'CodigoTarifaIVA'     => '08',
                    'TotalMontoImpuesto'  => 800,
                ],
            ],
        ],
    ];

    app(TaxBreakdownValidator::class)->validate($data);

    expect(true)->toBeTrue();
});