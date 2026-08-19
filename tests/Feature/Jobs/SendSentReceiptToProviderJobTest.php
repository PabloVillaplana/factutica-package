<?php

use Illuminate\Support\Facades\Http;
use FactuTica\FactuticaCR\Enums\HaciendaStatus;
use FactuTica\FactuticaCR\Enums\ReceiptStatus;
use FactuTica\FactuticaCR\Jobs\SendSentReceiptToProviderJob;
use FactuTica\FactuticaCR\Models\SentReceipt;
use FactuTica\FactuticaCR\Services\Hacienda\XmlSignerService;

beforeEach(function () {
    Http::fake([
        '*/token' => Http::response([
            'access_token'       => 'fake-token',
            'refresh_token'      => 'fake-refresh',
            'expires_in'         => 300,
            'refresh_expires_in' => 1800,
            'token_type'         => 'Bearer',
        ]),
        '*/recepcion/v1/*' => Http::response([
            'clave' => str_repeat('5', 50),
            'fecha' => now()->toIso8601String(),
        ], 200),
    ]);

    // Mock del signer — no tenemos certificado en tests unitarios.
    $signerMock = Mockery::mock(XmlSignerService::class);
    $signerMock->shouldReceive('sign')->andReturnUsing(fn (string $xml) => $xml.'<!-- signed -->');
    app()->instance(XmlSignerService::class, $signerMock);
});

function pendingSentReceipt(array $overrides = []): SentReceipt
{
    return SentReceipt::create(array_merge([
        'receipt_type'                 => 'FE',
        'consecutive_number'           => '00100001010000000001',
        'emission_date'                => '2026-04-01',
        'receipt_status'               => ReceiptStatus::Pending,
        'hacienda_status'              => HaciendaStatus::Pending,
        'reception_status'             => 'accepted',
        'reception_message'            => 'Documento aceptado',
        'total_expense'                => 10000,
        'tax_amount'                   => 1300,
        'total_voucher'                => 11300,
        'issuer_name'                  => 'Proveedor Test S.A.',
        'issuer_number'                => '3102654321',
        'issuer_identification_type'   => '02',
        'receiver_name'                => 'Test Emisor S.A.',
        'receiver_number'              => '3101123456',
        'receiver_identification_type' => '02',
    ], $overrides));
}

function receptionJobData(array $overrides = []): array
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

it('dispatches synchronously without a TypeError when ui_key is null (regression for getKey/getUiKey collision)', function () {
    $sentReceipt = pendingSentReceipt();

    // Freshly created SentReceipt has no ui_key yet — this is exactly the
    // condition that used to break serialization inside dispatchSync().
    expect($sentReceipt->ui_key)->toBeNull();

    SendSentReceiptToProviderJob::dispatchSync($sentReceipt, receptionJobData());

    $sentReceipt->refresh();

    // Reaching this point (no TypeError thrown by SerializesModels during
    // dispatch) proves handle() actually ran.
    expect($sentReceipt->receipt_status)->toBe(ReceiptStatus::Sent);
    expect($sentReceipt->ui_key)->toHaveLength(50);
    expect($sentReceipt->consecutive_number)->not->toBeNull();
});

it('exposes getUiKey() as a nullable business identifier without overriding Eloquent getKey()', function () {
    $sentReceipt = pendingSentReceipt();

    expect($sentReceipt->getUiKey())->toBeNull();
    expect($sentReceipt->getKey())->toBe($sentReceipt->id);
});
