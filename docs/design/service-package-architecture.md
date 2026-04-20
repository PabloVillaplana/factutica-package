# Arquitectura de paquetes — Estrategia multi-paquete

> De un paquete single-tenant a una familia de paquetes que sirve desde una app simple hasta un servicio tipo Cyberfuel.

---

## La idea

En vez de saltar directo a un microservicio, construir una familia de paquetes:

```
factutica/laravel-paquete-facturacion           ← core single-tenant (ya existe)
factutica/laravel-paquete-facturacion-multi     ← capa multi-tenant (nuevo)
```

El paquete multi-tenant **extiende** el core. Reutiliza todo (XML, firma, validacion, Hacienda) y agrega tenancy, API keys, y gestion de certificados por tenant.

---

## Como se usan

### Caso 1: Una app, un emisor (hoy)

```bash
composer require factutica/laravel-paquete-facturacion
```

```php
// Configura emisor en .env, sube .p12, listo
Facturacion::createAndSend('FE', $payload);
```

Mismo paquete que ya existe. Sin cambios.

### Caso 2: helixERP — una app, multiples emisores

```bash
composer require factutica/laravel-paquete-facturacion-multi
```

```php
// Cada cliente de helixERP es un tenant
$tenant = Tenant::find($clienteId);

Facturacion::for($tenant)->createAndSend('FE', $payload);
```

helixERP instala el paquete multi-tenant y factura para cualquiera de sus clientes. Sin microservicio, sin HTTP hop, sin infra extra.

### Caso 3: Servicio tipo Cyberfuel

```
invoicing-service/           ← app Laravel minima
├── composer.json
│   └── require: factutica/laravel-paquete-facturacion-multi
├── routes/api.php           ← expone la API publica
├── app/Http/Middleware/
│   └── ResolveApiKey.php    ← resuelve tenant desde API key
└── (nada mas)
```

El microservicio es simplemente una app Laravel vacia con el paquete multi-tenant instalado. Toda la logica vive en el paquete. La app solo expone endpoints y resuelve el tenant desde la API key.

---

## Diagrama de dependencias

```
┌─────────────────────────────────────────────────────────┐
│           factutica/laravel-paquete-facturacion                │
│                    (core)                                │
│                                                          │
│  XmlGeneratorService    CalculationValidatorService      │
│  XmlSignerService       DetailLineValidator              │
│  CertificateLoader      TaxCalculationValidator          │
│  HaciendaIdpService     InvoiceSummaryValidator          │
│  HaciendaProvider       TaxBreakdownValidator            │
│  KeyGeneratorService    AssortmentValidator               │
│  PayloadTransformer     WebhookService                   │
│  XmlPipelineService     WebhookVerifierService           │
│  InvoicingService       ReceiptBuilderService            │
│                                                          │
│  Models: Receipt, ReceiptPayload, ReceiptConsecutive,    │
│          HaciendaResponse, SentReceipt                   │
│                                                          │
│  Enums, Constants, Exceptions, Rules, Events, Jobs       │
└──────────────────────────┬──────────────────────────────┘
                           │ depends on
                           │
┌──────────────────────────▼──────────────────────────────┐
│        factutica/laravel-paquete-facturacion-multi             │
│                 (multi-tenant)                           │
│                                                          │
│  TenantContext (DTO inyectado por request)               │
│  TenantConfigResolver (interface)                        │
│  DatabaseTenantConfigResolver (implementacion)           │
│                                                          │
│  Models: Tenant, TenantIssuer, TenantCertificate,       │
│          TenantCredential, ApiKey                        │
│                                                          │
│  Middleware: ResolveTenantFromApiKey                     │
│  Overrides: MultiTenantServiceProvider                   │
│                                                          │
│  Migrations: tablas de tenant                            │
│  Commands: tenant:create, tenant:certificate, etc.       │
└─────────────────────────────────────────────────────────┘
                           │
                           │ puede ser usado por
                           │
          ┌────────────────┼────────────────┐
          │                │                │
     ┌────▼────┐    ┌─────▼─────┐   ┌──────▼──────┐
     │ helixERP│    │ Servicio  │   │ Cualquier   │
     │ (directo│    │ Cyberfuel │   │ app Laravel │
     │  in-app)│    │ (HTTP API)│   │             │
     └─────────┘    └───────────┘   └─────────────┘
```

