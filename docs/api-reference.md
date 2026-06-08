# API Reference — FactuTica Laravel Package

Referencia completa de todos los endpoints, campos, tipos de dato, y respuestas.

---

## Contenido

- [Convenciones](#convenciones)
- [Endpoints](#endpoints)
  - [POST /receipts — Crear comprobante](#post-invoicing-crreceipts)
  - [GET /receipts — Listar](#get-invoicing-crreceipts)
  - [GET /receipts/{id} — Por ID](#get-invoicing-crreceiptsid)
  - [GET /receipts/key/{clave} — Por clave](#get-invoicing-crreceiptskeyclave)
  - [GET /receipts/key/{clave}/status — Estado en Hacienda](#get-invoicing-crreceiptskeyclavestatus)
  - [POST /reception — Mensaje de receptor](#post-invoicing-crreception)
  - [POST /webhook — Callback de Hacienda](#post-invoicing-crwebhook)
- [Campos del comprobante](#campos-del-comprobante)
  - [Raíz](#raíz)
  - [Receptor](#receptor)
  - [DetalleServicio](#detalleservicio)
  - [LineaDetalle](#lineadetalle)
  - [Impuesto](#impuesto)
  - [Exoneracion](#exoneracion)
  - [DatosImpuestoEspecifico](#datosimpuestoespecifico)
  - [Descuento](#descuento)
  - [ResumenFactura](#resumenfactura)
  - [MedioPago](#mediopago)
  - [InformacionReferencia](#informacionreferencia)
- [Catálogos de valores](#catálogos-de-valores)
- [Formato de respuestas](#formato-de-respuestas)
- [Errores](#errores)

---

## Convenciones

- Todos los requests van en `application/json`
- Los campos del request van en **snake_case** (el paquete los transforma a PascalCase internamente)
- Los montos son **strings decimales** con hasta 5 decimales (ej: `"10000.00"`)
- Las fechas son **ISO 8601** con timezone Costa Rica (ej: `"2025-01-15T10:00:00-06:00"`)
- Los errores de validación retornan `422` con el campo `errors` en formato estándar de Laravel

---

## Endpoints

### POST /invoicing-cr/receipts

Crea, firma, y envía un comprobante electrónico a Hacienda.

**Headers:**
```
Content-Type: application/json
Authorization: Bearer {token}    (si tienes middleware auth configurado)
```

**Respuesta sync (201):**
```json
{
    "mensaje": "Comprobante creado y enviado.",
    "data": { ... }    // ReceiptResource completo
}
```

**Respuesta async (202):**
```json
{
    "mensaje": "Comprobante creado y encolado para envío.",
    "data": { ... }    // ReceiptResource con estado "pending"
}
```

**Respuesta error validación (422):**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "ResumenFactura.TotalComprobante": ["El TotalComprobante no coincide..."]
    }
}
```

---

### GET /invoicing-cr/receipts

Lista comprobantes con paginación y filtros opcionales.

**Query parameters:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `type` | string | Filtrar por tipo: `FE`, `TE`, `NC`, `ND`, `FEC`, `FEE`, `REP` |
| `status` | string | Filtrar por estado: `pending`, `sent`, `accepted`, `rejected`, `failed` |
| `per_page` | integer | Resultados por página (default: 25, max: 100) |

**Respuesta (200):**
```json
{
    "data": [ ... ],    // Array de ReceiptResource
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 25,
        "total": 112
    }
}
```

---

### GET /invoicing-cr/receipts/{id}

Retorna un comprobante por ID de base de datos.

**Respuesta (200):**
```json
{
    "data": { ... }    // ReceiptResource completo con payload y respuesta Hacienda
}
```

**Respuesta (404):** Comprobante no encontrado.

---

### GET /invoicing-cr/receipts/key/{clave}

Retorna un comprobante por su clave de 50 dígitos.

**Parámetro:** `clave` — clave única de 50 dígitos generada por el paquete.

**Respuesta (200):**
```json
{
    "data": { ... }    // ReceiptResource completo
}
```

---

### GET /invoicing-cr/receipts/key/{clave}/status

Consulta el estado del comprobante tanto en la DB local como directamente en la API de Hacienda.

**Respuesta (200):**
```json
{
    "data": {
        "estado_comprobante": "accepted",
        "estado_hacienda": "accepted"
    },
    "hacienda": {
        "ind-estado": "aceptado",
        "respuesta-xml": "PD94bWwgdmVyc2lvbj0iMS4wIi..."
    }
}
```

**Respuesta (502):** Si Hacienda no está disponible:
```json
{
    "data": { ... },
    "hacienda": null,
    "error": "Error al consultar estado en Hacienda: ..."
}
```

---

### POST /invoicing-cr/reception

Registra la recepción de un comprobante de un proveedor y envía el MensajeReceptor (CA/CAP/CR) a Hacienda.

**Body:**

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `receipt_type` | string | Sí | Tipo del comprobante recibido: `FE`, `TE`, etc. |
| `consecutive_number` | string | Sí | Consecutivo 20 dígitos del comprobante recibido |
| `emission_date` | string | Sí | Fecha de emisión del comprobante original (ISO 8601) |
| `reception_status` | string | Sí | `1`=Aceptado, `2`=Aceptado parcial, `3`=Rechazado |
| `issuer_name` | string | Sí | Nombre del emisor del comprobante recibido |
| `issuer_number` | string | Sí | Número de cédula del emisor |
| `issuer_identification_type` | string | Sí | Tipo de identificación del emisor |
| `total_voucher` | numeric | Sí | Total del comprobante recibido |
| `tax_amount` | numeric | No | Monto de impuesto del comprobante recibido |
| `reception_code` | string | No | Código de recepción |
| `reception_message` | string | No | Mensaje de recepción (si es rechazo, indicar razón) |
| `economic_activity_code` | string | No | Código de actividad económica del receptor |
| `tax_condition_code` | string | No | Código de condición del impuesto |
| `tax_credited` | string | No | Código del impuesto acreditado |
| `receiver_name` | string | No | Nombre del receptor (default: desde config) |
| `receiver_number` | string | No | Cédula del receptor (default: desde config) |
| `receiver_identification_type` | string | No | Tipo de ID del receptor (default: desde config) |

**Respuesta (202):**
```json
{
    "mensaje": "Mensaje de recepción creado y encolado.",
    "data": {
        "id": 1,
        "tipo_comprobante": "FE",
        "estado_comprobante": "pending"
    }
}
```

---

### POST /invoicing-cr/webhook

Endpoint receptor del callback asíncrono de Hacienda. **No requiere autenticación** (la seguridad la maneja `WebhookVerifierService` con verificación de clave + firma XML).

Hacienda llama este endpoint con el resultado de cada comprobante enviado. El paquete:
1. Verifica que la clave pertenezca a un Receipt existente
2. Verifica la firma XAdES del XML de respuesta
3. Actualiza el estado del Receipt
4. Dispara `ReceiptAccepted` o `ReceiptRejected`

**Body enviado por Hacienda:**
```json
{
    "clave": "50601021800310123456700100001010000000011199999999",
    "ind-estado": "aceptado",
    "fecha": "2025-01-15T10:00:00-06:00",
    "respuesta-xml": "PD94bWwgdmVyc2lvbj0iMS4wIi..."
}
```

**Respuesta (200):** `{ "ok": true }`

---

## Campos del comprobante

### Raíz

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `receipt_type` | string | **Sí** | `FE`, `TE`, `NC`, `ND`, `FEC`, `FEE`, `REP` |
| `condicion_venta` | string | **Sí** | Código de condición de venta (ver catálogo) |
| `condicion_venta_otros` | string | No | Descripción si `condicion_venta` = `06` |
| `plazo_credito` | string | No | Días de crédito si `condicion_venta` = `02` |
| `codigo_actividad_emisor` | string | Cond. | Requerido para FE, TE, ND, NC, FEE. Código de actividad económica (ej: `6201.0`) |
| `codigo_actividad_receptor` | string | Cond. | Requerido para FEC. Actividad económica del receptor. |
| `external_reference` | string | No | Referencia externa al sistema del cliente. Máx 100 chars. |
| `establishment` | integer | No | Sucursal (1-999). Default desde config. |
| `terminal` | integer | No | Terminal/caja (1-99999). Default desde config. |
| `emisor` | object | No | Datos del emisor. Si se omite, se toman de `config/invoicing.php`. |
| `receptor` | object | Cond. | Requerido en FE. Opcional en TE (sin identificación). |
| `detalle_servicio` | object | Cond. | Requerido en FE, TE, FEE, FEC, ND. Opcional en NC, REP. |
| `resumen_factura` | object | **Sí** | Totales del comprobante. |
| `informacion_referencia` | array | Cond. | Requerido en NC y ND. |
| `otros_cargos` | array | No | Cargos adicionales al comprobante. |

### Receptor

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `nombre` | string | **Sí** | Nombre del receptor. Máx 100 chars. |
| `identificacion` | object | Cond. | Requerido en FE, FEC. No requerido en TE. |
| `identificacion.tipo` | string | Cond. | `01`=Física, `02`=Jurídica, `03`=DIMEX, `04`=NITE, `05`=Extranjero, `06`=NoContribuyente |
| `identificacion.numero` | string | Cond. | Número de cédula/identificación |
| `nombre_comercial` | string | No | Nombre comercial del receptor |
| `ubicacion` | object | No | Provincia, cantón, distrito, otras señas |
| `telefono` | object | No | `codigo_pais` + `num_telefono` |
| `correo_electronico` | array | No | Lista de emails (se incluyen todos en el XML) |
| `otras_senas_extranjero` | string | Cond. | Requerido para receptores extranjeros (tipo `05`) |

### DetalleServicio

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `linea_detalle` | array | **Sí** | Al menos 1 línea de detalle. Máx 1000 líneas. |

### LineaDetalle

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `numero_linea` | integer | **Sí** | Número de línea (correlativo, empieza en 1) |
| `codigo_cabys` | string | **Sí** | Código CABYS de 13 dígitos |
| `codigo_comercial` | array | No | Códigos comerciales del producto |
| `cantidad` | string | **Sí** | Cantidad en string decimal (ej: `"2.5"`) |
| `unidad_medida` | string | **Sí** | Código de unidad de medida (ver catálogo) |
| `unidad_medida_comercial` | string | No | Unidad de medida comercial (descripción libre) |
| `detalle` | string | **Sí** | Descripción del bien o servicio. Máx 200 chars. |
| `precio_unitario` | string | **Sí** | Precio unitario sin impuestos |
| `monto_total` | string | **Sí** | `Cantidad × PrecioUnitario` |
| `descuento` | array | No | Lista de descuentos aplicados (máx 5) |
| `sub_total` | string | **Sí** | `MontoTotal - SumaDescuentos` |
| `base_imponible` | string | Cond. | Base de cálculo del impuesto (= SubTotal si no hay exoneración) |
| `monto_exportacion` | string | Cond. | Requerido en FEE cuando la línea es de exportación |
| `impuesto` | array | No | Lista de impuestos aplicados a la línea |
| `impuesto_neto` | string | Cond. | Suma de `Impuesto.ImpuestoNeto` de todos los impuestos de la línea |
| `monto_total_linea` | string | **Sí** | `SubTotal + ImpuestoNeto` |
| `impuesto_asumido_emisor_fabrica` | string | No | Monto de impuesto asumido por el emisor (default: `"0"`) |
| `datos_impuesto_especifico` | object | Cond. | Datos de impuesto específico (licores, tabaco, combustibles) |

**Fórmulas de validación:**
```
MontoTotal = Cantidad × PrecioUnitario  (±0.01)
SubTotal = MontoTotal - Σ(Descuento.MontoDescuento)  (±0.01)
ImpuestoNeto = Impuesto.Monto - Impuesto.Exoneracion.MontoExoneracion  (±0.01)
MontoTotalLinea = SubTotal + ImpuestoNeto  (±0.01)
```

### Impuesto

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `codigo` | string | **Sí** | `01`=IVA, `02`=Específico, `03`=Aduanero, `04`=Otro |
| `codigo_tarifa_iva` | string | Cond. | Requerido si `codigo` = `01`. Ver catálogo de tarifas. |
| `codigo_impuesto_otro` | string | Cond. | Requerido si `codigo` = `04` |
| `tarifa` | string | **Sí** | Porcentaje de la tarifa (ej: `"13.00"`) |
| `factor_calculo_iva` | string | No | Factor de cálculo del IVA |
| `monto` | string | **Sí** | `BaseImponible × (Tarifa / 100)` |
| `exoneracion` | object | No | Datos de exoneración aplicada |

**Códigos de tarifa IVA:**

| Código | Tarifa | Descripción |
|---|---|---|
| `01` | 0% | Exento |
| `02` | 1% | Canasta básica (reducida) |
| `03` | 2% | Medicamentos, materias primas agrícolas |
| `04` | 4% | Servicios profesionales electrónicos |
| `05` | 8% | Bienes y servicios (reducida) |
| `06` | 13% | Tarifa general |
| `07` | 2% | Insumos agropecuarios |
| `08` | 13% | Tarifa general (alias de 06, más común en uso) |
| `12` | 4% | Servicios de salud |
| `13` | 2% | Medicamentos de control especial |

### Exoneracion

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `tipo_documento_ex1` | string | **Sí** | Tipo de documento de exoneración |
| `tipo_documento_otro` | string | No | Descripción si `tipo_documento_ex1` = otro |
| `numero_documento` | string | **Sí** | Número del documento de exoneración |
| `articulo` | string | No | Artículo de ley |
| `inciso` | string | No | Inciso del artículo |
| `nombre_institucion` | string | **Sí** | Institución que otorga la exoneración |
| `fecha_emision_ex` | string | **Sí** | Fecha del documento de exoneración (ISO 8601) |
| `tarifa_exonerada` | string | **Sí** | Porcentaje exonerado (ej: `"13.00"`) |
| `monto_exoneracion` | string | **Sí** | `BaseImponible × (TarifaExonerada / 100)` |

### DatosImpuestoEspecifico

Requerido para productos con impuesto específico (licores, cigarrillos, combustibles):

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `cantidad_unidad_medida` | string | No | Cantidad por unidad de medida |
| `porcentaje` | string | No | Porcentaje del impuesto específico |
| `proporcion` | string | No | Proporción del impuesto |
| `volumen_unidad_consumo` | string | No | Volumen por unidad de consumo |
| `impuesto_unidad` | string | No | Impuesto por unidad |

### Descuento

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `monto_descuento` | string | **Sí** | Monto del descuento |
| `codigo_descuento` | string | **Sí** | Código de razón del descuento (ver catálogo) |
| `codigo_descuento_otro` | string | No | Descripción si `codigo_descuento` = `99` |
| `naturaleza_descuento` | string | No | Descripción libre del descuento |

### ResumenFactura

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `codigo_tipo_moneda` | object | **Sí** | `codigo_moneda` + `tipo_cambio` |
| `total_serv_gravados` | string | No | Total de servicios gravados |
| `total_serv_exentos` | string | No | Total de servicios exentos |
| `total_serv_exonerado` | string | No | Total de servicios exonerados |
| `total_serv_no_sujeto` | string | No | Total de servicios no sujetos |
| `total_mercancias_gravadas` | string | No | Total de mercancías gravadas |
| `total_mercancias_exentas` | string | No | Total de mercancías exentas |
| `total_merc_exonerada` | string | No | Total de mercancías exoneradas |
| `total_merc_no_sujeta` | string | No | Total de mercancías no sujetas |
| `total_gravado` | string | No | Suma de `TotalServGravados + TotalMercanciasGravadas` |
| `total_exento` | string | No | Suma de `TotalServExentos + TotalMercanciasExentas` |
| `total_exonerado` | string | No | Suma de exonerados |
| `total_no_sujeto` | string | No | Suma de no sujetos |
| `total_venta` | string | **Sí** | Suma de todos los subtotales de líneas |
| `total_descuentos` | string | No | Suma de todos los descuentos |
| `total_venta_neta` | string | **Sí** | `TotalVenta - TotalDescuentos` |
| `total_impuesto` | string | No | Suma de todos los impuestos netos |
| `total_iva_devuelto` | string | No | IVA devuelto (crédito fiscal) |
| `total_otros_cargos` | string | No | Otros cargos al comprobante |
| `total_comprobante` | string | **Sí** | `TotalVentaNeta + TotalImpuesto + TotalOtrosCargos` |
| `medio_pago` | array | **Sí** | Medios de pago. Al menos 1. |

**Fórmula principal de validación:**
```
TotalComprobante = TotalVentaNeta + TotalImpuesto + TotalOtrosCargos  (±0.01)
TotalVentaNeta = TotalVenta - TotalDescuentos  (±0.01)
```

### MedioPago

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `tipo_medio_pago` | string | **Sí** | Código del medio de pago (ver catálogo) |
| `medio_pago_otros` | string | No | Descripción si `tipo_medio_pago` = `99` |
| `total_medio_pago` | string | No | Monto pagado con este medio |

**Códigos de medio de pago:**

| Código | Descripción |
|---|---|
| `01` | Efectivo |
| `02` | Tarjeta |
| `03` | Cheque |
| `04` | Transferencia bancaria |
| `05` | Recaudado por terceros |
| `06` | Monedero electrónico |
| `07` | En especie |
| `08` | Bonos |
| `09` | SINPE Móvil |
| `99` | Otro |

### InformacionReferencia

Requerido en NC y ND. Apunta al comprobante que se está corrigiendo.

| Campo | Tipo | Requerido | Descripción |
|---|---|:---:|---|
| `tipo_doc` | string | **Sí** | Tipo del comprobante referenciado (`01`=FE, `04`=TE, etc.) |
| `numero` | string | **Sí** | Clave de 50 dígitos del comprobante referenciado |
| `fecha_emision_ref` | string | **Sí** | Fecha de emisión del comprobante referenciado (ISO 8601) |
| `codigo` | string | **Sí** | Código de razón de la referencia (ver catálogo) |
| `razon` | string | No | Descripción de la razón |

---

## Catálogos de valores

### Condición de venta

| Código | Descripción |
|---|---|
| `01` | Contado |
| `02` | Crédito |
| `03` | Consignación |
| `04` | Apartado |
| `05` | Arrendamiento con opción de compra |
| `06` | Arrendamiento en función financiera |
| `07` | Cobro a favor del estado |
| `08` | Servicios prestados al estado a crédito |
| `09` | Pago del servicios prestado al estado |
| `99` | Otras condiciones |

### Tipo de identificación

| Código | Descripción |
|---|---|
| `01` | Cédula Física |
| `02` | Cédula Jurídica |
| `03` | DIMEX (Residentes) |
| `04` | NITE |
| `05` | Identificación extranjero |
| `06` | No contribuyente |

### Monedas

| Código | Descripción |
|---|---|
| `CRC` | Colón costarricense |
| `USD` | Dólar estadounidense |
| `EUR` | Euro |

### Unidades de medida (más comunes)

| Código | Descripción |
|---|---|
| `Sp` | Servicios profesionales |
| `unid` | Unidad |
| `kg` | Kilogramo |
| `g` | Gramo |
| `m` | Metro |
| `m2` | Metro cuadrado |
| `m3` | Metro cúbico |
| `L` | Litro |
| `ml` | Mililitro |
| `h` | Hora |
| `km` | Kilómetro |
| `Os` | Otro |

> El catálogo completo de unidades de medida está en `config/catalogues.php`.

---

## Formato de respuestas

### ReceiptResource

```json
{
    "id": 1,
    "tipo_comprobante": "FE",
    "clave": "50601021800310123456700100001010000000011199999999",
    "referencia_externa": "INV-0024",
    "sucursal": 1,
    "terminal": 1,
    "numero_consecutivo": "00100001010000000001",
    "fecha_emision": "2025-01-15T10:00:00-06:00",
    "enviado_hacienda_en": "2025-01-15T10:00:02-06:00",
    "estado_comprobante": "accepted",
    "estado_hacienda": "accepted",
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
    "payload": {
        "CondicionVenta": "01",
        "CodigoActividadEmisor": "6201.0",
        "Emisor": { ... },
        "Receptor": { ... },
        "DetalleServicio": { ... },
        "ResumenFactura": { ... }
    },
    "respuesta_hacienda": {
        "estado": "accepted",
        "mensaje": "Comprobante aceptado",
        "fecha": "2025-01-15T10:00:30-06:00"
    },
    "creado_en": "2025-01-15T10:00:00-06:00",
    "actualizado_en": "2025-01-15T10:00:30-06:00"
}
```

---

## Errores

### Errores de validación estructural (422)

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "receipt_type": ["El campo receipt_type es obligatorio."],
        "receptor.identificacion.numero": ["El número de cédula jurídica debe tener máximo 10 dígitos."],
        "ResumenFactura.TotalComprobante": ["El TotalComprobante debe ser un número decimal válido."]
    }
}
```

### Errores de cálculo matemático (422)

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "DetalleServicio.LineaDetalle.0.MontoTotalLinea": [
            "El MontoTotalLinea (10500.00) no coincide con SubTotal (10000.00) + ImpuestoNeto (1300.00) = 11300.00"
        ]
    }
}
```

### Error de Hacienda (502)

```json
{
    "hacienda": null,
    "error": "Error al consultar estado en Hacienda: HTTP 503 Service Unavailable"
}
```

### Error de tipo inválido (422)

```json
{
    "mensaje": "Tipo de comprobante no válido: [XX]"
}
```

### Excepciones del paquete

| Excepción | Cuándo se lanza |
|---|---|
| `InvalidReceiptException` | Datos inválidos, tipo desconocido, consecutivo fuera de rango |
| `HaciendaException` | Error en la API de Hacienda (HTTP ≠ 200/202) |
| `XmlSignerException` | Error al firmar el XML con el certificado |
| `CertificateException` | Certificado .p12 no encontrado, PIN incorrecto, expirado |
