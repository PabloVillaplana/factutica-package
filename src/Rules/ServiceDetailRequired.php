<?php

namespace FactuTica\FactuticaCR\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * DetalleServicio es requerido EXCEPTO cuando OtrosCargos contiene
 * tipos 04 (IVA asumido terceros), 08, 09, o 10.
 *
 * Esto permite facturas que solo tienen OtrosCargos sin líneas de detalle
 * para casos específicos definidos por Hacienda.
 */
class ServiceDetailRequired implements DataAwareRule, ValidationRule
{
    private const EXEMPT_OTROS_CARGOS_TYPES = ['04', '08', '09', '10'];

    protected array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Si DetalleServicio tiene contenido, todo bien
        if (! empty($value) && ! empty(data_get($value, 'LineaDetalle'))) {
            return;
        }

        // Verificar si OtrosCargos tiene tipos que eximen del requisito
        $otrosCargos = $this->data['OtrosCargos'] ?? [];

        foreach ($otrosCargos as $cargo) {
            if (in_array($cargo['TipoDocumentoOC'] ?? '', self::EXEMPT_OTROS_CARGOS_TYPES)) {
                return;
            }
        }

        $fail('DetalleServicio es requerido cuando no hay OtrosCargos de tipos 04, 08, 09 o 10.');
    }
}
