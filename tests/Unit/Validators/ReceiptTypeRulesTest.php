<?php

use FactuTica\FactuticaCR\Enums\ReceiptType;
use FactuTica\FactuticaCR\Services\Validators\ReceiptTypeRules;
use Illuminate\Support\Facades\Validator;

it('accepts every official TipoDocIR catalog code (Hacienda Anexo v4.4 Nota 10)', function () {
    $tipoDocIRRule = ReceiptTypeRules::toValidationRules(ReceiptType::PurchaseInvoice)['InformacionReferencia.*.TipoDocIR'];

    $catalog = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '15', '16', '17', '18', '99'];

    foreach ($catalog as $code) {
        $validator = Validator::make([
            'InformacionReferencia' => [
                ['TipoDocIR' => $code],
            ],
        ], [
            'InformacionReferencia.*.TipoDocIR' => $tipoDocIRRule,
        ]);

        expect($validator->passes())->toBeTrue("TipoDocIR code {$code} should be valid but failed: ".json_encode($validator->errors()->all()));
    }
});

it('rejects a TipoDocIR code outside the official catalog', function () {
    $tipoDocIRRule = ReceiptTypeRules::toValidationRules(ReceiptType::PurchaseInvoice)['InformacionReferencia.*.TipoDocIR'];

    $validator = Validator::make([
        'InformacionReferencia' => [
            ['TipoDocIR' => '19'],
        ],
    ], [
        'InformacionReferencia.*.TipoDocIR' => $tipoDocIRRule,
    ]);

    expect($validator->fails())->toBeTrue();
});
