# Invoicing Service — Arquitectura

> Arquitectura tecnica del microservicio de facturacion electronica.

---

## Vista general

```
┌─────────────────────────────────────────────────────────────────┐
│                        API Gateway                              │
│  SSL termination · Rate limiting · API key validation           │
└──────────────────────────┬──────────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────────┐
│                    Invoicing Service                             │
│                                                                  │
│  ┌────────────────┐  ┌────────────────┐  ┌───────────────────┐  │
│  │ Tenant API     │  │ Invoicing API  │  │ Webhook Handler   │  │
│  │                │  │                │  │                    │  │
│  │ POST /register │  │ POST /receipts │  │ POST /webhook     │  │
│  │ POST /certs    │  │ GET  /receipts │  │ (desde Hacienda)  │  │
│  │ POST /creds    │  │ GET  /status   │  │                    │  │
│  │ GET  /account  │  │ POST /reception│  │ Webhook Dispatcher │  │
│  │ PUT  /settings │  │                │  │ (hacia clientes)   │  │
│  └────────┬───────┘  └───────┬────────┘  └────────┬──────────┘  │
│           │                  │                     │             │
│  ┌────────▼──────────────────▼─────────────────────▼──────────┐  │
│  │                   Tenant Context Layer                      │  │
│  │  Resuelve tenant desde API key → inyecta config del tenant  │  │
│  └────────────────────────────┬───────────────────────────────┘  │
│                               │                                  │
│  ┌────────────────────────────▼───────────────────────────────┐  │
│  │                     Core Engine                             │  │
│  │  (reutilizado del paquete laravel-paquete-facturacion)             │  │
│  │                                                             │  │
│  │  InvoicingService → ReceiptBuilderService                   │  │
│  │  CalculationValidatorService (5 validators)                 │  │
│  │  XmlPipelineService → XmlGeneratorService                   │  │
│  │                     → XmlSignerService                      │  │
│  │                     → HaciendaProvider                      │  │
│  │  KeyGeneratorService · PayloadTransformerService             │  │
│  │  HaciendaIdpService · CertificateLoaderService              │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                  │
└────────┬──────────────┬───────────────┬─────────────────────────┘
         │              │               │
    ┌────▼────┐   ┌─────▼─────┐   ┌────▼─────┐
    │ MySQL   │   │ Cert      │   │ Queue    │
    │         │   │ Storage   │   │ (Redis)  │
    └─────────┘   └───────────┘   └──────────┘
```

---

## Capas del servicio

### 1. API Gateway

Capa externa que maneja concerns transversales:

- **SSL termination** — todo HTTPS
- **Rate limiting** — por API key / plan del tenant
- **API key validation** — rechaza requests sin key valida antes de llegar al servicio
- **Request logging** — audit trail

Puede ser nginx, Traefik, Kong, o un middleware Laravel si se empieza simple.

### 2. Tenant API

Gestion del ciclo de vida del tenant (la empresa que factura):

| Endpoint | Proposito |
|---|---|
| `POST /register` | Crear cuenta + tenant, retorna API key |
| `POST /certificates` | Subir certificado .p12 + PIN |
| `POST /credentials` | Configurar credenciales IDP de Hacienda |
| `GET /account` | Ver configuracion actual del tenant |
| `PUT /settings` | Actualizar emisor, sucursales, callback URL, ambiente |
| `POST /api-keys` | Generar API keys adicionales |
| `DELETE /api-keys/{id}` | Revocar API key |

### 3. Invoicing API

Emision y consulta de comprobantes — **misma API que el paquete actual**, pero con contexto de tenant:

| Endpoint | Proposito |
|---|---|
| `POST /receipts` | Crear, firmar y enviar comprobante |
| `GET /receipts` | Listar comprobantes del tenant |
| `GET /receipts/{id}` | Ver comprobante por ID |
| `GET /receipts/key/{clave}` | Ver comprobante por clave |
| `GET /receipts/key/{clave}/status` | Consultar estado en Hacienda |
| `POST /reception` | Recibir documento y encolar respuesta |

### 4. Webhook Handler

Dos direcciones:

- **Entrante (Hacienda → Servicio):** Recibe respuestas asincronas de Hacienda. Reutiliza `WebhookService` + `WebhookVerifierService` del paquete.
- **Saliente (Servicio → Cliente):** Notifica al cliente cuando su factura fue aceptada/rechazada. El cliente configura su `callback_url` en settings.

### 5. Tenant Context Layer

Middleware que intercepta toda request autenticada:

```
Request con X-Api-Key
  → Buscar API key en DB
  → Cargar tenant con su config (emisor, ambiente, certificado, credenciales)
  → Inyectar TenantContext en el container
  → Los servicios del core leen de TenantContext en vez de config()
```

