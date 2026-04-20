<?php

use Illuminate\Support\Facades\Queue;
use FactuTica\FactuticaCR\Enums\ReceiptStatus;
use FactuTica\FactuticaCR\Jobs\SendSentReceiptToProviderJob;
use FactuTica\FactuticaCR\Models\SentReceipt;

function receptionPayload(array $overrides = []): array
{
    return array_merge([
        'clave'                      => str_repeat('5', 50),
        'receipt_type'               => 'FE',
        'consecutive_number'         => '00100001010000000001',
        'emission_date'              => '2026-04-01',
        'reception_status'           => 'accepted',
        'reception_message'          => 'Documento aceptado',
        'total_expense'              => 10000,
        'tax_amount'                 => 1300,
        'total_voucher'              => 11300,
        'issuer_name'                => 'Proveedor Test S.A.',
        'issuer_number'              => '3102654321',
        'issuer_identification_type' => '02',
    ], $overrides);
}

// ── POST /reception ──────────────────────────────────────────────────

it('creates a sent receipt and dispatches job', function () {
    Queue::fake();

    $response = $this->postJson('/invoicing-cr/reception', receptionPayload());

    $response->assertStatus(202)
        ->assertJsonPath('mensaje', 'Mensaje de recepción creado y encolado.')
        ->assertJsonPath('data.tipo_comprobante', 'FE')
        ->assertJsonPath('data.estado_comprobante', 'pending');

    expect(SentReceipt::count())->toBe(1);

    $sentReceipt = SentReceipt::first();
    expect($sentReceipt->receipt_status)->toBe(ReceiptStatus::Pending);
    expect($sentReceipt->reception_status->value)->toBe('accepted');
    expect($sentReceipt->issuer_name)->toBe('Proveedor Test S.A.');
    expect($sentReceipt->issuer_number)->toBe('3102654321');
    expect((float) $sentReceipt->total_voucher)->toBe(11300.0);

    Queue::assertPushed(SendSentReceiptToProviderJob::class);
});

it('accepts partially_accepted reception status', function () {
    Queue::fake();

    $response = $this->postJson('/invoicing-cr/reception', receptionPayload([
        'reception_status' => 'partially_accepted',
        'reception_message' => 'Pendiente verificar línea 3',
    ]));

    $response->assertStatus(202);

    $sentReceipt = SentReceipt::first();
    expect($sentReceipt->reception_status->value)->toBe('partially_accepted');
});

it('accepts rejected reception status', function () {
    Queue::fake();

    $response = $this->postJson('/invoicing-cr/reception', receptionPayload([
        'reception_status' => 'rejected',
        'reception_message' => 'Datos del emisor incorrectos',
    ]));

    $response->assertStatus(202);

    $sentReceipt = SentReceipt::first();
    expect($sentReceipt->reception_status->value)->toBe('rejected');
});

it('uses config emisor as receiver when not provided', function () {
    Queue::fake();

    $response = $this->postJson('/invoicing-cr/reception', receptionPayload());

    $response->assertStatus(202);

    $sentReceipt = SentReceipt::first();
    expect($sentReceipt->receiver_name)->toBe(config('invoicing-cr.invoicing.emisor.nombre'));
    expect($sentReceipt->receiver_number)->toBe(config('invoicing-cr.invoicing.emisor.cedula'));
});

it('returns 422 when clave is missing', function () {
    $response = $this->postJson('/invoicing-cr/reception', receptionPayload([
        'clave' => null,
    ]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['clave']);
});

it('returns 422 when clave is not 50 characters', function () {
    $response = $this->postJson('/invoicing-cr/reception', receptionPayload([
        'clave' => '12345',
    ]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['clave']);
});

it('returns 422 when reception_status is invalid', function () {
    $response = $this->postJson('/invoicing-cr/reception', receptionPayload([
        'reception_status' => 'invalid_status',
    ]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['reception_status']);
});

it('returns 422 when required fields are missing', function () {
    $response = $this->postJson('/invoicing-cr/reception', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'clave', 'receipt_type', 'consecutive_number',
            'emission_date', 'reception_status',
            'total_expense', 'total_voucher',
            'issuer_name', 'issuer_number', 'issuer_identification_type',
        ]);
});

it('returns 422 when issuer_identification_type is invalid', function () {
    $response = $this->postJson('/invoicing-cr/reception', receptionPayload([
        'issuer_identification_type' => '99',
    ]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['issuer_identification_type']);
});