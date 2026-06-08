# Arquitectura — FactuTica Laravel Package

Descripción interna del paquete: cómo está organizado, cómo fluye un comprobante, y cómo extenderlo.

---

## Contenido

- [Principios de diseño](#principios-de-diseño)
- [Estructura de directorios](#estructura-de-directorios)
- [Flujo principal](#flujo-principal)
- [Capa de validación](#capa-de-validación)
- [Generación de clave](#generación-de-clave)
- [Generación de XML](#generación-de-xml)
- [Firma XAdES-EPES](#firma-xades-epes)
- [Autenticación OAuth2](#autenticación-oauth2)
- [Provider de envío](#provider-de-envío)
- [Webhook](#webhook)
- [Sistema de consecutivos](#sistema-de-consecutivos)
- [Modelos y base de datos](#modelos-y-base-de-datos)
- [Jobs y modo async](#jobs-y-modo-async)
- [Eventos](#eventos)
- [Extensión del paquete](#extensión-del-paquete)

---

## Principios de diseño

1. **API en snake_case, internamente PascalCase** — el cliente envía snake_case (REST estándar); el paquete convierte a PascalCase para fidelidad 1:1 con el XSD de Hacienda.

2. **Validación antes de persistir** — los 3 layers de validación (estructura, tipo, matemática) se ejecutan antes de consumir un consecutivo o tocar la DB.

3. **Transaccional por diseño** — `createAndSend` envuelve todo en `DB::transaction`. Si cualquier paso falla, se revierte.

4. **Enums sobre strings** — nunca strings hardcodeados (`'FE'`, `'01'`, `'CRC'`). Siempre `ReceiptType::ElectronicInvoice`, etc.

5. **Excepciones propias** — jerarquía clara. `HaciendaException` para problemas con la API, `XmlSignerException` para firma, `CertificateException` para el .p12, `InvalidReceiptException` para datos inválidos.

6. **DTOs tipados** — el `ProviderInterface::send()` retorna `ProviderResponse` (DTO), no arrays. El `HaciendaIdpService` retorna `TokenData` (DTO con expiración).

---

## Estructura de directorios

```
src/
├── Console/
│   ├── SetConsecutiveCommand.php     ← invoicing:set-consecutive
│   └── CheckCertificateCommand.php   ← invoicing:check-certificate
├── Constants.php                     ← constantes globales del paquete
├── Contracts/
│   ├── InvoicingInterface.php        ← contrato del servicio principal
│   ├── ProviderInterface.php         ← contrato para providers de envío
│   └── Sendable.php                  ← interfaz compartida Receipt/SentReceipt
├── Enums/
│   ├── ReceiptType.php               ← FE, FEE, FEC, TE, ND, NC, REP
│   ├── IdentificationType.php        ← 01=Física, 02=Jurídica, 03=DIMEX, 04=NITE, 05=Extranjero, 06=NoContribuyente
│   ├── CurrencyType.php              ← CRC, USD, EUR
│   ├── ReceiptStatus.php             ← pending, sent, accepted, rejected, failed
│   ├── HaciendaStatus.php            ← pending, accepted, rejected
│   └── ReceptionStatus.php           ← pending, accepted, partially_accepted, rejected
├── Exceptions/
│   ├── HaciendaException.php
│   ├── XmlSignerException.php
│   ├── InvalidReceiptException.php
│   └── CertificateException.php
├── Facades/
│   └── Facturacion.php               ← facade alias 'invoicing'
├── Http/
│   ├── Controllers/
│   │   ├── ReceiptController.php
│   │   ├── ReceptionController.php
│   │   └── WebhookController.php
│   ├── Requests/
│   │   ├── StoreReceiptRequest.php   ← prepareForValidation() + validación
│   │   └── StoreReceptionRequest.php
│   └── Resources/
│       ├── ReceiptResource.php
│       └── ReceiptStatusResource.php
├── Jobs/
│   ├── SendReceiptToProviderJob.php      ← async FE/TE/NC/etc (ShouldBeUnique)
│   └── SendSentReceiptToProviderJob.php  ← async MensajeReceptor (ShouldBeUnique)
├── Models/
│   ├── Receipt.php
│   ├── ReceiptPayload.php
│   ├── ReceiptConsecutive.php
│   ├── HaciendaResponse.php
│   ├── SentReceipt.php
│   └── Cabys.php
├── Providers/
│   ├── ProviderFactoryService.php    ← factory extensible con register()
│   ├── ProviderResponse.php          ← DTO tipado de respuesta del provider
│   └── HaciendaProvider.php          ← provider oficial de Hacienda
├── Rules/
│   ├── DecimalDinero.php
│   ├── ServiceDetailRequired.php
│   └── ValidateIdentification.php
├── Services/
│   ├── Hacienda/
│   │   ├── CertificateLoaderService.php
│   │   ├── XmlSignerService.php
│   │   └── Idp/
│   │       ├── HaciendaIdpService.php
│   │       └── TokenData.php
│   ├── Validators/
│   │   ├── ReceiptTypeRules.php
│   │   ├── CalculationValidatorService.php
│   │   ├── DetailLineValidator.php
│   │   ├── TaxCalculationValidator.php
│   │   ├── InvoiceSummaryValidator.php
│   │   ├── TaxBreakdownValidator.php
│   │   └── AssortmentValidator.php
│   ├── Webhook/
│   │   ├── WebhookService.php
│   │   └── WebhookVerifierService.php
│   ├── XmlGenerator/
│   │   ├── XmlGeneratorService.php
│   │   └── MensajeReceptorXmlGeneratorService.php
│   ├── InvoicingService.php
│   ├── ReceiptBuilderService.php
│   ├── XmlPipelineService.php
│   ├── KeyGeneratorService.php
│   └── PayloadTransformerService.php
├── Traits/
│   └── SendsToProvider.php
└── InvoicingServiceProvider.php
```

---

## Flujo principal

```
POST /invoicing-cr/receipts
        │
        ▼
StoreReceiptRequest
  ├── prepareForValidation()
  │     └── PayloadTransformerService::transform()
  │           snake_case → PascalCase (mapeo explícito)
  └── rules() + after()
        ├── Validación estructural Laravel (campos, tipos, formatos)
        └── ReceiptTypeRules::apply() (required/prohibited por tipo)
        │
        ▼ [422 si falla]
        │
InvoicingService::createAndSend(type, data)
  │
  ├── ReceiptType::tryFrom(type)      ← [422 si tipo inválido]
  │
  ├── CalculationValidatorService::validate(data)
  │     ├── DetailLineValidator
  │     ├── TaxCalculationValidator
  │     ├── InvoiceSummaryValidator
  │     ├── TaxBreakdownValidator
  │     └── AssortmentValidator
  │           [422 si cálculos incorrectos]
  │
  └── DB::transaction()
        │
        ├── ReceiptBuilderService::build(type, data)
        │     ├── ReceiptConsecutive::next() — incrementa consecutivo atómicamente
        │     ├── KeyGeneratorService::generateUniqueKey() — clave 50 dígitos
        │     ├── KeyGeneratorService::generateConsecutiveKey() — consecutivo 20 dígitos
        │     ├── Inyección automática:
        │     │     Emisor (de config), FechaEmision (now()), ProveedorSistemas (de config)
        │     │     CodigoActividad (de config si no viene en request)
        │     │     TotalDesgloseImpuesto (auto-generado de impuestos de líneas)
        │     ├── Receipt::create() + ReceiptPayload::create()
        │     └── ReceiptCreated::dispatch(receipt)
        │
        ├── [send_mode = sync]
        │     XmlPipelineService::generateSignAndSend(receipt, type, data)
        │       ├── XmlGeneratorService::generate(type, data) → XML string
        │       ├── XmlSignerService::sign(xml) → signed XML string
        │       ├── HaciendaIdpService::getToken() → TokenData (con cache)
        │       ├── HaciendaProvider::send(payload) → ProviderResponse
        │       ├── Receipt::markAsSent(providerResponse)
        │       └── ReceiptSent::dispatch(receipt, response)
        │
        └── [send_mode = async]
              SendReceiptToProviderJob::dispatch(receipt)
              └── (mismo pipeline, ejecutado por queue worker)
```

---

## Capa de validación

### PayloadTransformerService

Convierte las claves del request de `snake_case` a `PascalCase` antes de que corra la validación. El mapeo es **explícito** (un `const KEY_MAP` de 100+ entradas) — no se usa `Str::studly()` genérico para evitar conversiones incorrectas en campos especiales como `CodigoCABYS` o `IVACobradoFabrica`.

Se llama en `StoreReceiptRequest::prepareForValidation()`, de modo que todas las reglas de Laravel trabajan ya con PascalCase.

### ReceiptTypeRules

Define las reglas condicionales por tipo. Estructura:

```php
// ReceiptTypeRules para FE:
[
    'required' => [
        'DetalleServicio',
        'CodigoActividadEmisor',
        'Receptor.Identificacion',
    ],
    'prohibited' => [],
    'extra' => [
        // Reglas de Laravel adicionales
        'Receptor.Identificacion.Tipo' => ['required', 'in:01,02,03,04'],
    ],
]
```

### CalculationValidatorService

Orquestador que llama los 5 validators en orden y acumula errores. Si hay errores, los lanza como `ValidationException` (Laravel) — retorna 422 con el mismo formato estándar de errores.

Tolerancia: `abs($esperado - $recibido) <= 0.01`

---

## Generación de clave

```
Estructura de la clave de 50 dígitos:

┌─────┬──────┬────────────┬────────────────────┬─┬────────┐
│ 506 │ DDMMYY │ ID_EMISOR  │   CONSECUTIVO_20   │S│  SEC8  │
│  3  │   6  │     12     │        20          │1│   8    │
└─────┴──────┴────────────┴────────────────────┴─┴────────┘
Total: 50 dígitos

Consecutivo de 20 dígitos:
┌─────┬─────────┬────┬──────────────┐
│ SUC │   TER   │ TP │  CONSECUTIVO │
│  3  │    5    │  2 │      10      │
└─────┴─────────┴────┴──────────────┘

SUC: Sucursal (001-999)
TER: Terminal (00001-99999)
TP:  Código tipo (01=FE, 02=ND, 03=NC, 04=TE, 07=REP, 08=FEC, 09=FEE)
```

**Formateo del ID del emisor (12 dígitos):**

| Tipo | Regla |
|---|---|
| Física (01) | Máx 9 dígitos → pad izquierda a 12 |
| Jurídica (02) | Máx 10 dígitos → pad izquierda a 12 |
| DIMEX (03) | 11 dígitos → `0` + número; 12 dígitos → sin pad |
| NITE (04) | Máx 10 dígitos → pad izquierda a 12 |

**Código de seguridad (8 dígitos):**
Se genera con SHA-256 de `uniqid(more_entropy: true) + microtime() + random_bytes(16)`, extrayendo solo dígitos.

---

## Generación de XML

`XmlGeneratorService` genera el XML v4.4 para todos los tipos de comprobante. Usa builders especializados:

```
XmlGeneratorService::generate(type, data)
  ├── Root element + namespace según tipo:
  │     FE  → FacturaElectronica
  │     TE  → TiqueteElectronico
  │     NC  → NotaCreditoElectronica
  │     ND  → NotaDebitoElectronica
  │     FEC → FacturaElectronicaCompra
  │     FEE → FacturaElectronicaExportacion
  │     REP → ComprobanteReciboElectronico
  │
  ├── EmisorReceptorBuilder   → <Emisor>, <Receptor>
  ├── DetalleServicioBuilder  → <DetalleServicio> con todas las líneas
  ├── ResumenFacturaBuilder   → <ResumenFactura> con todos los totales
  └── ComplementosBuilder     → <InformacionReferencia>, <OtrosCargos>
```

El XML resultante sigue el esquema del XSD oficial de Hacienda v4.4. Namespaces:
```
https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.4/facturaElectronica
```

`MensajeReceptorXmlGeneratorService` genera los mensajes de receptor (CA/CAP/CR) con el XSD `MensajeReceptor_V4.4`.

---

## Firma XAdES-EPES

`XmlSignerService` implementa firma digital conforme al perfil requerido por Hacienda:

```
Algoritmo:         RSA-SHA256
Canonicalización:  Exclusive C14N (xml-exc-c14n)
Tipo de firma:     XAdES-EPES (Enveloped Signature)

Referencias firmadas:
  1. Documento completo (con transforms: Enveloped Signature + Exc-C14N)
  2. Nodo ds:KeyInfo
  3. Nodo xades:SignedProperties

XAdES Object:
  QualifyingProperties
    SignedProperties
      SignedSignatureProperties
        SigningTime         ← timestamp actual
        SigningCertificate  ← hash SHA256 del certificado
        SignaturePolicyIdentifier
          SigPolicyId:      https://tribunet.hacienda.go.cr/docs/esquemas/2016/v4/
                            Resolucion_Comprobante_Electronico_vr4.1.pdf
          SigPolicyHash:    V8lVVNGDCPen6VELRD1Dc3vC9o4=  (SHA1 del PDF)
```

El `.p12` se carga con `CertificateLoaderService`, que valida la expiración del certificado y lanza `CertificateException` si está expirado o el PIN es incorrecto.

---

## Autenticación OAuth2

`HaciendaIdpService` maneja la autenticación OAuth2 contra el IdP de Hacienda:

```
Endpoints:
  Sandbox:    https://idp.comprobanteselectronicos.go.cr/auth/realms/rut-stag/...
  Producción: https://idp.comprobanteselectronicos.go.cr/auth/realms/rut/...

Grant types:
  password        ← primer token
  refresh_token   ← renovación automática

Cache:
  El token se guarda en Laravel Cache con TTL = expires_in - TOKEN_MARGIN_SECONDS
  TOKEN_MARGIN_SECONDS = 30 (por defecto)
  Al expirar la cache → intenta refresh_token → si falla → nuevo password grant
```

`TokenData` es un DTO inmutable:

```php
class TokenData {
    public readonly string $accessToken;
    public readonly string $refreshToken;
    public readonly Carbon $expiresAt;
    public function isExpired(): bool { ... }
}
```

---

## Provider de envío

`ProviderInterface` define el contrato:

```php
interface ProviderInterface
{
    public function send(array $payload): ProviderResponse;
    public function getStatus(string $uiKey): array;
}
```

`HaciendaProvider` es la implementación oficial. El payload que envía a Hacienda:

```json
{
    "clave": "50601021...",
    "fecha": "2025-01-15T10:00:00-06:00",
    "emisor": {
        "tipoIdentificacion": "02",
        "numeroIdentificacion": "3101234567"
    },
    "receptor": {
        "tipoIdentificacion": "02",
        "numeroIdentificacion": "3101234567"
    },
    "comprobanteXml": "PD94bWwgdmVyc2lvbj0i...",   ← XML firmado en base64
    "callbackUrl": "https://mi-app.cr/api/invoicing-cr/webhook"
}
```

`ProviderFactoryService` es el factory extensible:

```php
// Registrar provider personalizado
$factory->register('mi-broker', MyBrokerProvider::class);

// Crear el provider activo (según config)
$provider = $factory->make();   // instancia HaciendaProvider o mi-broker
```

`ProviderResponse` es el DTO de retorno:

```php
class ProviderResponse {
    public function __construct(
        public readonly string $clave,
        public readonly string $fecha,
        public readonly int $httpStatus,
        public readonly ?string $signedXml,
    ) {}
}
```

---

## Webhook

```
POST /invoicing-cr/webhook
        │
        ▼
WebhookController::store()
  └── WebhookService::process(data)
        │
        ├── Busca Receipt por clave   ← [404 si no existe]
        │
        ├── WebhookVerifierService::verify()
        │     ├── Capa 1: clave coincide con el Receipt en DB
        │     └── Capa 2: verifica firma XAdES del XML de respuesta
        │           [rechaza si firma inválida]
        │
        ├── HaciendaResponse::create() — persiste respuesta
        │
        ├── Receipt::update(status)   ← accepted/rejected
        │
        └── ReceiptAccepted o ReceiptRejected::dispatch(receipt)
```

**Idempotencia:** Si el mismo webhook llega dos veces, `HaciendaResponse` usa `firstOrCreate` por clave. El segundo procesamiento no genera duplicados ni lanza eventos extras.

---

## Sistema de consecutivos

`ReceiptConsecutive` tiene unique constraint en `(receipt_type, establishment, terminal)`. El incremento es atómico:

```php
// Dentro de DB::transaction():
$consecutive = ReceiptConsecutive::lockForUpdate()
    ->firstOrCreate([
        'receipt_type'  => $type->value,
        'establishment' => $establishment,
        'terminal'      => $terminal,
    ], ['consecutive' => 0]);

$consecutive->increment('consecutive');
```

El `lockForUpdate` garantiza que dos requests concurrentes en la misma sucursal+terminal+tipo no obtengan el mismo consecutivo.

---

## Modelos y base de datos

### invoicing_cr_receipts

Comprobante principal. Campos derivados del payload para búsquedas y reportes:

```
id, receipt_type, ui_key (clave 50 dígitos), external_reference,
establishment, terminal, consecutive_number, emission_date,
sent_to_hacienda_at, receipt_status, hacienda_status, signed_xml,
sell_condition, total_amount, tax_amount, total_discount, total_voucher,
currency, exchange_rate,
issuer_name, issuer_number, issuer_identification_type,
receiver_name, receiver_number, receiver_identification_type,
created_at, updated_at
```

### invoicing_cr_receipt_payloads

Payload JSON completo del comprobante (PascalCase, listo para regenerar el XML):

```
id, receipt_id (FK), payload (json), created_at, updated_at
```

### invoicing_cr_receipt_consecutives

Consecutivos por combinación única tipo+sucursal+terminal:

```
id, receipt_type, establishment, terminal, consecutive, created_at, updated_at
UNIQUE(receipt_type, establishment, terminal)
```

### invoicing_cr_hacienda_responses

Respuesta del webhook de Hacienda:

```
id, receipt_id (FK), hacienda_status, response_message (text),
responded_at, created_at, updated_at
```

### invoicing_cr_sent_receipts

Mensajes de recepción (CA/CAP/CR) enviados como receptor:

```
id, receipt_type, consecutive_number, emission_date,
receipt_status, hacienda_status, reception_status,
reception_code, reception_message, economic_activity_code,
tax_condition_code, tax_credited, total_expense, tax_amount, total_voucher,
issuer_name, issuer_number, issuer_identification_type,
receiver_name, receiver_number, receiver_identification_type,
created_at, updated_at
```

### invoicing_cr_cabys

Catálogo CABYS (~20,500 registros):

```
codigo (13 chars, PK), descripcion, impuesto (tarifa IVA), created_at, updated_at
```

---

## Jobs y modo async

### SendReceiptToProviderJob

- Implementa `ShouldBeUnique` — no se encola dos veces el mismo `receipt_id`
- 3 reintentos con backoff: 30s, 60s, 90s
- Usa `SendsToProvider` trait para lógica compartida de failed/logging
- En `failed()`: actualiza `receipt_status = 'failed'` y logea el error

### SendSentReceiptToProviderJob

- Igual para MensajeReceptor (SentReceipt)
- Genera XML del MensajeReceptor, lo firma, y lo envía a Hacienda

### SendsToProvider Trait

Lógica compartida entre ambos jobs:
- Logging de intentos y errores
- Marcado como `failed` cuando todos los reintentos se agotan

---

## Eventos

| Evento | Namespace | Payload |
|---|---|---|
| `ReceiptCreated` | `FactuTica\FactuticaCR\Events` | `Receipt $receipt` |
| `ReceiptSent` | `FactuTica\FactuticaCR\Events` | `Receipt $receipt, ?ProviderResponse $response` |
| `ReceiptAccepted` | `FactuTica\FactuticaCR\Events` | `Receipt $receipt, ?string $message` |
| `ReceiptRejected` | `FactuTica\FactuticaCR\Events` | `Receipt $receipt, ?string $message` |

Todos son clases estándar de Laravel — compatibles con `Event::listen()`, `EventServiceProvider::$listen`, y Listeners con `ShouldQueue`.

---

## Extensión del paquete

### Agregar un provider personalizado

1. Implementa `ProviderInterface`:

```php
use FactuTica\FactuticaCR\Contracts\ProviderInterface;
use FactuTica\FactuticaCR\Providers\ProviderResponse;

class MiBrokerProvider implements ProviderInterface
{
    public function send(array $payload): ProviderResponse { ... }
    public function getStatus(string $uiKey): array { ... }
}
```

2. Regístralo en tu `AppServiceProvider`:

```php
$this->app->make(ProviderFactoryService::class)
    ->register('mi-broker', MiBrokerProvider::class);
```

3. Configúralo:

```env
INVOICING_CR_PROVIDER=mi-broker
```

### Usar los servicios internamente

Todos los servicios están registrados como singletons:

```php
// Generar clave manualmente
$keyGen = app('FactuTica\FactuticaCR\Services\KeyGeneratorService');
$clave = $keyGen->generateUniqueKey(...);

// Firmar XML manualmente
$signer = app('invoicing.signer');
$signedXml = $signer->sign($xmlString);

// Obtener token OAuth2
$idp = app('invoicing.idp');
$token = $idp->getToken();

// Generar XML
$xmlGen = app('invoicing.xml');
$xml = $xmlGen->generate(ReceiptType::ElectronicInvoice, $data);
```

### Deshabilitar rutas y usar el servicio directamente

```env
INVOICING_CR_REGISTER_ROUTES=false
```

```php
use FactuTica\FactuticaCR\Facades\Facturacion;

$result = Facturacion::createAndSend('FE', $validatedData);
```

Útil cuando la app tiene su propia capa HTTP y solo necesita el pipeline de generación/firma/envío.
