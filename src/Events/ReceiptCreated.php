<?php

namespace FactuTica\FactuticaCR\Events;

use Illuminate\Foundation\Events\Dispatchable;
use FactuTica\FactuticaCR\Models\Receipt;

class ReceiptCreated
{
    use Dispatchable;

    public function __construct(
        public readonly Receipt $receipt,
    ) {}
}
