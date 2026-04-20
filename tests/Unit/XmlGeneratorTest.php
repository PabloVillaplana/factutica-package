<?php

use FactuTica\FactuticaCR\Enums\ReceiptType;
use FactuTica\FactuticaCR\Exceptions\InvalidReceiptException;
use FactuTica\FactuticaCR\Services\XmlGenerator\XmlGeneratorService;

function sampleInvoiceData(array $overrides = []): array
{
    return array_merge([
        'Clave'                  => '50601032600310112345600100001010000000001199999999',
        'ProveedorSistema'       => 'factutica',
        'CodigoActividadEmisor'  => '620101',
        'NumeroConsecutivo'      => '00100001010000000001',
        'FechaEmision'           => '2026-03-30T10:00:00-06:00',
        'CondicionVenta'         => '01',
        'Emisor' => [
            'Nombre'         => 'Test Emisor S.A.',
            'Identificacion' => ['Tipo' => '02', 'Numero' => '3101123456'],
            'Ubicacion'      => [
                'Provincia'  => '1',
                'Canton'     => '01',
                'Distrito'   => '01',
                'OtrasSenas' => 'San José Centro',
            ],
            'CorreoElectronico' => ['test@emisor.com'],
        ],
        'Receptor' => [
            'Nombre'         => 'Cliente Test',
            'Identificacion' => ['Tipo' => '01', 'Numero' => '112345678'],
        ],
        'DetalleServicio' => [
            'LineaDetalle' => [
                [
                    'NumeroLinea'    => '1',
                    'CodigoCABYS'    => '8410101000100',
                    'Cantidad'       => '2',
                    'UnidadMedida'   => 'Unid',
                    'Detalle'        => 'Servicio de consultoría',
                    'PrecioUnitario' => '5000.00',
                    'MontoTotal'     => '10000.00',
                    'SubTotal'       => '10000.00',
                    'BaseImponible'  => '10000.00',
                    'Impuesto'       => [
                        [
                            'Codigo'         => '01',
                            'CodigoTarifaIVA' => '08',
                            'Tarifa'         => '13.00',
                            'Monto'          => '1300.00',
                        ],
                    ],
                    'ImpuestoNeto'    => '1300.00',
                    'MontoTotalLinea' => '11300.00',
                ],
            ],
        ],
        'ResumenFactura' => [
            'CodigoTipoMoneda'     => ['CodigoMoneda' => 'CRC', 'TipoCambio' => '1'],
            'TotalServGravados'    => '10000.00',
            'TotalGravado'         => '10000.00',
            'TotalVenta'           => '10000.00',
            'TotalVentaNeta'       => '10000.00',
            'TotalImpuesto'        => '1300.00',
            'TotalComprobante'     => '11300.00',
            'MedioPago'            => [
                ['TipoMedioPago' => '01', 'TotalMedioPago' => '11300.00'],
            ],
        ],
    ], $overrides);
}

it('generates valid XML for Factura Electrónica', function () {
    $generator = new XmlGeneratorService();
    $xml = $generator->generate(ReceiptType::ElectronicInvoice, sampleInvoiceData());

    expect($xml)->toBeString();
    expect($xml)->toContain('<?xml version="1.0" encoding="UTF-8"?>');
    expect($xml)->toContain('<FacturaElectronica');
    expect($xml)->toContain('facturaElectronica');
    expect($xml)->toContain('</FacturaElectronica>');
});

it('generates correct root element per receipt type', function (ReceiptType $type, string $rootTag) {
    $generator = new XmlGeneratorService();
    $xml = $generator->generate($type, sampleInvoiceData());

    expect($xml)->toContain("<{$rootTag}");
    expect($xml)->toContain("</{$rootTag}>");
})->with([
    [ReceiptType::ElectronicInvoice, 'FacturaElectronica'],
    [ReceiptType::DebitNote, 'NotaDebitoElectronica'],
    [ReceiptType::CreditNote, 'NotaCreditoElectronica'],
    [ReceiptType::ElectronicTicket, 'TiqueteElectronico'],
    [ReceiptType::PurchaseInvoice, 'FacturaElectronicaCompras'],
    [ReceiptType::ExportInvoice, 'FacturaElectronicaExportacion'],
    [ReceiptType::ElectronicPaymentReceipt, 'ComprobanteElectronicoPago'],
]);

it('generates REP with minimal structure', function () {
    $generator = new XmlGeneratorService();
    $repData = [
        'Clave'                  => '50601032600310112345600100001070000000001199999999',
        'ProveedorSistema'       => 'factutica',
        'NumeroConsecutivo'      => '00100001070000000001',
        'FechaEmision'           => '2026-04-02T10:00:00-06:00',
        'CondicionVenta'         => '09',
        'Emisor' => [
            'Nombre'         => 'Test Emisor S.A.',
            'Identificacion' => ['Tipo' => '02', 'Numero' => '3101123456'],
            'CorreoElectronico' => ['test@emisor.com'],
        ],
        'Receptor' => [
            'Nombre' => 'Cliente Test',
        ],
        'ResumenFactura' => [
            'CodigoTipoMoneda' => ['CodigoMoneda' => 'CRC', 'TipoCambio' => '1'],
            'TotalVenta'       => '50000.00',
            'TotalComprobante' => '50000.00',
            'MedioPago'        => [['TipoMedioPago' => '01', 'TotalMedioPago' => '50000.00']],
        ],
    ];

    $xml = $generator->generate(ReceiptType::ElectronicPaymentReceipt, $repData);

    expect($xml)->toContain('<ComprobanteElectronicoPago');
    expect($xml)->toContain('comprobanteElectronicoPago');
    expect($xml)->toContain('<CondicionVenta>09</CondicionVenta>');
    expect($xml)->toContain('<TotalComprobante>50000.00</TotalComprobante>');
    expect($xml)->toContain('<TipoMedioPago>01</TipoMedioPago>');
    expect($xml)->not->toContain('<DetalleServicio>');
    expect($xml)->not->toContain('<OtrosCargos>');
    expect($xml)->not->toContain('<InformacionReferencia>');
});

