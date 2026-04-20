<?php

namespace FactuTica\FactuticaCR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    private const TIMEZONE = 'America/Costa_Rica';

    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'tipo_comprobante'      => $this->receipt_type,
            'clave'                 => $this->ui_key,
            'referencia_externa'    => $this->external_reference,
            'sucursal'              => $this->establishment,
            'terminal'              => $this->terminal,
            'numero_consecutivo'    => $this->consecutive_number,
            'fecha_emision'         => $this->toCR($this->emission_date),
            'enviado_hacienda_en'   => $this->toCR($this->sent_to_hacienda_at),
            'estado_comprobante'    => $this->receipt_status,
            'estado_hacienda'       => $this->hacienda_status,
            'emisor' => [
                'nombre'              => $this->issuer_name,
                'numero'              => $this->issuer_number,
                'tipo_identificacion' => $this->issuer_identification_type,
            ],
            'receptor' => [
                'nombre'              => $this->receiver_name,
                'numero'              => $this->receiver_number,
                'tipo_identificacion' => $this->receiver_identification_type,
            ],
            'montos' => [
                'total_venta'       => $this->total_amount,
                'total_impuesto'    => $this->tax_amount,
                'total_descuentos'  => $this->total_discount,
                'total_comprobante' => $this->total_voucher,
                'moneda'            => $this->currency,
                'tipo_cambio'       => $this->exchange_rate,
            ],
            'condicion_venta'   => $this->sell_condition,
            'payload'           => $this->whenLoaded('payload', fn () => $this->payload->payload),
            'respuesta_hacienda' => $this->whenLoaded('haciendaResponse', fn () => [
                'estado'  => $this->haciendaResponse->hacienda_status,
                'mensaje' => $this->haciendaResponse->response_message,
                'fecha'   => $this->haciendaResponse->responded_at,
            ]),
            'creado_en'      => $this->toCR($this->created_at),
            'actualizado_en' => $this->toCR($this->updated_at),
        ];
    }

    private function toCR(?\DateTimeInterface $date): ?string
    {
        return $date?->setTimezone(self::TIMEZONE)->toIso8601String();
    }
}
