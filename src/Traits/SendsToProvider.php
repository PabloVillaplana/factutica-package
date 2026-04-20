<?php

namespace FactuTica\FactuticaCR\Traits;

use Illuminate\Support\Facades\Log;
use FactuTica\FactuticaCR\Contracts\Sendable;

/**
 * Lógica compartida entre jobs que envían comprobantes al provider.
 *
 * Cada job implementa su propio handle() con la generación de XML específica.
 * Este trait provee el manejo de errores y logging compartido.
 */
trait SendsToProvider
{
    abstract protected function getSendable(): Sendable;

    abstract protected function getJobName(): string;

    public function failed(\Throwable $exception): void
    {
        $sendable = $this->getSendable();

        Log::error("{$this->getJobName()}: falló", [
            'id'           => $sendable->getKey(),
            'receipt_type' => $sendable->getReceiptType()->value,
            'error'        => $exception->getMessage(),
        ]);

        $sendable->markAsFailed();
    }
}
