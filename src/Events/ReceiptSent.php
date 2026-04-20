<?php

namespace FactuTica\FactuticaCR\Events;

use Illuminate\Foundation\Events\Dispatchable;
use FactuTica\FactuticaCR\Models\Receipt;
use FactuTica\FactuticaCR\Providers\ProviderResponse;

class ReceiptSent
{
    use Dispatchable;

    public function __construct(
        public readonly Receipt $receipt,
        public readonly ?ProviderResponse $response = null,
    ) {}
}
