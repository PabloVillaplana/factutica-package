<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use FactuTica\FactuticaCR\Enums\ReceiptStatus;
use FactuTica\FactuticaCR\Events\ReceiptCreated;
use FactuTica\FactuticaCR\Events\ReceiptSent;
use FactuTica\FactuticaCR\Enums\ReceiptType;
use FactuTica\FactuticaCR\Exceptions\InvalidReceiptException;
use FactuTica\FactuticaCR\Models\Receipt;
use FactuTica\FactuticaCR\Models\ReceiptPayload;
use FactuTica\FactuticaCR\Services\Hacienda\XmlSignerService;
use FactuTica\FactuticaCR\Services\InvoicingService;

beforeEach(function () {
    Http::fake([
        '*/token' => Http::response([
            'access_token'       => 'fake-token',
            'refresh_token'      => 'fake-refresh',
            'expires_in'         => 300,
            'refresh_expires_in' => 1800,
            'token_type'         => 'Bearer',
        ]),
        '*/recepcion/v1/*' => Http::sequence()
            ->push(['clave' => 'clave-'.uniqid(), 'fecha' => now()->toIso8601String()], 200)
            ->push(['clave' => 'clave-'.uniqid(), 'fecha' => now()->toIso8601String()], 200)
            ->push(['clave' => 'clave-'.uniqid(), 'fecha' => now()->toIso8601String()], 200)
            ->push(['clave' => 'clave-'.uniqid(), 'fecha' => now()->toIso8601String()], 200)
            ->push(['clave' => 'clave-'.uniqid(), 'fecha' => now()->toIso8601String()], 200),
    ]);

    // Mock del signer — no tenemos certificado en tests unitarios
    $signerMock = Mockery::mock(XmlSignerService::class);
    $signerMock->shouldReceive('sign')->andReturnUsing(fn (string $xml) => $xml.'<!-- signed -->');
    app()->instance(XmlSignerService::class, $signerMock);
});

/**
 * Datos completos para generar XML + crear receipt.
 */
function validReceiptData(array $overrides = []): array
{
    return array_merge([
        'CondicionVenta'               => '01',
        'CodigoActividadEmisor'        => '6201.0',
        'Receptor' => [
            'Nombre'         => 'Cliente Test',
            'Identificacion' => ['Tipo' => '01', 'Numero' => '112345678'],
        ],
        'DetalleServicio' => [
            'LineaDetalle' => [[
                'NumeroLinea' => '1', 'CodigoCABYS' => '8410101000100',
                'Cantidad' => '1', 'UnidadMedida' => 'Sp',
                'Detalle' => 'Servicio', 'PrecioUnitario' => '10000.00',
                'MontoTotal' => '10000.00', 'SubTotal' => '10000.00',
                'BaseImponible' => '10000.00',
                'Impuesto' => [['Codigo' => '01', 'CodigoTarifaIVA' => '08', 'Tarifa' => '13.00', 'Monto' => '1300.00']],
                'ImpuestoNeto' => '1300.00', 'MontoTotalLinea' => '11300.00',
            ]],
        ],
        'ResumenFactura' => [
            'CodigoTipoMoneda' => ['CodigoMoneda' => 'CRC', 'TipoCambio' => '1'],
            'TotalServGravados' => '10000.00', 'TotalGravado' => '10000.00',
            'TotalVenta' => '10000.00', 'TotalVentaNeta' => '10000.00',
            'TotalImpuesto' => '1300.00', 'TotalComprobante' => '11300.00',
            'MedioPago' => [['TipoMedioPago' => '01', 'TotalMedioPago' => '11300.00']],
        ],
    ], $overrides);
}

it('throws on invalid receipt type', function () {
    $service = app(InvoicingService::class);
    $service->createAndSend('INVALID', []);
})->throws(InvalidReceiptException::class, 'no válido');

it('creates a receipt, generates XML, signs it, and sends to provider', function () {
    $service = app(InvoicingService::class);

    $result = $service->createAndSend('FE', validReceiptData());

    expect($result)->toHaveKeys(['receipt', 'response']);
    expect($result['receipt'])->toBeInstanceOf(Receipt::class);
    expect($result['receipt']->receipt_type)->toBe(ReceiptType::ElectronicInvoice);
    expect($result['receipt']->receipt_status)->toBe(ReceiptStatus::Sent);
    expect($result['receipt']->consecutive_number)->toHaveLength(20);
    expect($result['receipt']->issuer_name)->toBe('Test Emisor S.A.'); // from config
    expect($result['receipt']->ui_key)->toHaveLength(50);
    expect($result['receipt']->consecutive_number)->toHaveLength(20);
    expect($result['receipt']->signed_xml)->toContain('<!-- signed -->');
});

