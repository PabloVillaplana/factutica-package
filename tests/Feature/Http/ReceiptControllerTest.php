<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use FactuTica\FactuticaCR\Enums\HaciendaStatus;
use FactuTica\FactuticaCR\Enums\ReceiptStatus;
use FactuTica\FactuticaCR\Enums\ReceiptType;
use FactuTica\FactuticaCR\Models\Receipt;
use FactuTica\FactuticaCR\Models\ReceiptPayload;
use FactuTica\FactuticaCR\Services\Hacienda\XmlSignerService;

beforeEach(function () {
    Http::fake([
        '*/token' => Http::response([
            'access_token' => 'fake-token', 'refresh_token' => 'fake-refresh',
            'expires_in' => 300, 'refresh_expires_in' => 1800,
        ]),
        '*/recepcion*' => Http::sequence()
            ->push(['clave' => str_repeat('5', 50), 'fecha' => now()->toIso8601String()], 202)
            ->push(['clave' => str_repeat('6', 50), 'fecha' => now()->toIso8601String()], 202)
            ->push(['clave' => str_repeat('7', 50), 'fecha' => now()->toIso8601String()], 202),
    ]);

    $signerMock = Mockery::mock(XmlSignerService::class);
    $signerMock->shouldReceive('sign')->andReturnUsing(fn (string $xml) => $xml);
    app()->instance(XmlSignerService::class, $signerMock);
});

function receiptPayload(array $overrides = []): array
{
    return array_merge([
        'receipt_type'             => 'FE',
        'condicion_venta'          => '01',
        'codigo_actividad_emisor'  => '6201.0',
        'receptor' => [
            'nombre' => 'Cliente Test',
            'identificacion' => ['tipo' => '01', 'numero' => '112345678'],
        ],
        'detalle_servicio' => [
            'linea_detalle' => [[
                'numero_linea' => '1', 'codigo_cabys' => '8311200000000',
                'cantidad' => '1', 'unidad_medida' => 'Sp',
                'detalle' => 'Servicio', 'precio_unitario' => '5000.00',
                'monto_total' => '5000.00', 'sub_total' => '5000.00',
                'base_imponible' => '5000.00',
                'impuesto' => [['codigo' => '01', 'codigo_tarifa_iva' => '08', 'tarifa' => '13.00', 'monto' => '650.00']],
                'impuesto_neto' => '650.00', 'monto_total_linea' => '5650.00',
            ]],
        ],
        'resumen_factura' => [
            'codigo_tipo_moneda' => ['codigo_moneda' => 'CRC', 'tipo_cambio' => '1'],
            'total_serv_gravados' => '5000.00', 'total_gravado' => '5000.00',
            'total_venta' => '5000.00', 'total_venta_neta' => '5000.00',
            'total_impuesto' => '650.00', 'total_comprobante' => '5650.00',
            'medio_pago' => [['tipo_medio_pago' => '01', 'total_medio_pago' => '5650.00']],
        ],
    ], $overrides);
}

// ── POST /receipts ─────────────────────────────────────────────────────

it('creates a receipt via POST', function () {
    $response = $this->postJson('/invoicing-cr/receipts', receiptPayload());

    $response->assertStatus(201)
        ->assertJsonPath('mensaje', 'Comprobante creado y enviado.')
        ->assertJsonPath('data.tipo_comprobante', 'FE')
        ->assertJsonPath('data.estado_comprobante', 'sent')
        ->assertJsonPath('data.emisor.nombre', 'Test Emisor S.A.')
        ->assertJsonStructure([
            'data' => ['id', 'tipo_comprobante', 'clave', 'numero_consecutivo', 'fecha_emision', 'estado_comprobante'],
        ]);
});

it('creates a receipt in async mode and returns 202', function () {
    Queue::fake();
    config(['invoicing-cr.invoicing.send_mode' => 'async']);

    $response = $this->postJson('/invoicing-cr/receipts', receiptPayload());

    $response->assertStatus(202)
        ->assertJsonPath('mensaje', 'Comprobante creado y encolado para envío.')
        ->assertJsonPath('data.tipo_comprobante', 'FE')
        ->assertJsonPath('data.estado_comprobante', 'pending');

    Queue::assertPushed(\FactuTica\FactuticaCR\Jobs\SendReceiptToProviderJob::class);
});

it('returns 422 for invalid receipt type', function () {
    $response = $this->postJson('/invoicing-cr/receipts', receiptPayload(['receipt_type' => 'INVALID']));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['receipt_type']);
});

it('returns 422 when required fields are missing', function () {
    $response = $this->postJson('/invoicing-cr/receipts', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['receipt_type', 'CondicionVenta']);
});

// ── GET /receipts ──────────────────────────────────────────────────────

it('lists receipts with pagination', function () {
    Receipt::factory()->count(3)->create();

    $response = $this->getJson('/invoicing-cr/receipts?per_page=2');

    $response->assertStatus(200)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonCount(2, 'data');
});

it('filters receipts by type', function () {
    Receipt::factory()->create(['receipt_type' => ReceiptType::ElectronicInvoice]);
    Receipt::factory()->create(['receipt_type' => ReceiptType::CreditNote]);

    $response = $this->getJson('/invoicing-cr/receipts?type=FE');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.tipo_comprobante', 'FE');
});

it('filters receipts by status', function () {
    Receipt::factory()->create(['receipt_status' => ReceiptStatus::Sent]);
    Receipt::factory()->create(['receipt_status' => ReceiptStatus::Accepted]);

    $response = $this->getJson('/invoicing-cr/receipts?status=accepted');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.estado_comprobante', 'accepted');
});

// ── GET /receipts/{id} ─────────────────────────────────────────────────

it('shows a receipt by id', function () {
    $receipt = Receipt::factory()->create();

    $response = $this->getJson("/invoicing-cr/receipts/{$receipt->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $receipt->id);
});

it('returns 404 for nonexistent receipt', function () {
    $response = $this->getJson('/invoicing-cr/receipts/9999');

    $response->assertStatus(404);
});

// ── GET /receipts/key/{uiKey} ──────────────────────────────────────────

it('shows a receipt by ui_key', function () {
    $receipt = Receipt::factory()->create(['ui_key' => str_repeat('1', 50)]);

    $response = $this->getJson('/invoicing-cr/receipts/key/'.str_repeat('1', 50));

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $receipt->id)
        ->assertJsonPath('data.clave', str_repeat('1', 50));
});

it('returns 404 for nonexistent ui_key', function () {
    $response = $this->getJson('/invoicing-cr/receipts/key/'.str_repeat('0', 50));

    $response->assertStatus(404);
});
