<?php

namespace FactuTica\FactuticaCR\Services;

use Illuminate\Support\Facades\DB;
use FactuTica\FactuticaCR\Contracts\InvoicingInterface;
use FactuTica\FactuticaCR\Enums\ReceiptType;
use FactuTica\FactuticaCR\Exceptions\HaciendaException;
use FactuTica\FactuticaCR\Exceptions\InvalidReceiptException;
use FactuTica\FactuticaCR\Jobs\SendReceiptToProviderJob;
use FactuTica\FactuticaCR\Models\Receipt;
use FactuTica\FactuticaCR\Providers\ProviderFactoryService;
use FactuTica\FactuticaCR\Services\Validators\CalculationValidatorService;

/**
 * Orquestador principal: validar → construir receipt → enviar a Hacienda.
 *
 * Delega responsabilidades a servicios especializados:
 * - CalculationValidatorService: validación matemática
 * - ReceiptBuilderService: consecutivo, clave, persistencia
 * - XmlPipelineService: XML, firma, envío (sync)
 * - SendReceiptToProviderJob: XML, firma, envío (async)
 */
class InvoicingService implements InvoicingInterface
{
    public function __construct(
        private readonly ProviderFactoryService $providerFactory,
        private readonly ReceiptBuilderService $receiptBuilder,
        private readonly XmlPipelineService $xmlPipeline,
        private readonly CalculationValidatorService $calculationValidator,
    ) {}

    /**
     * Crea un comprobante, genera el XML, lo firma, y lo envía al provider.
     *
     * @throws InvalidReceiptException|HaciendaException
     */
    public function createAndSend(string $type, array $data): array
    {
        $receiptType = ReceiptType::tryFrom($type);

        if (! $receiptType) {
            throw new InvalidReceiptException("Tipo de comprobante no válido: [{$type}]");
        }

        // 1. Validar cálculos matemáticos antes de consumir consecutivo
        $this->calculationValidator->validate($data);

        // 2. Construir, persistir, generar XML, firmar y enviar — todo atómico.
        //    Si cualquier paso falla, se revierte el receipt, payload y consecutivo.
        return DB::transaction(function () use ($receiptType, $data) {
            $result = $this->receiptBuilder->build($receiptType, $data);
            $receipt = $result['receipt'];

            if (config('invoicing-cr.invoicing.send_mode', 'sync') === 'async') {
                SendReceiptToProviderJob::dispatch($receipt);

                return [
                    'receipt'  => $receipt->fresh(),
                    'response' => null,
                ];
            }

            $response = $this->xmlPipeline->generateSignAndSend($receipt, $receiptType, $result['data']);

            return [
                'receipt'  => $receipt->fresh(),
                'response' => $response,
            ];
        });
    }

    public function getDocumentById(int|string $id): Receipt
    {
        return Receipt::with(['payload', 'haciendaResponse'])->findOrFail($id);
    }

    public function getDocumentByUiKey(string $uiKey): Receipt
    {
        return Receipt::with(['payload', 'haciendaResponse'])
            ->where('ui_key', $uiKey)
            ->firstOrFail();
    }

    /**
     * @throws HaciendaException
     */
    public function getDocumentStatus(string $uiKey): array
    {
        $provider = $this->providerFactory->make();

        return $provider->getStatus($uiKey);
    }
}
