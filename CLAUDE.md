# CLAUDE.md — laravel-paquete-facturacion

Este archivo provee contexto a Claude (IA) y otros desarrolladores sobre el paquete.

---

## Información del paquete

| Campo | Valor |
|---|---|
| **Nombre** | `factutica/laravel-paquete-facturacion` |
| **Descripción** | Laravel package for electronic invoicing (Facturación Electrónica) compliant with Costa Rica's Ministerio de Hacienda API |
| **Namespace** | `FactuTica\FactuticaCR` |
| **Licencia** | Proprietary — uso comercial, no redistribuir |
| **PHP** | `^8.2` |
| **Laravel** | `^11.0 \| ^12.0 \| ^13.0` |

---

## Autores

- **FactuTica** — https://github.com/PabloVillaplana
- **Juan Pablo Villaplana Corrales** — jpvillaplana@gmail.com — Lead Developer
- **Juan Pablo Villaplana Corrales** — jpvillaplana@gmail.com — Developer

---

## Estructura del paquete

```
laravel-paquete-facturacion/
├── config/
│   ├── invoicing.php             ← ambiente, provider, emisor, defaults, middleware, send_mode, callback
│   ├── hacienda.php              ← credenciales IdP, certificado .p12, endpoints por ambiente
│   └── catalogues.php            ← catálogos oficiales de Hacienda (unidades, monedas, formas farmacéuticas)
├── database/
│   ├── factories/
│   │   └── ReceiptFactory.php    ← factory para tests
│   └── migrations/               ← 8 migraciones del paquete
├── docs/
│   ├── invoicing-cr.postman_collection.json  ← colección Postman/Bruno (7 tipos + recepción + 30 tests taxes)
│   ├── request-factura-electronica.md        ← ejemplos completos de request FE en snake_case
│   ├── auditoria-final-produccion.md          ← evaluación de calidad del paquete (8.2/10)
│   ├── flujos.md                             ← 7 diagramas Mermaid de flujos end-to-end
│   ├── matriz-cobertura.md                   ← estado por tipo de comprobante
│   ├── validaciones-vs-xsd.md                ← validación del paquete vs XSD de Hacienda
│   ├── catalogo-errores.md                   ← 30+ puntos de falla documentados
│   ├── checklist-produccion.md               ← pendientes antes de producción
│   ├── comparacion-cyberfuel-vs-paquete.md   ← comparación con proveedor Cyberfuel
│   └── basexml-FacturaElectronica_V4.4.xsd  ← XSD oficial de Hacienda
├── routes/
│   └── api.php                   ← 7 endpoints RESTful (middleware configurable api/webhook)
├── src/
│   ├── Console/
│   │   ├── SetConsecutiveCommand.php     ← invoicing:set-consecutive (configura consecutivo por suc+term+tipo)
│   │   └── CheckCertificateCommand.php   ← invoicing:check-certificate (verifica expiración .p12)
│   ├── Constants.php             ← CLAVE_LENGTH, CONSECUTIVE_LENGTH, TOKEN_MARGIN_SECONDS, CERTIFICATE_WARNING_DAYS
│   ├── Contracts/
│   │   ├── InvoicingInterface.php    ← contrato del servicio principal (return types concretos: Receipt, array)
│   │   ├── ProviderInterface.php     ← contrato para providers (send → ProviderResponse)
│   │   └── Sendable.php              ← interfaz compartida Receipt/SentReceipt (markAsSent, markAsFailed)
│   ├── Enums/
│   │   ├── ReceiptType.php           ← FE, FEE, FEC, TE, ND, NC, REP
│   │   ├── IdentificationType.php    ← 01=Física, 02=Jurídica, 03=DIMEX, 04=NITE, 05=Extranjero, 06=No Contribuyente
│   │   ├── CurrencyType.php          ← CRC, USD, EUR
│   │   ├── ReceiptStatus.php         ← pending, sent, accepted, rejected, failed
│   │   ├── HaciendaStatus.php        ← pending, accepted, rejected
│   │   └── ReceptionStatus.php       ← pending, accepted, partially_accepted, rejected
│   ├── Exceptions/
│   │   ├── HaciendaException.php     ← excepción base del paquete
│   │   ├── XmlSignerException.php    ← errores al firmar XML
│   │   ├── InvalidReceiptException.php ← datos inválidos en comprobante
│   │   └── CertificateException.php  ← problemas con certificado .p12
│   ├── Facades/
│   │   └── Facturacion.php           ← facade principal (alias: 'invoicing')
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── ReceiptController.php     ← CRUD de comprobantes (store sync/async, index, show, showByKey, status)
│   │   │   ├── ReceptionController.php   ← recepción de documentos (store → encola job)
│   │   │   └── WebhookController.php     ← webhook de Hacienda (delega a WebhookService)
│   │   ├── Requests/
│   │   │   ├── StoreReceiptRequest.php   ← prepareForValidation() + validación base + reglas por tipo
│   │   │   └── StoreReceptionRequest.php ← validación de recepción
│   │   └── Resources/
│   │       ├── ReceiptResource.php       ← transformación completa (español, timezone Costa Rica)
│   │       └── ReceiptStatusResource.php ← solo status
│   ├── Jobs/
│   │   ├── SendReceiptToProviderJob.php      ← envío async de Receipt (modo async, ShouldBeUnique, 3 reintentos)
│   │   └── SendSentReceiptToProviderJob.php  ← envío async de MensajeReceptor (ShouldBeUnique)
│   ├── Models/
│   │   ├── Receipt.php               ← comprobante principal (implements Sendable, external_reference, establishment, terminal)
│   │   ├── ReceiptPayload.php        ← payload JSON del comprobante
│   │   ├── ReceiptConsecutive.php    ← consecutivos por tipo+sucursal+terminal (unique compuesto)
│   │   ├── HaciendaResponse.php      ← respuesta de Hacienda (response_message text)
│   │   └── SentReceipt.php           ← mensajes de recepción enviados (implements Sendable)
│   ├── Providers/
│   │   ├── ProviderFactoryService.php    ← factory de providers (extensible con register())
│   │   ├── ProviderResponse.php          ← DTO tipado (clave, fecha, httpStatus, signedXml)
│   │   ├── HaciendaProvider.php          ← provider directo a API de Hacienda
│   │   └── (extensible via ProviderFactoryService::register())
│   ├── Rules/
│   │   ├── DecimalDinero.php                ← regla de validación para DecimalDineroType
│   │   ├── ServiceDetailRequired.php        ← regla para detalle de servicio requerido
│   │   └── ValidateIdentification.php       ← regla para validar cédula/identificación
│   ├── Services/
│   │   ├── Hacienda/
│   │   │   ├── CertificateLoaderService.php  ← carga .p12 SINPE, valida expiración
│   │   │   ├── XmlSignerService.php          ← firma XAdES-EPES (exc-c14n, SHA-256, policy)
│   │   │   └── Idp/
│   │   │       ├── HaciendaIdpService.php    ← OAuth2 con cache + refresh token
│   │   │       └── TokenData.php             ← DTO del token con expiración (TOKEN_MARGIN_SECONDS)
│   │   ├── Validators/
│   │   │   ├── ReceiptTypeRules.php              ← reglas required/prohibited/extra por tipo
│   │   │   ├── CalculationValidatorService.php   ← orquestador de validaciones matemáticas
│   │   │   ├── DetailLineValidator.php           ← validación de cálculos por línea de detalle
│   │   │   ├── TaxCalculationValidator.php       ← validación de cálculos de impuestos
│   │   │   ├── InvoiceSummaryValidator.php       ← validación de totales del resumen
│   │   │   ├── TaxBreakdownValidator.php         ← validación del desglose de impuestos
│   │   │   └── AssortmentValidator.php           ← validación de surtido/datos específicos
│   │   ├── Webhook/
│   │   │   ├── WebhookService.php            ← procesa respuesta de Hacienda (transacción DB)
│   │   │   └── WebhookVerifierService.php    ← verificación 2 capas (clave + firma XML)
│   │   ├── XmlGenerator/
│   │   │   ├── XmlGeneratorService.php           ← genera XML v4.4 para todos los tipos
│   │   │   └── MensajeReceptorXmlGeneratorService.php ← XML de mensajes de recepción (CA/CAP/CR)
│   │   ├── InvoicingService.php      ← orquestador: validar → clave → persistir → XML → firmar → enviar (sync/async)
│   │   ├── KeyGeneratorService.php   ← genera clave 50 dígitos, consecutivo 20 dígitos, código seguridad
│   │   └── PayloadTransformerService.php ← transforma snake_case (API) → PascalCase (XSD) con mapeo explícito
│   ├── Traits/
│   │   └── SendsToProvider.php       ← trait compartido entre jobs (failed + logging)
│   └── InvoicingServiceProvider.php  ← registro de singletons, aliases, configs, rutas, migraciones, comandos
├── tests/
│   ├── Pest.php
│   ├── TestCase.php                  ← base con Orchestra Testbench + SQLite in-memory
│   ├── Unit/
│   │   ├── CertificateLoaderTest.php     ← 7 tests
│   │   ├── ConfigTest.php                ← 3 tests
│   │   ├── EnumTest.php                  ← 7 tests
│   │   ├── KeyGeneratorTest.php          ← 15 tests
│   │   ├── ProviderFactoryTest.php       ← 5 tests
│   │   ├── RulesTest.php                 ← 35 tests (DecimalDinero, ValidateIdentification, ServiceDetailRequired)
│   │   ├── ServiceProviderTest.php       ← 5 tests
│   │   ├── TokenDataTest.php            ← 5 tests
│   │   ├── XmlGeneratorTest.php          ← 11 tests
│   │   ├── XmlSecurityTest.php           ← 8 tests (injection, XXE, CDATA, attributes)
│   │   └── Validators/
│   │       ├── AssortmentValidatorTest.php           ← 20 tests
│   │       ├── CalculationValidatorServiceTest.php   ← 7 tests
│   │       ├── DetailLineValidatorTest.php           ← 15 tests
│   │       ├── InvoiceSummaryValidatorTest.php       ← 14 tests
│   │       ├── TaxBreakdownValidatorTest.php         ← 7 tests
│   │       └── TaxCalculationValidatorTest.php       ← 16 tests
│   ├── Feature/
│   │   ├── InvoicingServiceTest.php      ← 6 tests (incluye establishment/terminal)
│   │   └── Http/
│   │       ├── ReceiptControllerTest.php     ← 11 tests (incluye async mode)
│   │       ├── ReceptionControllerTest.php   ← 9 tests
│   │       └── WebhookControllerTest.php     ← 5 tests
│   └── Integration/                  ← 5 tests contra sandbox real (--group=integration)
│       ├── IntegrationTestCase.php
│       └── HaciendaServicesTest.php
├── phpunit.xml
└── composer.json
```

