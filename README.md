# Laravel Invoicing CR

Paquete de Laravel para Facturacion Electronica compatible con la API del Ministerio de Hacienda de Costa Rica (XSD v4.4).

## Requisitos

- PHP ^8.2
- Laravel ^11.0 | ^12.0 | ^13.0
- Certificado .p12 de firma digital (SINPE)
- Credenciales IdP de Hacienda (OAuth2)

## Instalacion

Este es un paquete privado. Agrega el repositorio VCS en el `composer.json` de tu proyecto:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:PabloVillaplana/laravel-paquete-facturacion.git"
        }
    ]
}
```

Luego instala con Composer:

```bash
composer require factutica/laravel-paquete-facturacion
```

## Configuracion

Publica los archivos de configuracion:

```bash
php artisan vendor:publish --tag=invoicing-config
```

Agrega las variables de entorno requeridas en tu `.env`:

```env
# Obligatorias — sin estas el paquete no funciona
INVOICING_CR_EMISOR_NOMBRE="Mi Empresa S.A."
INVOICING_CR_EMISOR_CEDULA=3101234567
INVOICING_CR_IDP_USERNAME=cpj-3101234567@stag.comprobanteselectronicos.go.cr
INVOICING_CR_IDP_PASSWORD=tu_password
INVOICING_CR_CERTIFICADO_PATH=app/private/certificado.p12
INVOICING_CR_CERTIFICADO_PIN=1234
```

Con solo esas 6 variables el paquete funciona en sandbox. Las demas tienen defaults razonables.

### Variables opcionales

Estas ya tienen default. Solo agregalas si necesitas cambiar el valor:

```env
# Ambiente (default: sandbox)
INVOICING_CR_AMBIENTE=production

# Emisor — datos adicionales (default: null, se omiten del XML si no estan)
INVOICING_CR_EMISOR_TIPO=02                    # default: 01 (fisica)
INVOICING_CR_EMISOR_NOMBRE_COMERCIAL="Mi Marca"
INVOICING_CR_EMISOR_TELEFONO=22345678
INVOICING_CR_EMISOR_EMAIL=facturacion@empresa.cr
INVOICING_CR_EMISOR_PROVINCIA=1
INVOICING_CR_EMISOR_CANTON=01
INVOICING_CR_EMISOR_DISTRITO=01
INVOICING_CR_EMISOR_OTRAS_SENAS="100m norte del parque"
INVOICING_CR_EMISOR_ACTIVIDAD=6201.0           # codigo con punto

# Hacienda
INVOICING_CR_PROVEEDOR_SISTEMAS=3102910527     # codigo asignado por Hacienda
INVOICING_CR_CALLBACK_URL=api/invoicing-cr/webhook  # default

# Operacion (defaults razonables para un negocio simple)
INVOICING_CR_SUCURSAL=1                        # default: 1
INVOICING_CR_TERMINAL=1                        # default: 1
INVOICING_CR_SEND_MODE=sync                    # default: sync (async requiere queue worker)
INVOICING_CR_REGISTER_ROUTES=true              # default: true (false si usas tus propios controllers)
```

Ejecuta las migraciones:

```bash
php artisan migrate
```

## Middleware

Las rutas del paquete no tienen middleware por defecto. Configura autenticacion en `config/invoicing.php`:

```php
'middleware' => [
    'api'     => ['api', 'auth:sanctum'],  // rutas de comprobantes y consultas
    'webhook' => [],                        // webhook de Hacienda (sin auth)
],
```

## Consecutivos

Si migrás desde otro sistema de facturacion, configurá el ultimo consecutivo usado para evitar rechazos por duplicado:

```bash
# Ver consecutivos actuales
php artisan invoicing:set-consecutive --list

# Configurar ultimo consecutivo usado (el siguiente sera 101)
php artisan invoicing:set-consecutive FE 100

