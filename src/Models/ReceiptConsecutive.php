<?php

namespace FactuTica\FactuticaCR\Models;

use Illuminate\Database\Eloquent\Model;
use FactuTica\FactuticaCR\Casts\ReceiptOrMessageTypeCast;

class ReceiptConsecutive extends Model
{
    protected $table = 'invoicing_cr_receipt_consecutives';

    protected $fillable = [
        'receipt_type',
        'establishment',
        'terminal',
        'last_number',
    ];

    protected function casts(): array
    {
        return [
            'receipt_type' => ReceiptOrMessageTypeCast::class,
            'last_number' => 'integer',
        ];
    }
}