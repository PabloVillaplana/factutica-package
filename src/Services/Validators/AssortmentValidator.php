<?php

namespace FactuTica\FactuticaCR\Services\Validators;

use FactuTica\FactuticaCR\Exceptions\InvalidReceiptException;

/**
 * Valida cálculos del DetalleSurtido (combos/paquetes/surtidos).
 *
 * Un surtido es una combinación de 2+ productos con diferentes CABYS
 * facturados como una sola línea de detalle.
 *
 * Cálculos:
 *   MontoTotalSurtido     = CantidadSurtido × PrecioUnitarioSurtido
 *   SubTotalSurtido       = MontoTotalSurtido - sum(DescuentosSurtido)
 *   BaseImponibleSurtido  = SubTotalSurtido + sum(impuestos selectivos 02,04,05)
 *   MontoImpuestoSurtido  = fórmula según código (IVA, selectivo, alcohol, etc.)
 */
class AssortmentValidator
{
    private const TOLERANCE = 0.01;

    private const IVA_TARIFF_MAP = [
        '01' => 0, '02' => 1, '03' => 2, '04' => 4, '05' => 0,
        '06' => 4, '07' => 8, '08' => 13, '09' => 0.5, '10' => 0, '11' => 0,
    ];

    /**
     * @throws InvalidReceiptException
     */
    public function validate(array $data): void
    {
        $lineas = $data['DetalleServicio']['LineaDetalle'] ?? [];

        foreach ($lineas as $i => $linea) {
            $surtido = $linea['DetalleSurtido'] ?? null;

            if ($surtido === null) {
                continue;
            }

            $lineNum = $linea['NumeroLinea'] ?? ($i + 1);

            foreach ($surtido['LineaDetalleSurtido'] ?? [] as $j => $item) {
                $this->validateItem($lineNum, $j + 1, $item);
            }
        }
    }

    private function validateItem(int|string $lineNum, int $itemNum, array $s): void
    {
        $prefix = "Línea {$lineNum}, Surtido {$itemNum}";

        $this->validateUnidadMedida($prefix, $s);
        $this->validateMontoTotal($prefix, $s);
        $this->validateDescuentoBounds($prefix, $s);
        $this->validateSubTotal($prefix, $s);
        $this->validateBaseImponible($prefix, $s);
        $this->validateImpuestos($prefix, $s);
    }

    private function validateUnidadMedida(string $prefix, array $s): void
    {
        $unidad = $s['UnidadMedidaSurtido'] ?? null;

        if ($unidad === null) {
            return;
        }

        $validUnits = array_keys(config('invoicing-cr.catalogues.measurement_units', []));

        if (! empty($validUnits) && ! in_array($unidad, $validUnits)) {
            throw new InvalidReceiptException(
                "{$prefix}: UnidadMedidaSurtido '{$unidad}' no es válida según catálogos"
            );
        }
    }

    /**
     * MontoTotalSurtido = CantidadSurtido × PrecioUnitarioSurtido
     */
    private function validateMontoTotal(string $prefix, array $s): void
    {
        $cantidad = (float) ($s['CantidadSurtido'] ?? 0);
        $precio = (float) ($s['PrecioUnitarioSurtido'] ?? 0);
        $monto = (float) ($s['MontoTotalSurtido'] ?? 0);
        $expected = $cantidad * $precio;

        if (! $this->approx($monto, $expected)) {
            throw new InvalidReceiptException(
                "{$prefix}: MontoTotalSurtido ({$monto}) ≠ CantidadSurtido ({$cantidad}) × PrecioUnitarioSurtido ({$precio}) = {$expected}"
            );
        }
    }

    private function validateDescuentoBounds(string $prefix, array $s): void
    {
        $montoTotal = (float) ($s['MontoTotalSurtido'] ?? 0);

        foreach ($s['DescuentoSurtido'] ?? [] as $j => $desc) {
            $monto = (float) ($desc['MontoDescuentoSurtido'] ?? 0);

            if ($monto < 0 || $monto > $montoTotal + self::TOLERANCE) {
                throw new InvalidReceiptException(
                    "{$prefix}, Descuento ".($j + 1).": MontoDescuentoSurtido ({$monto}) fuera de rango [0, {$montoTotal}]"
                );
            }
        }
    }

    /**
     * SubTotalSurtido = MontoTotalSurtido - sum(DescuentosSurtido)
     */
    private function validateSubTotal(string $prefix, array $s): void
    {
        $montoTotal = (float) ($s['MontoTotalSurtido'] ?? 0);
        $totalDesc = 0;

        foreach ($s['DescuentoSurtido'] ?? [] as $desc) {
            $totalDesc += (float) ($desc['MontoDescuentoSurtido'] ?? 0);
        }

        $subTotal = (float) ($s['SubTotalSurtido'] ?? 0);
        $expected = $montoTotal - $totalDesc;

        if (! $this->approx($subTotal, $expected)) {
            throw new InvalidReceiptException(
                "{$prefix}: SubTotalSurtido ({$subTotal}) ≠ MontoTotalSurtido ({$montoTotal}) - Descuentos ({$totalDesc}) = {$expected}"
            );
        }
    }

