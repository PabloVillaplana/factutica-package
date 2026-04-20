<?php

namespace FactuTica\FactuticaCR\Services\XmlGenerator\Concerns;

use DOMDocument;
use DOMElement;

/**
 * Helpers compartidos para construir elementos XML con createTextNode (escape seguro).
 */
trait XmlElementHelpers
{
    private DOMDocument $dom;

    private function el(DOMElement $parent, string $name, mixed $value): DOMElement
    {
        $element = $this->dom->createElement($name);
        $element->appendChild($this->dom->createTextNode((string) $value));
        $parent->appendChild($element);

        return $element;
    }

    private function elIf(DOMElement $parent, string $name, mixed $value): ?DOMElement
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->el($parent, $name, $value);
    }

    private function group(DOMElement $parent, string $name): DOMElement
    {
        $element = $this->dom->createElement($name);
        $parent->appendChild($element);

        return $element;
    }

    private function addIfPresent(array $data, string $key, callable $callback): void
    {
        if (! empty($data[$key])) {
            $callback($data[$key]);
        }
    }
}
