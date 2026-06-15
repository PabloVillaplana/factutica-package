<?php

namespace FactuTica\FactuticaCR\Services\Validators;

use FactuTica\FactuticaCR\Exceptions\InvalidReceiptException;

/**
 * Valida los cálculos del ResumenFactura según las reglas de Hacienda.
 *
 * Clasifica líneas como servicio o mercancía por CABYS:
 *   - CABYS que inicia con 0-4 → Mercancía
 *   - CABYS que inicia con 5-9 → Servicio
 *
 * Clasifica líneas por tipo de gravamen según CodigoTarifaIVA:
 *   - Gravado:    tarifas 02,03,04,06,07,08,09
 *   - Exento:     tarifa 10
 *   - No Sujeto:  tarifas 01, 11
 *   - Exonerado:  tiene Exoneracion en algún impuesto
 */
class InvoiceSummaryValidator
{
    private const TOLERANCE = 0.01;

    public function __construct(
        private readonly LineClassifier $lineClassifier = new LineClassifier,
    ) {}

    /**
     * @throws InvalidReceiptException
     */
    public function validate(array $data): void
    {
        $resumen = $data['ResumenFactura'] ?? [];
        $lineas = $data['DetalleServicio']['LineaDetalle'] ?? [];
        $otrosCargos = $data['OtrosCargos'] ?? [];

        $this->validateCurrencyExchangeRate($resumen);

        if (! empty($lineas)) {
            $calc = $this->lineClassifier->calculateFromLines($lineas);
            $this->validateServiceMerchandiseTotals($resumen, $calc);
            $this->validateAggregatedTotals($resumen, $calc);
            $this->validateTotalDescuentos($resumen, $lineas);
            $this->validateTotalImpuesto($resumen, $lineas);
            $this->validateTotalImpAsumEmisorFabrica($resumen, $lineas);
            $this->validateTotalIVADevuelto($resumen, $lineas, $data);
        }

        $this->validateTotalVenta($resumen);
        $this->validateTotalVentaNeta($resumen);
        $this->validateTotalOtrosCargos($resumen, $otrosCargos);
        $this->validateTotalComprobante($resumen);
    }

    /**
     * Valida los 8 subtotales de servicio/mercancía.
     */
    private function validateServiceMerchandiseTotals(array $r, array $calc): void
    {
        $checks = [
            ['TotalServGravados', $calc['serv_gravados']],
            ['TotalServExentos', $calc['serv_exentos']],
            ['TotalServExonerado', $calc['serv_exonerado']],
            ['TotalServNoSujeto', $calc['serv_no_sujeto']],
            ['TotalMercanciasGravadas', $calc['merc_gravadas']],
            ['TotalMercanciasExentas', $calc['merc_exentas']],
            ['TotalMercExonerada', $calc['merc_exonerada']],
            ['TotalMercNoSujeta', $calc['merc_no_sujeta']],
        ];

        foreach ($checks as [$field, $expected]) {
            $declared = (float) ($r[$field] ?? 0);

            if (! $this->approx($declared, $expected)) {
                throw new InvalidReceiptException(
                    "{$field} ({$declared}) no coincide con el cálculo desde las líneas ({$expected})"
                );
            }
        }
    }

    /**
     * TotalGravado = TotalServGravados + TotalMercanciasGravadas
     * TotalExento  = TotalServExentos + TotalMercanciasExentas
     * etc.
     */
    private function validateAggregatedTotals(array $r, array $calc): void
    {
        $checks = [
            ['TotalGravado', $calc['serv_gravados'] + $calc['merc_gravadas']],
            ['TotalExento', $calc['serv_exentos'] + $calc['merc_exentas']],
            ['TotalExonerado', $calc['serv_exonerado'] + $calc['merc_exonerada']],
            ['TotalNoSujeto', $calc['serv_no_sujeto'] + $calc['merc_no_sujeta']],
        ];

        foreach ($checks as [$field, $expected]) {
            $declared = (float) ($r[$field] ?? 0);

            if (! $this->approx($declared, $expected)) {
                throw new InvalidReceiptException(
                    "{$field} ({$declared}) no coincide con el cálculo esperado ({$expected})"
                );
            }
        }
    }

