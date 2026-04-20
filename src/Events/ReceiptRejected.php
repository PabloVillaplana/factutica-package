<?php

namespace FactuTica\FactuticaCR\Events;

use Illuminate\Foundation\Events\Dispatchable;
use FactuTica\FactuticaCR\Models\Receipt;

class ReceiptRejected
{
    use Dispatchable;

    public function __construct(
        public readonly Receipt $receipt,
        public readonly ?string $message = null,
    ) {}
}
