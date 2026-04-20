<?php

namespace FactuTica\FactuticaCR\Services\Validators;

/**
 * Clasifica líneas de detalle por tipo (servicio/mercancía) y gravamen.
 *
 * Por CABYS:
 *   - 0-4 → Mercancía
 *   - 5-9 → Servicio
 *
 * Por CodigoTarifaIVA (códigos 01/07):
 *   - Gravado:    02, 03, 04, 06, 07, 08, 09
 *   - Exento:     10
 *   - No Sujeto:  01, 11
 *   - Exonerado:  tiene Exoneracion en algún impuesto (precede sobre las demás)
 */
class LineClassifier
{
    private const GRAVADO_TARIFAS = ['02', '03', '04', '06', '07', '08', '09'];

    private const EXENTO_TARIFAS = ['10'];

    private const NO_SUJETO_TARIFAS = ['01', '11'];

    /**
     * Calcula los 8 subtotales por categoría desde las líneas de detalle.
     *
     * @return array{
     *     serv_gravados: float, serv_exentos: float, serv_exonerado: float, serv_no_sujeto: float,
     *     merc_gravadas: float, merc_exentas: float, merc_exonerada: float, merc_no_sujeta: float,
     * }
     */
    public function calculateFromLines(array $lineas): array
    {
        $calc = [
            'serv_gravados' => 0, 'serv_exentos' => 0, 'serv_exonerado' => 0, 'serv_no_sujeto' => 0,
            'merc_gravadas' => 0, 'merc_exentas' => 0, 'merc_exonerada' => 0, 'merc_no_sujeta' => 0,
        ];

        foreach ($lineas as $linea) {
            $cabys = (string) ($linea['CodigoCABYS'] ?? '');
            $isService = $cabys !== '' && $cabys[0] >= '5';
            $classification = $this->classifyLine($linea);

            if ($isService) {
                $calc['serv_gravados'] += $classification['gravado'];
                $calc['serv_exentos'] += $classification['exento'];
                $calc['serv_exonerado'] += $classification['exonerado'];
                $calc['serv_no_sujeto'] += $classification['no_sujeto'];
            } else {
                $calc['merc_gravadas'] += $classification['gravado'];
                $calc['merc_exentas'] += $classification['exento'];
                $calc['merc_exonerada'] += $classification['exonerado'];
                $calc['merc_no_sujeta'] += $classification['no_sujeto'];
            }
        }

        return $calc;
    }

    /**
     * Clasifica una línea según su tipo de gravamen.
     *
     * @return array{gravado: float, exento: float, exonerado: float, no_sujeto: float}
     */
    public function classifyLine(array $linea): array
    {
        $montoTotal = (float) ($linea['MontoTotal'] ?? $linea['SubTotal'] ?? 0);
        $result = ['gravado' => 0, 'exento' => 0, 'exonerado' => 0, 'no_sujeto' => 0];

        $impuestos = $linea['Impuesto'] ?? [];

        if (empty($impuestos)) {
            $result['exento'] = $montoTotal;

            return $result;
        }

        $tarifaIVA = null;
        $hasExoneracion = false;

        foreach ($impuestos as $imp) {
            $codigo = $imp['Codigo'] ?? '';
            if (in_array($codigo, ['01', '07'])) {
                $tarifaIVA = $imp['CodigoTarifaIVA'] ?? null;
            }
            if (! empty($imp['Exoneracion'])) {
                $hasExoneracion = true;
            }
        }

        if ($hasExoneracion) {
            $result['exonerado'] = $montoTotal;
        } elseif ($tarifaIVA !== null && in_array($tarifaIVA, self::NO_SUJETO_TARIFAS)) {
            $result['no_sujeto'] = $montoTotal;
        } elseif ($tarifaIVA !== null && in_array($tarifaIVA, self::EXENTO_TARIFAS)) {
            $result['exento'] = $montoTotal;
        } else {
            $result['gravado'] = $montoTotal;
        }

        return $result;
    }
}
