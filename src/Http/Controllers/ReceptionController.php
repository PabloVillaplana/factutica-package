<?php

namespace FactuTica\FactuticaCR\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use FactuTica\FactuticaCR\Enums\HaciendaStatus;
use FactuTica\FactuticaCR\Exceptions\InvalidReceiptException;
use FactuTica\FactuticaCR\Enums\ReceiptStatus;
use FactuTica\FactuticaCR\Http\Requests\StoreReceptionRequest;
use FactuTica\FactuticaCR\Jobs\SendSentReceiptToProviderJob;
use FactuTica\FactuticaCR\Models\SentReceipt;

class ReceptionController extends Controller
{
    /**
     * POST /invoicing-cr/reception
     *
     * Recibe un comprobante de un emisor y envía el mensaje de recepción.
     */
    public function store(StoreReceptionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $receiverName = $data['receiver_name'] ?? config('invoicing-cr.invoicing.emisor.nombre');
        $receiverNumber = $data['receiver_number'] ?? config('invoicing-cr.invoicing.emisor.cedula');
        $receiverIdType = $data['receiver_identification_type'] ?? config('invoicing-cr.invoicing.emisor.tipo_cedula');

        if (! $receiverName || ! $receiverNumber || ! $receiverIdType) {
            throw new InvalidReceiptException(
                'Configuración de emisor incompleta: nombre, cedula y tipo_cedula son requeridos en invoicing-cr.invoicing.emisor'
            );
        }

        $sentReceipt = SentReceipt::create([
            'receipt_type'                 => $data['receipt_type'],
            'consecutive_number'           => $data['consecutive_number'],
            'emission_date'                => $data['emission_date'],
            'receipt_status'               => ReceiptStatus::Pending,
            'hacienda_status'              => HaciendaStatus::Pending,
            'reception_status'             => $data['reception_status'],
            'reception_code'               => $data['reception_code'] ?? null,
            'reception_message'            => $data['reception_message'] ?? null,
            'economic_activity_code'       => $data['economic_activity_code'] ?? null,
            'tax_condition_code'           => $data['tax_condition_code'] ?? null,
            'tax_credited'                 => $data['tax_credited'] ?? null,
            'total_expense'                => $data['total_expense'] ?? 0,
            'tax_amount'                   => $data['tax_amount'] ?? 0,
            'total_voucher'                => $data['total_voucher'] ?? 0,
            'issuer_name'                  => $data['issuer_name'],
            'issuer_number'                => $data['issuer_number'],
            'issuer_identification_type'   => $data['issuer_identification_type'],
            'receiver_name'                => $receiverName,
            'receiver_number'              => $receiverNumber,
            'receiver_identification_type' => $receiverIdType,
        ]);

        SendSentReceiptToProviderJob::dispatch($sentReceipt, $data);

        return response()->json([
            'mensaje' => 'Mensaje de recepción creado y encolado.',
            'data'    => [
                'id'                 => $sentReceipt->id,
                'tipo_comprobante'   => $sentReceipt->receipt_type,
                'estado_comprobante' => $sentReceipt->receipt_status,
            ],
        ], 202);
    }
}