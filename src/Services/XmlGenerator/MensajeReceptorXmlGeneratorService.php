<?php

namespace FactuTica\FactuticaCR\Services\XmlGenerator;

use DOMDocument;
use DOMElement;
use FactuTica\FactuticaCR\Exceptions\InvalidReceiptException;

/**
 * Genera el XML de MensajeReceptor (CA/CAP/CR) para confirmar
 * la recepción de un comprobante electrónico.
 *
 * Tipos:
 *   05 = Confirmación de Aceptación (CA)
 *   06 = Confirmación de Aceptación Parcial (CAP)
 *   07 = Confirmación de Rechazo (CR)
 */
class MensajeReceptorXmlGeneratorService
{
    private DOMDocument $dom;

    private DOMElement $root;

    /**
     * @throws InvalidReceiptException
     */
    public function generate(array $data): string
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = false;

        try {
            $this->initRootElement();

            // Elementos requeridos (orden según XSD)
            $this->el($this->root, 'Clave', $data['Clave']);
            $this->el($this->root, 'NumeroCedulaEmisor', $data['NumeroCedulaEmisor']);
            $this->el($this->root, 'FechaEmisionDoc', $data['FechaEmisionDoc']);
            $this->el($this->root, 'Mensaje', (string) $data['Mensaje']);

            // Elementos opcionales (orden exacto según XSD)
            $this->elIf($this->root, 'DetalleMensaje', $data['DetalleMensaje'] ?? null);
            $this->elIf($this->root, 'MontoTotalImpuesto', $data['MontoTotalImpuesto'] ?? null);
            $this->elIf($this->root, 'CodigoActividad', $data['CodigoActividad'] ?? null);
            $this->elIf($this->root, 'CondicionImpuesto', $data['CondicionImpuesto'] ?? null);
            $this->elIf($this->root, 'MontoTotalImpuestoAcreditar', $data['MontoTotalImpuestoAcreditar'] ?? null);
            $this->elIf($this->root, 'MontoTotalDeGastoAplicable', $data['MontoTotalDeGastoAplicable'] ?? null);

            // Elementos requeridos finales
            $this->el($this->root, 'TotalFactura', (string) $data['TotalFactura']);
            $this->el($this->root, 'NumeroCedulaReceptor', $data['NumeroCedulaReceptor']);
            $this->el($this->root, 'NumeroConsecutivoReceptor', $data['NumeroConsecutivoReceptor']);

            return $this->dom->saveXML();
        } catch (InvalidReceiptException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new InvalidReceiptException("Error generando XML de MensajeReceptor: {$e->getMessage()}", 0, $e);
        }
    }

    private function initRootElement(): void
    {
        $this->root = $this->dom->createElementNS(
            'https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.4/mensajeReceptor',
            'MensajeReceptor'
        );
        $this->dom->appendChild($this->root);
    }

    private function el(DOMElement $parent, string $name, string $value): DOMElement
    {
        $element = $this->dom->createElement($name);
        $element->appendChild($this->dom->createTextNode($value));
        $parent->appendChild($element);

        return $element;
    }

    private function elIf(DOMElement $parent, string $name, mixed $value): ?DOMElement
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->el($parent, $name, (string) $value);
    }
}