<?php

namespace FactuTica\FactuticaCR\Services\Validators;

use FactuTica\FactuticaCR\Exceptions\InvalidReceiptException;

/**
 * Valida los cálculos de impuestos por línea de detalle.
 *
 * Cada código de impuesto tiene su propia fórmula:
 *   01 IVA              → BaseImponible × (Tarifa / 100)
 *   02 Selectivo        → SubTotal × (Tarifa / 100)
 *   03 Combustible      → CantidadUnidadMedida × ImpuestoUnidad
 *   04 Alcohólico       → Cantidad × Proporción × ImpuestoUnidad
 *   05 Bebidas sin alc. → Cantidad × CantidadUnidadMedida × (ImpuestoUnidad / VolumenUnidadConsumo)
 *   06 Tabaco           → Cantidad × CantidadUnidadMedida × ImpuestoUnidad
 *   07 IVA Bienes Usados→ BaseImponible × (Tarifa / 100) × FactorCalculoIVA
 *   08 Cemento          → SubTotal × (Tarifa / 100)
 *   12 Impuesto Cemento → SubTotal × (Tarifa / 100)
 *
 * Mapeo CodigoTarifaIVA → Tarifa esperada (para códigos 01 y 07):
 *   01→0%, 02→1%, 03→2%, 04→4%, 05→0%, 06→4%, 07→8%, 08→13%, 09→0.5%, 10→0%, 11→0%
 */
class TaxCalculationValidator
{
    private const TOLERANCE = 0.01;

    /**
     * Mapeo de CodigoTarifaIVA a porcentaje de tarifa.
     */
    private const IVA_TARIFF_MAP = [
        '01' => 0,
        '02' => 1,
        '03' => 2,
        '04' => 4,
        '05' => 0,
        '06' => 4,
        '07' => 8,
        '08' => 13,
        '09' => 0.5,
        '10' => 0,
        '11' => 0,
    ];

    /**
     * @throws InvalidReceiptException
     */
    public function validate(array $data): void
    {
        $lineas = $data['DetalleServicio']['LineaDetalle'] ?? [];

        foreach ($lineas as $i => $linea) {
            $num = $linea['NumeroLinea'] ?? ($i + 1);

            foreach ($linea['Impuesto'] ?? [] as $j => $impuesto) {
                $this->validateTaxAmount($num, $j + 1, $linea, $impuesto);
                $this->validateTariffConsistency($num, $j + 1, $impuesto);
                $this->validateExoneracion($num, $j + 1, $linea, $impuesto);
                $this->validateDatosEspecificos($num, $j + 1, $impuesto);
            }
        }
    }

    /**
     * Valida que el Monto del impuesto coincida con la fórmula del código.
     */
    private function validateTaxAmount(int|string $lineNum, int $taxNum, array $linea, array $imp): void
    {
        $codigo = $imp['Codigo'] ?? '';
        $monto = (float) ($imp['Monto'] ?? 0);
        $tarifa = (float) ($imp['Tarifa'] ?? 0);
        $cantidad = (float) ($linea['Cantidad'] ?? 0);
        $subTotal = (float) ($linea['SubTotal'] ?? 0);
        $baseImponible = (float) ($linea['BaseImponible'] ?? 0);
        $datos = $imp['DatosImpuestoEspecifico'] ?? [];

        $expected = match ($codigo) {
            '01', '07' => $baseImponible * ($tarifa / 100),
            '02'       => $subTotal * ($tarifa / 100),
            '03'       => ((float) ($datos['CantidadUnidadMedida'] ?? 0)) * ((float) ($datos['ImpuestoUnidad'] ?? 0)),
            '04'       => $cantidad * ((float) ($datos['Proporcion'] ?? 0)) * ((float) ($datos['ImpuestoUnidad'] ?? 0)),
            '05'       => $this->calcBebidas($cantidad, $datos),
            '06'       => $cantidad * ((float) ($datos['CantidadUnidadMedida'] ?? 0)) * ((float) ($datos['ImpuestoUnidad'] ?? 0)),
            '08', '12' => $subTotal * ($tarifa / 100),
            default    => null,
        };

        if ($expected !== null && ! $this->approx($monto, $expected)) {
            throw new InvalidReceiptException(
                "Línea {$lineNum}, Impuesto {$taxNum} (código {$codigo}): Monto ({$monto}) no coincide con el cálculo esperado ({$expected})"
            );
        }
    }

