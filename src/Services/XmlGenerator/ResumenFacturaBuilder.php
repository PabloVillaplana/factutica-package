<?php

namespace FactuTica\FactuticaCR\Services\XmlGenerator;

use DOMDocument;
use DOMElement;
use FactuTica\FactuticaCR\Services\XmlGenerator\Concerns\XmlElementHelpers;

/**
 * Construye el bloque ResumenFactura del XML (totales, moneda, medios de pago, desglose).
 */
class ResumenFacturaBuilder
{
    use XmlElementHelpers;

    public function __construct(DOMDocument $dom)
    {
        $this->dom = $dom;
    }

    public function add(DOMElement $root, array $d): void
    {
        $resumen = $this->group($root, 'ResumenFactura');

        $this->addCodigoTipoMoneda($resumen, $d['CodigoTipoMoneda']);

        $this->el($resumen, 'TotalServGravados', (string) ($d['TotalServGravados'] ?? '0'));
        $this->el($resumen, 'TotalServExentos', (string) ($d['TotalServExentos'] ?? '0'));
        $this->el($resumen, 'TotalServExonerado', (string) ($d['TotalServExonerado'] ?? '0'));
        $this->el($resumen, 'TotalServNoSujeto', (string) ($d['TotalServNoSujeto'] ?? '0'));
        $this->el($resumen, 'TotalMercanciasGravadas', (string) ($d['TotalMercanciasGravadas'] ?? '0'));
        $this->el($resumen, 'TotalMercanciasExentas', (string) ($d['TotalMercanciasExentas'] ?? '0'));
        $this->el($resumen, 'TotalMercExonerada', (string) ($d['TotalMercExonerada'] ?? '0'));
        $this->el($resumen, 'TotalMercNoSujeta', (string) ($d['TotalMercNoSujeta'] ?? '0'));
        $this->el($resumen, 'TotalGravado', (string) ($d['TotalGravado'] ?? '0'));
        $this->el($resumen, 'TotalExento', (string) ($d['TotalExento'] ?? '0'));
        $this->el($resumen, 'TotalExonerado', (string) ($d['TotalExonerado'] ?? '0'));
        $this->el($resumen, 'TotalNoSujeto', (string) ($d['TotalNoSujeto'] ?? '0'));
        $this->el($resumen, 'TotalVenta', (string) ($d['TotalVenta'] ?? '0'));
        $this->el($resumen, 'TotalDescuentos', (string) ($d['TotalDescuentos'] ?? '0'));
        $this->el($resumen, 'TotalVentaNeta', (string) ($d['TotalVentaNeta'] ?? '0'));

        if (! empty($d['TotalDesgloseImpuesto'])) {
            $this->addTotalDesgloseImpuesto($resumen, $d['TotalDesgloseImpuesto']);
        }

        $this->el($resumen, 'TotalImpuesto', (string) ($d['TotalImpuesto'] ?? '0'));
        $this->el($resumen, 'TotalImpAsumEmisorFabrica', (string) ($d['TotalImpAsumEmisorFabrica'] ?? '0'));
        $this->el($resumen, 'TotalIVADevuelto', (string) ($d['TotalIVADevuelto'] ?? '0'));
        $this->el($resumen, 'TotalOtrosCargos', (string) ($d['TotalOtrosCargos'] ?? '0'));

        if (! empty($d['MedioPago'])) {
            $this->addMediosPago($resumen, $d['MedioPago']);
        }

        $this->el($resumen, 'TotalComprobante', $d['TotalComprobante']);
    }

    private function addCodigoTipoMoneda(DOMElement $parent, array $d): void
    {
        $moneda = $this->group($parent, 'CodigoTipoMoneda');
        $this->el($moneda, 'CodigoMoneda', $d['CodigoMoneda']);
        $this->el($moneda, 'TipoCambio', $d['TipoCambio']);
    }

    private function addTotalDesgloseImpuesto(DOMElement $parent, array $data): void
    {
        foreach ($data as $d) {
            $desglose = $this->group($parent, 'TotalDesgloseImpuesto');
            $this->el($desglose, 'Codigo', $d['Codigo']);
            $this->elIf($desglose, 'CodigoTarifaIVA', $d['CodigoTarifaIVA'] ?? null);
            $this->el($desglose, 'TotalMontoImpuesto', $d['TotalMontoImpuesto']);
        }
    }

    private function addMediosPago(DOMElement $parent, array $data): void
    {
        foreach ($data as $d) {
            $medio = $this->group($parent, 'MedioPago');
            $this->el($medio, 'TipoMedioPago', $d['TipoMedioPago']);
            $this->elIf($medio, 'MedioPagoOtros', $d['MedioPagoOtros'] ?? null);
            $this->el($medio, 'TotalMedioPago', $d['TotalMedioPago']);
        }
    }
}