---

## Flujo principal (createAndSend)

```
Cliente POST /invoicing-cr/receipts (payload en snake_case)
  → StoreReceiptRequest::prepareForValidation() transforma snake_case → PascalCase via PayloadTransformerService
  → StoreReceiptRequest (validación base + reglas por tipo)
  → InvoicingService::createAndSend()
    1. Genera consecutivo (ReceiptConsecutive)
    2. Genera clave 50 dígitos + consecutivo 20 dígitos (KeyGeneratorService)
    3. Inyecta Emisor desde config, FechaEmision, ProveedorSistemas, CodigoActividad
    4. Auto-genera TotalDesgloseImpuesto si hay impuestos
    5. Deriva campos del modelo Receipt desde el payload (CondicionVenta, ResumenFactura.*, Receptor.*)
    6. Persiste Receipt + ReceiptPayload
    7. Genera XML v4.4 (XmlGeneratorService)
    8. Firma XAdES-EPES (XmlSignerService)
    9. Construye payload Hacienda (clave, emisor, comprobanteXml base64, callbackUrl)
    10. Envía a API recepción con token OAuth2 (HaciendaProvider)
    11. Actualiza receipt (markAsSent)
  → 201 con ReceiptResource

Hacienda responde async:
  POST /invoicing-cr/webhook
    → WebhookController (valida clave 50 dígitos)
    → WebhookService::process()
      → WebhookVerifierService::verify() (clave + firma XAdES)
      → Persiste HaciendaResponse
      → Actualiza Receipt (accepted/rejected)
```