---

## Que cambia en el core para soportar esto

### El problema actual

El core lee config con `config()` directo en 6 servicios:

| Servicio | Que lee de config | Cantidad |
|---|---|---|
| `ReceiptBuilderService` | Emisor completo (nombre, cedula, ubicacion, telefono, actividad, proveedor_sistemas), defaults (sucursal, terminal) | 13 calls |
| `HaciendaIdpService` | Credenciales IDP (username, password), ambiente, endpoints, cache key | 5 calls |
| `CertificateLoaderService` | Ruta certificado .p12, PIN | 2 calls |
| `HaciendaProvider` | Ambiente, endpoint de recepcion | 2 calls |
| `XmlPipelineService` | Callback URL | 1 call |
| `InvoicingService` | Send mode (sync/async) | 1 call |

**Total: ~24 llamadas a `config()` que son tenant-specific.**

### La solucion: TenantContext

Crear un DTO que los servicios reciban en vez de leer config:

```php
// Nuevo en el core
interface TenantConfigResolver
{
    public function resolve(): TenantContext;
}

class TenantContext
{
    public function __construct(
        public readonly string $ambiente,
        public readonly string $sendMode,
        public readonly string $callbackUrl,
        public readonly IssuerData $issuer,
        public readonly CertificateData $certificate,
        public readonly IdpCredentials $credentials,
        public readonly DefaultsData $defaults,
        public readonly EndpointsData $endpoints,
    ) {}
}
```

### Dos implementaciones del resolver

**En el paquete core (single-tenant):**

```php
class ConfigTenantConfigResolver implements TenantConfigResolver
{
    public function resolve(): TenantContext
    {
        // Lee de config() como hoy — backwards compatible
        return new TenantContext(
            ambiente: config('invoicing-cr.invoicing.ambiente', 'sandbox'),
            sendMode: config('invoicing-cr.invoicing.send_mode', 'sync'),
            callbackUrl: config('invoicing-cr.invoicing.callback_url'),
            issuer: IssuerData::fromConfig(config('invoicing-cr.invoicing.emisor')),
            certificate: CertificateData::fromConfig(config('invoicing-cr.hacienda.certificado')),
            credentials: IdpCredentials::fromConfig(config('invoicing-cr.hacienda.idp')),
            defaults: DefaultsData::fromConfig(config('invoicing-cr.invoicing.defaults')),
            endpoints: EndpointsData::fromConfig(config('invoicing-cr.hacienda.endpoints')),
        );
    }
}
```

**En el paquete multi-tenant:**

```php
class DatabaseTenantConfigResolver implements TenantConfigResolver
{
    public function __construct(
        private TenantContextManager $manager,
    ) {}

    public function resolve(): TenantContext
    {
        $tenant = $this->manager->current();

        return new TenantContext(
            ambiente: $tenant->ambiente,
            sendMode: $tenant->send_mode,
            callbackUrl: $tenant->callback_url,
            issuer: IssuerData::fromTenant($tenant->activeIssuer),
            certificate: CertificateData::fromTenant($tenant->activeCertificate),
            credentials: IdpCredentials::fromTenant($tenant->activeCredential),
            defaults: DefaultsData::fromTenant($tenant),
            endpoints: EndpointsData::forAmbiente($tenant->ambiente),
        );
    }
}
```

### Cambio en los servicios

Antes (config directo):

