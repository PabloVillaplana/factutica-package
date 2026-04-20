<?php

namespace FactuTica\FactuticaCR\Services\Validators;

use FactuTica\FactuticaCR\Exceptions\InvalidReceiptException;

/**
 * Valida cálculos de cada línea de detalle:
 *
 *   MontoTotal                    = Cantidad × PrecioUnitario
 *   SubTotal                     = MontoTotal - sum(Descuentos)
 *   BaseImponible                = SubTotal + sum(impuestos selectivos 02,04,05,12)
 *   ImpuestoNeto                 = sum(Monto) - sum(MontoExoneracion) - ImpuestoAsumidoEmisorFabrica
 *   MontoTotalLinea              = SubTotal + ImpuestoNeto
 *   MontoDescuento               ≤ MontoTotal
 *   0 ≤ MontoExportacion         ≤ MontoTotal
 */
class DetailLineValidator
{
    private const TOLERANCE = 0.01;

    /**
     * Códigos de impuestos que se suman a la base imponible.
     */
    private const ADDITIVE_TAX_CODES = ['02', '04', '05', '12'];

    /**
     * @throws InvalidReceiptException
     */
    public function validate(array $data): void
    {
        $lineas = $data['DetalleServicio']['LineaDetalle'] ?? [];

        foreach ($lineas as $i => $linea) {
            $num = $linea['NumeroLinea'] ?? ($i + 1);

            $this->validateUnidadMedida($num, $linea);
            $this->validateFormaFarmaceutica($num, $linea);
            $this->validateVinForTransport($num, $linea);
            $this->validateMontoTotal($num, $linea);
            $this->validateDescuentoBounds($num, $linea);
            $this->validateSubTotal($num, $linea);
            $this->validateBaseImponible($num, $linea);
            $this->validateMontoExportacion($num, $linea);
            $this->validateImpuestoNeto($num, $linea);
            $this->validateMontoTotalLinea($num, $linea);
        }
    }

    /**
     * UnidadMedida debe existir en los catálogos de Hacienda.
     */
    private function validateUnidadMedida(int|string $num, array $l): void
    {
        $unidad = $l['UnidadMedida'] ?? null;

        if ($unidad === null) {
            return;
        }

        $validUnits = array_keys(config('invoicing-cr.catalogues.measurement_units', []));

        if (! empty($validUnits) && ! in_array($unidad, $validUnits)) {
            throw new InvalidReceiptException(
                "Línea {$num}: UnidadMedida '{$unidad}' no es una unidad de medida válida según catálogos de Hacienda"
            );
        }
    }

    /**
     * FormaFarmaceutica debe existir en los catálogos de Hacienda.
     */
    private function validateFormaFarmaceutica(int|string $num, array $l): void
    {
        $forma = $l['FormaFarmaceutica'] ?? null;

        if ($forma === null) {
            return;
        }

        $validForms = array_keys(config('invoicing-cr.catalogues.pharmaceutical_forms', []));

        if (! empty($validForms) && ! in_array($forma, $validForms)) {
            throw new InvalidReceiptException(
                "Línea {$num}: FormaFarmaceutica '{$forma}' no es válida según catálogos de Hacienda"
            );
        }
    }

    /**
     * NumeroVINoSerie es requerido para servicios de transporte (CABYS 64 o 65).
     */
    private function validateVinForTransport(int|string $num, array $l): void
    {
        $cabys = (string) ($l['CodigoCABYS'] ?? '');

        if (strlen($cabys) >= 2 && in_array(substr($cabys, 0, 2), ['64', '65'])) {
            if (empty($l['NumeroVINoSerie'])) {
                throw new InvalidReceiptException(
                    "Línea {$num}: NumeroVINoSerie es requerido para servicios de transporte (CABYS {$cabys})"
                );
            }
        }
    }

    /**
     * MontoTotal = Cantidad × PrecioUnitario
     */
    private function validateMontoTotal(int|string $num, array $l): void
    {
        $cantidad = (float) ($l['Cantidad'] ?? 0);
        $precio = (float) ($l['PrecioUnitario'] ?? 0);
        $monto = (float) ($l['MontoTotal'] ?? 0);
        $expected = $cantidad * $precio;

        if (! $this->approx($monto, $expected)) {
            throw new InvalidReceiptException(
                "Línea {$num}: MontoTotal ({$monto}) ≠ Cantidad ({$cantidad}) × PrecioUnitario ({$precio}) = {$expected}"
            );
        }
    }

    /**
     * Cada descuento no puede ser mayor al MontoTotal.
     */
    private function validateDescuentoBounds(int|string $num, array $l): void
    {
        $montoTotal = (float) ($l['MontoTotal'] ?? 0);

        foreach ($l['Descuento'] ?? [] as $j => $desc) {
            $monto = (float) ($desc['MontoDescuento'] ?? 0);

            if ($monto > $montoTotal + self::TOLERANCE) {
                throw new InvalidReceiptException(
                    "Línea {$num}, Descuento ".($j + 1).": MontoDescuento ({$monto}) no puede ser mayor a MontoTotal ({$montoTotal})"
                );
            }
        }
    }

