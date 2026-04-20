<?php

namespace FactuTica\FactuticaCR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use FactuTica\FactuticaCR\Enums\HaciendaStatus;

class HaciendaResponse extends Model
{
    use HasFactory;

    protected $table = 'invoicing_cr_hacienda_responses';

    protected $fillable = [
        'receipt_id',
        'receipt_key',
        'hacienda_status',
        'response_xml',
        'response_message',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'hacienda_status' => HaciendaStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }
}