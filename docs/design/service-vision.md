# Invoicing Service — Vision y Analisis

> De paquete Laravel a servicio de facturacion electronica como Cyberfuel.

---

## Contexto

`laravel-paquete-facturacion` es un paquete Laravel que resuelve facturacion electronica para Costa Rica (Hacienda XSD v4.4). Hoy funciona para **una sola app con un solo emisor**.

El objetivo es convertirlo en un **servicio independiente de facturacion electronica** — un producto tipo Cyberfuel donde multiples clientes (empresas) se registran, suben su certificado digital, y emiten facturas a traves de una API.

---

## Por que microservicio

### El caso de helixERP

helixERP es un ERP multi-tenant. Cada cliente de helixERP es una empresa costarricense que necesita facturar. El paquete actual no soporta esto porque:

| Recurso | Paquete actual | Necesidad helixERP |
|---|---|---|
| Emisor (nombre, cedula, ubicacion) | Hardcoded en `.env` | Diferente por empresa |
| Certificado `.p12` + PIN | Un archivo en disco | Uno por empresa |
| Credenciales IDP | Un par en `.env` | Un par por empresa |
| Consecutivos | Globales por tipo+sucursal | Por empresa+tipo+sucursal |
| Actividad economica | Config unica | Varias por empresa |
| Ambiente (sandbox/prod) | Global | Por empresa (onboarding gradual) |

### Por que no solo hacer el paquete multi-tenant

Si helixERP fuera el unico consumidor, evolucionarlo a multi-tenant dentro de Laravel seria suficiente. Pero el objetivo es mayor: **ofrecer facturacion como servicio** a cualquier sistema, no solo a helixERP.

Esto significa:

- Los consumidores **no son codigo nuestro** — son terceros
- No van a instalar un paquete Laravel
- Necesitan una API publica con auth, docs, rate limiting
- Necesitamos control total sobre versionado, uptime, y billing

---

## Modelo de negocio

### Que es un servicio tipo Cyberfuel

Cyberfuel es un proveedor de facturacion electronica en Costa Rica. Las empresas se conectan a su API para emitir facturas sin lidiar directamente con Hacienda. El proveedor se encarga de:

- Recibir los datos de la factura
- Generar el XML segun XSD de Hacienda
- Firmar con el certificado digital del emisor
- Enviar a Hacienda via API
- Recibir la respuesta asincrona (aceptada/rechazada)
- Notificar al cliente

### Nuestro servicio haria exactamente eso

```
┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│  helixERP    │  │  App X       │  │  App Y       │
└──────┬───────┘  └──────┬───────┘  └──────┬───────┘
       │                 │                 │
       └────────────┬────┴─────────────────┘
                    │
                    ▼
         ┌──────────────────────┐
         │  Invoicing Service   │  ← nuestro servicio
         │  (API REST)          │
         └─────────┬────────────┘
                   ▼
         ┌──────────────────────┐
         │  Hacienda CR API     │
         └──────────────────────┘
```

Cada cliente se registra, sube su certificado `.p12`, configura credenciales, y emite facturas via API. No importa si es helixERP, un POS, un e-commerce, o un sistema contable.

---

## Que se reutiliza del paquete actual

El paquete ya tiene resuelto **todo lo dificil**:

### Core (se reutiliza 100%)

| Componente | Que hace |
|---|---|
| `XmlGeneratorService` | Genera XML XSD v4.4 para los 7 tipos de documento |
| `XmlSignerService` | Firma XAdES-EPES (RSA-SHA256, exc-c14n) |
| `CertificateLoaderService` | Carga y valida certificados PKCS#12 |
| `HaciendaIdpService` | OAuth2 con cache + refresh token |
| `HaciendaProvider` | Envio a API de recepcion de Hacienda |
| `KeyGeneratorService` | Genera clave 50 digitos + consecutivo 20 digitos |
| `PayloadTransformerService` | snake_case ↔ PascalCase |
| `CalculationValidatorService` | Validacion matematica en 3 capas (5 validators) |
| `WebhookService` | Procesa respuestas de Hacienda |
| `WebhookVerifierService` | Verifica autenticidad (clave + firma XML) |

### Logica de negocio (se adapta)

| Componente | Cambio necesario |
|---|---|
| `InvoicingService` | Recibe contexto del tenant en vez de leer config |
| `ReceiptBuilderService` | Consecutivos por tenant |
| `XmlPipelineService` | Certificado y credenciales del tenant |
| Jobs async | Worker interno, no Laravel Queue del host |

### Se construye nuevo

| Componente | Proposito |
|---|---|
| Tenant management | Registro, configuracion, API keys |
| Certificate storage | Almacen encriptado de .p12 por tenant |
| Credential storage | Credenciales IDP encriptadas por tenant |
| Webhook dispatcher | Notificar a clientes (callbacks salientes) |
| Rate limiting | Por plan/tenant |
| API authentication | API keys, no Sanctum del host |
| Portal web | Dashboard para clientes |
| Billing | Cobro por factura o plan mensual |

---

## Ventajas competitivas vs Cyberfuel

| Aspecto | Cyberfuel | Nuestro servicio |
|---|---|---|
| Validacion previa | Basica | 3 capas con 5 validators matematicos |
| Mensajes de error | Genericos | Especificos por campo y calculo |
| Soporte XSD | Parcial | Fidelidad 1:1 con XSD v4.4 |
| Documentacion API | Limitada | OpenAPI/Swagger completa |
| Testing | Desconocido | 243 tests, 508 assertions en el core |
| Multi-sucursal | Si | Si, con consecutivos independientes |
| Webhook verificacion | Basica | 2 capas (clave + firma XML) |

---

## Riesgos y mitigaciones

| Riesgo | Mitigacion |
|---|---|
| Certificados .p12 son datos sensibles | Encriptacion at-rest (AES-256), acceso por servicio unico |
| Credenciales IDP de terceros | Encriptacion en DB, nunca en logs, rotacion de keys |
| Consecutivos son criticos (Hacienda los valida) | Locks por tenant, sin gaps, auditoria |
| Disponibilidad — clientes dependen del servicio | Health checks, redundancia, alertas, SLA definido |
| Hacienda cambia specs | Core ya abstrae XSD — cambios localizados |
| Rate limiting de Hacienda | Cola por tenant, backoff, retry con exponential delay |