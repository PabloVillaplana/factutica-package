<?php

namespace FactuTica\FactuticaCR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use FactuTica\FactuticaCR\Contracts\Sendable;
use FactuTica\FactuticaCR\Enums\HaciendaStatus;
use FactuTica\FactuticaCR\Models\Concerns\HasSendableStatus;
use FactuTica\FactuticaCR\Enums\IdentificationType;
use FactuTica\FactuticaCR\Enums\ReceiptStatus;
use FactuTica\FactuticaCR\Enums\ReceiptType;
use FactuTica\FactuticaCR\Enums\ReceptionStatus;

class SentReceipt extends Model implements Sendable
{
    use HasFactory, HasSendableStatus;

    protected $table = 'invoicing_cr_sent_receipts';

    protected $fillable = [
        'receipt_type',
        'ui_key',
        'consecutive_number',
        'emission_date',
        'sent_to_hacienda_at',
        'receipt_status',
        'hacienda_status',
        'signed_xml',
        'reception_message',
        'reception_status',
        'reception_code',
        'economic_activity_code',
        'tax_condition_code',
        'tax_credited',
        'total_expense',
        'tax_amount',
        'total_voucher',
        'issuer_name',
        'issuer_number',
        'issuer_identification_type',
        'receiver_name',
        'receiver_number',
        'receiver_identification_type',
    ];

    protected function casts(): array
    {
        return [
            'receipt_type' => ReceiptType::class,
            'receipt_status' => ReceiptStatus::class,
            'hacienda_status' => HaciendaStatus::class,
            'reception_status' => ReceptionStatus::class,
            'emission_date' => 'datetime',
            'sent_to_hacienda_at' => 'datetime',
            'total_expense' => 'decimal:5',
            'tax_amount' => 'decimal:5',
            'total_voucher' => 'decimal:5',
            'tax_credited' => 'decimal:5',
            'issuer_identification_type' => IdentificationType::class,
            'receiver_identification_type' => IdentificationType::class,
        ];
    }

}