<?php

namespace FactuTica\FactuticaCR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use FactuTica\FactuticaCR\Enums\ReceiptType;

class ReceiptPayload extends Model
{
    use HasFactory;

    protected $table = 'invoicing_cr_receipt_payloads';

    protected $fillable = [
        'receipt_id',
        'receipt_type',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'receipt_type' => ReceiptType::class,
            'payload' => 'array',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }
}