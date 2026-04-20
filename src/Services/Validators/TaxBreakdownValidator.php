<?php

namespace FactuTica\FactuticaCR\Services\Validators;

use FactuTica\FactuticaCR\Exceptions\InvalidReceiptException;

/**
 * Valida que TotalDesgloseImpuesto del ResumenFactura coincida
 * con la suma real de impuestos agrupados por Codigo + CodigoTarifaIVA
 * desde las líneas de detalle.
 *
 * Cada entrada en TotalDesgloseImpuesto debe tener:
 *   - Codigo: código del impuesto
 *   - CodigoTarifaIVA: tarifa (opcional para códigos != 01,07)
 *   - TotalMontoImpuesto: suma de Monto de ese código+tarifa de todas las líneas
 */
class TaxBreakdownValidator
{
    private const TOLERANCE = 0.01;

    /**
     * @throws InvalidReceiptException
     */
    public function validate(array $data): void
    {
        $lineas = $data['DetalleServicio']['LineaDetalle'] ?? [];
        $desglose = $data['ResumenFactura']['TotalDesgloseImpuesto'] ?? [];

        if (empty($lineas)) {
            return;
        }

        $calculated = $this->calculateFromLines($lineas);

        // Si no se declaró TotalDesgloseImpuesto, InvoicingService lo auto-genera
        if (empty($desglose)) {
            return;
        }

        // Validar que cada entrada declarada coincida con el cálculo
        foreach ($desglose as $i => $entry) {
            $codigo = $entry['Codigo'] ?? '';
            $tarifa = $entry['CodigoTarifaIVA'] ?? '';
            $key = $codigo.'-'.$tarifa;
            $declared = (float) ($entry['TotalMontoImpuesto'] ?? 0);
            $expected = $calculated[$key] ?? 0;

            if (! $this->approx($declared, $expected)) {
                throw new InvalidReceiptException(
                    "TotalDesgloseImpuesto[{$i}] (Código={$codigo}, Tarifa={$tarifa}): TotalMontoImpuesto ({$declared}) ≠ suma de líneas ({$expected})"
                );
            }

            unset($calculated[$key]);
        }

        // Validar que no falten códigos en el desglose
        foreach ($calculated as $key => $amount) {
            if (abs($amount) > self::TOLERANCE) {
                throw new InvalidReceiptException(
                    "Falta TotalDesgloseImpuesto para código-tarifa [{$key}] con monto {$amount}"
                );
            }
        }
    }

    /**
     * Agrupa los impuestos de todas las líneas por Codigo+CodigoTarifaIVA.
     *
     * @return array<string, float> Mapa de "codigo-tarifa" => monto total
     */
    private function calculateFromLines(array $lineas): array
    {
        $totals = [];

        foreach ($lineas as $linea) {
            foreach ($linea['Impuesto'] ?? [] as $imp) {
                $codigo = $imp['Codigo'] ?? '';
                $tarifa = $imp['CodigoTarifaIVA'] ?? '';
                $monto = (float) ($imp['Monto'] ?? 0);
                $exoneracion = (float) ($imp['Exoneracion']['MontoExoneracion'] ?? 0);

                $key = $codigo.'-'.$tarifa;
                $totals[$key] = ($totals[$key] ?? 0) + $monto - $exoneracion;
            }
        }

        return $totals;
    }

    private function approx(float $actual, float $expected): bool
    {
        return abs($actual - $expected) <= self::TOLERANCE;
    }
}
