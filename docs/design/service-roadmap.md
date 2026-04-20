# Invoicing Service — Roadmap de Implementacion

> Plan de fases para construir el servicio de facturacion electronica.

---

## Resumen de fases

| Fase | Nombre | Objetivo | Resultado |
|---|---|---|---|
| **0** | Extraer core | Separar el engine de facturacion como libreria reutilizable | Libreria PHP independiente de Laravel |
| **1** | Microservicio MVP | Servicio funcional multi-tenant con API | helixERP (y cualquier app) puede facturar via API |
| **2** | Produccion | Hardening, monitoreo, seguridad | Servicio listo para clientes reales |
| **3** | Portal y billing | Dashboard web + cobro | Clientes externos pueden registrarse y pagar |

---

## Fase 0 — Extraer core

**Objetivo:** Separar el motor de facturacion (XML, firma, validacion, Hacienda) del framework Laravel para que pueda vivir en el microservicio sin depender del paquete original.

### Tareas

- [ ] Crear proyecto Laravel standalone para el microservicio
- [ ] Copiar servicios core del paquete al microservicio:
  - `XmlGeneratorService` + builders
  - `XmlSignerService`
  - `CertificateLoaderService`
  - `HaciendaIdpService` + `TokenData`
  - `HaciendaProvider` + `ProviderResponse`
  - `KeyGeneratorService`
  - `PayloadTransformerService`
  - `CalculationValidatorService` + 5 validators
  - `WebhookService` + `WebhookVerifierService`
  - `XmlPipelineService`
  - `ReceiptBuilderService`
  - `InvoicingService`
  - Enums, Constants, Exceptions, Rules
- [ ] Refactorizar servicios para recibir config via inyeccion (no `config()`)
- [ ] Crear interfaz `TenantConfigProvider` que los servicios usen en vez de config
- [ ] Verificar que los 243 tests pasan en el nuevo contexto

### Criterio de exito

El core genera XML, firma, y envia a Hacienda sandbox sin depender de config estatica.

---

## Fase 1 — Microservicio MVP

**Objetivo:** Servicio funcional donde un cliente se registra, sube su certificado, y emite facturas via API.

### 1.1 Modelo de datos multi-tenant

- [ ] Migracion: tabla `tenants`
- [ ] Migracion: tabla `tenant_issuers` (emisores por tenant)
- [ ] Migracion: tabla `tenant_certificates` (certificados encriptados)
- [ ] Migracion: tabla `tenant_credentials` (credenciales IDP encriptadas)
- [ ] Migracion: tabla `api_keys` (API keys hasheadas)
- [ ] Agregar `tenant_id` a `invoicing_cr_receipts`
- [ ] Agregar `tenant_id` a `invoicing_cr_receipt_consecutives` (unique compuesto)
- [ ] Agregar `tenant_id` a `invoicing_cr_sent_receipts`
- [ ] Modelos Eloquent: `Tenant`, `TenantIssuer`, `TenantCertificate`, `TenantCredential`, `ApiKey`

### 1.2 Autenticacion y tenant context

- [ ] Middleware `ResolveApiKey`: busca API key en DB → carga tenant
- [ ] `TenantContext` DTO con emisor, certificado, credenciales, defaults
- [ ] Implementar `TenantConfigProvider` que lee de `TenantContext`
- [ ] Binding en service container: `TenantContext` como scoped singleton por request
- [ ] Scope global en modelos: `Receipt::where('tenant_id', $context->tenantId)`

### 1.3 API de tenant management

- [ ] `POST /register` — crear cuenta + primer API key
- [ ] `GET /account` — ver configuracion
- [ ] `PUT /settings` — actualizar emisor, callback_url, defaults
- [ ] `POST /certificates` — subir .p12 (encriptar, validar, guardar)
- [ ] `GET /certificates` — listar certificados
- [ ] `POST /credentials` — configurar IDP (validar con Hacienda, encriptar, guardar)
- [ ] `POST /api-keys` — generar API key adicional
- [ ] `GET /api-keys` — listar keys (sin mostrar completas)
- [ ] `DELETE /api-keys/{id}` — revocar key