---

## API Endpoints

| Método | Ruta | Descripción |
|---|---|---|
| `POST` | `/invoicing-cr/receipts` | Crear, firmar y enviar comprobante |
| `GET` | `/invoicing-cr/receipts` | Listar (filtro `?type=FE&status=sent&per_page=25`) |
| `GET` | `/invoicing-cr/receipts/{id}` | Ver por ID |
| `GET` | `/invoicing-cr/receipts/key/{uiKey}` | Ver por clave |
| `GET` | `/invoicing-cr/receipts/key/{uiKey}/status` | Consultar estado en Hacienda |
| `POST` | `/invoicing-cr/reception` | Recibir documento y encolar respuesta |
| `POST` | `/invoicing-cr/webhook` | Webhook de Hacienda |

---

## Tipos de comprobantes soportados

| Código | Enum | Hacienda | Descripción |
|---|---|---|---|
| `FE` | `ReceiptType::ElectronicInvoice` | 01 | Factura Electrónica |
| `FEE` | `ReceiptType::ExportInvoice` | 09 | Factura Electrónica de Exportación |
| `FEC` | `ReceiptType::PurchaseInvoice` | 08 | Factura Electrónica de Compra |
| `TE` | `ReceiptType::ElectronicTicket` | 04 | Tiquete Electrónico |
| `ND` | `ReceiptType::DebitNote` | 02 | Nota de Débito |
| `NC` | `ReceiptType::CreditNote` | 03 | Nota de Crédito |
| `REP` | `ReceiptType::ElectronicPaymentReceipt` | 07 | Comprobante de Recibo Electrónico de Pago |

