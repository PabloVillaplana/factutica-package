<?php

namespace FactuTica\FactuticaCR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use FactuTica\FactuticaCR\Contracts\Sendable;
use FactuTica\FactuticaCR\Enums\CurrencyType;
use FactuTica\FactuticaCR\Models\Concerns\HasSendableStatus;
use FactuTica\FactuticaCR\Enums\HaciendaStatus;
use FactuTica\FactuticaCR\Enums\IdentificationType;
use FactuTica\FactuticaCR\Enums\ReceiptStatus;
use FactuTica\FactuticaCR\Enums\ReceiptType;

class Receipt extends Model implements Sendable
{
    use HasFactory, HasSendableStatus;

    protected static function newFactory()
    {
        return \FactuTica\FactuticaCR\Database\Factories\ReceiptFactory::new();
    }

    protected $table = 'invoicing_cr_receipts';

    protected $fillable = [
        'receipt_type',
        'ui_key',
        'external_reference',
        'establishment',
        'terminal',
        'consecutive_number',
        'emission_date',
        'sent_to_hacienda_at',
        'receipt_status',
        'hacienda_status',
        'signed_xml',
        'sell_condition',
        'total_amount',
        'tax_amount',
        'total_discount',
        'total_voucher',
        'currency',
        'exchange_rate',
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
            'emission_date' => 'datetime',
            'sent_to_hacienda_at' => 'datetime',
            'total_amount' => 'decimal:5',
            'tax_amount' => 'decimal:5',
            'total_discount' => 'decimal:5',
            'total_voucher' => 'decimal:5',
            'exchange_rate' => 'decimal:5',
            'currency' => CurrencyType::class,
            'issuer_identification_type' => IdentificationType::class,
            'receiver_identification_type' => IdentificationType::class,
        ];
    }

    public function payload(): HasOne
    {
        return $this->hasOne(ReceiptPayload::class);
    }

    public function haciendaResponse(): HasOne
    {
        return $this->hasOne(HaciendaResponse::class);
    }

    // Scopes

    public function scopeOfType($query, ReceiptType $type)
    {
        return $query->where('receipt_type', $type);
    }

    public function scopeWithStatus($query, string|ReceiptStatus $status)
    {
        return $query->where('receipt_status', $status);
    }

    public function scopeFilter($query, array $filters)
    {
        return $query
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->ofType(ReceiptType::tryFrom($type)))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->withStatus($status));
    }

}