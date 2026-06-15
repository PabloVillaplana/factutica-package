<?php

namespace FactuTica\FactuticaCR\Services\XmlGenerator;

use DOMDocument;
use DOMElement;
use FactuTica\FactuticaCR\Services\XmlGenerator\Concerns\XmlElementHelpers;

/**
 * Construye el bloque DetalleServicio del XML (líneas, impuestos, descuentos, surtidos).
 */
class DetalleServicioBuilder
{
    use XmlElementHelpers;

    public function __construct(DOMDocument $dom)
    {
        $this->dom = $dom;
    }

    public function add(DOMElement $root, array $data): void
    {
        $detalle = $this->group($root, 'DetalleServicio');

        foreach ($data['LineaDetalle'] as $line) {
            $this->addLineaDetalle($detalle, $line);
        }
    }

    private function addLineaDetalle(DOMElement $parent, array $d): void
    {
        $linea = $this->group($parent, 'LineaDetalle');

        $this->el($linea, 'NumeroLinea', $d['NumeroLinea']);
        $this->el($linea, 'CodigoCABYS', $d['CodigoCABYS']);

        if (! empty($d['CodigoComercial'])) {
            foreach ($d['CodigoComercial'] as $codigo) {
                $cc = $this->group($linea, 'CodigoComercial');
                $this->el($cc, 'Tipo', $codigo['Tipo']);
                $this->el($cc, 'Codigo', $codigo['Codigo']);
            }
        }

        $this->el($linea, 'Cantidad', $d['Cantidad']);
        $this->el($linea, 'UnidadMedida', $d['UnidadMedida']);
        $this->elIf($linea, 'TipoTransaccion', $d['TipoTransaccion'] ?? null);
        $this->elIf($linea, 'UnidadMedidaComercial', $d['UnidadMedidaComercial'] ?? null);
        $this->el($linea, 'Detalle', $d['Detalle']);

        if (! empty($d['NumeroVINoSerie'])) {
            foreach ($d['NumeroVINoSerie'] as $vin) {
                $this->el($linea, 'NumeroVINoSerie', $vin);
            }
        }

        $this->elIf($linea, 'RegistroMedicamento', $d['RegistroMedicamento'] ?? null);
        $this->elIf($linea, 'FormaFarmaceutica', $d['FormaFarmaceutica'] ?? null);

        if (! empty($d['DetalleSurtido'])) {
            $this->addDetalleSurtido($linea, $d['DetalleSurtido']);
        }

        $this->el($linea, 'PrecioUnitario', $d['PrecioUnitario']);
        $this->el($linea, 'MontoTotal', $d['MontoTotal']);

        if (! empty($d['Descuento'])) {
            $this->addDescuentos($linea, $d['Descuento']);
        }

        $this->el($linea, 'SubTotal', $d['SubTotal']);
        $this->elIf($linea, 'IVACobradoFabrica', $d['IVACobradoFabrica'] ?? null);
        $this->el($linea, 'BaseImponible', $d['BaseImponible']);
        $this->elIf($linea, 'MontoExportacion', $d['MontoExportacion'] ?? null);

        if (! empty($d['Impuesto'])) {
            $this->addImpuestos($linea, $d['Impuesto']);
        }

        $this->el($linea, 'ImpuestoAsumidoEmisorFabrica', (string) ($d['ImpuestoAsumidoEmisorFabrica'] ?? '0'));
        $this->el($linea, 'ImpuestoNeto', (string) ($d['ImpuestoNeto'] ?? '0'));
        $this->el($linea, 'MontoTotalLinea', $d['MontoTotalLinea']);
    }

    private function addDescuentos(DOMElement $parent, array $data): void
    {
        foreach ($data as $d) {
            $desc = $this->group($parent, 'Descuento');
            $this->el($desc, 'MontoDescuento', $d['MontoDescuento']);
            $this->el($desc, 'CodigoDescuento', $d['CodigoDescuento']);
            $this->elIf($desc, 'CodigoDescuentoOtro', $d['CodigoDescuentoOtro'] ?? null);
            $this->elIf($desc, 'NaturalezaDescuento', $d['NaturalezaDescuento'] ?? null);
        }
    }

    private function addImpuestos(DOMElement $parent, array $data): void
    {
        foreach ($data as $d) {
            $imp = $this->group($parent, 'Impuesto');
            $this->el($imp, 'Codigo', $d['Codigo']);
            $this->elIf($imp, 'CodigoTarifaIVA', $d['CodigoTarifaIVA'] ?? null);
            $this->elIf($imp, 'CodigoImpuestoOtro', $d['CodigoImpuestoOtro'] ?? null);
            $this->elIf($imp, 'Tarifa', $d['Tarifa'] ?? null);
            $this->elIf($imp, 'FactorCalculoIVA', $d['FactorCalculoIVA'] ?? null);

            if (! empty($d['DatosImpuestoEspecifico'])) {
                $this->addDatosImpuestoEspecifico($imp, $d['DatosImpuestoEspecifico']);
            }

            $this->el($imp, 'Monto', $d['Monto']);

            if (! empty($d['Exoneracion'])) {
                $this->addExoneracion($imp, $d['Exoneracion']);
            }
        }
    }

