<?php

namespace FactuTica\FactuticaCR\Models;

use Illuminate\Database\Eloquent\Model;

class Cabys extends Model
{
    protected $table = 'invoicing_cr_cabys';

    protected $primaryKey = 'codigo';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'codigo',
        'descripcion',
        'impuesto',
    ];

    protected function casts(): array
    {
        return [
            'impuesto' => 'decimal:2',
        ];
    }

    /**
     * Busca por código (prefijo) o descripción (LIKE).
     */
    public function scopeSearch($query, string $term)
    {
        if (trim($term) === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($term) {
            $q->where('codigo', 'like', "{$term}%")
                ->orWhere('descripcion', 'like', "%{$term}%");
        });
    }
}