<?php

namespace FactuTica\FactuticaCR\Models;

use Illuminate\Database\Eloquent\Model;

class EconomicActivity extends Model
{
    protected $table = 'invoicing_cr_economic_activities';

    protected $primaryKey = 'codigo';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'codigo',
        'descripcion',
    ];

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