    /**
     * SubTotal = MontoTotal - sum(Descuentos)
     */
    private function validateSubTotal(int|string $num, array $l): void
    {
        $montoTotal = (float) ($l['MontoTotal'] ?? 0);
        $totalDesc = $this->sumField($l['Descuento'] ?? [], 'MontoDescuento');
        $subTotal = (float) ($l['SubTotal'] ?? 0);
        $expected = $montoTotal - $totalDesc;

        if (! $this->approx($subTotal, $expected)) {
            throw new InvalidReceiptException(
                "Línea {$num}: SubTotal ({$subTotal}) ≠ MontoTotal ({$montoTotal}) - Descuentos ({$totalDesc}) = {$expected}"
            );
        }
    }

    /**
     * BaseImponible = SubTotal + sum de impuestos con códigos selectivos (02,04,05,12).
     * Se puede editar manualmente si IVACobradoFabrica=01 o código impuesto=07.
     */
    private function validateBaseImponible(int|string $num, array $l): void
    {
        $subTotal = (float) ($l['SubTotal'] ?? 0);
        $baseImponible = (float) ($l['BaseImponible'] ?? 0);
        $ivaCobrado = $l['IVACobradoFabrica'] ?? null;

        // Si IVACobradoFabrica=01 o tiene impuesto código 07, BaseImponible es editable
        $hasCode07 = collect($l['Impuesto'] ?? [])->contains(fn ($i) => ($i['Codigo'] ?? '') === '07');

        if ($ivaCobrado === '01' || $hasCode07) {
            return;
        }

        $additiveTaxSum = 0;
        foreach ($l['Impuesto'] ?? [] as $imp) {
            if (in_array($imp['Codigo'] ?? '', self::ADDITIVE_TAX_CODES)) {
                $additiveTaxSum += (float) ($imp['Monto'] ?? 0);
            }
        }

        $expected = $subTotal + $additiveTaxSum;

        if (! $this->approx($baseImponible, $expected)) {
            throw new InvalidReceiptException(
                "Línea {$num}: BaseImponible ({$baseImponible}) ≠ SubTotal ({$subTotal}) + Impuestos selectivos ({$additiveTaxSum}) = {$expected}"
            );
        }
    }

    /**
     * 0 ≤ MontoExportacion ≤ MontoTotal
     */
    private function validateMontoExportacion(int|string $num, array $l): void
    {
        $exportacion = $l['MontoExportacion'] ?? null;

        if ($exportacion === null) {
            return;
        }

        $montoTotal = (float) ($l['MontoTotal'] ?? 0);
        $exp = (float) $exportacion;

        if ($exp < 0 || $exp > $montoTotal + self::TOLERANCE) {
            throw new InvalidReceiptException(
                "Línea {$num}: MontoExportacion ({$exp}) debe estar entre 0 y MontoTotal ({$montoTotal})"
            );
        }
    }

    /**
     * ImpuestoNeto = sum(Monto) - sum(MontoExoneracion) - ImpuestoAsumidoEmisorFabrica
     */
    private function validateImpuestoNeto(int|string $num, array $l): void
    {
        $impuestoNeto = (float) ($l['ImpuestoNeto'] ?? 0);
        $asumido = (float) ($l['ImpuestoAsumidoEmisorFabrica'] ?? 0);

        $totalMontoImpuesto = 0;
        $totalExoneracion = 0;

        foreach ($l['Impuesto'] ?? [] as $imp) {
            $totalMontoImpuesto += (float) ($imp['Monto'] ?? 0);
            $totalExoneracion += (float) ($imp['Exoneracion']['MontoExoneracion'] ?? 0);
        }

        $expected = $totalMontoImpuesto - $totalExoneracion - $asumido;

        if (! $this->approx($impuestoNeto, $expected)) {
            throw new InvalidReceiptException(
                "Línea {$num}: ImpuestoNeto ({$impuestoNeto}) ≠ Impuestos ({$totalMontoImpuesto}) - Exoneraciones ({$totalExoneracion}) - Asumido ({$asumido}) = {$expected}"
            );
        }
    }

    /**
     * MontoTotalLinea = SubTotal + ImpuestoNeto
     */
    private function validateMontoTotalLinea(int|string $num, array $l): void
    {
        $subTotal = (float) ($l['SubTotal'] ?? 0);
        $impuestoNeto = (float) ($l['ImpuestoNeto'] ?? 0);
        $total = (float) ($l['MontoTotalLinea'] ?? 0);
        $expected = $subTotal + $impuestoNeto;

        if (! $this->approx($total, $expected)) {
            throw new InvalidReceiptException(
                "Línea {$num}: MontoTotalLinea ({$total}) ≠ SubTotal ({$subTotal}) + ImpuestoNeto ({$impuestoNeto}) = {$expected}"
            );
        }
    }

    private function approx(float $actual, float $expected): bool
    {
        return abs($actual - $expected) <= self::TOLERANCE;
    }

    private function sumField(array $items, string $field): float
    {
        return array_sum(array_map(fn ($i) => (float) ($i[$field] ?? 0), $items));
    }
}