it('dispatches ReceiptCreated and ReceiptSent events', function () {
    Event::fake([ReceiptCreated::class, ReceiptSent::class]);

    $service = app(InvoicingService::class);
    $service->createAndSend('FE', validReceiptData());

    Event::assertDispatched(ReceiptCreated::class);
    Event::assertDispatched(ReceiptSent::class);
});

it('increments consecutive number per receipt type', function () {
    $service = app(InvoicingService::class);

    $result1 = $service->createAndSend('FE', validReceiptData());
    $result2 = $service->createAndSend('FE', validReceiptData());
    $result3 = $service->createAndSend('TE', validReceiptData());

    // Los últimos 10 dígitos del consecutivo de 20 son el número secuencial
    $num = fn ($r) => (int) substr($r->consecutive_number, -10);
    expect($num($result1['receipt']))->toBe(1);
    expect($num($result2['receipt']))->toBe(2);
    expect($num($result3['receipt']))->toBe(1); // TE starts at 1
});

it('increments consecutive independently per establishment and terminal', function () {
    $service = app(InvoicingService::class);

    $result1 = $service->createAndSend('FE', validReceiptData(['establishment' => 1, 'terminal' => 1]));
    $result2 = $service->createAndSend('FE', validReceiptData(['establishment' => 1, 'terminal' => 1]));
    $result3 = $service->createAndSend('FE', validReceiptData(['establishment' => 1, 'terminal' => 3]));
    $result4 = $service->createAndSend('FE', validReceiptData(['establishment' => 2, 'terminal' => 1]));

    $num = fn ($r) => (int) substr($r->consecutive_number, -10);
    expect($num($result1['receipt']))->toBe(1); // est=1, term=1, FE #1
    expect($num($result2['receipt']))->toBe(2); // est=1, term=1, FE #2
    expect($num($result3['receipt']))->toBe(1); // est=1, term=3, FE #1 (new terminal)
    expect($num($result4['receipt']))->toBe(1); // est=2, term=1, FE #1 (new establishment)

    // Verify establishment and terminal are in the consecutive key
    $key1 = $result1['receipt']->consecutive_number;
    expect(substr($key1, 0, 3))->toBe('001');   // establishment=1
    expect(substr($key1, 3, 5))->toBe('00001'); // terminal=1

    $key3 = $result3['receipt']->consecutive_number;
    expect(substr($key3, 0, 3))->toBe('001');   // establishment=1
    expect(substr($key3, 3, 5))->toBe('00003'); // terminal=3

    $key4 = $result4['receipt']->consecutive_number;
    expect(substr($key4, 0, 3))->toBe('002');   // establishment=2
    expect(substr($key4, 3, 5))->toBe('00001'); // terminal=1
});

it('stores payload with receipt', function () {
    $service = app(InvoicingService::class);
    $data = validReceiptData();

    $result = $service->createAndSend('FE', $data);

    $payload = ReceiptPayload::where('receipt_id', $result['receipt']->id)->first();
    expect($payload)->not->toBeNull();
    expect($payload->payload)->toBeArray();
    expect($payload->payload['ResumenFactura']['TotalVenta'])->toBe('10000.00');
});

it('retrieves document by id', function () {
    $receipt = Receipt::create([
        'receipt_type'               => ReceiptType::ElectronicInvoice,
        'consecutive_number'         => 1,
        'emission_date'              => now(),
        'receipt_status'             => ReceiptStatus::Pending,
        'hacienda_status'            => 'pending',
        'sell_condition'             => '01',
        'total_amount'               => 1000,
        'total_voucher'              => 1000,
        'currency'                   => 'CRC',
        'issuer_name'                => 'Test',
        'issuer_number'              => '123',
        'issuer_identification_type' => '01',
    ]);

    $service = app(InvoicingService::class);
    $found = $service->getDocumentById($receipt->id);

    expect($found->id)->toBe($receipt->id);
});