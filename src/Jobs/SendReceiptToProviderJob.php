<?php

namespace FactuTica\FactuticaCR\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use FactuTica\FactuticaCR\Contracts\Sendable;
use FactuTica\FactuticaCR\Models\Receipt;
use FactuTica\FactuticaCR\Services\XmlPipelineService;
use FactuTica\FactuticaCR\Traits\SendsToProvider;

class SendReceiptToProviderJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SendsToProvider, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var int[] */
    public array $backoff = [30, 60, 90];

    public function __construct(
        public Receipt $receipt,
    ) {}

    public function uniqueId(): string
    {
        return 'receipt-'.$this->receipt->id;
    }

    protected function getSendable(): Sendable
    {
        return $this->receipt;
    }

    protected function getJobName(): string
    {
        return 'SendReceiptToProviderJob';
    }

    public function handle(XmlPipelineService $pipeline): void
    {
        $receipt = $this->receipt;
        $data = $receipt->payload->payload;

        $response = $pipeline->generateSignAndSend($receipt, $receipt->receipt_type, $data);

        Log::info('SendReceiptToProviderJob: enviado', [
            'id'           => $receipt->getKey(),
            'receipt_type' => $receipt->getReceiptType()->value,
            'ui_key'       => $response->clave,
        ]);
    }
}
