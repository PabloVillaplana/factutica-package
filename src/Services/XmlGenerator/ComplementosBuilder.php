<?php

namespace FactuTica\FactuticaCR\Services\XmlGenerator;

use DOMDocument;
use DOMElement;
use FactuTica\FactuticaCR\Services\XmlGenerator\Concerns\XmlElementHelpers;

/**
 * Construye los bloques complementarios del XML: OtrosCargos, InformacionReferencia, Otros.
 */
class ComplementosBuilder
{
    use XmlElementHelpers;

    public function __construct(DOMDocument $dom)
    {
        $this->dom = $dom;
    }

    public function addOtrosCargos(DOMElement $root, array $data): void
    {
        foreach ($data as $d) {
            $cargo = $this->group($root, 'OtrosCargos');
            $this->el($cargo, 'TipoDocumentoOC', $d['TipoDocumentoOC']);
            $this->elIf($cargo, 'TipoDocumentoOTROS', $d['TipoDocumentoOTROS'] ?? null);

            if (! empty($d['IdentificacionTercero'])) {
                $id = $this->group($cargo, 'IdentificacionTercero');
                $this->el($id, 'Tipo', $d['IdentificacionTercero']['Tipo']);
                $this->el($id, 'Numero', $d['IdentificacionTercero']['Numero']);
            }

            $this->elIf($cargo, 'NombreTercero', $d['NombreTercero'] ?? null);
            $this->el($cargo, 'Detalle', $d['Detalle']);
            $this->elIf($cargo, 'PorcentajeOC', $d['PorcentajeOC'] ?? null);
            $this->el($cargo, 'MontoCargo', $d['MontoCargo']);
        }
    }

    public function addInformacionReferencia(DOMElement $root, array $data): void
    {
        foreach ($data as $d) {
            $ref = $this->group($root, 'InformacionReferencia');
            $this->el($ref, 'TipoDocIR', $d['TipoDocIR']);
            $this->elIf($ref, 'TipoDocRefOTRO', $d['TipoDocRefOTRO'] ?? null);
            $this->elIf($ref, 'Numero', $d['Numero'] ?? null);
            $this->el($ref, 'FechaEmisionIR', $d['FechaEmisionIR']);
            $this->elIf($ref, 'Codigo', $d['Codigo'] ?? null);
            $this->elIf($ref, 'CodigoReferenciaOTRO', $d['CodigoReferenciaOTRO'] ?? null);
            $this->elIf($ref, 'Razon', $d['Razon'] ?? null);
        }
    }

    public function addOtros(DOMElement $root, array $data): void
    {
        $otros = $this->group($root, 'Otros');

        if (! empty($data['OtroTexto'])) {
            foreach ($data['OtroTexto'] as $texto) {
                $value = is_string($texto) ? $texto : ($texto['valor'] ?? $texto['value'] ?? '');
                $ot = $this->el($otros, 'OtroTexto', $value);

                $codigo = is_array($texto) ? ($texto['codigo'] ?? null) : null;
                if ($codigo !== null && $codigo !== '') {
                    $ot->setAttribute('codigo', $codigo);
                }
            }
        }

        if (! empty($data['OtroContenido'])) {
            foreach ($data['OtroContenido'] as $contenido) {
                $value = is_string($contenido) ? $contenido : ($contenido['valor'] ?? $contenido['value'] ?? '');
                $oc = $this->el($otros, 'OtroContenido', $value);

                $codigo = is_array($contenido) ? ($contenido['codigo'] ?? null) : null;
                if ($codigo !== null && $codigo !== '') {
                    $oc->setAttribute('codigo', $codigo);
                }
            }
        }
    }
}
