<?php

namespace FactuTica\FactuticaCR\Services\XmlGenerator;

use DOMDocument;
use DOMElement;
use FactuTica\FactuticaCR\Enums\ReceiptType;
use FactuTica\FactuticaCR\Exceptions\InvalidReceiptException;
use FactuTica\FactuticaCR\Services\XmlGenerator\Concerns\XmlElementHelpers;

/**
 * Orquestador: genera el XML v4.4 del comprobante delegando a builders especializados.
 */
class XmlGeneratorService
{
    use XmlElementHelpers;

    private DOMDocument $dom;

    private DOMElement $root;

    private const ROOT_ELEMENTS = [
        'FE'  => ['FacturaElectronica',           'https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.4/facturaElectronica'],
        'ND'  => ['NotaDebitoElectronica',         'https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.4/notaDebitoElectronica'],
        'NC'  => ['NotaCreditoElectronica',        'https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.4/notaCreditoElectronica'],
        'TE'  => ['TiqueteElectronico',            'https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.4/tiqueteElectronico'],
        'FEC' => ['FacturaElectronicaCompras',     'https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.4/facturaElectronicaCompras'],
        'FEE' => ['FacturaElectronicaExportacion', 'https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.4/facturaElectronicaExportacion'],
        'REP' => ['ComprobanteElectronicoPago',    'https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.4/comprobanteElectronicoPago'],
    ];

    /**
     * @throws InvalidReceiptException
     */
    public function generate(ReceiptType $type, array $data): string
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = false;

        try {
            $this->initRootElement($type);

            $emisorReceptor = new EmisorReceptorBuilder($this->dom);
            $detalle = new DetalleServicioBuilder($this->dom);
            $resumen = new ResumenFacturaBuilder($this->dom);
            $complementos = new ComplementosBuilder($this->dom);

            // Estructura principal del comprobante
            $this->addBasicData($data);
            $emisorReceptor->addEmisor($this->root, $data['Emisor']);
            if (! empty($data['Receptor'])) {
                $emisorReceptor->addReceptor($this->root, $data['Receptor']);
            }
            $this->addCondicionVenta($data);
            $this->addIfPresent($data, 'DetalleServicio', fn (array $d) => $detalle->add($this->root, $d));
            $this->addIfPresent($data, 'OtrosCargos', fn (array $d) => $complementos->addOtrosCargos($this->root, $d));
            $resumen->add($this->root, $data['ResumenFactura']);
            $this->addIfPresent($data, 'InformacionReferencia', fn (array $d) => $complementos->addInformacionReferencia($this->root, $d));
            $this->addIfPresent($data, 'Otros', fn (array $d) => $complementos->addOtros($this->root, $d));

            return $this->dom->saveXML();
        } catch (InvalidReceiptException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new InvalidReceiptException("Error generando XML: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @throws InvalidReceiptException
     */
    private function initRootElement(ReceiptType $type): void
    {
        $config = self::ROOT_ELEMENTS[$type->value] ?? null;

        if (! $config) {
            throw new InvalidReceiptException("Tipo de comprobante [{$type->value}] no soportado para generación XML.");
        }

        [$rootName, $namespace] = $config;
        $this->root = $this->dom->createElementNS($namespace, $rootName);
        $this->dom->appendChild($this->root);
    }

    private function addBasicData(array $data): void
    {
        $this->el($this->root, 'Clave', $data['Clave']);
        $this->el($this->root, 'ProveedorSistemas', $data['ProveedorSistema']);
        $this->elIf($this->root, 'CodigoActividadEmisor', $data['CodigoActividadEmisor'] ?? null);
        $this->elIf($this->root, 'CodigoActividadReceptor', $data['CodigoActividadReceptor'] ?? null);
        $this->el($this->root, 'NumeroConsecutivo', $data['NumeroConsecutivo']);
        $this->el($this->root, 'FechaEmision', $data['FechaEmision']);
    }

    private function addCondicionVenta(array $data): void
    {
        $this->el($this->root, 'CondicionVenta', $data['CondicionVenta']);
        $this->elIf($this->root, 'CondicionVentaOtros', $data['CondicionVentaOtros'] ?? null);
        $this->elIf($this->root, 'PlazoCredito', $data['PlazoCredito'] ?? null);
    }
}