Este es **el cambio arquitectonico principal** respecto al paquete actual.

### 6. Core Engine

El motor de facturacion — reutilizado del paquete `laravel-paquete-facturacion`. La unica diferencia es de donde lee la configuracion:

| Servicio | Paquete (hoy) | Microservicio |
|---|---|---|
| `InvoicingService` | `config('invoicing.emisor')` | `$tenantContext->emisor()` |
| `XmlSignerService` | `config('hacienda.certificado.path')` | `$tenantContext->certificate()` |
| `HaciendaIdpService` | `config('hacienda.idp.username')` | `$tenantContext->idpCredentials()` |
| `ReceiptBuilderService` | `config('invoicing.defaults')` | `$tenantContext->defaults()` |
| `HaciendaProvider` | `config('hacienda.endpoints')` | Endpoints por ambiente del tenant |

---

## Multi-tenancy

### Modelo de datos del tenant

```
┌─────────────────────────────────────────────────┐
│ tenants                                          │
├─────────────────────────────────────────────────┤
│ id (uuid)                                        │
│ name (string)                — nombre empresa    │
│ email (string)               — contacto          │
│ status (enum)                — active/suspended   │
│ ambiente (enum)              — sandbox/production │
│ callback_url (string, null)  — webhook saliente  │
│ created_at, updated_at                           │
└─────────────────────────────────────────────────┘
         │
         ├──── tenant_issuers (emisores por tenant)
         │     ├── id, tenant_id
         │     ├── nombre, cedula, tipo_identificacion
         │     ├── nombre_comercial, telefono, email
         │     ├── provincia, canton, distrito, otras_senas
         │     ├── codigo_actividad (puede tener varias)
         │     ├── proveedor_sistemas
         │     └── is_default (boolean)
         │
         ├──── tenant_certificates
         │     ├── id, tenant_id
         │     ├── certificate_data (binary, encrypted)
         │     ├── pin_encrypted (string, encrypted)
         │     ├── expires_at (date)
         │     ├── fingerprint (string, unique)
         │     └── is_active (boolean)
         │
         ├──── tenant_credentials
         │     ├── id, tenant_id
         │     ├── idp_username_encrypted
         │     ├── idp_password_encrypted
         │     ├── ambiente (sandbox/production)
         │     └── is_active (boolean)
         │
         ├──── api_keys
         │     ├── id, tenant_id
         │     ├── key_hash (string, unique, indexed)
         │     ├── prefix (string)  — "tk_live_" / "tk_test_"
         │     ├── name (string)    — descripcion
         │     ├── last_used_at
         │     └── revoked_at (nullable)
         │
         ├──── invoicing_cr_receipts
         │     └── + tenant_id (FK) — misma tabla del paquete + tenant
         │
         ├──── invoicing_cr_receipt_payloads
         │     └── (sin cambios, via receipt_id)
         │
         ├──── invoicing_cr_receipt_consecutives
         │     └── + tenant_id — unique(tenant_id, receipt_type, establishment, terminal)
         │
         ├──── invoicing_cr_hacienda_responses
         │     └── (sin cambios, via receipt_id)
         │
         └──── invoicing_cr_sent_receipts
               └── + tenant_id
```

### Aislamiento de datos

- Todas las queries incluyen `WHERE tenant_id = ?`
- Los consecutivos son unicos por `tenant_id + receipt_type + establishment + terminal`
- Los certificados y credenciales son encriptados at-rest con una key por tenant
- Los API keys solo dan acceso a datos de su tenant

### TenantContext (DTO inyectado por middleware)

```php
class TenantContext
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $ambiente,       // sandbox | production
        public readonly ?string $callbackUrl,
    ) {}

    public function emisor(): array;            // datos del emisor activo
    public function certificate(): CertificateData;  // .p12 desencriptado + pin
    public function idpCredentials(): IdpCredentials; // user/pass desencriptados
    public function defaults(): array;          // sucursal, terminal defaults
}
```

---

## Gestion de certificados

Los certificados `.p12` son el recurso mas sensible del servicio.

### Almacenamiento

```
Cliente sube .p12 + PIN via API
  → Validar formato PKCS#12 (CertificateLoaderService existente)
  → Validar que no esta expirado
  → Extraer fingerprint + fecha expiracion
  → Encriptar .p12 con AES-256-GCM (key derivada del tenant)
  → Encriptar PIN separadamente
  → Guardar en tabla tenant_certificates
  → Retornar fingerprint + expires_at (nunca el cert)
```

### Uso en runtime