---

## Sistema de Validacion

El paquete implementa un pipeline de validacion en tres capas antes de generar el XML.

El cliente envia los campos en **snake_case** (ej: `condicion_venta`, `detalle_servicio`, `resumen_factura`). El `PayloadTransformerService` los convierte a **PascalCase** (ej: `CondicionVenta`, `DetalleServicio`, `ResumenFactura`) en `prepareForValidation()` antes de que se ejecute la validacion. Internamente todo el pipeline trabaja con PascalCase para mantener fidelidad 1:1 con el XSD de Hacienda.

1. **StoreReceiptRequest** -- transforma snake_case → PascalCase, luego validacion estructural de Laravel (campos requeridos, tipos, formatos).
2. **ReceiptTypeRules** -- reglas condicionales por tipo de comprobante (campos required/prohibited/extra segun FE, TE, NC, etc.).
3. **CalculationValidatorService** -- validacion matematica de totales e impuestos, orquesta cinco validadores especializados:
   - **DetailLineValidator** -- verifica calculos por linea de detalle (MontoTotal, SubTotal, MontoTotalLinea).
   - **TaxCalculationValidator** -- valida montos de impuestos (Monto, Exoneracion, ImpuestoNeto) en cada linea.
   - **InvoiceSummaryValidator** -- valida los totales del resumen del comprobante (TotalServGravados, TotalMercanciasExentas, TotalVenta, TotalVentaNeta, TotalComprobante, etc.).
   - **TaxBreakdownValidator** -- valida que TotalDesgloseImpuesto coincida con la suma de impuestos de las lineas.
   - **AssortmentValidator** -- valida datos de surtido e impuesto especifico cuando aplica.

Adicionalmente, el directorio `Rules/` contiene reglas de validacion reutilizables de Laravel: `DecimalDinero`, `ServiceDetailRequired`, y `ValidateIdentification`.

El flujo completo es: **StoreReceiptRequest** -> **ReceiptTypeRules** -> **CalculationValidatorService** -> generacion XML.

---

## Patrones y convenciones

### Siempre
- API externa en **snake_case** (lo que el cliente envía). Internamente se usa **PascalCase** (XSD) via `PayloadTransformerService`
- Usar `Enums` en lugar de strings hardcodeados (`ReceiptType::ElectronicInvoice`)
- Usar excepciones propias del paquete (`CertificateException`, `HaciendaException`, etc.)
- Configs en `config/` raíz — **nunca** dentro de `src/`
- Registrar servicios como `singleton` en el `ServiceProvider`
- Los modelos que se envían a Hacienda implementan `Sendable`
- Los jobs usan el trait `SendsToProvider` para lógica compartida
- El `ProviderInterface::send()` retorna `ProviderResponse` (DTO tipado), no arrays
- Campos requeridos del XSD (ImpuestoAsumidoEmisorFabrica, ImpuestoNeto, totales del resumen) siempre se envían con default `0`

