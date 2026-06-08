# FactuTica — Laravel Package para Facturación Electrónica CR

Paquete de Laravel para Facturación Electrónica compatible con la API del Ministerio de Hacienda de Costa Rica (XSD v4.4).

Genera, firma (XAdES-EPES), y envía comprobantes electrónicos directamente a Hacienda desde tu aplicación Laravel.

---

## Contenido

- [Características](#características)
- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [Configuración](#configuración)
- [Inicio rápido](#inicio-rápido)
- [Tipos de comprobante](#tipos-de-comprobante)
- [Uso detallado](#uso-detallado)
  - [Factura Electrónica (FE)](#factura-electrónica-fe)
  - [Tiquete Electrónico (TE)](#tiquete-electrónico-te)
  - [Nota de Crédito (NC)](#nota-de-crédito-nc)
  - [Nota de Débito (ND)](#nota-de-débito-nd)
  - [Factura de Exportación (FEE)](#factura-de-exportación-fee)
  - [Factura de Compra (FEC)](#factura-de-compra-fec)
  - [Descuentos por línea](#descuentos-por-línea)
  - [Exoneraciones](#exoneraciones)
  - [Multi-moneda](#multi-moneda)
  - [Multi-sucursal](#multi-sucursal)
- [Modo sync vs async](#modo-sync-vs-async)
- [Recepción de documentos](#recepción-de-documentos)
- [Endpoints REST](#endpoints-rest)
- [Respuesta de la API](#respuesta-de-la-api)
- [Eventos](#eventos)
- [Comandos Artisan](#comandos-artisan)
- [Catálogo CABYS](#catálogo-cabys)
- [Validación](#validación)
- [Sistema de estados](#sistema-de-estados)
- [Webhook de Hacienda](#webhook-de-hacienda)
- [Facade y uso programático](#facade-y-uso-programático)
- [Providers personalizados](#providers-personalizados)
- [Testing](#testing)
- [Arquitectura](#arquitectura)
- [Documentación adicional](#documentación-adicional)
- [Licencia](#licencia)

---

## Características

- **7 tipos de comprobante** — FE, TE, NC, ND, FEC, FEE, REP
- **XML v4.4** — Generación conforme al XSD oficial de Hacienda
- **Firma XAdES-EPES** — RSA-SHA256, Exclusive C14N, referencia de política de firma
- **OAuth2 con cache** — Token caching automático con `TOKEN_MARGIN_SECONDS` y refresh
- **Validación en 3 capas** — Estructura, reglas por tipo, y matemática (5 validators especializados)
- **Modo sync y async** — Sync para respuesta inmediata, async (queue) para sistemas de alto volumen
- **Webhook verificado** — Verificación en 2 capas: clave 50 dígitos + firma XAdES del XML
- **Multi-sucursal y multi-terminal** — Consecutivos independientes por sucursal + terminal + tipo
- **Recepción de documentos** — MensajeReceptor (CA/CAP/CR) con envío automático
- **Transaccional** — Todo dentro de `DB::transaction`, nada queda en estado corrupto
- **Events de Laravel** — `ReceiptCreated`, `ReceiptSent`, `ReceiptAccepted`, `ReceiptRejected`
- **Catálogo CABYS** — ~20,500 códigos importables con búsqueda por descripción
- **242 tests** — 506 assertions cubriendo todos los componentes

---

## Requisitos

| Requisito | Versión |
|---|---|
| PHP | `^8.2` |
| Laravel | `^11.0 \| ^12.0 \| ^13.0` |
| Certificado `.p12` | SINPE — Firma Digital de Costa Rica |
| Credenciales IdP | OAuth2 de Hacienda (usuario/contraseña del sistema) |

---

## Instalación

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

Luego instala:

```bash
composer require factutica/laravel-paquete-facturacion
```

Publica los archivos de configuración:

```bash
php artisan vendor:publish --tag=invoicing-config
```

Ejecuta las migraciones:

```bash
php artisan migrate
```

---

## Configuración

### Variables de entorno mínimas (sandbox)

Con estas 5 variables el paquete funciona en sandbox:

```env
INVOICING_CR_EMISOR_NOMBRE="Mi Empresa S.A."
INVOICING_CR_EMISOR_CEDULA=3101234567
INVOICING_CR_IDP_USERNAME=cpj-3101234567@stag.comprobanteselectronicos.go.cr
INVOICING_CR_IDP_PASSWORD=tu_password
INVOICING_CR_CERTIFICADO_PATH=app/private/certificado.p12
INVOICING_CR_CERTIFICADO_PIN=1234
```

### Referencia completa de variables

```env
# ─── Ambiente ─────────────────────────────────────────────────────────────────
INVOICING_CR_AMBIENTE=sandbox               # sandbox | production

# ─── Emisor ───────────────────────────────────────────────────────────────────
INVOICING_CR_EMISOR_NOMBRE="Mi Empresa S.A."
INVOICING_CR_EMISOR_CEDULA=3101234567
INVOICING_CR_EMISOR_TIPO=02                 # 01=física, 02=jurídica, 03=DIMEX, 04=NITE (default: 01)
INVOICING_CR_EMISOR_NOMBRE_COMERCIAL="Mi Marca"
INVOICING_CR_EMISOR_TELEFONO=22345678
INVOICING_CR_EMISOR_EMAIL=facturacion@empresa.cr
INVOICING_CR_EMISOR_PROVINCIA=1             # 1=San José, 2=Alajuela, …
INVOICING_CR_EMISOR_CANTON=01
INVOICING_CR_EMISOR_DISTRITO=01
INVOICING_CR_EMISOR_OTRAS_SENAS="100m norte del parque"
INVOICING_CR_EMISOR_ACTIVIDAD=6201.0        # Código de actividad económica (con punto)

# ─── Autenticación Hacienda (OAuth2) ──────────────────────────────────────────
INVOICING_CR_IDP_USERNAME=cpj-3101234567@stag.comprobanteselectronicos.go.cr
INVOICING_CR_IDP_PASSWORD=tu_password

# ─── Certificado de firma digital (.p12) ──────────────────────────────────────
INVOICING_CR_CERTIFICADO_PATH=app/private/certificado.p12  # relativo a storage_path()
INVOICING_CR_CERTIFICADO_PIN=1234

# ─── Operación ────────────────────────────────────────────────────────────────
INVOICING_CR_PROVEEDOR_SISTEMAS=3102910527  # código asignado por Hacienda
INVOICING_CR_CALLBACK_URL=api/invoicing-cr/webhook  # URL donde Hacienda notifica
INVOICING_CR_SUCURSAL=1                     # sucursal por defecto (1-999)
INVOICING_CR_TERMINAL=1                     # terminal/caja por defecto (1-99999)
INVOICING_CR_SEND_MODE=sync                 # sync | async
INVOICING_CR_REGISTER_ROUTES=true           # false si defines tus propios controllers

# ─── Funcionalidades opcionales ───────────────────────────────────────────────
INVOICING_CR_VALIDATE_CABYS=false           # true requiere invoicing:sync-cabys previo
```

### Middleware

Las rutas del paquete no tienen middleware por defecto. Configura autenticación en `config/invoicing.php`:

```php
'middleware' => [
    'api'     => ['api', 'auth:sanctum'],  // comprobantes, recepción, consultas
    'webhook' => [],                        // Hacienda no envía tokens — la seguridad
],                                         // la maneja WebhookVerifierService
```

---

## Inicio rápido

```bash
POST /invoicing-cr/receipts
Content-Type: application/json
```

```json
{
    "receipt_type": "FE",
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
            "cantidad": "1",
            "unidad_medida": "Sp",
            "detalle": "Servicio de consultoría",
            "precio_unitario": "10000.00",
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

**El paquete auto-genera:** Clave (50 dígitos), NumeroConsecutivo, FechaEmision, Emisor (desde `.env`), TotalDesgloseImpuesto, firma XAdES-EPES — y envía a Hacienda en una sola llamada.

---

## Tipos de comprobante

| Código | Enum | # Hacienda | Descripción | Estado en Sandbox |
|---|---|:---:|---|:---:|
| `FE` | `ReceiptType::ElectronicInvoice` | 01 | Factura Electrónica | Aceptada |
| `ND` | `ReceiptType::DebitNote` | 02 | Nota de Débito | Aceptada |
| `NC` | `ReceiptType::CreditNote` | 03 | Nota de Crédito | Aceptada |
| `TE` | `ReceiptType::ElectronicTicket` | 04 | Tiquete Electrónico | Aceptado |
| `FEC` | `ReceiptType::PurchaseInvoice` | 08 | Factura Electrónica de Compra | Implementada |
| `FEE` | `ReceiptType::ExportInvoice` | 09 | Factura Electrónica de Exportación | Implementada |
| `REP` | `ReceiptType::ElectronicPaymentReceipt` | 07 | Comprobante de Recibo Electrónico de Pago | Implementado |

---

## Uso detallado

Todos los campos del request van en **snake_case**. El paquete los transforma a **PascalCase** internamente para fidelidad con el XSD.

### Campos comunes a todos los tipos

| Campo | Requerido | Descripción |
|---|:---:|---|
| `receipt_type` | Sí | Código del tipo: `FE`, `TE`, `NC`, `ND`, `FEC`, `FEE`, `REP` |
| `condicion_venta` | Sí | `01`=Contado, `02`=Crédito, `03`=Consignación, `04`=Apartado, `05`=Arrendamiento, `06`=OtrasCondicione, `07`=CesionFacturas, `08`=Cobro a favor del estado, `09`=ServiceLevel |
| `codigo_actividad_emisor` | Sí (FE, TE, ND, NC, FEE) | Código de actividad económica del emisor (ej: `6201.0`) |
| `external_reference` | No | Referencia al sistema del cliente (ej: `INV-0024`). Máx 100 chars. |
| `establishment` | No | Sucursal (1-999). Default desde config. |
| `terminal` | No | Terminal/caja (1-99999). Default desde config. |
| `detalle_servicio` | Sí | Cuerpo de líneas del comprobante |
| `resumen_factura` | Sí | Totales del comprobante |

### Factura Electrónica (FE)

Requiere receptor con identificación completa.

```json
{
    "receipt_type": "FE",
    "condicion_venta": "01",
    "codigo_actividad_emisor": "6201.0",
    "receptor": {
        "nombre": "Cliente S.A.",
        "identificacion": {
            "tipo": "02",
            "numero": "3101234567"
        },
        "nombre_comercial": "Cliente Comercial",
        "correo_electronico": ["facturacion@cliente.cr", "contabilidad@cliente.cr"]
    },
    "detalle_servicio": {
        "linea_detalle": [{
            "numero_linea": 1,
            "codigo_cabys": "6803000000000",
            "cantidad": "2",
            "unidad_medida": "Sp",
            "detalle": "Servicio de desarrollo de software",
            "precio_unitario": "150000.00",
            "monto_total": "300000.00",
            "sub_total": "300000.00",
            "base_imponible": "300000.00",
            "impuesto": [{
                "codigo": "01",
                "codigo_tarifa_iva": "08",
                "tarifa": "13.00",
                "monto": "39000.00"
            }],
            "impuesto_neto": "39000.00",
            "monto_total_linea": "339000.00"
        }]
    },
    "resumen_factura": {
        "codigo_tipo_moneda": { "codigo_moneda": "CRC", "tipo_cambio": "1" },
        "total_serv_gravados": "300000.00",
        "total_gravado": "300000.00",
        "total_venta": "300000.00",
        "total_venta_neta": "300000.00",
        "total_impuesto": "39000.00",
        "total_comprobante": "339000.00",
        "medio_pago": [
            { "tipo_medio_pago": "01", "total_medio_pago": "339000.00" }
        ]
    }
}
```

### Tiquete Electrónico (TE)

El receptor es opcional. Si se incluye, no necesita identificación.

```json
{
    "receipt_type": "TE",
    "condicion_venta": "01",
    "codigo_actividad_emisor": "6201.0",
    "receptor": {
        "nombre": "Consumidor Final"
    },
    "detalle_servicio": {
        "linea_detalle": [{
            "numero_linea": 1,
            "codigo_cabys": "6803000000000",
            "cantidad": "1",
            "unidad_medida": "Sp",
            "detalle": "Producto A",
            "precio_unitario": "5000.00",
            "monto_total": "5000.00",
            "sub_total": "5000.00",
            "base_imponible": "5000.00",
            "impuesto": [{
                "codigo": "01",
                "codigo_tarifa_iva": "08",
                "tarifa": "13.00",
                "monto": "650.00"
            }],
            "impuesto_neto": "650.00",
            "monto_total_linea": "5650.00"
        }]
    },
    "resumen_factura": {
        "codigo_tipo_moneda": { "codigo_moneda": "CRC", "tipo_cambio": "1" },
        "total_serv_gravados": "5000.00",
        "total_gravado": "5000.00",
        "total_venta": "5000.00",
        "total_venta_neta": "5000.00",
        "total_impuesto": "650.00",
        "total_comprobante": "5650.00",
        "medio_pago": [{ "tipo_medio_pago": "01", "total_medio_pago": "5650.00" }]
    }
}
```

### Nota de Crédito (NC)

Requiere `informacion_referencia` apuntando al comprobante que corrige.

```json
{
    "receipt_type": "NC",
    "condicion_venta": "01",
    "codigo_actividad_emisor": "6201.0",
    "informacion_referencia": [{
        "tipo_doc": "01",
        "numero": "50601021800310123456700100001010000000011199999999",
        "fecha_emision_ref": "2025-01-15T10:00:00-06:00",
        "codigo": "01",
        "razon": "Anulación de factura por error en monto"
    }],
    "receptor": {
        "nombre": "Cliente S.A.",
        "identificacion": { "tipo": "02", "numero": "3101234567" }
    },
    "detalle_servicio": { ... },
    "resumen_factura": { ... }
}
```

**Códigos de razón para NC/ND:**

| Código | Descripción |
|---|---|
| `01` | Anula documento de referencia |
| `02` | Corrige texto documento de referencia |
| `03` | Corrige monto |
| `04` | Referencia a otro documento |
| `05` | Sustituye comprobante provisional por contingencia |
| `99` | Otros |

### Nota de Débito (ND)

Misma estructura que NC. `informacion_referencia` es requerido.

```json
{
    "receipt_type": "ND",
    "condicion_venta": "01",
    "codigo_actividad_emisor": "6201.0",
    "informacion_referencia": [{
        "tipo_doc": "01",
        "numero": "50601021800310123456700100001010000000011199999999",
        "fecha_emision_ref": "2025-01-15T10:00:00-06:00",
        "codigo": "03",
        "razon": "Ajuste de monto por intereses"
    }],
    "receptor": { ... },
    "detalle_servicio": { ... },
    "resumen_factura": { ... }
}
```

### Factura de Exportación (FEE)

Receptor extranjero. No lleva impuestos. Requiere `CodigoActividadEmisor`.

```json
{
    "receipt_type": "FEE",
    "condicion_venta": "01",
    "codigo_actividad_emisor": "6201.0",
    "receptor": {
        "nombre": "Foreign Client LLC",
        "identificacion": { "tipo": "05", "numero": "123456789" },
        "otras_senas_extranjero": "123 Main St, New York, NY",
        "correo_electronico": ["billing@foreignclient.com"]
    },
    "detalle_servicio": {
        "linea_detalle": [{
            "numero_linea": 1,
            "codigo_cabys": "6803000000000",
            "cantidad": "1",
            "unidad_medida": "Sp",
            "detalle": "Software development service",
            "precio_unitario": "1000.00",
            "monto_total": "1000.00",
            "sub_total": "1000.00",
            "monto_exportacion": "1000.00",
            "monto_total_linea": "1000.00"
        }]
    },
    "resumen_factura": {
        "codigo_tipo_moneda": { "codigo_moneda": "USD", "tipo_cambio": "520.00" },
        "total_serv_exentos": "1000.00",
        "total_exento": "1000.00",
        "total_venta": "1000.00",
        "total_venta_neta": "1000.00",
        "total_comprobante": "1000.00",
        "medio_pago": [{ "tipo_medio_pago": "03", "total_medio_pago": "1000.00" }]
    }
}
```

### Factura de Compra (FEC)

El receptor compra; requiere `CodigoActividadReceptor` en lugar de `CodigoActividadEmisor`.

```json
{
    "receipt_type": "FEC",
    "condicion_venta": "01",
    "codigo_actividad_receptor": "6201.0",
    "receptor": {
        "nombre": "Mi Empresa S.A.",
        "identificacion": { "tipo": "02", "numero": "3101234567" }
    },
    "detalle_servicio": { ... },
    "resumen_factura": { ... }
}
```

### Descuentos por línea

Cada línea soporta hasta 5 descuentos:

```json
{
    "numero_linea": 1,
    "precio_unitario": "10000.00",
    "monto_total": "10000.00",
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
    ],
    "sub_total": "8500.00",
    ...
}
```

**Códigos de descuento:**

| Código | Razón |
|---|---|
| `01` | Oferta comercial |
| `02` | Volumen |
| `03` | Cliente especial |
| `04` | Muestra |
| `05` | Regalia |
| `06` | Bonificación |
| `07` | Descuento general |
| `08` | Precio especial |
| `99` | Otro |

### Exoneraciones

Para líneas con exoneración de IVA (ej: entidades del Estado):

```json
"impuesto": [{
    "codigo": "01",
    "codigo_tarifa_iva": "08",
    "tarifa": "13.00",
    "monto": "1300.00",
    "exoneracion": {
        "tipo_documento_ex1": "01",
        "numero_documento": "EXO-2024-001",
        "nombre_institucion": "Ministerio de Educación",
        "fecha_emision_ex": "2024-01-01T00:00:00-06:00",
        "tarifa_exonerada": "13.00",
        "monto_exoneracion": "1300.00"
    }
}]
```

### Multi-moneda

```json
"resumen_factura": {
    "codigo_tipo_moneda": {
        "codigo_moneda": "USD",
        "tipo_cambio": "520.00"
    },
    ...
}
```

Monedas soportadas: `CRC`, `USD`, `EUR`.

### Multi-sucursal

Los consecutivos son independientes por `establishment + terminal + tipo`. Envía los parámetros en cada request para controlar desde qué punto de venta se emite el comprobante:

```json
{
    "receipt_type": "FE",
    "establishment": 2,
    "terminal": 3,
    ...
}
```

Si no se envían, se usan los defaults de `config/invoicing.php` (`INVOICING_CR_SUCURSAL`, `INVOICING_CR_TERMINAL`).

Para migrar desde otro sistema y configurar el consecutivo inicial:

```bash
php artisan invoicing:set-consecutive FE 100 --establishment=2 --terminal=3
```

---

## Modo sync vs async

| | sync (default) | async |
|---|---|---|
| Respuesta | ~2-5 seg (espera Hacienda) | <100ms inmediato |
| HTTP status | `201 Created` | `202 Accepted` |
| Requisito | Ninguno | Queue worker corriendo |
| Uso ideal | APIs internas, pocos docs | POS, ERP, alto volumen |

```env
INVOICING_CR_SEND_MODE=sync    # espera respuesta de Hacienda en el request
INVOICING_CR_SEND_MODE=async   # devuelve 202 inmediato, encola el envío
```

En modo async, el Job reintenta 3 veces con backoff de 30s, 60s, 90s:

```bash
php artisan queue:work
```

---

## Recepción de documentos

Cuando recibes una factura electrónica de un proveedor y debes responder con un mensaje de aceptación o rechazo:

```bash
POST /invoicing-cr/reception
```

```json
{
    "receipt_type": "FE",
    "consecutive_number": "00100001010000000001",
    "emission_date": "2025-01-15T10:00:00-06:00",
    "reception_status": "1",
    "issuer_name": "Proveedor S.A.",
    "issuer_number": "3101234567",
    "issuer_identification_type": "02",
    "total_voucher": 100000,
    "tax_amount": 13000,
    "economic_activity_code": "6201.0",
    "tax_condition_code": "01",
    "tax_credited": "01"
}
```

**Códigos de `reception_status`:**

| Código | Mensaje de Receptor |
|---|---|
| `1` | Aceptado (CA — Confirmación de Aceptación) |
| `2` | Aceptado Parcialmente (CAP — Confirmación de Aceptación Parcial) |
| `3` | Rechazado (CR — Rechazo) |

El paquete genera el XML de MensajeReceptor, lo firma con XAdES-EPES, y lo envía a la API de Hacienda en un Job en background.

---

## Endpoints REST

| Método | Ruta | Descripción |
|---|---|---|
| `POST` | `/invoicing-cr/receipts` | Crear, firmar y enviar comprobante |
| `GET` | `/invoicing-cr/receipts` | Listar comprobantes |
| `GET` | `/invoicing-cr/receipts/{id}` | Ver comprobante por ID |
| `GET` | `/invoicing-cr/receipts/key/{clave}` | Ver comprobante por clave 50 dígitos |
| `GET` | `/invoicing-cr/receipts/key/{clave}/status` | Consultar estado en Hacienda |
| `POST` | `/invoicing-cr/reception` | Enviar mensaje de recepción |
| `POST` | `/invoicing-cr/webhook` | Webhook de respuesta de Hacienda |

### Filtros en listado

```
GET /invoicing-cr/receipts?type=FE&status=accepted&per_page=25
```

| Parámetro | Valores | Descripción |
|---|---|---|
| `type` | `FE`, `TE`, `NC`, `ND`, `FEC`, `FEE`, `REP` | Filtrar por tipo |
| `status` | `pending`, `sent`, `accepted`, `rejected`, `failed` | Filtrar por estado |
| `per_page` | 1-100 | Resultados por página (default: 25) |

---

## Respuesta de la API

### Crear comprobante (201 sync / 202 async)

```json
{
    "mensaje": "Comprobante creado y enviado.",
    "data": {
        "id": 1,
        "tipo_comprobante": "FE",
        "clave": "50601021800310123456700100001010000000011199999999",
        "referencia_externa": "INV-0024",
        "sucursal": 1,
        "terminal": 1,
        "numero_consecutivo": "00100001010000000001",
        "fecha_emision": "2025-01-15T10:00:00-06:00",
        "enviado_hacienda_en": "2025-01-15T10:00:02-06:00",
        "estado_comprobante": "sent",
        "estado_hacienda": "pending",
        "emisor": {
            "nombre": "Mi Empresa S.A.",
            "numero": "3101234567",
            "tipo_identificacion": "02"
        },
        "receptor": {
            "nombre": "Cliente S.A.",
            "numero": "3101234567",
            "tipo_identificacion": "02"
        },
        "montos": {
            "total_venta": "300000.00000",
            "total_impuesto": "39000.00000",
            "total_descuentos": "0.00000",
            "total_comprobante": "339000.00000",
            "moneda": "CRC",
            "tipo_cambio": "1.00000"
        },
        "condicion_venta": "01",
        "payload": { ... },
        "respuesta_hacienda": null,
        "creado_en": "2025-01-15T10:00:00-06:00",
        "actualizado_en": "2025-01-15T10:00:02-06:00"
    }
}
```

### Error de validación (422)

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "ResumenFactura.TotalComprobante": [
            "El TotalComprobante (339001.00) no coincide con TotalVentaNeta (300000.00) + TotalImpuesto (39000.00) = 339000.00"
        ]
    }
}
```

### Consulta de estado (200)

```json
{
    "data": {
        "estado_comprobante": "accepted",
        "estado_hacienda": "accepted"
    },
    "hacienda": {
        "ind-estado": "aceptado",
        "respuesta-xml": "..."
    }
}
```

---

## Eventos

El paquete dispara Events de Laravel en momentos clave del ciclo de vida. Escúchalos para enviar emails, generar PDFs, notificar por Slack, etc.

| Event | Cuándo | Payload |
|---|---|---|
| `ReceiptCreated` | Al persistir receipt + payload en DB | `Receipt $receipt` |
| `ReceiptSent` | Al enviar a Hacienda (modo sync) | `Receipt $receipt, ?ProviderResponse $response` |
| `ReceiptAccepted` | Webhook recibe respuesta `aceptado` | `Receipt $receipt, ?string $message` |
| `ReceiptRejected` | Webhook recibe respuesta `rechazado` | `Receipt $receipt, ?string $message` |

```php
// app/Providers/AppServiceProvider.php
use FactuTica\FactuticaCR\Events\ReceiptAccepted;
use FactuTica\FactuticaCR\Events\ReceiptRejected;

public function boot(): void
{
    Event::listen(ReceiptAccepted::class, function (ReceiptAccepted $event) {
        // Enviar email al cliente con el XML aceptado
        Mail::to($event->receipt->receiver_email)
            ->queue(new InvoiceAcceptedMail($event->receipt));
    });

    Event::listen(ReceiptRejected::class, function (ReceiptRejected $event) {
        // Notificar al equipo de facturación
        Notification::send(User::admin()->get(), new InvoiceRejectedNotification($event->receipt));
    });
}
```

---

## Comandos Artisan

### Ver y configurar consecutivos

```bash
# Ver todos los consecutivos actuales
php artisan invoicing:set-consecutive --list

# Output:
# Tipo  Sucursal  Terminal  Consecutivo
# FE    1         1         42
# TE    1         1         7

# Configurar consecutivo (el siguiente será 101)
php artisan invoicing:set-consecutive FE 100

# Para sucursal y terminal específicos
php artisan invoicing:set-consecutive FE 50 --establishment=2 --terminal=3
```

### Verificar certificado .p12

```bash
php artisan invoicing:check-certificate

# Output:
# Certificado: /var/www/storage/app/private/cert.p12
# Válido desde: 2023-01-01
# Expira: 2028-01-01 (1095 días restantes)
# Titular: CN=EMPRESA SA, serialNumber=3101234567
```

Genera alerta si el certificado expira en menos de `CERTIFICATE_WARNING_DAYS` (default: 30 días).

### Importar catálogo CABYS

```bash
# Importar ~20,500 códigos del catálogo oficial
php artisan invoicing:sync-cabys

# Limpiar e importar (útil para actualizaciones)
php artisan invoicing:sync-cabys --fresh
```

---

## Catálogo CABYS

El paquete incluye el catálogo oficial CABYS completo (~20,500 códigos) en `data/cabys.json`. Después de importarlo, puedes buscar y validar códigos:

```php
use FactuTica\FactuticaCR\Models\Cabys;

// Buscar por código exacto
$item = Cabys::find('8410101000100');
// => ['codigo' => '8410101000100', 'descripcion' => 'Computadoras portátiles', 'impuesto' => 13]

// Buscar por descripción
$resultados = Cabys::search('computadora')->limit(10)->get();

// Buscar con tarifa de IVA
$gravados = Cabys::whereImpuesto(13)->get();
```

Para habilitar la validación automática de CABYS en cada línea del comprobante:

```env
INVOICING_CR_VALIDATE_CABYS=true
```

---

## Validación

El paquete valida en **3 capas** antes de consumir un consecutivo o tocar la base de datos.

### Capa 1: Estructura (StoreReceiptRequest)

Validación de Laravel: campos requeridos, tipos de dato, formatos. Si falla, retorna `422` sin consumir consecutivo.

### Capa 2: Reglas por tipo (ReceiptTypeRules)

Reglas condicionales según el tipo de comprobante:
- **FE** — requiere `DetalleServicio`, `CodigoActividadEmisor`, receptor con identificación
- **TE** — receptor sin identificación requerida
- **NC/ND** — requiere `InformacionReferencia`
- **FEE** — prohíbe impuestos y exoneraciones (exportación exenta)
- **FEC** — requiere `CodigoActividadReceptor`
- **REP** — solo `MedioPago`, prohíbe detalle de líneas

### Capa 3: Validación matemática (CalculationValidatorService)

5 validators especializados que comprueban que los números cuadren antes de generar el XML:

| Validator | Qué valida |
|---|---|
| `DetailLineValidator` | MontoTotal = Cantidad × PrecioUnitario, SubTotal tras descuentos, MontoTotalLinea |
| `TaxCalculationValidator` | Monto del impuesto = BaseImponible × Tarifa, ImpuestoNeto tras exoneraciones |
| `InvoiceSummaryValidator` | TotalServGravados, TotalGravado, TotalVenta, TotalVentaNeta, TotalComprobante |
| `TaxBreakdownValidator` | TotalDesgloseImpuesto = suma de impuestos por línea |
| `AssortmentValidator` | DatosImpuestoEspecifico e ImpuestoAsumidoEmisorFabrica cuando aplica |

Tolerancia de error: `±0.01` (redondeo de centavos).

### Atomicidad

`createAndSend` está envuelto en `DB::transaction`. Si el XML, la firma, o el envío a Hacienda fallan, se revierte receipt, payload y consecutivo. Nunca quedan documentos huérfanos en estado `pending` por errores técnicos.

---

## Sistema de estados

### Estado del comprobante (`receipt_status`)

| Estado | Descripción |
|---|---|
| `pending` | Creado, pendiente de envío (solo en modo async antes del Job) |
| `sent` | Enviado a Hacienda, esperando respuesta del webhook |
| `accepted` | Hacienda confirmó como aceptado |
| `rejected` | Hacienda rechazó el comprobante |
| `failed` | Error técnico al enviar (ver logs) |

### Estado de Hacienda (`hacienda_status`)

| Estado | Descripción |
|---|---|
| `pending` | Sin respuesta de Hacienda todavía |
| `accepted` | Aceptado por Hacienda |
| `rejected` | Rechazado por Hacienda |

---

## Webhook de Hacienda

Hacienda envía las respuestas de comprobantes de manera asíncrona a:

```
POST /invoicing-cr/webhook
```

El paquete maneja esto automáticamente con **verificación en 2 capas**:

1. **Clave válida** — valida que la clave 50 dígitos pertenezca a un Receipt existente
2. **Firma XAdES** — verifica la firma digital del XML de respuesta de Hacienda

Al recibir respuesta válida:
- Persiste `HaciendaResponse` (estado, mensaje, fecha)
- Actualiza `Receipt.receipt_status` → `accepted` o `rejected`
- Actualiza `Receipt.hacienda_status`
- Dispara `ReceiptAccepted` o `ReceiptRejected`

**El webhook es idempotente** — si Hacienda envía la misma respuesta dos veces, el segundo procesamiento no genera duplicados.

Configura la URL en `.env`:

```env
INVOICING_CR_CALLBACK_URL=api/invoicing-cr/webhook
```

Asegúrate de que esta URL sea accesible desde internet (Hacienda la llama desde sus servidores).

---

## Facade y uso programático

Si desactivaste las rutas (`INVOICING_CR_REGISTER_ROUTES=false`) o quieres usar el paquete desde tu propio código:

```php
use FactuTica\FactuticaCR\Facades\Facturacion;

// Crear y enviar comprobante
$result = Facturacion::createAndSend('FE', $payload);
$receipt = $result['receipt'];

// Consultar por ID
$receipt = Facturacion::getDocumentById(42);

// Consultar por clave
$receipt = Facturacion::getDocumentByUiKey('50601021800310123456700100001010000000011199999999');

// Consultar estado en Hacienda
$status = Facturacion::getDocumentStatus($uiKey);
```

O inyecta el servicio directamente:

```php
use FactuTica\FactuticaCR\Services\InvoicingService;

class MyInvoiceController extends Controller
{
    public function __construct(
        private readonly InvoicingService $invoicing,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $result = $this->invoicing->createAndSend('FE', $request->validated());

        return response()->json(['clave' => $result['receipt']->ui_key], 201);
    }
}
```

**Aliases disponibles:**

| Alias | Servicio |
|---|---|
| `invoicing` | `InvoicingService` |
| `invoicing.signer` | `XmlSignerService` |
| `invoicing.idp` | `HaciendaIdpService` |
| `invoicing.xml` | `XmlGeneratorService` |

---

## Providers personalizados

Si necesitas enviar a un intermediario (broker de facturación) en lugar de directo a Hacienda, puedes registrar tu propio provider:

```php
use FactuTica\FactuticaCR\Contracts\ProviderInterface;
use FactuTica\FactuticaCR\Providers\ProviderResponse;

class MyCustomProvider implements ProviderInterface
{
    public function send(array $payload): ProviderResponse
    {
        // Lógica de envío al intermediario
        $response = Http::post('https://mi-broker.com/api/send', $payload);

        return new ProviderResponse(
            clave: $payload['clave'],
            fecha: now()->toIso8601String(),
            httpStatus: $response->status(),
            signedXml: $payload['comprobanteXml'],
        );
    }

    public function getStatus(string $uiKey): array
    {
        return Http::get("https://mi-broker.com/api/status/{$uiKey}")->json();
    }
}
```

Registra el provider en tu `AppServiceProvider`:

```php
use FactuTica\FactuticaCR\Providers\ProviderFactoryService;

public function boot(): void
{
    $this->app->make(ProviderFactoryService::class)
        ->register('mi-broker', MyCustomProvider::class);
}
```

Luego configúralo:

```env
INVOICING_CR_PROVIDER=mi-broker
```

---

## Testing

```bash
# Tests unitarios + feature (excluye integración con Hacienda real)
vendor/bin/pest --exclude-group=integration

# Tests de integración contra sandbox de Hacienda (requiere .env con credenciales)
vendor/bin/pest --group=integration

# Todos los tests
vendor/bin/pest
```

**Cobertura: 242 tests, 506 assertions**

| Área | Tests |
|---|---|
| Configuración y enums | 10 |
| KeyGeneratorService | 15 |
| CertificateLoaderService | 7 |
| TokenData | 5 |
| XmlGeneratorService | 11 |
| XmlSecurityTest (injection, XXE, CDATA) | 8 |
| ProviderFactoryService | 5 |
| ServiceProvider | 5 |
| Rules (DecimalDinero, ValidateIdentification, ServiceDetailRequired) | 35 |
| DetailLineValidator | 15 |
| TaxCalculationValidator | 16 |
| InvoiceSummaryValidator | 14 |
| TaxBreakdownValidator | 7 |
| AssortmentValidator | 20 |
| CalculationValidatorService | 7 |
| InvoicingService (sync/async) | 6 |
| ReceiptController | 11 |
| ReceptionController | 9 |
| WebhookController | 5 |
| Integración sandbox | 5 |

---

## Arquitectura

```
POST /invoicing-cr/receipts
        │
        ▼
StoreReceiptRequest
  ├── prepareForValidation() → PayloadTransformerService (snake_case → PascalCase)
  └── rules() → validación estructural Laravel + ReceiptTypeRules
        │
        ▼
InvoicingService::createAndSend()
  │
  ├── CalculationValidatorService::validate() (5 validators matemáticos)
  │
  └── DB::transaction()
        │
        ├── ReceiptBuilderService::build()
        │     ├── ReceiptConsecutive (consecutivo por suc+term+tipo)
        │     ├── KeyGeneratorService (clave 50 dígitos + consecutivo 20)
        │     ├── Inyecta Emisor, FechaEmision, ProveedorSistemas
        │     ├── Auto-genera TotalDesgloseImpuesto
        │     ├── Persiste Receipt + ReceiptPayload
        │     └── Dispara ReceiptCreated
        │
        └── [sync] XmlPipelineService::generateSignAndSend()
              ├── XmlGeneratorService (XML v4.4)
              ├── XmlSignerService (XAdES-EPES, RSA-SHA256, exc-c14n)
              ├── HaciendaIdpService (OAuth2 token con cache)
              ├── HaciendaProvider::send() → API Hacienda
              └── Receipt.markAsSent() + ReceiptSent

            [async] SendReceiptToProviderJob::dispatch()
              └── (mismo pipeline, en queue worker)
```

### Modelos y base de datos

| Tabla | Modelo | Descripción |
|---|---|---|
| `invoicing_cr_receipts` | `Receipt` | Comprobante principal |
| `invoicing_cr_receipt_payloads` | `ReceiptPayload` | Payload JSON completo del comprobante |
| `invoicing_cr_receipt_consecutives` | `ReceiptConsecutive` | Consecutivos por tipo+sucursal+terminal |
| `invoicing_cr_hacienda_responses` | `HaciendaResponse` | Respuestas del webhook de Hacienda |
| `invoicing_cr_sent_receipts` | `SentReceipt` | Mensajes de recepción enviados (CA/CAP/CR) |
| `invoicing_cr_cabys` | `Cabys` | Catálogo CABYS (~20,500 códigos) |

---

## Documentación adicional

| Archivo | Descripción |
|---|---|
| `docs/api-reference.md` | Referencia completa de API: todos los campos, tipos, validaciones |
| `docs/architecture.md` | Arquitectura interna, flujos detallados, guía de extensión |
| `docs/request-factura-electronica.md` | Ejemplos completos de requests por tipo de comprobante |
| `docs/flujos.md` | Diagramas Mermaid de todos los flujos end-to-end |
| `docs/validaciones-vs-xsd.md` | Comparación campo por campo con el XSD de Hacienda |
| `docs/catalogo-errores.md` | 30+ puntos de falla documentados con mensajes de error |
| `docs/matriz-cobertura.md` | Estado de implementación por tipo de comprobante y validator |
| `docs/auditoria-final-produccion.md` | Evaluación de calidad del paquete |
| `docs/checklist-produccion.md` | Checklist antes de ir a producción |
| `invoicing-cr.postman_collection.json` | Colección Postman/Bruno con todos los endpoints y casos de prueba |

---

## Licencia

Propietario — FactuTica. Uso comercial, no redistribuir.

Copyright © 2025 FactuTica / Juan Pablo Villaplana Corrales