```
Request de facturacion llega
  → TenantContext carga certificado activo
  → Desencripta .p12 y PIN en memoria
  → CertificateLoaderService::fromString($decryptedP12, $pin)
  → XmlSignerService firma el XML
  → Certificado desencriptado nunca se persiste ni se loggea
```

### Rotacion

- El cliente puede subir un nuevo .p12 antes de que expire el anterior
- Se marca como `is_active` y el anterior queda como historico
- Alerta cuando faltan 30 dias para expiracion (CERTIFICATE_WARNING_DAYS)

---

## Gestion de credenciales IDP

```
Cliente configura credenciales via API
  → Validar formato (no vacio)
  → Test de autenticacion contra Hacienda IDP (dry-run)
  → Si OK: encriptar y guardar
  → Si falla: retornar error sin guardar
```

- Credenciales separadas por ambiente (sandbox/production)
- Cache del token OAuth2 por tenant: `invoicing_token_{tenant_id}_{ambiente}`
- El tenant puede tener credenciales sandbox activas mientras prueba, y despues agregar produccion

---

## Webhooks salientes (Servicio → Cliente)

Cuando Hacienda responde (aceptada/rechazada), el servicio notifica al cliente:

```
Hacienda → POST /webhook (nuestro servicio)
  → WebhookService procesa respuesta
  → Actualiza Receipt (accepted/rejected)
  → Si tenant tiene callback_url:
      POST {callback_url}
      Content-Type: application/json
      X-Signature: HMAC-SHA256 del body con secret del tenant
      {
        "event": "receipt.accepted",
        "receipt_id": "uuid",
        "clave": "50614...",
        "hacienda_status": "accepted",
        "timestamp": "2026-04-05T10:30:00-06:00"
      }
```

### Garantias de entrega

- Retry con backoff exponencial (3 intentos: 30s, 2min, 10min)
- Si los 3 fallan, marcar como `delivery_failed`
- El cliente puede consultar el estado via `GET /receipts/key/{clave}` como fallback
- Log de todos los intentos de delivery

---

## Queue y workers

### Procesamiento asincrono

El servicio siempre trabaja en modo async internamente:

```
POST /receipts
  → Validar payload (sincrono, respuesta inmediata de errores)
  → Persistir Receipt + Payload
  → Encolar job de envio
  → Retornar 202 Accepted con receipt_id

Worker procesa:
  → Cargar contexto del tenant
  → Generar XML → Firmar → Enviar a Hacienda
  → Si falla: retry con backoff (3 intentos)
```

Esto permite:

- Respuesta rapida al cliente (~50ms en vez de ~2-5s)
- Retry automatico si Hacienda esta lento o caido
- Control de concurrencia por tenant (evitar flood a Hacienda)

### Colas por prioridad

| Cola | Proposito |
|---|---|
| `receipts` | Envio de comprobantes a Hacienda |
| `webhooks-in` | Procesamiento de respuestas de Hacienda |
| `webhooks-out` | Notificaciones a clientes |
| `certificates` | Validacion de certificados subidos |

---

## Monitoreo y observabilidad

### Health checks

| Check | Que valida |
|---|---|
| `/health` | Servicio activo |
| `/health/db` | Conexion a base de datos |
| `/health/queue` | Workers activos |
| `/health/hacienda` | Conectividad con API de Hacienda |

### Metricas clave

- Facturas emitidas por tenant / hora
- Tiempo de respuesta de Hacienda
- Tasa de aceptacion / rechazo
- Webhook delivery success rate
- Certificados proximos a expirar
- Cola de envio (depth + latency)

### Alertas

- Hacienda no responde en > 5 minutos
- Tasa de rechazo > 10% en una hora
- Cola de envio > 100 jobs
- Certificado expira en < 30 dias
- Webhook delivery falla 3 veces seguidas

---

## Seguridad

### Principios

1. **Encriptacion at-rest** — Certificados y credenciales encriptados en DB (AES-256-GCM)
2. **Encriptacion in-transit** — Todo HTTPS, incluyendo comunicacion con Hacienda
3. **Aislamiento de tenants** — Queries siempre filtradas por tenant_id, nunca cross-tenant
4. **API keys hasheadas** — Solo se almacena el hash, el plaintext se muestra una vez al crear
5. **No logging de secrets** — Certificados, PINs, y credenciales nunca en logs
6. **Webhook verification** — HMAC-SHA256 en webhooks salientes, clave + firma en entrantes

### API Key formato

```
tk_live_a1b2c3d4e5f6...   (produccion)
tk_test_a1b2c3d4e5f6...   (sandbox)
```

- Prefijo indica ambiente
- 32 bytes random, base62 encoded
- Se almacena solo SHA-256 del key
- Se muestra completo solo al momento de creacion