### 1.4 API de facturacion (adaptar del paquete)

- [ ] `POST /receipts` — crear y encolar envio (siempre async)
- [ ] `GET /receipts` — listar con filtros + paginacion
- [ ] `GET /receipts/{id}` — ver por ID
- [ ] `GET /receipts/key/{clave}` — ver por clave
- [ ] `GET /receipts/key/{clave}/status` — consultar Hacienda
- [ ] `POST /reception` — recibir documento
- [ ] `GET /consecutives` — ver estado de consecutivos
- [ ] `PUT /consecutives` — ajustar consecutivo (solo incrementar)

### 1.5 Webhook de Hacienda

- [ ] `POST /webhook` — recibir respuestas de Hacienda (adaptar del paquete)
- [ ] Resolver tenant desde la clave del comprobante (los primeros digitos contienen cedula)
- [ ] Actualizar receipt del tenant correcto

### 1.6 Webhooks salientes

- [ ] Job `DispatchWebhookJob` — notificar al callback_url del tenant
- [ ] Firma HMAC-SHA256 en header
- [ ] Retry con backoff (3 intentos)
- [ ] Log de intentos de delivery

### 1.7 Workers y colas

- [ ] Cola `receipts` — envio a Hacienda
- [ ] Cola `webhooks-in` — procesamiento de respuestas
- [ ] Cola `webhooks-out` — notificaciones a clientes
- [ ] Supervisor config para workers

### Criterio de exito

Un cliente externo puede:
1. Registrarse via API
2. Subir certificado y credenciales
3. Emitir una FE que Hacienda acepte (sandbox)
4. Recibir webhook de aceptacion en su callback_url

---

## Fase 2 — Produccion

**Objetivo:** Hardening para manejar clientes reales con datos reales.

### 2.1 Seguridad

- [ ] Encriptacion de certificados con AES-256-GCM (key por tenant derivada de master key)
- [ ] Audit log de acciones sensibles (subir cert, cambiar credenciales, revocar key)
- [ ] Rate limiting por API key (configurable por plan)
- [ ] Input sanitization y proteccion contra injection
- [ ] Nunca loggear certificados, PINs, credenciales, ni API keys completas
- [ ] CORS configurado para dominios permitidos

### 2.2 Monitoreo y observabilidad

- [ ] Health checks (`/health`, `/health/db`, `/health/queue`, `/health/hacienda`)
- [ ] Metricas: facturas/hora, latencia Hacienda, tasa aceptacion/rechazo
- [ ] Alertas: Hacienda caido, cola saturada, certificados por expirar
- [ ] Structured logging con tenant_id en cada entry
- [ ] Dashboard de metricas (Grafana o similar)

### 2.3 Resiliencia

- [ ] Circuit breaker para API de Hacienda
- [ ] Retry con exponential backoff en envio
- [ ] Dead letter queue para jobs que fallan despues de todos los reintentos
- [ ] Graceful degradation: si Hacienda esta caido, encolar y reintentar
- [ ] Backup de base de datos automatizado

### 2.4 Documentacion

- [ ] OpenAPI/Swagger spec completa
- [ ] Guia de integracion paso a paso
- [ ] Ejemplos por tipo de comprobante (FE, TE, NC, ND)
- [ ] Guia de errores comunes y como resolverlos
- [ ] Changelog versionado

### 2.5 Ambiente de produccion

- [ ] Activacion de ambiente produccion por tenant (requiere verificacion)
- [ ] Separacion de API keys por ambiente (tk_test_ vs tk_live_)
- [ ] Certificado expiring notification job (cron diario)
- [ ] Migracion de datos desde el paquete actual (si aplica)

### Criterio de exito

helixERP en produccion emitiendo facturas reales a traves del servicio. Monitoreo activo, alertas funcionando, zero downtime deployment.

---

## Fase 3 — Portal y billing

**Objetivo:** Abrir el servicio a clientes externos con portal de autoservicio y cobro.

