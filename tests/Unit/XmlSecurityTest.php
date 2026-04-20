<?php

use FactuTica\FactuticaCR\Enums\ReceiptType;
use FactuTica\FactuticaCR\Services\XmlGenerator\XmlGeneratorService;

function xmlPayload(array $overrides = []): array
{
    $base = [
        'Clave' => str_repeat('5', 50),
        'ProveedorSistema' => '12345',
        'CodigoActividadEmisor' => '6201.0',
        'NumeroConsecutivo' => str_pad('1', 20, '0', STR_PAD_LEFT),
        'FechaEmision' => '2026-04-01T10:00:00-06:00',
        'CondicionVenta' => '01',
        'Emisor' => [
            'Nombre' => 'Emisor Test',
            'Identificacion' => ['Tipo' => '01', 'Numero' => '112345678'],
        ],
        'Receptor' => [
            'Nombre' => 'Receptor Test',
            'Identificacion' => ['Tipo' => '02', 'Numero' => '3101234567'],
        ],
        'DetalleServicio' => [
            'LineaDetalle' => [[
                'NumeroLinea' => 1,
                'CodigoCABYS' => '6803000000000',
                'Cantidad' => '1',
                'UnidadMedida' => 'Sp',
                'Detalle' => 'Servicio',
                'PrecioUnitario' => '1000.00',
                'MontoTotal' => '1000.00',
                'SubTotal' => '1000.00',
                'BaseImponible' => '1000.00',
                'ImpuestoAsumidoEmisorFabrica' => '0',
                'ImpuestoNeto' => '0',
                'MontoTotalLinea' => '1000.00',
            ]],
        ],
        'ResumenFactura' => [
            'CodigoTipoMoneda' => ['CodigoMoneda' => 'CRC', 'TipoCambio' => '1'],
            'TotalVenta' => '1000.00',
            'TotalComprobante' => '1000.00',
        ],
    ];

    return array_replace_recursive($base, $overrides);
}

// ── XML Injection via text content ──────────────────────────────────

it('escapes HTML/XML tags in Emisor.Nombre', function () {
    $generator = app(XmlGeneratorService::class);
    $data = xmlPayload(['Emisor' => [
        'Nombre' => '<script>alert("xss")</script>',
        'Identificacion' => ['Tipo' => '01', 'Numero' => '112345678'],
    ]]);

    $xml = $generator->generate(ReceiptType::ElectronicInvoice, $data);

    expect($xml)->toContain('&lt;script&gt;alert("xss")&lt;/script&gt;');
    expect($xml)->not->toContain('<script>');
});

it('escapes HTML/XML tags in Receptor.Nombre', function () {
    $generator = app(XmlGeneratorService::class);
    $data = xmlPayload(['Receptor' => [
        'Nombre' => '"><img src=x onerror=alert(1)>',
        'Identificacion' => ['Tipo' => '02', 'Numero' => '3101234567'],
    ]]);

    $xml = $generator->generate(ReceiptType::ElectronicInvoice, $data);

    expect($xml)->not->toContain('<img');
    expect($xml)->toContain('&lt;img');
});

it('escapes XML entities in line Detalle', function () {
    $generator = app(XmlGeneratorService::class);
    $data = xmlPayload(['DetalleServicio' => [
        'LineaDetalle' => [[
            'NumeroLinea' => 1,
            'CodigoCABYS' => '6803000000000',
            'Cantidad' => '1',
            'UnidadMedida' => 'Sp',
            'Detalle' => 'Servicio con <b>HTML</b> & "comillas" & \'apostrofes\'',
            'PrecioUnitario' => '1000.00',
            'MontoTotal' => '1000.00',
            'SubTotal' => '1000.00',
            'BaseImponible' => '1000.00',
            'ImpuestoAsumidoEmisorFabrica' => '0',
            'ImpuestoNeto' => '0',
            'MontoTotalLinea' => '1000.00',
        ]],
    ]]);

    $xml = $generator->generate(ReceiptType::ElectronicInvoice, $data);

    expect($xml)->toContain('&lt;b&gt;HTML&lt;/b&gt;');
    expect($xml)->toContain('&amp;');
    expect($xml)->not->toContain('<b>HTML</b>');
});

