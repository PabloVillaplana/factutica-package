<?php

namespace FactuTica\FactuticaCR\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use FactuTica\FactuticaCR\Enums\ReceiptType;

/**
 * Cast para `ReceiptConsecutive.receipt_type`, columna reutilizada para dos
 * conceptos distintos: tipos reales de comprobante (FE, TE, NC, ...) y
 * pseudo-tipos de mensaje de recepción ('MSG-05', 'MSG-06', 'MSG-07') que no
 * existen en el catálogo `ReceiptType`.
 *
 * En lectura, devuelve la instancia real del enum cuando el valor coincide
 * con un caso conocido, o el string crudo cuando no (nunca lanza).
 * En escritura, acepta tanto instancias de `ReceiptType` como strings.
 *
 * @implements CastsAttributes<ReceiptType|string, ReceiptType|string>
 */
class ReceiptOrMessageTypeCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ReceiptType|string|null
    {
        if ($value === null) {
            return null;
        }

        return ReceiptType::tryFrom($value) ?? $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ReceiptType) {
            return $value->value;
        }

        if (is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException(
            'El valor de receipt_type debe ser una instancia de ReceiptType o un string.'
        );
    }
}
