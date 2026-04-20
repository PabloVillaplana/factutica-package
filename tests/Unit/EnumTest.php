<?php

use FactuTica\FactuticaCR\Enums\CurrencyType;
use FactuTica\FactuticaCR\Enums\HaciendaStatus;
use FactuTica\FactuticaCR\Enums\IdentificationType;
use FactuTica\FactuticaCR\Enums\ReceiptStatus;
use FactuTica\FactuticaCR\Enums\ReceiptType;

it('has all receipt types', function () {
    expect(ReceiptType::cases())->toHaveCount(7);
    expect(ReceiptType::ElectronicInvoice->value)->toBe('FE');
    expect(ReceiptType::CreditNote->value)->toBe('NC');
    expect(ReceiptType::ElectronicPaymentReceipt->value)->toBe('REP');
});

it('receipt type has labels', function () {
    expect(ReceiptType::ElectronicInvoice->label())->toBe('Factura Electrónica');
    expect(ReceiptType::ElectronicTicket->label())->toBe('Tiquete Electrónico');
});

it('can create receipt type from value', function () {
    expect(ReceiptType::from('FE'))->toBe(ReceiptType::ElectronicInvoice);
    expect(ReceiptType::tryFrom('INVALID'))->toBeNull();
});

it('has all receipt statuses', function () {
    expect(ReceiptStatus::cases())->toHaveCount(5);
    expect(ReceiptStatus::Pending->value)->toBe('pending');
    expect(ReceiptStatus::Sent->value)->toBe('sent');
    expect(ReceiptStatus::Failed->value)->toBe('failed');
});

it('has all hacienda statuses', function () {
    expect(HaciendaStatus::cases())->toHaveCount(3);
    expect(HaciendaStatus::Accepted->value)->toBe('accepted');
});

it('has identification types', function () {
    expect(IdentificationType::Physical->value)->toBe('01');
    expect(IdentificationType::Legal->value)->toBe('02');
    expect(IdentificationType::Dimex->value)->toBe('03');
});

it('has currency types', function () {
    expect(CurrencyType::Colones->value)->toBe('CRC');
    expect(CurrencyType::Dollars->value)->toBe('USD');
    expect(CurrencyType::Euros->value)->toBe('EUR');
});