<?php

namespace FactuTica\FactuticaCR\Contracts;

use FactuTica\FactuticaCR\Enums\ReceiptStatus;
use FactuTica\FactuticaCR\Enums\ReceiptType;

/**
 * Contrato compartido entre Receipt y SentReceipt.
 * Define los campos comunes que el sistema de envío necesita.
 */
interface Sendable
{
    public function getUiKey(): ?string;

    public function getReceiptType(): ReceiptType;

    public function getReceiptStatus(): ReceiptStatus;

    public function markAsSent(string $uiKey, ?string $signedXml = null): void;

    public function markAsFailed(): void;
}