<?php

namespace FactuTica\FactuticaCR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'tipo_comprobante'    => $this->receipt_type,
            'clave'               => $this->ui_key,
            'numero_consecutivo'  => $this->consecutive_number,
            'fecha_emision'       => $this->emission_date?->toIso8601String(),
            'enviado_hacienda_en' => $this->sent_to_hacienda_at?->toIso8601String(),
            'estado_comprobante'  => $this->receipt_status,
            'estado_hacienda'     => $this->hacienda_status,
        ];
    }
}
