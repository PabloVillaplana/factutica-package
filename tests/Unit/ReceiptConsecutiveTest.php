<?php

use FactuTica\FactuticaCR\Enums\ReceiptType;
use FactuTica\FactuticaCR\Models\ReceiptConsecutive;

it('stores and reads back a message pseudo-type without throwing (regression for MSG-05/06/07 enum cast crash)', function () {
    $record = ReceiptConsecutive::firstOrCreate(
        ['receipt_type' => 'MSG-05', 'establishment' => 1, 'terminal' => 1],
        ['last_number' => 0]
    );

    expect($record->receipt_type)->toBe('MSG-05');

    $record->refresh();
    expect($record->receipt_type)->toBe('MSG-05');
});

it('still round-trips a real ReceiptType value as a proper enum instance', function () {
    $record = ReceiptConsecutive::firstOrCreate(
        ['receipt_type' => ReceiptType::ElectronicInvoice, 'establishment' => 1, 'terminal' => 1],
        ['last_number' => 0]
    );

    expect($record->receipt_type)->toBeInstanceOf(ReceiptType::class);
    expect($record->receipt_type)->toBe(ReceiptType::ElectronicInvoice);

    $record->refresh();
    expect($record->receipt_type)->toBeInstanceOf(ReceiptType::class);
    expect($record->receipt_type)->toBe(ReceiptType::ElectronicInvoice);
});

it('rejects an invalid receipt_type value on write', function () {
    ReceiptConsecutive::create([
        'receipt_type'   => 12345,
        'establishment'  => 1,
        'terminal'       => 1,
        'last_number'    => 0,
    ]);
})->throws(InvalidArgumentException::class);