```php
class ReceiptBuilderService
{
    public function build(ReceiptType $type, array $data): array
    {
        $emisorId = config('invoicing-cr.invoicing.emisor.cedula');
        // ...
    }
}
```

Despues (inyeccion):

```php
class ReceiptBuilderService
{
    public function __construct(
        private readonly KeyGeneratorService $keyGenerator,
        private readonly TenantConfigResolver $configResolver,
    ) {}

    public function build(ReceiptType $type, array $data): array
    {
        $context = $this->configResolver->resolve();
        $emisorId = $data['issuer_number'] ?? $context->issuer->cedula;
        // ...
    }
}
```

**El servicio no sabe si es single-tenant o multi-tenant.** Solo sabe que recibe un `TenantContext`.

---

## Cambios en el ServiceProvider del core

```php
// Antes (singleton con config global):
$this->app->singleton(CertificateLoaderService::class);
$this->app->singleton(HaciendaIdpService::class);

// Despues (scoped, resuelve por request):
$this->app->scoped(TenantConfigResolver::class, ConfigTenantConfigResolver::class);
$this->app->scoped(CertificateLoaderService::class);
$this->app->scoped(HaciendaIdpService::class);
```

`scoped` en vez de `singleton` — se resuelve una vez por request, no una vez por app. En single-tenant da lo mismo. En multi-tenant, cada request puede ser un tenant diferente.

El paquete multi-tenant **sobrescribe** el binding:

```php
// MultiTenantServiceProvider (en el paquete multi)
$this->app->scoped(TenantConfigResolver::class, DatabaseTenantConfigResolver::class);
```

---

## Estructura del paquete multi-tenant

```
laravel-paquete-facturacion-multi/
├── config/
│   └── invoicing-multi.php         ← config del paquete multi
├── database/
│   └── migrations/
│       ├── create_tenants_table.php
│       ├── create_tenant_issuers_table.php
│       ├── create_tenant_certificates_table.php
│       ├── create_tenant_credentials_table.php
│       ├── create_api_keys_table.php
│       └── add_tenant_id_to_invoicing_tables.php
├── src/
│   ├── Models/
│   │   ├── Tenant.php
│   │   ├── TenantIssuer.php
│   │   ├── TenantCertificate.php
│   │   ├── TenantCredential.php
│   │   └── ApiKey.php
│   ├── Resolvers/
│   │   └── DatabaseTenantConfigResolver.php
│   ├── TenantContextManager.php      ← set/get current tenant
│   ├── Http/
│   │   ├── Middleware/
│   │   │   └── ResolveTenantFromApiKey.php
│   │   └── Controllers/
│   │       ├── TenantController.php
│   │       ├── CertificateController.php
│   │       ├── CredentialController.php
│   │       └── ApiKeyController.php
│   ├── Services/
│   │   ├── TenantService.php           ← CRUD de tenants
│   │   ├── CertificateStoreService.php ← encriptar/guardar .p12
│   │   ├── CredentialStoreService.php  ← encriptar/guardar IDP creds
│   │   └── WebhookDispatcherService.php ← webhooks salientes
│   ├── Jobs/
│   │   └── DispatchWebhookJob.php
│   ├── Console/
│   │   ├── TenantCreateCommand.php
│   │   └── TenantCertificateCommand.php
│   └── MultiTenantServiceProvider.php
├── routes/
│   └── api.php                   ← rutas de tenant management
├── tests/
└── composer.json
```

### composer.json del paquete multi

```json
{
    "name": "factutica/laravel-paquete-facturacion-multi",
    "description": "Multi-tenant layer for laravel-paquete-facturacion",
    "type": "library",
    "license": "proprietary",
    "require": {
        "php": "^8.2",
        "factutica/laravel-paquete-facturacion": "^1.0",
        "illuminate/support": "^11.0|^12.0|^13.0"
    }
}
```

---

## Modelo de datos multi-tenant

