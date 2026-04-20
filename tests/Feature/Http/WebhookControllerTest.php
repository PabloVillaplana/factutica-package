<?php

use Illuminate\Support\Facades\Event;
use FactuTica\FactuticaCR\Enums\HaciendaStatus;
use FactuTica\FactuticaCR\Enums\ReceiptStatus;
use FactuTica\FactuticaCR\Events\ReceiptAccepted;
use FactuTica\FactuticaCR\Events\ReceiptRejected;
use FactuTica\FactuticaCR\Models\HaciendaResponse;
use FactuTica\FactuticaCR\Models\Receipt;

/**
 * Genera una clave de 50 dígitos válida para un emisor dado.
 * Estructura: 506 + DDMMYY + emisorId(12) + consecutivo(20) + situacion(1) + security(8)
 */
function fakeUiKey(string $emisorNumber): string
{
    $padded = str_pad(preg_replace('/[^0-9]/', '', $emisorNumber), 12, '0', STR_PAD_LEFT);
    return '506310326'.$padded.'00100001010000000001'.'1'.'12345678';
}

it('processes a valid webhook and updates receipt status', function () {
    $receipt = Receipt::factory()->create([
        'ui_key'         => fakeUiKey('112750560'),
        'receipt_status'  => ReceiptStatus::Sent,
        'hacienda_status' => HaciendaStatus::Pending,
        'issuer_number'   => '112750560',
    ]);

    $response = $this->postJson('/invoicing-cr/webhook', [
        'clave'        => fakeUiKey('112750560'),
        'ind-estado'   => 'aceptado',
        'fecha'        => now()->toIso8601String(),
        'respuesta-xml' => null,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('mensaje', 'Webhook procesado.')
        ->assertJsonPath('comprobante_id', $receipt->id);

    $receipt->refresh();
    expect($receipt->hacienda_status)->toBe(HaciendaStatus::Accepted);
    expect($receipt->receipt_status)->toBe(ReceiptStatus::Accepted);
});

it('rejects webhook with invalid clave length', function () {
    $response = $this->postJson('/invoicing-cr/webhook', [
        'clave' => '12345',
        'ind-estado' => 'aceptado',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('mensaje', 'Clave inválida.');
});

it('returns 404 for nonexistent clave', function () {
    $response = $this->postJson('/invoicing-cr/webhook', [
        'clave'      => str_repeat('9', 50),
        'ind-estado' => 'aceptado',
    ]);

    $response->assertStatus(404);
});

it('handles rejected status from Hacienda', function () {
    $receipt = Receipt::factory()->create([
        'ui_key'          => fakeUiKey('223456789'),
        'issuer_number'   => '223456789',
        'receipt_status'  => ReceiptStatus::Sent,
        'hacienda_status' => HaciendaStatus::Pending,
    ]);

    $this->postJson('/invoicing-cr/webhook', [
        'clave'      => fakeUiKey('223456789'),
        'ind-estado' => 'rechazado',
        'fecha'      => now()->toIso8601String(),
    ]);

    $receipt->refresh();
    expect($receipt->hacienda_status)->toBe(HaciendaStatus::Rejected);
    expect($receipt->receipt_status)->toBe(ReceiptStatus::Rejected);
});

it('creates a HaciendaResponse record', function () {
    $receipt = Receipt::factory()->create([
        'ui_key'       => fakeUiKey('334567890'),
        'issuer_number' => '334567890',
    ]);

    $this->postJson('/invoicing-cr/webhook', [
        'clave'      => fakeUiKey('334567890'),
        'ind-estado' => 'aceptado',
        'fecha'      => '2026-03-31T10:00:00-06:00',
    ]);

    $haciendaResponse = HaciendaResponse::where('receipt_id', $receipt->id)->first();
    expect($haciendaResponse)->not->toBeNull();
    expect($haciendaResponse->hacienda_status)->toBe(HaciendaStatus::Accepted);
});

it('dispatches ReceiptAccepted event on accepted webhook', function () {
    Event::fake([ReceiptAccepted::class]);

    $receipt = Receipt::factory()->create([
        'ui_key'       => fakeUiKey('445678901'),
        'issuer_number' => '445678901',
    ]);

    $this->postJson('/invoicing-cr/webhook', [
        'clave'      => fakeUiKey('445678901'),
        'ind-estado' => 'aceptado',
        'fecha'      => now()->toIso8601String(),
    ]);

    Event::assertDispatched(ReceiptAccepted::class);
});

it('dispatches ReceiptRejected event on rejected webhook', function () {
    Event::fake([ReceiptRejected::class]);

    $receipt = Receipt::factory()->create([
        'ui_key'       => fakeUiKey('556789012'),
        'issuer_number' => '556789012',
    ]);

    $this->postJson('/invoicing-cr/webhook', [
        'clave'      => fakeUiKey('556789012'),
        'ind-estado' => 'rechazado',
        'fecha'      => now()->toIso8601String(),
    ]);

    Event::assertDispatched(ReceiptRejected::class);
});