<?php

use FactuTica\FactuticaCR\Services\Hacienda\CertificateLoaderService;
use FactuTica\FactuticaCR\Services\Hacienda\Idp\HaciendaIdpService;
use FactuTica\FactuticaCR\Services\Hacienda\XmlSignerService;
use FactuTica\FactuticaCR\Services\XmlGenerator\XmlGeneratorService;
use FactuTica\FactuticaCR\Enums\ReceiptType;

/*
|--------------------------------------------------------------------------
| Integration tests — Hacienda sandbox
|--------------------------------------------------------------------------
|
| Estos tests se conectan al sandbox real de Hacienda.
| Requieren credenciales y certificado configurados en .env.
|
| Correr manualmente:
|   vendor/bin/pest --group=integration
|
*/

it('loads the .p12 certificate and extracts issuer data', function () {
    $cert = app(CertificateLoaderService::class);
    $cert->load();

    expect($cert->getPrivateKey())->toContain('-----BEGIN');
    expect($cert->getCertificate())->toContain('-----BEGIN CERTIFICATE-----');
    expect($cert->getCertificateBase64())->not->toContain('-----');
    expect($cert->getCertificateDigestSha1())->not->toBeEmpty();
    expect($cert->getCertificateDigestSha256())->not->toBeEmpty();

    $issuer = $cert->getIssuerSerial();
    expect($issuer)->toHaveKeys(['issuer', 'serial']);
    expect($issuer['issuer'])->toContain('HACIENDA');
    expect($issuer['serial'])->not->toBeEmpty();
})->group('integration');

it('authenticates with Hacienda IdP and obtains a token', function () {
    $idp = app(HaciendaIdpService::class);
    $token = $idp->getAccessToken();

    expect($token)->toBeString();
    expect($token)->not->toBeEmpty();
    expect(strlen($token))->toBeGreaterThan(100);
})->group('integration');

it('reuses cached token on second call', function () {
    $idp = app(HaciendaIdpService::class);

    $token1 = $idp->getAccessToken();
    $token2 = $idp->getAccessToken();

    expect($token1)->toBe($token2);
})->group('integration');

it('obtains a new token after invalidation', function () {
    $idp = app(HaciendaIdpService::class);

    $token1 = $idp->getAccessToken();
    $idp->invalidateToken();
    $token2 = $idp->getAccessToken();

    expect($token2)->toBeString();
    expect($token2)->not->toBeEmpty();
})->group('integration');

it('signs an XML document with the certificate', function () {
    $generator = new XmlGeneratorService();
    $xml = $generator->generate(ReceiptType::ElectronicInvoice, [
        'Clave'                 => '50601032600310112345600100001010000000001199999999',
        'ProveedorSistema'      => 'factutica',
        'CodigoActividadEmisor' => '620101',
        'NumeroConsecutivo'     => '00100001010000000001',
        'FechaEmision'          => now()->toIso8601String(),
        'CondicionVenta'        => '01',
        'Emisor' => [
            'Nombre'         => 'Test Emisor S.A.',
            'Identificacion' => ['Tipo' => '02', 'Numero' => '3101123456'],
            'Ubicacion'      => [
                'Provincia' => '1', 'Canton' => '01', 'Distrito' => '01',
                'OtrasSenas' => 'San José Centro',
            ],
            'CorreoElectronico' => ['test@emisor.com'],
        ],
        'Receptor' => [
            'Nombre'         => 'Cliente Test',
            'Identificacion' => ['Tipo' => '01', 'Numero' => '112345678'],
        ],
        'DetalleServicio' => [
            'LineaDetalle' => [[
                'NumeroLinea'    => '1',
                'CodigoCABYS'    => '8410101000100',
                'Cantidad'       => '1',
                'UnidadMedida'   => 'Sp',
                'Detalle'        => 'Servicio de prueba',
                'PrecioUnitario' => '1000.00',
                'MontoTotal'     => '1000.00',
                'SubTotal'       => '1000.00',
                'BaseImponible'  => '1000.00',
                'Impuesto'       => [[
                    'Codigo' => '01', 'CodigoTarifaIVA' => '08',
                    'Tarifa' => '13.00', 'Monto' => '130.00',
                ]],
                'ImpuestoNeto'    => '130.00',
                'MontoTotalLinea' => '1130.00',
            ]],
        ],
        'ResumenFactura' => [
            'CodigoTipoMoneda'  => ['CodigoMoneda' => 'CRC', 'TipoCambio' => '1'],
            'TotalServGravados' => '1000.00',
            'TotalGravado'      => '1000.00',
            'TotalVenta'        => '1000.00',
            'TotalVentaNeta'    => '1000.00',
            'TotalImpuesto'     => '130.00',
            'TotalComprobante'  => '1130.00',
            'MedioPago'         => [['TipoMedioPago' => '01', 'TotalMedioPago' => '1130.00']],
        ],
    ]);

    $signer = app(XmlSignerService::class);
    $signedXml = $signer->sign($xml);

    expect($signedXml)->toContain('<ds:Signature');
    expect($signedXml)->toContain('<ds:SignatureValue');
    expect($signedXml)->toContain('<ds:X509Certificate>');
    expect($signedXml)->toContain('xades:SignedProperties');
    expect($signedXml)->toContain('</FacturaElectronica>');

    // Verificar que el XML firmado sigue siendo parseable
    $doc = new DOMDocument();
    expect($doc->loadXML($signedXml))->toBeTrue();
})->group('integration');