it('escapes CDATA injection attempt in Detalle', function () {
    $generator = app(XmlGeneratorService::class);
    $data = xmlPayload(['DetalleServicio' => [
        'LineaDetalle' => [[
            'NumeroLinea' => 1,
            'CodigoCABYS' => '6803000000000',
            'Cantidad' => '1',
            'UnidadMedida' => 'Sp',
            'Detalle' => ']]><evil/><!--',
            'PrecioUnitario' => '1000.00',
            'MontoTotal' => '1000.00',
            'SubTotal' => '1000.00',
            'BaseImponible' => '1000.00',
            'ImpuestoAsumidoEmisorFabrica' => '0',
            'ImpuestoNeto' => '0',
            'MontoTotalLinea' => '1000.00',
        ]],
    ]]);

    $xml = $generator->generate(ReceiptType::ElectronicInvoice, $data);

    expect($xml)->not->toContain('<evil/>');
    expect($xml)->toContain('&lt;evil/&gt;');
});

it('escapes XML tags in Ubicacion.OtrasSenas', function () {
    $generator = app(XmlGeneratorService::class);
    $data = xmlPayload(['Emisor' => [
        'Nombre' => 'Emisor Test',
        'Identificacion' => ['Tipo' => '01', 'Numero' => '112345678'],
        'Ubicacion' => [
            'Provincia' => '1',
            'Canton' => '01',
            'Distrito' => '01',
            'OtrasSenas' => '<script>document.cookie</script>',
        ],
    ]]);

    $xml = $generator->generate(ReceiptType::ElectronicInvoice, $data);

    expect($xml)->not->toContain('<script>document.cookie</script>');
    expect($xml)->toContain('&lt;script&gt;');
});

it('escapes XML tags in CorreoElectronico', function () {
    $generator = app(XmlGeneratorService::class);
    $data = xmlPayload(['Emisor' => [
        'Nombre' => 'Emisor Test',
        'Identificacion' => ['Tipo' => '01', 'Numero' => '112345678'],
        'CorreoElectronico' => ['<img src=x>@evil.com'],
    ]]);

    $xml = $generator->generate(ReceiptType::ElectronicInvoice, $data);

    expect($xml)->not->toContain('<img src=x>');
    expect($xml)->toContain('&lt;img');
});

// ── XXE (XML External Entity) protection ────────────────────────────

it('produces valid parseable XML even with malicious input', function () {
    $generator = app(XmlGeneratorService::class);
    $data = xmlPayload([
        'Emisor' => [
            'Nombre' => '<?xml version="1.0"?><!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>',
            'Identificacion' => ['Tipo' => '01', 'Numero' => '112345678'],
        ],
        'Receptor' => [
            'Nombre' => '&xxe;',
            'Identificacion' => ['Tipo' => '02', 'Numero' => '3101234567'],
        ],
    ]);

    $xml = $generator->generate(ReceiptType::ElectronicInvoice, $data);

    // Should be parseable XML
    $dom = new DOMDocument;
    $result = $dom->loadXML($xml);
    expect($result)->toBeTrue();

    // Should not contain unescaped entities
    expect($xml)->not->toContain('<!DOCTYPE');
    expect($xml)->not->toContain('<!ENTITY');
    expect($xml)->toContain('&amp;xxe;');
});

it('escapes attribute injection in OtroTexto codigo', function () {
    $generator = app(XmlGeneratorService::class);
    $data = xmlPayload([
        'Otros' => [
            'OtroTexto' => [
                ['codigo' => '" onload="alert(1)', 'valor' => 'test'],
            ],
        ],
    ]);

    $xml = $generator->generate(ReceiptType::ElectronicInvoice, $data);

    // DOMDocument escapes " to &quot; in attributes — the injection is neutralized
    expect($xml)->toContain('&quot;');
    expect($xml)->not->toContain('onload="alert');

    // Verify the XML is still valid and parseable
    $dom = new DOMDocument;
    expect($dom->loadXML($xml))->toBeTrue();
});