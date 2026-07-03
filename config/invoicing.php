<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ambiente de facturación
    |--------------------------------------------------------------------------
    |
    | Define el ambiente contra el cual se envían los comprobantes.
    | Valores permitidos: "sandbox" | "production"
    |
    */

    'ambiente' => env('INVOICING_CR_AMBIENTE', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Provider de facturación electrónica
    |--------------------------------------------------------------------------
    |
    | Nombre del provider que se usará para enviar comprobantes.
    | Default: "hacienda". Se pueden registrar providers adicionales
    | con ProviderFactoryService::register().
    |
    */

    'provider' => env('INVOICING_CR_PROVIDER', 'hacienda'),

    /*
    |--------------------------------------------------------------------------
    | Datos del emisor
    |--------------------------------------------------------------------------
    |
    | Información del emisor que se incluye en todos los comprobantes
    | electrónicos generados por el sistema.
    |
    */

    'emisor' => [
        'nombre'            => env('INVOICING_CR_EMISOR_NOMBRE'),
        'cedula'            => env('INVOICING_CR_EMISOR_CEDULA'),
        'tipo_cedula'       => env('INVOICING_CR_EMISOR_TIPO', '01'), // 01=física, 02=jurídica, 03=DIMEX, 04=NITE
        'nombre_comercial'  => env('INVOICING_CR_EMISOR_NOMBRE_COMERCIAL'),
        'telefono'          => env('INVOICING_CR_EMISOR_TELEFONO'),
        'email'             => env('INVOICING_CR_EMISOR_EMAIL'),
        'ubicacion' => [
            'provincia'   => env('INVOICING_CR_EMISOR_PROVINCIA'),
            'canton'      => env('INVOICING_CR_EMISOR_CANTON'),
            'distrito'    => env('INVOICING_CR_EMISOR_DISTRITO'),
            'otras_senas' => env('INVOICING_CR_EMISOR_OTRAS_SENAS'),
        ],
        'actividad_economica' => env('INVOICING_CR_EMISOR_ACTIVIDAD'), // una o varias separadas por coma: 620100,731001
    ],

    /*
    |--------------------------------------------------------------------------
    | Proveedor de sistemas (código de Hacienda)
    |--------------------------------------------------------------------------
    |
    | Código numérico asignado por Hacienda al proveedor de sistemas
    | de facturación electrónica. Requerido en el XML.
    |
    */

    'proveedor_sistemas' => env('INVOICING_CR_PROVEEDOR_SISTEMAS'),

    /*
    |--------------------------------------------------------------------------
    | Sucursal y terminal por defecto
    |--------------------------------------------------------------------------
    |
    | Se usan para generar la clave de 50 dígitos y el consecutivo de 20 dígitos.
    | El cliente puede sobreescribirlos por request enviando "establishment" y "terminal".
    | Si no se configuran ni se envían, ambos defaults a 1 (negocio de un solo punto de venta).
    |
    | - establishment: 1-999 (sucursal/local)
    | - terminal: 1-99999 (caja/punto de venta)
    |
    */

    'defaults' => [
        'establishment' => (int) env('INVOICING_CR_SUCURSAL', 1),
        'terminal'      => (int) env('INVOICING_CR_TERMINAL', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Modo de envío a Hacienda
    |--------------------------------------------------------------------------
    |
    | - sync: genera XML, firma, y envía a Hacienda dentro del request.
    |   El cliente espera 2-5 segundos. Retorna 201 con estado "sent".
    |
    | - async: persiste el comprobante y encola el envío en un Job.
    |   El cliente recibe 202 inmediato (<100ms). El Job reintenta
    |   3 veces (30s, 60s, 90s) si Hacienda no responde.
    |   Requiere un queue worker corriendo (php artisan queue:work).
    |
    */

    'send_mode' => env('INVOICING_CR_SEND_MODE', 'sync'),

    /*
    |--------------------------------------------------------------------------
    | Callback URL (Webhook de Hacienda)
    |--------------------------------------------------------------------------
    |
    | URL absoluta donde Hacienda enviará las respuestas asíncronas de los
    | comprobantes. Se arma con APP_URL en vez del host del request actual,
    | para que funcione igual accediendo por localhost o por túnel (ngrok).
    |
    */

    'callback_url' => rtrim(env('APP_URL', ''), '/').'/'.ltrim(
        env('INVOICING_CR_CALLBACK_URL', 'api/invoicing-cr/webhook'),
        '/'
    ),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware aplicado a las rutas del paquete. El proyecto host debe
    | configurar su propia autenticación aquí (ej: ['auth:sanctum']).
    |
    | - api: rutas de comprobantes, recepción, y consultas
    | - webhook: ruta del webhook de Hacienda (normalmente sin auth,
    |   ya que Hacienda no envía tokens — la seguridad la maneja
    |   WebhookVerifierService con validación de clave + firma XML)
    |
    */

    'middleware' => [
        'api'     => [],
        'webhook' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Registrar rutas del paquete
    |--------------------------------------------------------------------------
    |
    | Si es false, el paquete no registra rutas. Útil cuando la app host
    | define sus propios controllers y rutas, y solo usa InvoicingService
    | directamente (ej: Facturacion::createAndSend()).
    |
    */

    'register_routes' => env('INVOICING_CR_REGISTER_ROUTES', true),

    /*
    |--------------------------------------------------------------------------
    | Validación de código CABYS
    |--------------------------------------------------------------------------
    |
    | Si es true, valida que el CodigoCABYS de cada línea exista en la
    | tabla local invoicing_cr_cabys. Requiere haber ejecutado
    | php artisan invoicing:sync-cabys previamente.
    |
    | Si es false (default), solo valida formato (string de 13 caracteres).
    |
    */

    'validate_cabys' => env('INVOICING_CR_VALIDATE_CABYS', false),

    /*
    |--------------------------------------------------------------------------
    | Empresa activa (multi-tenant)
    |--------------------------------------------------------------------------
    |
    | ID de la empresa activa en contextos multi-tenant. Cuando está seteado,
    | el paquete lo incluye en las queries de consecutivos para aislar las
    | secuencias de numeración por empresa.
    |
    | No configurar en .env — la app host lo sobreescribe en runtime mediante
    | InvoicingContext::configureFor($company) antes de cada operación.
    |
    */

    'active_company_id' => null,

];