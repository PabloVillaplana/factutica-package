<?php

namespace FactuTica\FactuticaCR\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida el formato del número de identificación según su tipo.
 *
 * Tipos:
 *   01 Física    → exactamente 9 dígitos, sin cero inicial
 *   02 Jurídica  → exactamente 10 dígitos
 *   03 DIMEX     → 11 o 12 dígitos, sin cero inicial
 *   04 NITE      → exactamente 10 dígitos
 *   05 Extranjero           → alfanumérico, max 20
 *   06 No contribuyente     → alfanumérico, max 20
 *
 * Uso en validación:
 *   'Emisor.Identificacion.Numero' => [new ValidateIdentification('Emisor.Identificacion.Tipo')]
 */
class ValidateIdentification implements DataAwareRule, ValidationRule
{
    protected array $data = [];

    public function __construct(
        private readonly string $typeField,
    ) {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $type = data_get($this->data, $this->typeField);

        if ($type === null || $value === null) {
            return;
        }

        $value = (string) $value;

        match ($type) {
            '01' => $this->validateFisica($value, $fail),
            '02' => $this->validateJuridica($value, $fail),
            '03' => $this->validateDimex($value, $fail),
            '04' => $this->validateNite($value, $fail),
            '05', '06' => $this->validateExtranjero($value, $fail),
            default => null,
        };
    }

    private function validateFisica(string $value, Closure $fail): void
    {
        if (! preg_match('/^[1-9]\d{8}$/', $value)) {
            $fail('La cédula física debe tener exactamente 9 dígitos sin cero inicial.');
        }
    }

    private function validateJuridica(string $value, Closure $fail): void
    {
        if (! preg_match('/^\d{10}$/', $value)) {
            $fail('La cédula jurídica debe tener exactamente 10 dígitos.');
        }
    }

    private function validateDimex(string $value, Closure $fail): void
    {
        if (! preg_match('/^[1-9]\d{10,11}$/', $value)) {
            $fail('El DIMEX debe tener 11 o 12 dígitos sin cero inicial.');
        }
    }

    private function validateNite(string $value, Closure $fail): void
    {
        if (! preg_match('/^\d{10}$/', $value)) {
            $fail('El NITE debe tener exactamente 10 dígitos.');
        }
    }

    private function validateExtranjero(string $value, Closure $fail): void
    {
        if (! preg_match('/^[a-zA-Z0-9\s\-]{1,20}$/', $value)) {
            $fail('La identificación debe ser alfanumérica, máximo 20 caracteres.');
        }
    }
}