```
tenants
├── id (uuid)
├── name
├── email
├── status (active/suspended)
├── ambiente (sandbox/production)
├── send_mode (sync/async)
├── callback_url (nullable)
├── webhook_secret
├── proveedor_sistemas
├── default_establishment
├── default_terminal
├── created_at, updated_at

tenant_issuers
├── id, tenant_id (FK)
├── nombre, cedula, tipo_identificacion
├── nombre_comercial, telefono, email
├── provincia, canton, distrito, otras_senas
├── codigo_actividad
├── is_default (boolean)

tenant_certificates
├── id, tenant_id (FK)
├── certificate_data (binary, encrypted)
├── pin_encrypted
├── fingerprint (unique)
├── expires_at
├── is_active (boolean)

tenant_credentials
├── id, tenant_id (FK)
├── idp_username_encrypted
├── idp_password_encrypted
├── ambiente (sandbox/production)
├── is_active (boolean)

api_keys
├── id, tenant_id (FK)
├── key_hash (unique, indexed)
├── prefix
├── name
├── last_used_at
├── revoked_at (nullable)
```

Las tablas existentes del core (`invoicing_cr_receipts`, `invoicing_cr_receipt_consecutives`, `invoicing_cr_sent_receipts`) reciben una columna `tenant_id` via migracion del paquete multi.

---

## Facade actualizada

### Single-tenant (sin cambios)

```php
Facturacion::createAndSend('FE', $payload);
```

### Multi-tenant

```php
// Opcion 1: Facade con tenant explicito
Facturacion::for($tenant)->createAndSend('FE', $payload);

// Opcion 2: Tenant ya resuelto por middleware (via API key)
// En controllers del servicio, el middleware ya seteo el tenant
Facturacion::createAndSend('FE', $payload);
// Internamente usa TenantContextManager::current()
```

### Implementacion de `for()`

```php
// En la Facade o en InvoicingService
public function for(Tenant $tenant): static
{
    app(TenantContextManager::class)->set($tenant);
    return $this;
}
```

---

## Uso en helixERP (in-process)

```php
// En un controller de helixERP
class InvoiceController extends Controller
{
    public function store(Request $request, Client $client)
    {
        // $client->tenant es la relacion al tenant de facturacion
        $receipt = Facturacion::for($client->tenant)
            ->createAndSend('FE', [
                'condicion_venta' => '01',
                'medio_pago' => ['01'],
                'receptor' => [...],
                'detalle_servicio' => [...],
                'resumen_factura' => [...],
                'external_reference' => "invoice-{$request->invoice_id}",
            ]);

        return response()->json($receipt);
    }
}
```

**Sin HTTP hop.** La factura se genera, firma, y envia en el mismo proceso.

---

## Uso en microservicio (HTTP API)

```php
// En el microservicio — routes/api.php
Route::middleware('resolve-tenant')->group(function () {
    Route::post('/receipts', [ReceiptController::class, 'store']);
    Route::get('/receipts', [ReceiptController::class, 'index']);
    // ... mismas rutas del core
});

// ResolveTenantFromApiKey middleware ya seteo el tenant
// Los controllers del core funcionan sin cambios
```

El microservicio es literalmente:

1. `composer require factutica/laravel-paquete-facturacion-multi`
2. Publicar migraciones
3. Agregar middleware de API key
4. Agregar rutas de tenant management
5. Deploy

---

## Plan de implementacion

### Paso 1: Refactorizar el core (no rompe nada)

Cambios en `factutica/laravel-paquete-facturacion`:

