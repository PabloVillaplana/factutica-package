<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Credenciales del IdP (Identity Provider) de Hacienda
    |--------------------------------------------------------------------------
    |
    | Credenciales para autenticarse con el servicio de tokens OAuth
    | del Ministerio de Hacienda de Costa Rica.
    |
    */

    'idp' => [
        'username' => env('INVOICING_CR_IDP_USERNAME'),
        'password' => env('INVOICING_CR_IDP_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Certificado de firma digital (.p12)
    |--------------------------------------------------------------------------
    |
    | Ruta y PIN del certificado PKCS#12 usado para firmar
    | los comprobantes electrónicos (XML).
    |
    */

    'certificado' => [
        'path' => env('INVOICING_CR_CERTIFICADO_PATH'),
        'pin'  => env('INVOICING_CR_CERTIFICADO_PIN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Endpoints de Hacienda por ambiente
    |--------------------------------------------------------------------------
    |
    | URLs oficiales del Ministerio de Hacienda para el IdP (tokens)
    | y el servicio de recepción de comprobantes electrónicos.
    |
    */

    'endpoints' => [
        'sandbox' => [
            'idp'       => 'https://idp.comprobanteselectronicos.go.cr/auth/realms/rut-stag/protocol/openid-connect/token',
            'recepcion' => 'https://api-sandbox.comprobanteselectronicos.go.cr/recepcion/v1/recepcion',
        ],
        'production' => [
            'idp'       => 'https://idp.comprobanteselectronicos.go.cr/auth/realms/rut/protocol/openid-connect/token',
            'recepcion' => 'https://api.comprobanteselectronicos.go.cr/recepcion/v1/recepcion',
        ],
    ],

];