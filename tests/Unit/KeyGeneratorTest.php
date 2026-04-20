<?php

use FactuTica\FactuticaCR\Enums\IdentificationType;
use FactuTica\FactuticaCR\Enums\ReceiptType;
use FactuTica\FactuticaCR\Exceptions\InvalidReceiptException;
use FactuTica\FactuticaCR\Services\KeyGeneratorService;

beforeEach(function () {
    $this->generator = new KeyGeneratorService();
});

// ── Security Code ──────────────────────────────────────────────────────

it('generates an 8-digit security code', function () {
    $code = $this->generator->generateSecurityCode();

    expect($code)->toHaveLength(8);
    expect($code)->toMatch('/^\d{8}$/');
});

it('generates unique security codes', function () {
    $codes = collect(range(1, 10))->map(fn () => $this->generator->generateSecurityCode());

    expect($codes->unique()->count())->toBe(10);
});

// ── Consecutive Key ────────────────────────────────────────────────────

it('generates a 20-digit consecutive key', function () {
    $key = $this->generator->generateConsecutiveKey(ReceiptType::ElectronicInvoice, 1);

    expect($key)->toHaveLength(20);
    expect($key)->toBe('00100001010000000001');
});

it('pads consecutive number correctly', function () {
    $key = $this->generator->generateConsecutiveKey(ReceiptType::ElectronicInvoice, 42);

    expect($key)->toBe('00100001010000000042');
});

it('uses correct type codes', function (ReceiptType $type, string $expectedCode) {
    $key = $this->generator->generateConsecutiveKey($type, 1);
    $typeCode = substr($key, 8, 2);

    expect($typeCode)->toBe($expectedCode);
})->with([
    [ReceiptType::ElectronicInvoice, '01'],
    [ReceiptType::DebitNote, '02'],
    [ReceiptType::CreditNote, '03'],
    [ReceiptType::ElectronicTicket, '04'],
    [ReceiptType::ElectronicPaymentReceipt, '07'],
    [ReceiptType::PurchaseInvoice, '08'],
    [ReceiptType::ExportInvoice, '09'],
]);

it('accepts custom establishment and terminal', function () {
    $key = $this->generator->generateConsecutiveKey(ReceiptType::ElectronicInvoice, 1, 5, 300);

    expect($key)->toBe('00500300010000000001');
});

it('throws on invalid establishment', function () {
    $this->generator->generateConsecutiveKey(ReceiptType::ElectronicInvoice, 1, 0);
})->throws(InvalidReceiptException::class, 'Sucursal');

it('throws on invalid terminal', function () {
    $this->generator->generateConsecutiveKey(ReceiptType::ElectronicInvoice, 1, 1, 0);
})->throws(InvalidReceiptException::class, 'Terminal');

it('throws on invalid consecutive', function () {
    $this->generator->generateConsecutiveKey(ReceiptType::ElectronicInvoice, 0);
})->throws(InvalidReceiptException::class, 'Consecutivo');

// ── Identification Formatting ──────────────────────────────────────────

it('formats cédula física to 12 digits', function () {
    $result = $this->generator->formatIdentificationNumber('112345678', IdentificationType::Physical);

    expect($result)->toBe('000112345678');
    expect($result)->toHaveLength(12);
});

it('formats cédula jurídica to 12 digits', function () {
    $result = $this->generator->formatIdentificationNumber('3101123456', IdentificationType::Legal);

    expect($result)->toBe('003101123456');
    expect($result)->toHaveLength(12);
});

it('formats DIMEX 11 digits to 12', function () {
    $result = $this->generator->formatIdentificationNumber('12345678901', IdentificationType::Dimex);

    expect($result)->toBe('012345678901');
    expect($result)->toHaveLength(12);
});

it('keeps DIMEX 12 digits as-is', function () {
    $result = $this->generator->formatIdentificationNumber('123456789012', IdentificationType::Dimex);

    expect($result)->toBe('123456789012');
});

it('formats NITE to 12 digits', function () {
    $result = $this->generator->formatIdentificationNumber('1234567890', IdentificationType::Nite);

    expect($result)->toBe('001234567890');
});

it('strips non-numeric characters', function () {
    $result = $this->generator->formatIdentificationNumber('3-101-123456', IdentificationType::Legal);

    expect($result)->toBe('003101123456');
});

it('throws on empty identification number', function () {
    $this->generator->formatIdentificationNumber('', IdentificationType::Physical);
})->throws(InvalidReceiptException::class, 'dígitos');

it('throws on física with too many digits', function () {
    $this->generator->formatIdentificationNumber('1234567890', IdentificationType::Physical);
})->throws(InvalidReceiptException::class, 'máximo 9');

it('throws on DIMEX with wrong length', function () {
    $this->generator->formatIdentificationNumber('12345', IdentificationType::Dimex);
})->throws(InvalidReceiptException::class, '11 o 12');

// ── Unique Key ─────────────────────────────────────────────────────────

it('generates a 50-digit unique key', function () {
    $key = $this->generator->generateUniqueKey(
        type: ReceiptType::ElectronicInvoice,
        emisorId: '3101123456',
        idType: IdentificationType::Legal,
        consecutive: 1,
        emissionDate: new \DateTimeImmutable('2026-03-30'),
    );

    expect($key)->toHaveLength(50);
    expect(substr($key, 0, 3))->toBe('506');          // country code
    expect(substr($key, 3, 2))->toBe('30');            // day
    expect(substr($key, 5, 2))->toBe('03');            // month
    expect(substr($key, 7, 2))->toBe('26');            // year
    expect(substr($key, 9, 12))->toBe('003101123456'); // emisor ID
    expect(substr($key, 41, 1))->toBe('1');            // situation
});

it('generates unique keys on repeated calls', function () {
    $keys = collect(range(1, 5))->map(fn ($i) => $this->generator->generateUniqueKey(
        type: ReceiptType::ElectronicInvoice,
        emisorId: '3101123456',
        idType: IdentificationType::Legal,
        consecutive: $i,
        emissionDate: new \DateTimeImmutable('2026-03-30'),
    ));

    expect($keys->unique()->count())->toBe(5);
});
