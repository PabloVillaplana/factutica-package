<?php

namespace FactuTica\FactuticaCR\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use FactuTica\FactuticaCR\Constants;
use FactuTica\FactuticaCR\Exceptions\HaciendaException;
use FactuTica\FactuticaCR\Services\Webhook\WebhookService;

class WebhookController extends Controller
{
    public function __construct(
        private readonly WebhookService $webhookService,
    ) {}

    /**
     * POST /invoicing-cr/webhook
     */
    public function handle(Request $request): JsonResponse
    {
        $clave = $request->input('clave');

        if (! $clave || strlen($clave) !== Constants::CLAVE_LENGTH) {
            return response()->json(['mensaje' => 'Clave inválida.'], 422);
        }

        try {
            $result = $this->webhookService->process(
                clave: $clave,
                indEstado: $request->input('ind-estado'),
                respuestaXml: $request->input('respuesta-xml'),
                fecha: $request->input('fecha'),
            );

            return response()->json([
                'mensaje'        => 'Webhook procesado.',
                'comprobante_id' => $result['receipt_id'],
                'clave'          => $result['ui_key'],
                'estado'         => $result['status'],
            ]);
        } catch (HaciendaException $e) {
            return response()->json(['mensaje' => $e->getMessage()], 404);
        }
    }
}
