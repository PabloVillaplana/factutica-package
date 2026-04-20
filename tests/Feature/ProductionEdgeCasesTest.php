<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use FactuTica\FactuticaCR\Enums\HaciendaStatus;
use FactuTica\FactuticaCR\Enums\IdentificationType;
use FactuTica\FactuticaCR\Enums\ReceiptStatus;
use FactuTica\FactuticaCR\Enums\ReceiptType;
use FactuTica\FactuticaCR\Events\ReceiptAccepted;
use FactuTica\FactuticaCR\Events\ReceiptRejected;
use FactuTica\FactuticaCR\Exceptions\HaciendaException;
use FactuTica\FactuticaCR\Exceptions\InvalidReceiptException;
use FactuTica\FactuticaCR\Models\HaciendaResponse;
use FactuTica\FactuticaCR\Models\Receipt;
use FactuTica\FactuticaCR\Models\ReceiptConsecutive;
use FactuTica\FactuticaCR\Providers\HaciendaProvider;
use FactuTica\FactuticaCR\Providers\ProviderResponse;
use FactuTica\FactuticaCR\Services\Hacienda\Idp\HaciendaIdpService;
use FactuTica\FactuticaCR\Services\Hacienda\XmlSignerService;
use FactuTica\FactuticaCR\Services\KeyGeneratorService;
use FactuTica\FactuticaCR\Services\PayloadTransformerService;
use FactuTica\FactuticaCR\Services\Webhook\WebhookService;
use FactuTica\FactuticaCR\Services\Webhook\WebhookVerifierService;

/**
 * Genera una clave de 50 digitos valida para un emisor dado.
 * Estructura: 506 + DDMMYY + emisorId(12) + consecutivo(20) + situacion(1) + security(8)
 */
function edgeCaseUiKey(string $emisorNumber): string
{
    $padded = str_pad(preg_replace('/[^0-9]/', '', $emisorNumber), 12, '0', STR_PAD_LEFT);

    return '506010426'.$padded.'00100001010000000001'.'1'.'87654321';
}

// ---------------------------------------------------------------------------
// Webhook idempotency tests
// ---------------------------------------------------------------------------

it('ignores duplicate webhook for same receipt and dispatches events only once', function () {
    Event::fake([ReceiptAccepted::class]);

    // Mock verifier to always pass
    $verifierMock = Mockery::mock(WebhookVerifierService::class);
    $verifierMock->shouldReceive('verify')->andReturn(['valid' => true, 'reason' => null]);
    app()->instance(WebhookVerifierService::class, $verifierMock);

    $uiKey = edgeCaseUiKey('601230456');
    $receipt = Receipt::factory()->create([
        'ui_key'          => $uiKey,
        'issuer_number'   => '601230456',
        'receipt_status'  => ReceiptStatus::Sent,
        'hacienda_status' => HaciendaStatus::Pending,
    ]);

    $service = app(WebhookService::class);

    // First call: should process normally
    $result1 = $service->process($uiKey, 'aceptado', null, now()->toIso8601String());
    expect($result1['status'])->toBe('accepted');

    // Second call: same webhook, should be ignored (idempotency)
    $result2 = $service->process($uiKey, 'aceptado', null, now()->toIso8601String());
    expect($result2['status'])->toBe('accepted');
    expect($result2['receipt_id'])->toBe($receipt->id);

    // Events should only have been dispatched once (from the first call)
    Event::assertDispatchedTimes(ReceiptAccepted::class, 1);

    // Only one HaciendaResponse record should exist
    expect(HaciendaResponse::where('receipt_id', $receipt->id)->count())->toBe(1);
});