### Nunca
- Usar `RuntimeException` genérica — usar las excepciones propias
- Hardcodear strings de tipos (`'FE'`, `'01'`, `'CRC'`) — usar Enums
- Poner configs dentro de `src/`
- Usar `mixed` en contratos — tipar con `Sendable`, `ProviderResponse`, etc.
- Copiar lógica directamente del paquete anterior sin revisar

---

## Aliases registrados

| Alias | Clase |
|---|---|
| `invoicing` | `InvoicingService` |
| `invoicing.signer` | `XmlSignerService` |
| `invoicing.idp` | `HaciendaIdpService` |
| `invoicing.xml` | `XmlGeneratorService` |

---

## Variables de entorno requeridas

```env
# Configuración general
INVOICING_CR_AMBIENTE=sandbox              # sandbox | production
INVOICING_CR_PROVIDER=hacienda
INVOICING_CR_PROVEEDOR_SISTEMAS=           # código numérico asignado por Hacienda

# Emisor (se inyectan automáticamente al XML si no vienen en el request)
INVOICING_CR_EMISOR_NOMBRE=
INVOICING_CR_EMISOR_CEDULA=
INVOICING_CR_EMISOR_TIPO=01               # 01=física, 02=jurídica, 03=DIMEX, 04=NITE
INVOICING_CR_EMISOR_NOMBRE_COMERCIAL=
INVOICING_CR_EMISOR_TELEFONO=
INVOICING_CR_EMISOR_EMAIL=
INVOICING_CR_EMISOR_PROVINCIA=
INVOICING_CR_EMISOR_CANTON=
INVOICING_CR_EMISOR_DISTRITO=
INVOICING_CR_EMISOR_OTRAS_SENAS=
INVOICING_CR_EMISOR_ACTIVIDAD=             # código con punto (ej: 6201.0)

# Hacienda IdP (autenticación OAuth2)
INVOICING_CR_IDP_USERNAME=
INVOICING_CR_IDP_PASSWORD=

# Firma digital (.p12)
INVOICING_CR_CERTIFICADO_PATH=             # ruta absoluta o relativa a storage_path()
INVOICING_CR_CERTIFICADO_PIN=

# Webhook (URL donde Hacienda notifica las respuestas)
INVOICING_CR_CALLBACK_URL=api/invoicing-cr/webhook

# Sucursal y terminal (default 1 si no se configura)
INVOICING_CR_SUCURSAL=1
INVOICING_CR_TERMINAL=1

# Modo de envío (sync = espera respuesta, async = encola job)
INVOICING_CR_SEND_MODE=sync
```

---

## Testing

```bash
# Tests unitarios + feature (CI)
vendor/bin/pest --exclude-group=integration

# Tests de integración contra sandbox real (requiere .env con credenciales)
vendor/bin/pest --group=integration

# Todos los tests
vendor/bin/pest
```

**242 tests, 506 assertions** — cubre configs, enums, token management, certificate loading, key generation, XML generation/security, providers, service provider, invoicing service flow (sync/async), HTTP controllers (receipts, reception, webhook), validation rules (DecimalDinero, ValidateIdentification, ServiceDetailRequired), y todos los validators de cálculo (DetailLine, TaxCalculation, InvoiceSummary, TaxBreakdown, Assortment).

---

## Estado MVP

### Listo (probado y aceptado en sandbox)

- FE, TE, NC, ND — los 4 tipos principales
- Generación XML v4.4, firma XAdES-EPES, OAuth2, API RESTful
- Pipeline de validación 3 capas (5 validators matemáticos)
- Modo sync/async, multi-sucursal, webhook idempotente
- Events, comandos artisan, external_reference
- 242 tests, 506 assertions

### Pendiente para futuras versiones

- [ ] Probar FEC, FEE y REP contra sandbox

---

## Referencia

Existe un paquete anterior en el repositorio `factutica-facturacion-electronica` que sirve
como referencia de lógica de negocio. **No copiar directamente** — este paquete mejora
la arquitectura del anterior.

---

## Entorno de desarrollo

- **Sandbox local:** `http://package-dev.test` (Laravel Herd)
- **Paquete ubicado en:** `~/Herd/package-dev/packages/factutica/laravel-paquete-facturacion`
- **Branch principal:** `develop`
- **Testing:** Pest + Orchestra Testbench
- **Certificado:** `storage/app/private/certificado.p12`
- **Sandbox Hacienda:** Facturas electrónicas aceptadas exitosamente