    /**
     * Valida que la Tarifa corresponda al CodigoTarifaIVA para códigos 01 y 07.
     */
    private function validateTariffConsistency(int|string $lineNum, int $taxNum, array $imp): void
    {
        $codigo = $imp['Codigo'] ?? '';
        $codigoTarifa = $imp['CodigoTarifaIVA'] ?? null;
        $tarifa = $imp['Tarifa'] ?? null;

        if (! in_array($codigo, ['01', '07']) || $codigoTarifa === null || $tarifa === null) {
            return;
        }

        $expectedTarifa = self::IVA_TARIFF_MAP[$codigoTarifa] ?? null;

        if ($expectedTarifa !== null && ! $this->approx((float) $tarifa, $expectedTarifa)) {
            throw new InvalidReceiptException(
                "Línea {$lineNum}, Impuesto {$taxNum}: Tarifa ({$tarifa}) no coincide con CodigoTarifaIVA ({$codigoTarifa}) que corresponde a {$expectedTarifa}%"
            );
        }
    }

    /**
     * Cálculo específico para código 05 (Bebidas sin alcohol).
     * Fórmula: Cantidad × CantidadUnidadMedida × (ImpuestoUnidad / VolumenUnidadConsumo)
     */
    private function calcBebidas(float $cantidad, array $datos): float
    {
        $cantUm = (float) ($datos['CantidadUnidadMedida'] ?? 0);
        $impUnidad = (float) ($datos['ImpuestoUnidad'] ?? 0);
        $volumen = (float) ($datos['VolumenUnidadConsumo'] ?? 1);

        return $volumen > 0
            ? $cantidad * $cantUm * ($impUnidad / $volumen)
            : 0;
    }

    /**
     * Valida MontoExoneracion = TarifaExonerada% × BaseImponible (o SubTotal).
     * Valida TarifaExonerada ≤ Tarifa para códigos 04, 11.
     */
    private function validateExoneracion(int|string $lineNum, int $taxNum, array $linea, array $imp): void
    {
        $exo = $imp['Exoneracion'] ?? null;

        if ($exo === null) {
            return;
        }

        $tarifaExonerada = (float) ($exo['TarifaExonerada'] ?? 0);
        $montoExoneracion = (float) ($exo['MontoExoneracion'] ?? 0);
        $tarifa = (float) ($imp['Tarifa'] ?? 0);
        $baseImponible = (float) ($linea['BaseImponible'] ?? 0);
        $subTotal = (float) ($linea['SubTotal'] ?? 0);
        $codigoTarifa = $imp['CodigoTarifaIVA'] ?? '';

        // TarifaExonerada no puede exceder la Tarifa para tarifas 04, 11
        if (in_array($codigoTarifa, ['04', '11']) && $tarifaExonerada > $tarifa + self::TOLERANCE) {
            throw new InvalidReceiptException(
                "Línea {$lineNum}, Impuesto {$taxNum}: TarifaExonerada ({$tarifaExonerada}) no puede exceder Tarifa ({$tarifa})"
            );
        }

        // MontoExoneracion = TarifaExonerada% × BaseImponible
        $base = $baseImponible > 0 ? $baseImponible : $subTotal;
        $expected = $base * ($tarifaExonerada / 100);

        if (! $this->approx($montoExoneracion, $expected)) {
            throw new InvalidReceiptException(
                "Línea {$lineNum}, Impuesto {$taxNum}: MontoExoneracion ({$montoExoneracion}) ≠ TarifaExonerada ({$tarifaExonerada}%) × Base ({$base}) = {$expected}"
            );
        }
    }

    /**
     * Valida que DatosImpuestoEspecifico esté presente para códigos 03,04,05,06.
     * Valida que Tarifa para código 12 (cemento) sea 5.
     */
    private function validateDatosEspecificos(int|string $lineNum, int $taxNum, array $imp): void
    {
        $codigo = $imp['Codigo'] ?? '';
        $datos = $imp['DatosImpuestoEspecifico'] ?? null;

        // DatosImpuestoEspecifico requerido para códigos 03,04,05,06
        if (in_array($codigo, ['03', '04', '05', '06']) && $datos === null) {
            throw new InvalidReceiptException(
                "Línea {$lineNum}, Impuesto {$taxNum}: DatosImpuestoEspecifico es requerido para código {$codigo}"
            );
        }

        // Tarifa para código 12 (cemento) debe ser 5
        if ($codigo === '12') {
            $tarifa = (float) ($imp['Tarifa'] ?? 0);

            if (! $this->approx($tarifa, 5.0)) {
                throw new InvalidReceiptException(
                    "Línea {$lineNum}, Impuesto {$taxNum}: Tarifa para código 12 (cemento) debe ser 5, recibido: {$tarifa}"
                );
            }
        }
    }

    private function approx(float $actual, float $expected): bool
    {
        return abs($actual - $expected) <= self::TOLERANCE;
    }
}
