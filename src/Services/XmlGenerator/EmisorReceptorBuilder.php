<?php

namespace FactuTica\FactuticaCR\Services\XmlGenerator;

use DOMDocument;
use DOMElement;
use FactuTica\FactuticaCR\Services\XmlGenerator\Concerns\XmlElementHelpers;

/**
 * Construye los bloques Emisor y Receptor del XML.
 */
class EmisorReceptorBuilder
{
    use XmlElementHelpers;

    public function __construct(DOMDocument $dom)
    {
        $this->dom = $dom;
    }

    public function addEmisor(DOMElement $root, array $data): void
    {
        $emisor = $this->group($root, 'Emisor');

        $this->el($emisor, 'Nombre', $data['Nombre']);
        $this->addIdentificacion($emisor, $data['Identificacion']);
        $this->elIf($emisor, 'Registrofiscal8707', $data['Registrofiscal8707'] ?? null);
        $this->elIf($emisor, 'NombreComercial', $data['NombreComercial'] ?? null);

        if (! empty($data['Ubicacion'])) {
            $this->addUbicacion($emisor, $data['Ubicacion']);
        }

        $this->elIf($emisor, 'OtrasSenasExtranjero', $data['OtrasSenasExtranjero'] ?? null);

        if (! empty($data['Telefono'])) {
            $this->addTelefono($emisor, $data['Telefono']);
        }

        if (! empty($data['CorreoElectronico'])) {
            foreach ($data['CorreoElectronico'] as $email) {
                $this->el($emisor, 'CorreoElectronico', $email);
            }
        }
    }

    public function addReceptor(DOMElement $root, array $data): void
    {
        $receptor = $this->group($root, 'Receptor');

        $this->el($receptor, 'Nombre', $data['Nombre']);

        if (! empty($data['Identificacion'])) {
            $this->addIdentificacion($receptor, $data['Identificacion']);
        }

        $this->elIf($receptor, 'Registrofiscal8707', $data['Registrofiscal8707'] ?? null);
        $this->elIf($receptor, 'NombreComercial', $data['NombreComercial'] ?? null);

        if (! empty($data['Ubicacion'])) {
            $this->addUbicacion($receptor, $data['Ubicacion']);
        }

        $this->elIf($receptor, 'OtrasSenasExtranjero', $data['OtrasSenasExtranjero'] ?? null);

        if (! empty($data['Telefono'])) {
            $this->addTelefono($receptor, $data['Telefono']);
        }

        if (! empty($data['CorreoElectronico'])) {
            $emails = is_array($data['CorreoElectronico']) ? $data['CorreoElectronico'] : [$data['CorreoElectronico']];
            foreach ($emails as $email) {
                $this->el($receptor, 'CorreoElectronico', $email);
            }
        }
    }

    private function addIdentificacion(DOMElement $parent, array $data): void
    {
        $id = $this->group($parent, 'Identificacion');
        $this->el($id, 'Tipo', $data['Tipo']);
        $this->el($id, 'Numero', $data['Numero']);
    }

    private function addUbicacion(DOMElement $parent, array $data): void
    {
        $ubi = $this->group($parent, 'Ubicacion');
        $this->el($ubi, 'Provincia', $data['Provincia']);
        $this->el($ubi, 'Canton', $data['Canton']);
        $this->el($ubi, 'Distrito', $data['Distrito']);
        $this->elIf($ubi, 'Barrio', $data['Barrio'] ?? null);
        $this->el($ubi, 'OtrasSenas', $data['OtrasSenas']);
    }

    private function addTelefono(DOMElement $parent, array $data): void
    {
        $tel = $this->group($parent, 'Telefono');
        $this->el($tel, 'CodigoPais', $data['CodigoPais']);
        $this->el($tel, 'NumTelefono', $data['NumTelefono']);
    }
}