    private function addDatosImpuestoEspecifico(DOMElement $parent, array $d): void
    {
        $datos = $this->group($parent, 'DatosImpuestoEspecifico');
        $this->el($datos, 'CantidadUnidadMedida', $d['CantidadUnidadMedida']);
        $this->elIf($datos, 'Porcentaje', $d['Porcentaje'] ?? null);
        $this->elIf($datos, 'Proporcion', $d['Proporcion'] ?? null);
        $this->elIf($datos, 'VolumenUnidadConsumo', $d['VolumenUnidadConsumo'] ?? null);
        $this->el($datos, 'ImpuestoUnidad', $d['ImpuestoUnidad']);
    }

    private function addExoneracion(DOMElement $parent, array $d): void
    {
        $exo = $this->group($parent, 'Exoneracion');
        $this->el($exo, 'TipoDocumentoEX1', $d['TipoDocumentoEX1']);
        $this->elIf($exo, 'TipoDocumentoOTRO', $d['TipoDocumentoOTRO'] ?? null);
        $this->el($exo, 'NumeroDocumento', $d['NumeroDocumento']);
        $this->elIf($exo, 'Articulo', $d['Articulo'] ?? null);
        $this->elIf($exo, 'Inciso', $d['Inciso'] ?? null);
        $this->el($exo, 'NombreInstitucion', $d['NombreInstitucion']);
        $this->elIf($exo, 'NombreInstitucionOTRO', $d['NombreInstitucionOTRO'] ?? null);
        $this->el($exo, 'FechaEmisionEX', $d['FechaEmisionEX']);
        $this->el($exo, 'TarifaExonerada', $d['TarifaExonerada']);
        $this->el($exo, 'MontoExoneracion', $d['MontoExoneracion']);
    }

    private function addDetalleSurtido(DOMElement $parent, array $data): void
    {
        $surtido = $this->group($parent, 'DetalleSurtido');

        foreach ($data['LineaDetalleSurtido'] as $line) {
            $this->addLineaDetalleSurtido($surtido, $line);
        }
    }

    private function addLineaDetalleSurtido(DOMElement $parent, array $d): void
    {
        $linea = $this->group($parent, 'LineaDetalleSurtido');

        $this->el($linea, 'CodigoCABYSSurtido', $d['CodigoCABYSSurtido']);

        if (! empty($d['CodigoComercialSurtido'])) {
            foreach ($d['CodigoComercialSurtido'] as $cc) {
                $el = $this->group($linea, 'CodigoComercialSurtido');
                $this->el($el, 'TipoSurtido', $cc['TipoSurtido']);
                $this->el($el, 'CodigoSurtido', $cc['CodigoSurtido']);
            }
        }

        $this->el($linea, 'CantidadSurtido', $d['CantidadSurtido']);
        $this->el($linea, 'UnidadMedidaSurtido', $d['UnidadMedidaSurtido']);
        $this->elIf($linea, 'UnidadMedidaComercialSurtido', $d['UnidadMedidaComercialSurtido'] ?? null);
        $this->el($linea, 'DetalleSurtido', $d['DetalleSurtido']);
        $this->el($linea, 'PrecioUnitarioSurtido', $d['PrecioUnitarioSurtido']);
        $this->el($linea, 'MontoTotalSurtido', $d['MontoTotalSurtido']);

        if (! empty($d['DescuentoSurtido'])) {
            foreach ($d['DescuentoSurtido'] as $desc) {
                $ds = $this->group($linea, 'DescuentoSurtido');
                $this->el($ds, 'MontoDescuentoSurtido', $desc['MontoDescuentoSurtido']);
                $this->el($ds, 'CodigoDescuentoSurtido', $desc['CodigoDescuentoSurtido']);
                $this->elIf($ds, 'CodigoDescuentoOtroSurtido', $desc['CodigoDescuentoOtroSurtido'] ?? null);
            }
        }

        $this->el($linea, 'SubTotalSurtido', $d['SubTotalSurtido']);
        $this->elIf($linea, 'IVACobradoFabricaSurtido', $d['IVACobradoFabricaSurtido'] ?? null);
        $this->el($linea, 'BaseImponibleSurtido', $d['BaseImponibleSurtido']);

        foreach ($d['ImpuestoSurtido'] as $imp) {
            $is = $this->group($linea, 'ImpuestoSurtido');
            $this->el($is, 'CodigoImpuestoSurtido', $imp['CodigoImpuestoSurtido']);
            $this->elIf($is, 'CodigoImpuestoOTROSurtido', $imp['CodigoImpuestoOTROSurtido'] ?? null);
            $this->elIf($is, 'CodigoTarifaIVASurtido', $imp['CodigoTarifaIVASurtido'] ?? null);
            $this->elIf($is, 'TarifaSurtido', $imp['TarifaSurtido'] ?? null);

            if (! empty($imp['DatosImpuestoEspecificoSurtido'])) {
                $die = $imp['DatosImpuestoEspecificoSurtido'];
                $datos = $this->group($is, 'DatosImpuestoEspecificoSurtido');
                $this->el($datos, 'CantidadUnidadMedidaSurtido', $die['CantidadUnidadMedidaSurtido']);
                $this->elIf($datos, 'PorcentajeSurtido', $die['PorcentajeSurtido'] ?? null);
                $this->elIf($datos, 'ProporcionSurtido', $die['ProporcionSurtido'] ?? null);
                $this->elIf($datos, 'VolumenUnidadConsumoSurtido', $die['VolumenUnidadConsumoSurtido'] ?? null);
                $this->el($datos, 'ImpuestoUnidadSurtido', $die['ImpuestoUnidadSurtido']);
            }

            $this->el($is, 'MontoImpuestoSurtido', $imp['MontoImpuestoSurtido']);
        }
    }
}