    private function validateCurrencyExchangeRate(array $r): void
    {
        $moneda = $r['CodigoTipoMoneda']['CodigoMoneda'] ?? '';
        $tipoCambio = (float) ($r['CodigoTipoMoneda']['TipoCambio'] ?? 0);

        if ($moneda === 'CRC' && ! $this->approx($tipoCambio, 1.0)) {
            throw new InvalidReceiptException(
                "TipoCambio debe ser 1 cuando la moneda es CRC, recibido: {$tipoCambio}"
            );
        }
    }

    /**
     * TotalVenta = TotalGravado + TotalExento + TotalExonerado + TotalNoSujeto
     */
    private function validateTotalVenta(array $r): void
    {
        $gravado = (float) ($r['TotalGravado'] ?? 0);
        $exento = (float) ($r['TotalExento'] ?? 0);
        $exonerado = (float) ($r['TotalExonerado'] ?? 0);
        $noSujeto = (float) ($r['TotalNoSujeto'] ?? 0);
        $totalVenta = (float) ($r['TotalVenta'] ?? 0);
        $expected = $gravado + $exento + $exonerado + $noSujeto;

        if (! $this->approx($totalVenta, $expected)) {
            throw new InvalidReceiptException(
                "TotalVenta ({$totalVenta}) ≠ TotalGravado ({$gravado}) + TotalExento ({$exento}) + TotalExonerado ({$exonerado}) + TotalNoSujeto ({$noSujeto}) = {$expected}"
            );
        }
    }

    /**
     * TotalVentaNeta = TotalVenta - TotalDescuentos
     */
    private function validateTotalVentaNeta(array $r): void
    {
        $totalVenta = (float) ($r['TotalVenta'] ?? 0);
        $descuentos = (float) ($r['TotalDescuentos'] ?? 0);
        $ventaNeta = (float) ($r['TotalVentaNeta'] ?? 0);

        if ($ventaNeta == 0 && $descuentos == 0) {
            return;
        }

        $expected = $totalVenta - $descuentos;

        if (! $this->approx($ventaNeta, $expected)) {
            throw new InvalidReceiptException(
                "TotalVentaNeta ({$ventaNeta}) ≠ TotalVenta ({$totalVenta}) - TotalDescuentos ({$descuentos}) = {$expected}"
            );
        }
    }

    /**
     * TotalDescuentos = sum de MontoDescuento de todas las líneas.
     */
    private function validateTotalDescuentos(array $r, array $lineas): void
    {
        $declared = (float) ($r['TotalDescuentos'] ?? 0);
        $calculated = 0;

        foreach ($lineas as $linea) {
            foreach ($linea['Descuento'] ?? [] as $desc) {
                $calculated += (float) ($desc['MontoDescuento'] ?? 0);
            }
        }

        if (! $this->approx($declared, $calculated)) {
            throw new InvalidReceiptException(
                "TotalDescuentos ({$declared}) ≠ suma de descuentos de líneas ({$calculated})"
            );
        }
    }

    /**
     * TotalImpuesto = suma por línea del impuesto efectivo:
     *   - ImpuestoAsumidoEmisorFabrica cuando la línea lo declara (> 0)
     *   - ImpuestoNeto cuando el impuesto tiene exoneración
     *   - Monto del impuesto en el resto de los casos
     */
    private function validateTotalImpuesto(array $r, array $lineas): void
    {
        $declared = (float) ($r['TotalImpuesto'] ?? 0);
        $calculated = 0;

        foreach ($lineas as $linea) {
            $asumido = (float) ($linea['ImpuestoAsumidoEmisorFabrica'] ?? 0);

            if ($asumido > 0) {
                $calculated += $asumido;

                continue;
            }

            foreach ($linea['Impuesto'] ?? [] as $imp) {
                if ((float) ($imp['Exoneracion']['MontoExoneracion'] ?? 0) > 0) {
                    $calculated += (float) ($linea['ImpuestoNeto'] ?? 0);
                } else {
                    $calculated += (float) ($imp['Monto'] ?? 0);
                }
            }
        }

        if (! $this->approx($declared, $calculated)) {
            throw new InvalidReceiptException(
                "TotalImpuesto ({$declared}) ≠ suma de ImpuestoNeto de líneas ({$calculated})"
            );
        }
    }

