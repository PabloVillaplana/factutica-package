<?php

namespace FactuTica\FactuticaCR\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use FactuTica\FactuticaCR\Exceptions\HaciendaException;
use FactuTica\FactuticaCR\Exceptions\InvalidReceiptException;
use FactuTica\FactuticaCR\Http\Requests\StoreReceiptRequest;
use FactuTica\FactuticaCR\Http\Resources\ReceiptResource;
use FactuTica\FactuticaCR\Http\Resources\ReceiptStatusResource;
use FactuTica\FactuticaCR\Models\Receipt;
use FactuTica\FactuticaCR\Services\InvoicingService;

class ReceiptController extends Controller
{
    public function __construct(
        private readonly InvoicingService $invoicingService,
    ) {}

    /**
     * POST /invoicing-cr/receipts
     *
     * Crea un comprobante, genera XML, firma, y envía al provider.
     */
    public function store(StoreReceiptRequest $request): JsonResponse
    {
        try {
            $result = $this->invoicingService->createAndSend(
                $request->validated('receipt_type'),
                $request->validated(),
            );
        } catch (InvalidReceiptException $e) {
            return response()->json([
                'mensaje' => $e->getMessage(),
            ], 422);
        }

        $async = $result['response'] === null;

        return response()->json([
            'mensaje' => $async ? 'Comprobante creado y encolado para envío.' : 'Comprobante creado y enviado.',
            'data'    => new ReceiptResource($result['receipt']),
        ], $async ? 202 : 201);
    }

    /**
     * GET /invoicing-cr/receipts
     *
     * Lista comprobantes con filtros opcionales.
     */
    public function index(Request $request): JsonResponse
    {
        $receipts = Receipt::with(['payload', 'haciendaResponse'])
            ->filter($request->only(['type', 'status']))
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => ReceiptResource::collection($receipts),
            'meta' => [
                'current_page' => $receipts->currentPage(),
                'last_page'    => $receipts->lastPage(),
                'per_page'     => $receipts->perPage(),
                'total'        => $receipts->total(),
            ],
        ]);
    }

    /**
     * GET /invoicing-cr/receipts/{receipt}
     */
    public function show(int $id): JsonResponse
    {
        $receipt = $this->invoicingService->getDocumentById($id);

        return response()->json([
            'data' => new ReceiptResource($receipt),
        ]);
    }

    /**
     * GET /invoicing-cr/receipts/key/{uiKey}
     */
    public function showByKey(string $uiKey): JsonResponse
    {
        $receipt = $this->invoicingService->getDocumentByUiKey($uiKey);

        return response()->json([
            'data' => new ReceiptResource($receipt),
        ]);
    }

    /**
     * GET /invoicing-cr/receipts/key/{uiKey}/status
     */
    public function status(string $uiKey): JsonResponse
    {
        $receipt = $this->invoicingService->getDocumentByUiKey($uiKey);

        try {
            $providerStatus = $this->invoicingService->getDocumentStatus($uiKey);
        } catch (HaciendaException $e) {
            return response()->json([
                'data'     => new ReceiptStatusResource($receipt),
                'hacienda' => null,
                'error'    => $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'data'     => new ReceiptStatusResource($receipt),
            'hacienda' => $providerStatus,
        ]);
    }
}