# Checklist de Producción

Todo lo que debe completarse antes de usar el paquete en producción.

**Última actualización:** 5 de abril de 2026

---

## Estado del paquete

El paquete soporta los 4 tipos de comprobante que cubren el 95% de los casos de uso de un negocio en Costa Rica:

| Tipo | Uso | Sandbox |
|---|---|---|
| **FE** — Factura Electrónica | Venta a cliente con cédula | Aceptada |
| **TE** — Tiquete Electrónico | Venta al público sin cédula | Aceptado |
| **NC** — Nota de Crédito | Anular o corregir factura | Aceptada |
| **ND** — Nota de Débito | Cobro adicional | Aceptada |

Tipos adicionales con XML generado pero pendientes de prueba en sandbox:

| Tipo | Uso | Sandbox |
|---|---|---|
| **FEC** — Factura de Compra | Compras a proveedores | Pendiente |
| **FEE** — Factura de Exportación | Ventas al exterior | Pendiente |
| **REP** — Recibo de Pago | Confirmación de pago | Pendiente |

---

## Bloqueantes para producción (responsabilidad del cliente)

- [ ] **Certificado .p12 de producción**
  - Obtener certificado de firma digital de producción (no sandbox)
  - Configurar ruta y PIN en variables de entorno
  - Verificar expiración: `php artisan invoicing:check-certificate`

- [ ] **Credenciales IdP de producción**
  - Obtener usuario y contraseña del IdP de Hacienda (producción)
  - Configurar `INVOICING_CR_IDP_USERNAME` y `INVOICING_CR_IDP_PASSWORD`

- [ ] **Cambiar ambiente a producción**
  - `INVOICING_CR_AMBIENTE=production`
  - Verificar endpoints de producción en `config/hacienda.php`

- [ ] **Webhook URL accesible desde internet**
  - `INVOICING_CR_CALLBACK_URL` debe ser URL pública que Hacienda pueda alcanzar

- [ ] **Configurar consecutivo inicial** (si migra de otro sistema)
  - `php artisan invoicing:set-consecutive FE 1000`
  - Ver consecutivos actuales: `php artisan invoicing:set-consecutive --list`

---

## Completado en el paquete

### Funcionalidad core
- [x] Generación XML v4.4 compliant con XSD de Hacienda (7 tipos)
- [x] Firma digital XAdES-EPES (RSA-SHA256, exc-c14n)
- [x] Autenticación OAuth2 con cache y refresh token
- [x] API RESTful: 7 endpoints (receipts CRUD, reception, webhook)
- [x] API externa en snake_case, internamente PascalCase
- [x] Middleware configurable para rutas API y webhook
- [x] Campo `external_reference` para vincular con sistema externo

### Validación
- [x] Pipeline de 3 capas: StoreReceiptRequest → ReceiptTypeRules → CalculationValidatorService
- [x] 5 validators matemáticos: DetailLine, TaxCalculation, InvoiceSummary, TaxBreakdown, Assortment
- [x] 3 reglas custom: DecimalDinero, ValidateIdentification, ServiceDetailRequired
- [x] CondicionVenta cross-validations (99→Otros, 02/10→PlazoCredito)
- [x] Receptor.CorreoElectronico como array (max 4 emails)
- [x] TipoCambio gt:0

### Operación
- [x] Modo sync y async (configurable via `INVOICING_CR_SEND_MODE`)
- [x] Multi-sucursal con consecutivos independientes por sucursal+terminal+tipo
- [x] Concurrencia en consecutivos con lockForUpdate() en transacción DB
- [x] Webhook idempotente con verificación de clave y firma XAdES
- [x] Events: ReceiptCreated, ReceiptSent, ReceiptAccepted, ReceiptRejected
- [x] Comandos: `invoicing:set-consecutive`, `invoicing:check-certificate`
- [x] FechaEmision forzada a timezone America/Costa_Rica

### Testing
- [x] 243 tests, 508 assertions (Pest + Orchestra Testbench)
- [x] Tests de seguridad XML (injection, XXE, CDATA, attributes)
- [x] Colección Postman con 7 tipos y 30 tests de impuestos

---

## Pendiente para futuras versiones

- [ ] Probar FEC, FEE y REP contra sandbox de Hacienda

---

## Variables de Entorno para Producción

```env
# CAMBIAR para producción
INVOICING_CR_AMBIENTE=production
INVOICING_CR_IDP_USERNAME=usuario_produccion
INVOICING_CR_IDP_PASSWORD=password_produccion
INVOICING_CR_CERTIFICADO_PATH=ruta/al/certificado_produccion.p12
INVOICING_CR_CERTIFICADO_PIN=pin_produccion
INVOICING_CR_SEND_MODE=async                    # async para POS/ERP, sync para desarrollo

# VERIFICAR que están configurados
INVOICING_CR_EMISOR_NOMBRE=
INVOICING_CR_EMISOR_CEDULA=
INVOICING_CR_EMISOR_TIPO=
INVOICING_CR_EMISOR_ACTIVIDAD=
INVOICING_CR_CALLBACK_URL=https://tu-dominio.com/api/invoicing-cr/webhook
INVOICING_CR_PROVEEDOR_SISTEMAS=

# MULTI-SUCURSAL (default 1 si no se configura)
INVOICING_CR_SUCURSAL=1
INVOICING_CR_TERMINAL=1

# OPCIONALES pero recomendados
INVOICING_CR_EMISOR_NOMBRE_COMERCIAL=
INVOICING_CR_EMISOR_TELEFONO=
INVOICING_CR_EMISOR_EMAIL=
INVOICING_CR_EMISOR_PROVINCIA=
INVOICING_CR_EMISOR_CANTON=
INVOICING_CR_EMISOR_DISTRITO=
INVOICING_CR_EMISOR_OTRAS_SENAS=
```