### 3.1 Portal web

- [ ] Login/registro web (ademas de API)
- [ ] Dashboard: facturas emitidas, estado, graficos
- [ ] Subir certificado via UI
- [ ] Configurar credenciales via UI
- [ ] Gestionar API keys via UI
- [ ] Ver logs de webhook delivery
- [ ] Configurar callback URL y webhook secret

### 3.2 Billing

- [ ] Definir planes (free tier sandbox, por factura, plan mensual)
- [ ] Tracking de uso por tenant (facturas emitidas por mes)
- [ ] Integracion con pasarela de pago (Stripe o similar)
- [ ] Facturacion automatica al tenant (ironicamente, usando el mismo servicio)
- [ ] Suspension automatica por falta de pago (con grace period)

### 3.3 Onboarding guiado

- [ ] Wizard de setup: empresa → certificado → credenciales → primera factura
- [ ] Sandbox automatico para probar sin credenciales reales
- [ ] Validacion pre-produccion: checklist antes de activar ambiente real
- [ ] Soporte para migracion de consecutivos desde otro proveedor

### 3.4 Funcionalidades avanzadas

- [ ] Multi-sucursal: gestionar sucursales y terminales desde el portal
- [ ] Reportes: totales por periodo, impuestos, por tipo de comprobante
- [ ] Descarga de XML firmados y respuestas de Hacienda
- [ ] API de reenvio: reintentar envio de facturas fallidas
- [ ] Sub-cuentas: un tenant puede tener usuarios con roles (admin, operador, viewer)

### Criterio de exito

Un cliente externo puede registrarse, configurar su cuenta, emitir facturas, y pagar — todo sin intervencion manual nuestra.

---

## Dependencias entre fases

```
Fase 0 (core) ──→ Fase 1 (MVP) ──→ Fase 2 (produccion) ──→ Fase 3 (portal)
                       │
                       └──→ helixERP ya puede integrar aqui
```

- **Fase 0 → 1:** El core debe estar desacoplado antes de construir el servicio
- **Fase 1 → helixERP:** helixERP puede integrar apenas el MVP este funcional (sandbox)
- **Fase 1 → 2:** Produccion requiere MVP estable
- **Fase 2 → 3:** Portal y billing requieren servicio hardened

---

## Stack tecnologico propuesto

| Componente | Tecnologia | Razon |
|---|---|---|
| Framework | Laravel | Reutiliza codigo del paquete, equipo ya lo conoce |
| Base de datos | MySQL | Consistente con el paquete actual |
| Cache | Redis | Tokens OAuth2, rate limiting, colas |
| Queue | Laravel Horizon + Redis | Monitoreo de workers incluido |
| Encriptacion | Laravel Crypt (AES-256-CBC) | Built-in, auditable |
| API docs | Scramble o similar | Auto-genera OpenAPI desde rutas Laravel |
| Deploy | Docker + servicio de hosting | Portable, escalable |
| CI/CD | GitHub Actions | Tests automaticos, deploy automatico |
| Monitoreo | Laravel Telescope + metricas externas | Debug en dev, metricas en prod |

---

## Notas importantes

### El paquete laravel-paquete-facturacion sigue existiendo

El paquete no se depreca. Sigue siendo la opcion correcta para una app Laravel que factura para un solo emisor. El microservicio es para el caso multi-tenant / servicio publico.

### Migracion desde el paquete

Si un cliente ya usa el paquete y quiere migrar al servicio:

1. Registrarse en el servicio
2. Subir su certificado y credenciales
3. Exportar consecutivos actuales → `PUT /consecutives`
4. Cambiar las llamadas de `Facturacion::createAndSend()` a `POST /receipts`
5. Configurar webhook para recibir notificaciones

### Versionado de API

- URL base incluye version: `/v1/receipts`
- Breaking changes solo en versiones mayores (`/v2/...`)
- Versiones anteriores se mantienen por minimo 12 meses
- Deprecation headers cuando se anuncia nueva version