# Para una sucursal y terminal especificos
php artisan invoicing:set-consecutive FE 50 --establishment=2 --terminal=3
```

## Uso basico

### Crear y enviar una Factura Electronica (FE)

```bash
POST /invoicing-cr/receipts
```

```json
{
    "receipt_type": "FE",
    "external_reference": "INV-0024",
    "condicion_venta": "01",
    "codigo_actividad_emisor": "6201.0",
    "receptor": {
        "nombre": "Cliente S.A.",
        "identificacion": { "tipo": "02", "numero": "3101234567" },
        "correo_electronico": ["cliente@example.com"]
    },
    "detalle_servicio": {
        "linea_detalle": [{
            "numero_linea": 1,
            "codigo_cabys": "6803000000000",
            "cantidad": "2",
            "unidad_medida": "Sp",
            "detalle": "Servicio de consultoria",
            "precio_unitario": "5000.00",
            "monto_total": "10000.00",
            "sub_total": "10000.00",
            "base_imponible": "10000.00",
            "impuesto": [{
                "codigo": "01",
                "codigo_tarifa_iva": "08",
                "tarifa": "13.00",
                "monto": "1300.00"
            }],
            "impuesto_neto": "1300.00",
            "monto_total_linea": "11300.00"
        }]
    },
    "resumen_factura": {
        "codigo_tipo_moneda": { "codigo_moneda": "CRC", "tipo_cambio": "1" },
        "total_serv_gravados": "10000.00",
        "total_gravado": "10000.00",
        "total_venta": "10000.00",
        "total_venta_neta": "10000.00",
        "total_impuesto": "1300.00",
        "total_comprobante": "11300.00",
        "medio_pago": [{ "tipo_medio_pago": "01", "total_medio_pago": "11300.00" }]
    }
}
```

El paquete genera automaticamente: Clave (50 digitos), NumeroConsecutivo, FechaEmision, Emisor (desde .env), TotalDesgloseImpuesto, firma XAdES-EPES, y envia a Hacienda.

### Modo sync vs async

```env
INVOICING_CR_SEND_MODE=sync   # default — espera respuesta de Hacienda (201)
INVOICING_CR_SEND_MODE=async  # POS/ERP — respuesta inmediata (202), Job envia en background
```

En modo async, requiere un queue worker: `php artisan queue:work`

### Crear un Tiquete Electronico (TE)

```json
{
    "receipt_type": "TE",
    "condicion_venta": "01",
    "codigo_actividad_emisor": "6201.0",
    "receptor": {
        "nombre": "Consumidor Final"
    },
    "detalle_servicio": { ... },
    "resumen_factura": { ... }
}
```

El TE no requiere identificacion del receptor.

### Campos opcionales

| Campo | Descripcion |
|---|---|
| `external_reference` | Referencia al sistema externo del cliente (max 100 chars) |
| `establishment` | Sucursal (1-999, default desde config) |
| `terminal` | Terminal/caja (1-99999, default desde config) |

### Multi-sucursal

Los consecutivos son independientes por sucursal + terminal + tipo:

```json
{
    "receipt_type": "FE",
    "establishment": 2,
    "terminal": 3,
    ...
}
```

## Endpoints

| Metodo | Ruta | Descripcion |
|---|---|---|
| `POST` | `/invoicing-cr/receipts` | Crear, firmar y enviar comprobante |
| `GET` | `/invoicing-cr/receipts` | Listar (filtro `?type=FE&status=sent&per_page=25`) |
| `GET` | `/invoicing-cr/receipts/{id}` | Ver por ID |
| `GET` | `/invoicing-cr/receipts/key/{uiKey}` | Ver por clave |
| `GET` | `/invoicing-cr/receipts/key/{uiKey}/status` | Consultar estado en Hacienda |
| `POST` | `/invoicing-cr/reception` | Recibir documento y encolar respuesta |
| `POST` | `/invoicing-cr/webhook` | Webhook de Hacienda |

## Tipos de comprobante

| Codigo | Descripcion | Sandbox |
|---|---|:---:|
| `FE` | Factura Electronica | Aceptada |
| `TE` | Tiquete Electronico | Aceptado |
| `NC` | Nota de Credito | Aceptada |
| `ND` | Nota de Debito | Aceptada |
| `FEC` | Factura Electronica de Compra | Pendiente |
| `FEE` | Factura Electronica de Exportacion | Pendiente |
| `REP` | Comprobante de Recibo Electronico de Pago | Implementado |

## Validacion

El paquete valida en 3 capas antes de enviar a Hacienda:

1. **Estructura** — campos requeridos, tipos, formatos (StoreReceiptRequest)
2. **Reglas por tipo** — campos required/prohibited segun FE, TE, NC, etc. (ReceiptTypeRules)
3. **Calculos matematicos** — MontoTotal, SubTotal, ImpuestoNeto, TotalComprobante, etc. con tolerancia de 0.01 (CalculationValidatorService)

Si algo no cuadra, retorna 422 con mensaje descriptivo antes de consumir consecutivo o tocar la DB.

### Atomicidad

`createAndSend` esta envuelto en una transaccion DB. Si la generacion del XML, la firma, o el envio a Hacienda fallan, todo se revierte (receipt, payload, consecutivo). Nunca quedan documentos huerfanos en estado pending por errores tecnicos.

## Events

El paquete dispara events en momentos clave del ciclo de vida. El host puede escucharlos para enviar emails, generar PDFs, notificar, etc.

| Event | Cuando | Payload |
|---|---|---|
| `ReceiptCreated` | Al persistir receipt + payload | `Receipt $receipt` |
| `ReceiptSent` | Al enviar a Hacienda (sync) | `Receipt $receipt, ?ProviderResponse $response` |
| `ReceiptAccepted` | Webhook recibe aceptado | `Receipt $receipt, ?string $message` |
| `ReceiptRejected` | Webhook recibe rechazado | `Receipt $receipt, ?string $message` |

```php
// app/Providers/EventServiceProvider.php
use FactuTica\FactuticaCR\Events\ReceiptAccepted;

