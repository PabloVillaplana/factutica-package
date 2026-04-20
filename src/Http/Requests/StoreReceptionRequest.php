<?php

namespace FactuTica\FactuticaCR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use FactuTica\FactuticaCR\Enums\ReceiptType;

class StoreReceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clave'                      => ['required', 'string', 'size:'.\FactuTica\FactuticaCR\Constants::CLAVE_LENGTH],
            'receipt_type'               => ['required', 'string', 'in:'.implode(',', array_column(ReceiptType::cases(), 'value'))],
            'consecutive_number'         => ['required', 'string'],
            'emission_date'              => ['required', 'date'],
            'reception_status'           => ['required', 'string', 'in:accepted,partially_accepted,rejected'],
            'reception_code'             => ['nullable', 'string'],
            'reception_message'          => ['nullable', 'string'],
            'economic_activity_code'     => ['nullable', 'string'],
            'tax_condition_code'         => ['nullable', 'string'],
            'tax_credited'               => ['nullable', 'numeric'],
            'total_expense'              => ['required', 'numeric'],
            'tax_amount'                 => ['nullable', 'numeric'],
            'total_voucher'              => ['required', 'numeric'],
            'issuer_name'                => ['required', 'string'],
            'issuer_number'              => ['required', 'string'],
            'issuer_identification_type' => ['required', 'string', 'in:01,02,03,04,05,06'],
            'receiver_name'              => ['nullable', 'string'],
            'receiver_number'            => ['nullable', 'string'],
            'receiver_identification_type' => ['nullable', 'string', 'in:01,02,03,04,05,06'],
        ];
    }
}