it('includes Clave and NumeroConsecutivo', function () {
    $generator = new XmlGeneratorService();
    $xml = $generator->generate(ReceiptType::ElectronicInvoice, sampleInvoiceData());

    expect($xml)->toContain('<Clave>50601032600310112345600100001010000000001199999999</Clave>');
    expect($xml)->toContain('<NumeroConsecutivo>00100001010000000001</NumeroConsecutivo>');
});

it('includes Emisor data', function () {
    $generator = new XmlGeneratorService();
    $xml = $generator->generate(ReceiptType::ElectronicInvoice, sampleInvoiceData());

    expect($xml)->toContain('<Emisor>');
    expect($xml)->toContain('<Nombre>Test Emisor S.A.</Nombre>');
    expect($xml)->toContain('<Tipo>02</Tipo>');
    expect($xml)->toContain('<Numero>3101123456</Numero>');
    expect($xml)->toContain('<Provincia>1</Provincia>');
    expect($xml)->toContain('<CorreoElectronico>test@emisor.com</CorreoElectronico>');
});

it('includes Receptor data', function () {
    $generator = new XmlGeneratorService();
    $xml = $generator->generate(ReceiptType::ElectronicInvoice, sampleInvoiceData());

    expect($xml)->toContain('<Receptor>');
    expect($xml)->toContain('<Nombre>Cliente Test</Nombre>');
    expect($xml)->toContain('<Numero>112345678</Numero>');
});

it('includes DetalleServicio with line items', function () {
    $generator = new XmlGeneratorService();
    $xml = $generator->generate(ReceiptType::ElectronicInvoice, sampleInvoiceData());

    expect($xml)->toContain('<DetalleServicio>');
    expect($xml)->toContain('<LineaDetalle>');
    expect($xml)->toContain('<NumeroLinea>1</NumeroLinea>');
    expect($xml)->toContain('<CodigoCABYS>8410101000100</CodigoCABYS>');
    expect($xml)->toContain('<Detalle>Servicio de consultoría</Detalle>');
    expect($xml)->toContain('<MontoTotalLinea>11300.00</MontoTotalLinea>');
});

it('includes Impuesto in line items', function () {
    $generator = new XmlGeneratorService();
    $xml = $generator->generate(ReceiptType::ElectronicInvoice, sampleInvoiceData());

    expect($xml)->toContain('<Impuesto>');
    expect($xml)->toContain('<Codigo>01</Codigo>');
    expect($xml)->toContain('<Tarifa>13.00</Tarifa>');
    expect($xml)->toContain('<Monto>1300.00</Monto>');
});

it('includes ResumenFactura', function () {
    $generator = new XmlGeneratorService();
    $xml = $generator->generate(ReceiptType::ElectronicInvoice, sampleInvoiceData());

    expect($xml)->toContain('<ResumenFactura>');
    expect($xml)->toContain('<CodigoMoneda>CRC</CodigoMoneda>');
    expect($xml)->toContain('<TotalComprobante>11300.00</TotalComprobante>');
    expect($xml)->toContain('<TotalImpuesto>1300.00</TotalImpuesto>');
});

it('includes MedioPago', function () {
    $generator = new XmlGeneratorService();
    $xml = $generator->generate(ReceiptType::ElectronicInvoice, sampleInvoiceData());

    expect($xml)->toContain('<MedioPago>');
    expect($xml)->toContain('<TipoMedioPago>01</TipoMedioPago>');
    expect($xml)->toContain('<TotalMedioPago>11300.00</TotalMedioPago>');
});

it('omits optional fields when not present', function () {
    $generator = new XmlGeneratorService();
    $xml = $generator->generate(ReceiptType::ElectronicInvoice, sampleInvoiceData());

    expect($xml)->not->toContain('<PlazoCredito>');
    expect($xml)->not->toContain('<OtrosCargos>');
    expect($xml)->not->toContain('<InformacionReferencia>');
    expect($xml)->not->toContain('<Otros>');
    expect($xml)->not->toContain('<NombreComercial>');
});

it('includes InformacionReferencia when provided', function () {
    $data = sampleInvoiceData([
        'InformacionReferencia' => [
            [
                'TipoDocIR'     => '01',
                'Numero'        => '50601032600310112345600100001010000000001199999999',
                'FechaEmisionIR' => '2026-03-28T10:00:00-06:00',
                'Codigo'        => '01',
                'Razon'         => 'Corrección de monto',
            ],
        ],
    ]);

    $generator = new XmlGeneratorService();
    $xml = $generator->generate(ReceiptType::CreditNote, $data);

    expect($xml)->toContain('<InformacionReferencia>');
    expect($xml)->toContain('<TipoDocIR>01</TipoDocIR>');
    expect($xml)->toContain('<Razon>Corrección de monto</Razon>');
});

it('produces parseable XML', function () {
    $generator = new XmlGeneratorService();
    $xml = $generator->generate(ReceiptType::ElectronicInvoice, sampleInvoiceData());

    $doc = new DOMDocument();
    $result = $doc->loadXML($xml);

    expect($result)->toBeTrue();
    expect($doc->documentElement->localName)->toBe('FacturaElectronica');
});