protected $listen = [
    ReceiptAccepted::class => [
        SendInvoiceEmailListener::class,
    ],
];
```

## Catalogo CABYS

El paquete incluye el catalogo oficial CABYS (~20,500 codigos) en `data/cabys.json`. Importalo a la tabla local con:

```bash
php artisan invoicing:sync-cabys
php artisan invoicing:sync-cabys --fresh   # limpia antes de importar
```

Despues podes buscar codigos via el modelo `Cabys`:

```php
use FactuTica\FactuticaCR\Models\Cabys;

Cabys::find('8410101000100');                    // por codigo
Cabys::search('computadora')->limit(15)->get();  // por descripcion o prefijo
```

### Validacion opcional

Para validar que el `CodigoCABYS` de cada linea exista en la tabla local:

```env
INVOICING_CR_VALIDATE_CABYS=true
```

Requiere haber corrido `invoicing:sync-cabys` previamente. Por default es `false`.

## Descuentos por linea

Cada linea soporta hasta 5 descuentos con su monto y razon (CodigoDescuento):

```json
"descuento": [
    {
        "monto_descuento": "1000.00",
        "codigo_descuento": "07",
        "naturaleza_descuento": "Descuento general"
    },
    {
        "monto_descuento": "500.00",
        "codigo_descuento": "02"
    }
]
```

| Codigo | Razon |
|---|---|
| `01` | Oferta comercial |
| `02` | Volumen |
| `03` | Cliente especial |
| `04` | Muestra |
| `05` | Regalia |
| `06` | Bonificacion |
| `07` | Descuento general |
| `08` | Precio especial |
| `99` | Otro |

## Comandos Artisan

```bash
# Ver/configurar consecutivos
php artisan invoicing:set-consecutive --list
php artisan invoicing:set-consecutive FE 100
php artisan invoicing:set-consecutive FE 50 --establishment=2 --terminal=3

# Verificar certificado .p12
php artisan invoicing:check-certificate

# Importar catalogo CABYS
php artisan invoicing:sync-cabys
```

## Testing

```bash
# Tests unitarios + feature (242 tests, 506 assertions)
vendor/bin/pest --exclude-group=integration

# Tests de integracion contra sandbox real
vendor/bin/pest --group=integration
```

## Documentacion

La documentacion detallada esta en el directorio `docs/`:

- `request-factura-electronica.md` — ejemplos completos de request FE
- `flujos.md` — diagramas de todos los flujos end-to-end
- `validaciones-vs-xsd.md` — comparacion campo por campo con XSD de Hacienda
- `catalogo-errores.md` — 30+ puntos de falla documentados
- `auditoria-final-produccion.md` — evaluacion de calidad del paquete (8.2/10)
- `invoicing-cr.postman_collection.json` — coleccion Postman con todos los endpoints

## Licencia

Propietario - FactuTica. Uso comercial, no redistribuir.
