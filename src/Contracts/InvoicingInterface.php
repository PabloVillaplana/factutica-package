<?php

namespace FactuTica\FactuticaCR\Contracts;

use FactuTica\FactuticaCR\Models\Receipt;

interface InvoicingInterface
{
    public function createAndSend(string $type, array $data): array;
    public function getDocumentById(int|string $id): Receipt;
    public function getDocumentByUiKey(string $uiKey): Receipt;
    public function getDocumentStatus(string $uiKey): array;
}