    /**
     * BaseImponibleSurtido = SubTotalSurtido + sum(impuestos con código 02,04,05)
     */
    private function validateBaseImponible(string $prefix, array $s): void
    {
        $subTotal = (float) ($s['SubTotalSurtido'] ?? 0);
        $additiveTaxSum = 0;

        foreach ($s['ImpuestoSurtido'] ?? [] as $imp) {
            $codigo = $imp['CodigoImpuestoSurtido'] ?? '';
            if (in_array($codigo, ['02', '04', '05'])) {
                $additiveTaxSum += (float) ($imp['MontoImpuestoSurtido'] ?? 0);
            }
        }

        $baseImponible = (float) ($s['BaseImponibleSurtido'] ?? 0);
        $expected = $subTotal + $additiveTaxSum;

        if (! $this->approx($baseImponible, $expected)) {
            throw new InvalidReceiptException(
                "{$prefix}: BaseImponibleSurtido ({$baseImponible}) ≠ SubTotalSurtido ({$subTotal}) + Impuestos selectivos ({$additiveTaxSum}) = {$expected}"
            );
        }
    }

    /**
     * Valida MontoImpuestoSurtido según la fórmula del código de impuesto.
     */
    private function validateImpuestos(string $prefix, array $s): void
    {
        foreach ($s['ImpuestoSurtido'] ?? [] as $k => $imp) {
            $taxPrefix = "{$prefix}, Impuesto ".($k + 1);
            $codigo = $imp['CodigoImpuestoSurtido'] ?? '';
            $monto = (float) ($imp['MontoImpuestoSurtido'] ?? 0);
            $datos = $imp['DatosImpuestoEspecificoSurtido'] ?? [];

            // Validar DatosImpuestoEspecifico requerido para códigos 04,05,06
            if (in_array($codigo, ['04', '05', '06']) && empty($datos)) {
                throw new InvalidReceiptException(
                    "{$taxPrefix}: DatosImpuestoEspecificoSurtido requerido para código {$codigo}"
                );
            }

            // Validar TarifaSurtido requerida para códigos 01,02,99
            $tarifa = $imp['TarifaSurtido'] ?? null;
            if (in_array($codigo, ['01', '02', '99']) && ($tarifa === null || $tarifa === '')) {
                throw new InvalidReceiptException(
                    "{$taxPrefix}: TarifaSurtido requerida para código {$codigo}"
                );
            }

            // Validar CodigoTarifaIVASurtido requerido para código 01
            if ($codigo === '01' && empty($imp['CodigoTarifaIVASurtido'])) {
                throw new InvalidReceiptException(
                    "{$taxPrefix}: CodigoTarifaIVASurtido requerido cuando código es 01"
                );
            }

            // Validar consistencia tarifa IVA para código 01
            if ($codigo === '01' && $tarifa !== null) {
                $codigoTarifa = $imp['CodigoTarifaIVASurtido'] ?? '';
                $expectedTarifa = self::IVA_TARIFF_MAP[$codigoTarifa] ?? null;

                if ($expectedTarifa !== null && ! $this->approx((float) $tarifa, $expectedTarifa)) {
                    throw new InvalidReceiptException(
                        "{$taxPrefix}: TarifaSurtido ({$tarifa}) no coincide con CodigoTarifaIVASurtido ({$codigoTarifa}) = {$expectedTarifa}%"
                    );
                }
            }

            // Calcular y validar MontoImpuestoSurtido
            $expected = $this->calculateExpectedTax($s, $imp);

            if ($expected !== null && ! $this->approx($monto, $expected)) {
                throw new InvalidReceiptException(
                    "{$taxPrefix}: MontoImpuestoSurtido ({$monto}) ≠ cálculo esperado ({$expected})"
                );
            }
        }
    }

    private function calculateExpectedTax(array $s, array $imp): ?float
    {
        $codigo = $imp['CodigoImpuestoSurtido'] ?? '';
        $tarifa = (float) ($imp['TarifaSurtido'] ?? 0) / 100;
        $cantidad = (float) ($s['CantidadSurtido'] ?? 0);
        $subTotal = (float) ($s['SubTotalSurtido'] ?? 0);
        $baseImponible = (float) ($s['BaseImponibleSurtido'] ?? 0);
        $datos = $imp['DatosImpuestoEspecificoSurtido'] ?? [];

        // Para descuentos tipo regalía/bonificación, usar MontoTotalSurtido
        $hasSpecialDiscount = $this->hasSpecialDiscount($s);
        $ivaBase = $hasSpecialDiscount ? (float) ($s['MontoTotalSurtido'] ?? 0) : $baseImponible;

        return match ($codigo) {
            '01'   => $ivaBase * $tarifa,
            '02'   => $subTotal * $tarifa,
            '04'   => $cantidad * ((float) ($datos['ProporcionSurtido'] ?? 0)) * ((float) ($datos['ImpuestoUnidadSurtido'] ?? 0)),
            '05'   => $this->calcBebidasSurtido($cantidad, $datos),
            '06'   => $cantidad * ((float) ($datos['CantidadUnidadMedidaSurtido'] ?? 0)) * ((float) ($datos['ImpuestoUnidadSurtido'] ?? 0)),
            default => null,
        };
    }

    private function calcBebidasSurtido(float $cantidad, array $datos): float
    {
        $cantUm = (float) ($datos['CantidadUnidadMedidaSurtido'] ?? 0);
        $impUnidad = (float) ($datos['ImpuestoUnidadSurtido'] ?? 0);
        $volumen = (float) ($datos['VolumenUnidadConsumoSurtido'] ?? 1);

        return $volumen > 0 ? $cantidad * $cantUm * ($impUnidad / $volumen) : 0;
    }

    private function hasSpecialDiscount(array $s): bool
    {
        foreach ($s['DescuentoSurtido'] ?? [] as $desc) {
            if (in_array($desc['CodigoDescuentoSurtido'] ?? '', ['01', '02'])) {
                return true;
            }
        }

        return false;
    }

    private function approx(float $actual, float $expected): bool
    {
        return abs($actual - $expected) <= self::TOLERANCE;
    }
}