it('ignores webhook with different status when receipt already accepted (idempotency)', function () {
    Event::fake([ReceiptAccepted::class, ReceiptRejected::class]);

    $verifierMock = Mockery::mock(WebhookVerifierService::class);
    $verifierMock->shouldReceive('verify')->andReturn(['valid' => true, 'reason' => null]);
    app()->instance(WebhookVerifierService::class, $verifierMock);

    $uiKey = edgeCaseUiKey('701230456');
    $receipt = Receipt::factory()->create([
        'ui_key'          => $uiKey,
        'issuer_number'   => '701230456',
        'receipt_status'  => ReceiptStatus::Accepted,
        'hacienda_status' => HaciendaStatus::Accepted,
    ]);

    $service = app(WebhookService::class);

    // Webhook arrives with "rechazado" but receipt is already accepted
    $result = $service->process($uiKey, 'rechazado', null, now()->toIso8601String());

    // Should return early with the current accepted status, not override it
    expect($result['status'])->toBe('accepted');

    // Receipt should remain accepted
    $receipt->refresh();
    expect($receipt->hacienda_status)->toBe(HaciendaStatus::Accepted);
    expect($receipt->receipt_status)->toBe(ReceiptStatus::Accepted);

    // No events should have been dispatched
    Event::assertNotDispatched(ReceiptAccepted::class);
    Event::assertNotDispatched(ReceiptRejected::class);

    // No HaciendaResponse should be created
    expect(HaciendaResponse::where('receipt_id', $receipt->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Hacienda returns 202 with empty clave
// ---------------------------------------------------------------------------

it('handles Hacienda 202 response with empty clave gracefully', function () {
    Http::fake([
        '*/token' => Http::response([
            'access_token'       => 'fake-token',
            'refresh_token'      => 'fake-refresh',
            'expires_in'         => 300,
            'refresh_expires_in' => 1800,
            'token_type'         => 'Bearer',
        ]),
        '*/recepcion*' => Http::response([], 202),
    ]);

    $receipt = Receipt::factory()->create([
        'ui_key'         => edgeCaseUiKey('801230456'),
        'issuer_number'  => '801230456',
        'receipt_status' => ReceiptStatus::Pending,
    ]);

    $provider = app(HaciendaProvider::class);

    $response = $provider->send($receipt, [
        'clave'          => $receipt->ui_key,
        'emisor'         => ['tipoIdentificacion' => '01', 'numeroIdentificacion' => '801230456'],
        'comprobanteXml' => base64_encode('<xml/>'),
    ]);

    // When Hacienda returns empty body, clave falls back to empty string
    expect($response)->toBeInstanceOf(ProviderResponse::class);
    expect($response->clave)->toBe('');
    expect($response->httpStatus)->toBe(202);
});

// ---------------------------------------------------------------------------
// IdP timeout handling
// ---------------------------------------------------------------------------

it('throws HaciendaException when IdP connection times out', function () {
    Http::fake([
        '*/token' => Http::response(fn () => throw new ConnectionException('Connection timed out')),
    ]);

    // Clear any cached token so it forces a fresh authenticate()
    $idpService = app(HaciendaIdpService::class);
    $idpService->invalidateToken();

    $idpService->getAccessToken();
})->throws(ConnectionException::class);

// ---------------------------------------------------------------------------
// Consecutive overflow
// ---------------------------------------------------------------------------

it('throws InvalidReceiptException when consecutive exceeds maximum', function () {
    $keyGenerator = app(KeyGeneratorService::class);

    // 9999999999 is the max allowed, should work
    $result = $keyGenerator->generateConsecutiveKey(ReceiptType::ElectronicInvoice, 9999999999);
    expect($result)->toHaveLength(20);
    expect(substr($result, -10))->toBe('9999999999');

    // 10000000000 exceeds the limit, should throw
    $keyGenerator->generateConsecutiveKey(ReceiptType::ElectronicInvoice, 10000000000);
})->throws(InvalidReceiptException::class, 'Consecutivo debe estar entre 1 y 9999999999');

it('throws InvalidReceiptException when consecutive is zero', function () {
    $keyGenerator = app(KeyGeneratorService::class);

    $keyGenerator->generateConsecutiveKey(ReceiptType::ElectronicInvoice, 0);
})->throws(InvalidReceiptException::class, 'Consecutivo debe estar entre 1 y 9999999999');

// ---------------------------------------------------------------------------
// PayloadTransformer with empty key
// ---------------------------------------------------------------------------

it('handles empty string key in payload transformer without crashing', function () {
    $transformer = app(PayloadTransformerService::class);

    $result = $transformer->toPascalCase([
        '' => 'some_value',
        'condicion_venta' => '01',
        'detalle_servicio' => [
            'linea_detalle' => [
                ['numero_linea' => '1', 'detalle' => 'Test'],
            ],
        ],
    ]);

    // Empty key passes through as-is (mapKey keeps unknown keys)
    expect($result)->toHaveKey('');
    expect($result[''])->toBe('some_value');
    // Normal keys are still transformed
    expect($result)->toHaveKey('CondicionVenta');
    expect($result['CondicionVenta'])->toBe('01');
    expect($result)->toHaveKey('DetalleServicio');
    expect($result['DetalleServicio']['LineaDetalle'][0])->toHaveKey('NumeroLinea');
});

// ---------------------------------------------------------------------------
// Webhook for receipt in "pending" status (normal case)
// ---------------------------------------------------------------------------

it('processes webhook for receipt in pending hacienda status', function () {
    Event::fake([ReceiptAccepted::class]);

    $verifierMock = Mockery::mock(WebhookVerifierService::class);
    $verifierMock->shouldReceive('verify')->andReturn(['valid' => true, 'reason' => null]);
    app()->instance(WebhookVerifierService::class, $verifierMock);

    $uiKey = edgeCaseUiKey('901230456');
    $receipt = Receipt::factory()->create([
        'ui_key'          => $uiKey,
        'issuer_number'   => '901230456',
        'receipt_status'  => ReceiptStatus::Pending,
        'hacienda_status' => HaciendaStatus::Pending,
    ]);

    $service = app(WebhookService::class);
    $result = $service->process($uiKey, 'aceptado', null, now()->toIso8601String());

    expect($result['status'])->toBe('accepted');

    $receipt->refresh();
    expect($receipt->hacienda_status)->toBe(HaciendaStatus::Accepted);
    expect($receipt->receipt_status)->toBe(ReceiptStatus::Accepted);

    Event::assertDispatched(ReceiptAccepted::class);
});

// ---------------------------------------------------------------------------
// Webhook for receipt in "sent" status (normal case)
// ---------------------------------------------------------------------------

it('processes webhook for receipt in sent status', function () {
    Event::fake([ReceiptRejected::class]);

    $verifierMock = Mockery::mock(WebhookVerifierService::class);
    $verifierMock->shouldReceive('verify')->andReturn(['valid' => true, 'reason' => null]);
    app()->instance(WebhookVerifierService::class, $verifierMock);

    $uiKey = edgeCaseUiKey('111230456');
    $receipt = Receipt::factory()->create([
        'ui_key'          => $uiKey,
        'issuer_number'   => '111230456',
        'receipt_status'  => ReceiptStatus::Sent,
        'hacienda_status' => HaciendaStatus::Pending,
    ]);

    $service = app(WebhookService::class);
    $result = $service->process($uiKey, 'rechazado', null, now()->toIso8601String());

    expect($result['status'])->toBe('rejected');

    $receipt->refresh();
    expect($receipt->hacienda_status)->toBe(HaciendaStatus::Rejected);
    expect($receipt->receipt_status)->toBe(ReceiptStatus::Rejected);

    Event::assertDispatched(ReceiptRejected::class);
});

// ---------------------------------------------------------------------------
// Webhook for receipt already in "accepted" status (idempotent return)
// ---------------------------------------------------------------------------

it('returns early without processing when receipt already accepted', function () {
    Event::fake([ReceiptAccepted::class, ReceiptRejected::class]);

    $verifierMock = Mockery::mock(WebhookVerifierService::class);
    $verifierMock->shouldReceive('verify')->andReturn(['valid' => true, 'reason' => null]);
    app()->instance(WebhookVerifierService::class, $verifierMock);

    $uiKey = edgeCaseUiKey('221230456');
    $receipt = Receipt::factory()->create([
        'ui_key'          => $uiKey,
        'issuer_number'   => '221230456',
        'receipt_status'  => ReceiptStatus::Accepted,
        'hacienda_status' => HaciendaStatus::Accepted,
    ]);

    $service = app(WebhookService::class);
    $result = $service->process($uiKey, 'aceptado', null, now()->toIso8601String());

    // Returns existing status without re-processing
    expect($result['status'])->toBe('accepted');
    expect($result['receipt_id'])->toBe($receipt->id);

    // No events dispatched (skipped entirely)
    Event::assertNotDispatched(ReceiptAccepted::class);
    Event::assertNotDispatched(ReceiptRejected::class);

    // No HaciendaResponse created
    expect(HaciendaResponse::where('receipt_id', $receipt->id)->count())->toBe(0);

    // Receipt unchanged
    $receipt->refresh();
    expect($receipt->hacienda_status)->toBe(HaciendaStatus::Accepted);
});