- [ ] Crear DTOs: `IssuerData`, `CertificateData`, `IdpCredentials`, `DefaultsData`, `EndpointsData`
- [ ] Crear `TenantContext` (agrupa todos los DTOs)
- [ ] Crear interface `TenantConfigResolver`
- [ ] Crear `ConfigTenantConfigResolver` (lee de config, backwards compatible)
- [ ] Refactorizar `ReceiptBuilderService`: inyectar resolver, eliminar 13 `config()` calls
- [ ] Refactorizar `HaciendaIdpService`: inyectar resolver, eliminar 5 `config()` calls
- [ ] Refactorizar `CertificateLoaderService`: inyectar resolver, eliminar 2 `config()` calls
- [ ] Refactorizar `HaciendaProvider`: inyectar resolver, eliminar 2 `config()` calls
- [ ] Refactorizar `XmlPipelineService`: inyectar resolver, eliminar 1 `config()` call
- [ ] Refactorizar `InvoicingService`: inyectar resolver, eliminar 1 `config()` call
- [ ] Cambiar singletons a scoped en `InvoicingServiceProvider`
- [ ] Actualizar cache key de IDP para incluir un identificador unico (no solo username)
- [ ] Verificar que los 243 tests siguen pasando

**Resultado:** El core funciona exactamente igual para usuarios single-tenant. Pero ahora acepta un resolver inyectable.

### Paso 2: Crear el paquete multi-tenant

Nuevo paquete `factutica/laravel-paquete-facturacion-multi`:

- [ ] Scaffold del paquete (composer.json, service provider, tests)
- [ ] Modelos: `Tenant`, `TenantIssuer`, `TenantCertificate`, `TenantCredential`, `ApiKey`
- [ ] Migraciones: 5 tablas nuevas + `tenant_id` en tablas existentes
- [ ] `DatabaseTenantConfigResolver` (lee de DB por tenant)
- [ ] `TenantContextManager` (set/get current tenant)
- [ ] `CertificateStoreService` (encriptar/guardar .p12 en DB)
- [ ] `CredentialStoreService` (encriptar/guardar credenciales IDP)
- [ ] Middleware `ResolveTenantFromApiKey`
- [ ] Facade `Facturacion::for($tenant)` override
- [ ] Controllers de tenant management (CRUD tenant, certificados, credenciales, API keys)
- [ ] Rutas de tenant management
- [ ] `WebhookDispatcherService` (webhooks salientes a clientes)
- [ ] Tests

### Paso 3: Microservicio (opcional)

Si se necesita servicio tipo Cyberfuel:

- [ ] Crear app Laravel vacia
- [ ] `composer require factutica/laravel-paquete-facturacion-multi`
- [ ] Publicar migraciones + configurar DB
- [ ] Agregar rutas publicas (register, receipts, webhooks)
- [ ] Configurar rate limiting, CORS, logging
- [ ] Deploy

---

## Backwards compatibility

| Escenario | Que pasa |
|---|---|
| Usuario actual del core (single-tenant) | **Cero cambios.** `ConfigTenantConfigResolver` lee de config como siempre |
| Usuario instala paquete multi | El `MultiTenantServiceProvider` reemplaza el resolver. Todo funciona por tenant |
| Tests existentes del core | Siguen pasando. `ConfigTenantConfigResolver` es el default |
| API del paquete (Facade, controllers) | Sin cambios en single-tenant. Multi agrega `for($tenant)` |

---

## Ventajas de este approach vs microservicio directo

| Aspecto | Solo microservicio | Paquetes + microservicio opcional |
|---|---|---|
| helixERP latencia | HTTP hop (~50ms+) | In-process (~1ms) |
| helixERP transaccionalidad | Eventual consistency | Misma transaccion DB |
| Consecutivos | Necesita lock distribuido o servicio dedicado | `lockForUpdate()` en la misma DB |
| Infra para helixERP | Servicio separado obligatorio | Cero infra extra |
| Reutilizacion del core | Copiar codigo o importar | `composer require` |
| Servicio tipo Cyberfuel | Si, unico camino | Si, instalando el paquete multi en una app |
| Complejidad | Alta desde el dia uno | Incremental |
| Time to market | Semanas/meses | Dias (para helixERP) |