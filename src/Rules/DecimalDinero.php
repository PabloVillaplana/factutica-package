<?php

namespace FactuTica\FactuticaCR\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida que un valor numérico cumpla con el formato DecimalDineroType
 * del XSD de Hacienda: máximo 13 dígitos enteros y 5 decimales.
 *
 * Uso:
 *   'MontoTotal' => ['required', 'numeric', new DecimalDinero]
 */
class DecimalDinero implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! preg_match('/^-?\d{0,13}(\.\d{0,5})?$/', (string) $value)) {
            $fail("El campo :attribute debe tener máximo 13 dígitos enteros y 5 decimales.");
        }
    }
}