    /**
     * TotalImpAsumEmisorFabrica = sum de ImpuestoAsumidoEmisorFabrica de todas las líneas.
     */
    private function validateTotalImpAsumEmisorFabrica(array $r, array $lineas): void
    {
        $declared = (float) ($r['TotalImpAsumEmisorFabrica'] ?? 0);
        $calculated = 0;

        foreach ($lineas as $linea) {
            $calculated += (float) ($linea['ImpuestoAsumidoEmisorFabrica'] ?? 0);
        }

        if (! $this->approx($declared, $calculated)) {
            throw new InvalidReceiptException(
                "TotalImpAsumEmisorFabrica ({$declared}) ≠ suma de líneas ({$calculated})"
            );
        }
    }

    /**
     * TotalIVADevuelto: aplica para servicios de salud (CABYS 93xx) pagados con tarjeta (MedioPago 02).
     * Se calcula como la suma de montos IVA (códigos 01/07) de líneas de salud con pago tarjeta.
     */
    private function validateTotalIVADevuelto(array $r, array $lineas, array $data): void
    {
        $declared = (float) ($r['TotalIVADevuelto'] ?? 0);

        if ($declared == 0) {
            return;
        }

        $medioPago = $r['MedioPago'] ?? [];
        $hasCreditCard = false;

        foreach ($medioPago as $pago) {
            if (($pago['TipoMedioPago'] ?? '') === '02') {
                $hasCreditCard = true;
                break;
            }
        }

        if (! $hasCreditCard) {
            return;
        }

        $calculated = 0;

        foreach ($lineas as $linea) {
            $cabys = (string) ($linea['CodigoCABYS'] ?? '');

            if (strlen($cabys) >= 2 && substr($cabys, 0, 2) === '93') {
                foreach ($linea['Impuesto'] ?? [] as $imp) {
                    if (in_array($imp['Codigo'] ?? '', ['01', '07'])) {
                        $calculated += (float) ($imp['Monto'] ?? 0);
                    }
                }
            }
        }

        if (! $this->approx($declared, $calculated)) {
            throw new InvalidReceiptException(
                "TotalIVADevuelto ({$declared}) ≠ suma de IVA de servicios de salud con tarjeta ({$calculated})"
            );
        }
    }

    /**
     * TotalOtrosCargos = sum de MontoCargo de OtrosCargos.
     */
    private function validateTotalOtrosCargos(array $r, array $otrosCargos): void
    {
        $declared = (float) ($r['TotalOtrosCargos'] ?? 0);

        if ($declared == 0 && empty($otrosCargos)) {
            return;
        }

        $calculated = 0;
        foreach ($otrosCargos as $cargo) {
            $calculated += (float) ($cargo['MontoCargo'] ?? 0);
        }

        if (! $this->approx($declared, $calculated)) {
            throw new InvalidReceiptException(
                "TotalOtrosCargos ({$declared}) ≠ suma de MontoCargo ({$calculated})"
            );
        }
    }

    /**
     * TotalComprobante = TotalVentaNeta + TotalImpuesto + TotalOtrosCargos - TotalIVADevuelto
     */
    private function validateTotalComprobante(array $r): void
    {
        $ventaNeta = (float) ($r['TotalVentaNeta'] ?? ($r['TotalVenta'] ?? 0));
        $impuesto = (float) ($r['TotalImpuesto'] ?? 0);
        $otrosCargos = (float) ($r['TotalOtrosCargos'] ?? 0);
        $ivaDevuelto = (float) ($r['TotalIVADevuelto'] ?? 0);
        $comprobante = (float) ($r['TotalComprobante'] ?? 0);
        $expected = $ventaNeta + $impuesto + $otrosCargos - $ivaDevuelto;

        if (! $this->approx($comprobante, $expected)) {
            throw new InvalidReceiptException(
                "TotalComprobante ({$comprobante}) ≠ TotalVentaNeta ({$ventaNeta}) + TotalImpuesto ({$impuesto}) + TotalOtrosCargos ({$otrosCargos}) - TotalIVADevuelto ({$ivaDevuelto}) = {$expected}"
            );
        }
    }

    private function approx(float $actual, float $expected): bool
    {
        return abs($actual - $expected) <= self::TOLERANCE;
    }
}
