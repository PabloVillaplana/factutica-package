# Changelog

All notable changes to `factutica/laravel-paquete-facturacion` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- 7 tipos de comprobantes: FE, FEE, FEC, TE, ND, NC, REP
- Generacion XML v4.4 compliant con XSD de Hacienda
- Firma digital XAdES-EPES (exc-c14n, SHA-256)
- Autenticacion OAuth2 con cache y refresh token (HaciendaIdpService)
- API RESTful: 7 endpoints (receipts CRUD, reception, webhook)
- API externa en snake_case, internamente PascalCase (PayloadTransformerService)
- Pipeline de validacion: StoreReceiptRequest -> ReceiptTypeRules -> CalculationValidatorService
- 5 validators de calculo: DetailLine, TaxCalculation, InvoiceSummary, TaxBreakdown, Assortment
- 3 reglas de validacion: DecimalDinero, ValidateIdentification, ServiceDetailRequired
- Soporte multi-sucursal y multi-terminal con consecutivos independientes
- Modo sync y async para envio a Hacienda (configurable via env)
- Webhook con verificacion de clave y firma XAdES (idempotente)
- Events de ciclo de vida: ReceiptCreated, ReceiptSent, ReceiptAccepted, ReceiptRejected
- Comandos artisan: `invoicing:set-consecutive`, `invoicing:check-certificate`
- Campo `external_reference` para vincular con sistema externo
- Middleware configurable para rutas API y webhook
- Soporte de recepcion de documentos (MensajeReceptor)
- Constants class para valores magicos
- FechaEmision forzada a timezone America/Costa_Rica (-06:00)
- Concurrencia en consecutivos con lockForUpdate en transaccion DB
- 243 tests, 508 assertions (Pest + Orchestra Testbench)
- Coleccion Postman con 7 tipos de comprobantes y 30 tests de impuestos

### Fixed
- `HasSendableStatus::getKey()` sobrescribia el `Model::getKey()` real de Eloquent, rompiendo la serializacion de `SendSentReceiptToProviderJob::dispatchSync()` con `TypeError` cuando `ui_key` era null. Renombrado a `getUiKey(): ?string` en `Sendable`, `HasSendableStatus` y sus 4 call sites.
- El cast enum de `ReceiptConsecutive.receipt_type` lanzaba `ValueError` al leer los pseudo-tipos de mensaje de recepcion `MSG-05`/`MSG-06`/`MSG-07` (no existen en `ReceiptType`). Agregado `ReceiptOrMessageTypeCast` que devuelve la instancia real del enum para tipos genuinos o el string crudo para pseudo-tipos, sin lanzar nunca.

### Sandbox
- FE (Factura Electronica) — aceptada
- TE (Tiquete Electronico) — aceptado
- NC (Nota de Credito) — aceptada
- ND (Nota de Debito) — aceptada