<?php

namespace FactuTica\FactuticaCR\Models\Concerns;

use FactuTica\FactuticaCR\Enums\ReceiptStatus;
use FactuTica\FactuticaCR\Enums\ReceiptType;

/**
 * Implementación compartida del contrato Sendable para Receipt y SentReceipt.
 *
 * Requiere que el modelo tenga: ui_key, receipt_type, receipt_status,
 * sent_to_hacienda_at, signed_xml en $fillable.
 */
trait HasSendableStatus
{
    public function getKey(): string
    {
        return $this->ui_key;
    }

    public function getReceiptType(): ReceiptType
    {
        return $this->receipt_type;
    }

    public function getReceiptStatus(): ReceiptStatus
    {
        return $this->receipt_status;
    }

    public function markAsSent(string $uiKey, ?string $signedXml = null): void
    {
        $this->update([
            'receipt_status'      => ReceiptStatus::Sent,
            'ui_key'              => $uiKey ?: $this->ui_key,
            'sent_to_hacienda_at' => now(),
            'signed_xml'          => $signedXml ?? $this->signed_xml,
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'receipt_status' => ReceiptStatus::Failed,
        ]);
    }
}
