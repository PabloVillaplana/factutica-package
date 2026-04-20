# Catálogo de Errores

Todos los puntos de falla del paquete, qué excepción lanzan, y qué respuesta recibe el cliente.

---

## Jerarquía de Excepciones

```
Exception (PHP)
└── HaciendaException (base del paquete)
    ├── InvalidReceiptException (datos inválidos)
    ├── XmlSignerException (errores de firma)
    └── CertificateException (errores de certificado)
```

---

## 1. Errores de Validación (HTTP 422)

Manejados automáticamente por Laravel FormRequest. No llegan al servicio.

| Origen | Mensaje ejemplo | Cuándo ocurre |
|--------|----------------|---------------|
| StoreReceiptRequest | "The receipt_type field is required." | Falta campo requerido |
| StoreReceiptRequest | "The Receptor.Nombre must be at least 3 characters." | Valor fuera de rango |
| StoreReceiptRequest | "The DetalleServicio.LineaDetalle.0.MontoTotalLinea field is required." | Línea sin total |
| StoreReceptionRequest | "The clave must be 50 characters." | Clave incorrecta |
| StoreReceptionRequest | "The reception_status must be accepted, partially_accepted, or rejected." | Status inválido |

**Respuesta al cliente:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "campo": ["mensaje de error"]
  }
}
```

---

## 2. Errores de Formato (HTTP 422)

Reglas de validación personalizadas aplicadas en FormRequests. Retornan 422 como parte de la validación de Laravel.

### ValidateIdentification

Valida el formato del número de identificación según su tipo (campo `Tipo`). Se aplica a `Emisor.Identificacion.Numero` y `Receptor.Identificacion.Numero`.

| Tipo | Mensaje | Regla |
|------|---------|-------|
| 01 (Física) | "La cédula física debe tener exactamente 9 dígitos sin cero inicial." | `/^[1-9]\d{8}$/` |
| 02 (Jurídica) | "La cédula jurídica debe tener exactamente 10 dígitos." | `/^\d{10}$/` |
| 03 (DIMEX) | "El DIMEX debe tener 11 o 12 dígitos sin cero inicial." | `/^[1-9]\d{10,11}$/` |
| 04 (NITE) | "El NITE debe tener exactamente 10 dígitos." | `/^\d{10}$/` |
| 05/06 (Extranjero/No contribuyente) | "La identificación debe ser alfanumérica, máximo 20 caracteres." | `/^[a-zA-Z0-9\s\-]{1,20}$/` |

**Ejemplo de error:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "Emisor.Identificacion.Numero": ["La cédula física debe tener exactamente 9 dígitos sin cero inicial."]
  }
}
```

### DecimalDinero

Valida que valores monetarios cumplan con el formato `DecimalDineroType` del XSD de Hacienda: máximo 13 dígitos enteros y 5 decimales.

| Mensaje | Regla |
|---------|-------|
| "El campo :attribute debe tener máximo 13 dígitos enteros y 5 decimales." | `/^-?\d{0,13}(\.\d{0,5})?$/` |

**Ejemplo de error:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "DetalleServicio.LineaDetalle.0.PrecioUnitario": ["El campo DetalleServicio.LineaDetalle.0.PrecioUnitario debe tener máximo 13 dígitos enteros y 5 decimales."]
  }
}
```

---

## 3. Errores de Validación Matemática (HTTP 422)

Lanzados como `InvalidReceiptException` desde los validators de cálculo. El `ReceiptController::store()` los captura y retorna HTTP 422 con el mensaje de error. Tolerancia de `0.01` en todas las comparaciones numéricas.

### DetailLineValidator

Valida los cálculos aritméticos de cada línea de detalle.

| Validación | Mensaje ejemplo | Fórmula |
|------------|----------------|---------|
| MontoTotal | "Línea 1: MontoTotal (1500) ≠ Cantidad (3) x PrecioUnitario (400) = 1200" | `Cantidad x PrecioUnitario` |
| Descuento excesivo | "Línea 1, Descuento 1: MontoDescuento (2000) no puede ser mayor a MontoTotal (1500)" | `MontoDescuento <= MontoTotal` |
| SubTotal | "Línea 1: SubTotal (1300) ≠ MontoTotal (1500) - Descuentos (100) = 1400" | `MontoTotal - sum(Descuentos)` |
| BaseImponible | "Línea 1: BaseImponible (1500) ≠ SubTotal (1400) + Impuestos selectivos (50) = 1450" | `SubTotal + sum(imp 02,04,05,12)` |
| MontoExportacion | "Línea 1: MontoExportacion (2000) debe estar entre 0 y MontoTotal (1500)" | `0 <= MontoExportacion <= MontoTotal` |
| ImpuestoNeto | "Línea 1: ImpuestoNeto (200) ≠ Impuestos (250) - Exoneraciones (30) - Asumido (0) = 220" | `sum(Monto) - sum(Exoneracion) - Asumido` |
| MontoTotalLinea | "Línea 1: MontoTotalLinea (1700) ≠ SubTotal (1400) + ImpuestoNeto (220) = 1620" | `SubTotal + ImpuestoNeto` |
| UnidadMedida | "Línea 1: UnidadMedida 'XYZ' no es una unidad de medida válida según catálogos de Hacienda" | Catálogo de Hacienda |
| FormaFarmaceutica | "Línea 1: FormaFarmaceutica 'XYZ' no es válida según catálogos de Hacienda" | Catálogo de Hacienda |
| VIN transporte | "Línea 1: NumeroVINoSerie es requerido para servicios de transporte (CABYS 6401)" | CABYS inicia con 64 o 65 |

### TaxCalculationValidator

Valida que el monto de cada impuesto coincida con la fórmula de su código.

| Validación | Mensaje ejemplo |
|------------|----------------|
| Monto impuesto | "Línea 1, Impuesto 1 (código 01): Monto (150) no coincide con el cálculo esperado (182)" |
| Tarifa vs CodigoTarifaIVA | "Línea 1, Impuesto 1: Tarifa (8) no coincide con CodigoTarifaIVA (08) que corresponde a 13%" |
| MontoExoneracion | "Línea 1, Impuesto 1: MontoExoneracion (100) ≠ TarifaExonerada (4%) x Base (1400) = 56" |
| TarifaExonerada excesiva | "Línea 1, Impuesto 1: TarifaExonerada (15) no puede exceder Tarifa (13)" |
| DatosEspecificos requerido | "Línea 1, Impuesto 1: DatosImpuestoEspecifico es requerido para código 03" |
| Tarifa cemento | "Línea 1, Impuesto 1: Tarifa para código 12 (cemento) debe ser 5, recibido: 10" |

**Fórmulas por código de impuesto:**

| Código | Impuesto | Fórmula |
|--------|----------|---------|
| 01 | IVA | `BaseImponible x (Tarifa / 100)` |
| 02 | Selectivo | `SubTotal x (Tarifa / 100)` |
| 03 | Combustible | `CantidadUnidadMedida x ImpuestoUnidad` |
| 04 | Alcohólico | `Cantidad x Proporción x ImpuestoUnidad` |
| 05 | Bebidas sin alcohol | `Cantidad x CantidadUnidadMedida x (ImpuestoUnidad / VolumenUnidadConsumo)` |
| 06 | Tabaco | `Cantidad x CantidadUnidadMedida x ImpuestoUnidad` |
| 07 | IVA Bienes Usados | `BaseImponible x (Tarifa / 100)` |
| 08 | Cemento | `SubTotal x (Tarifa / 100)` |
| 12 | Impuesto Cemento | `SubTotal x (Tarifa / 100)` (tarifa fija = 5) |

### InvoiceSummaryValidator

Valida los cálculos del ResumenFactura.

| Validación | Mensaje ejemplo |
|------------|----------------|
| Subtotales servicio/mercancía | "TotalServGravados (5000) no coincide con el cálculo desde las líneas (4500)" |
| Totales agregados | "TotalGravado (5000) no coincide con el cálculo esperado (4500)" |
| TipoCambio CRC | "TipoCambio debe ser 1 cuando la moneda es CRC, recibido: 570.5" |
| TotalVenta | "TotalVenta (10000) ≠ TotalGravado (5000) + TotalExento (3000) + TotalExonerado (1000) + TotalNoSujeto (500) = 9500" |
| TotalVentaNeta | "TotalVentaNeta (9500) ≠ TotalVenta (10000) - TotalDescuentos (200) = 9800" |
| TotalDescuentos | "TotalDescuentos (500) ≠ suma de descuentos de líneas (300)" |
| TotalImpuesto | "TotalImpuesto (1300) ≠ suma de ImpuestoNeto de líneas (1200)" |
| TotalImpAsumEmisorFabrica | "TotalImpAsumEmisorFabrica (100) ≠ suma de líneas (0)" |
| TotalIVADevuelto | "TotalIVADevuelto (200) ≠ suma de IVA de servicios de salud con tarjeta (150)" |
| TotalOtrosCargos | "TotalOtrosCargos (500) ≠ suma de MontoCargo (400)" |
| TotalComprobante | "TotalComprobante (11000) ≠ TotalVentaNeta (9800) + TotalImpuesto (1200) + TotalOtrosCargos (0) - TotalIVADevuelto (0) = 11000" |

**Clasificación de líneas para subtotales:**

| Criterio | Servicio | Mercancía |
|----------|----------|-----------|
| CABYS inicia con | 5-9 | 0-4 |

| CodigoTarifaIVA | Clasificación |
|-----------------|---------------|
| 02,03,04,06,07,08,09 | Gravado |
| 10 | Exento |
| 01, 11 | No Sujeto |
| (tiene Exoneracion) | Exonerado |

### TaxBreakdownValidator

Valida que `TotalDesgloseImpuesto` del ResumenFactura coincida con la suma de impuestos agrupados por `Codigo + CodigoTarifaIVA` desde las líneas.

| Validación | Mensaje ejemplo |
|------------|----------------|
| Monto no coincide | "TotalDesgloseImpuesto[0] (Código=01, Tarifa=08): TotalMontoImpuesto (1300) ≠ suma de líneas (1200)" |
| Código faltante | "Falta TotalDesgloseImpuesto para código-tarifa [02-] con monto 50" |

### AssortmentValidator

Valida los cálculos del `DetalleSurtido` (combos/paquetes/surtidos).

| Validación | Mensaje ejemplo |
|------------|----------------|
| MontoTotalSurtido | "Línea 1, Surtido 1: MontoTotalSurtido (500) ≠ CantidadSurtido (2) x PrecioUnitarioSurtido (200) = 400" |
| Descuento fuera de rango | "Línea 1, Surtido 1, Descuento 1: MontoDescuentoSurtido (600) fuera de rango [0, 500]" |
| SubTotalSurtido | "Línea 1, Surtido 1: SubTotalSurtido (450) ≠ MontoTotalSurtido (500) - Descuentos (100) = 400" |
| BaseImponibleSurtido | "Línea 1, Surtido 1: BaseImponibleSurtido (420) ≠ SubTotalSurtido (400) + Impuestos selectivos (10) = 410" |
| MontoImpuestoSurtido | "Línea 1, Surtido 1, Impuesto 1: MontoImpuestoSurtido (60) ≠ cálculo esperado (52)" |
| DatosEspecificos requerido | "Línea 1, Surtido 1, Impuesto 1: DatosImpuestoEspecificoSurtido requerido para código 04" |
| TarifaSurtido requerida | "Línea 1, Surtido 1, Impuesto 1: TarifaSurtido requerida para código 01" |
| CodigoTarifaIVASurtido | "Línea 1, Surtido 1, Impuesto 1: CodigoTarifaIVASurtido requerido cuando código es 01" |
| Tarifa IVA inconsistente | "Línea 1, Surtido 1, Impuesto 1: TarifaSurtido (8) no coincide con CodigoTarifaIVASurtido (08) = 13%" |
| UnidadMedidaSurtido | "Línea 1, Surtido 1: UnidadMedidaSurtido 'XYZ' no es válida según catálogos" |

**Respuesta al cliente (todos los validators matemáticos):**
```json
{
  "message": "Línea 1: MontoTotal (1500) ≠ Cantidad (3) × PrecioUnitario (400) = 1200"
}
```

---

## 4. Errores de Tipo de Comprobante

| Excepción | Mensaje | Origen | HTTP |
|-----------|---------|--------|------|
| InvalidReceiptException | "Tipo de comprobante no válido: [XYZ]" | InvoicingService:43 | 422 |
| InvalidReceiptException | "Tipo de comprobante [REP] no soportado para generación XML." | XmlGeneratorService:114 | 422 |
| InvalidReceiptException | "Tipo de comprobante sin código: XYZ" | KeyGeneratorService:107 | 422 |

---

## 5. Errores de Generación de Clave

| Excepción | Mensaje | Origen | HTTP |
|-----------|---------|--------|------|
| InvalidReceiptException | "Sucursal debe estar entre 1 y 999, recibido: N" | KeyGeneratorService:95 | 422 |
| InvalidReceiptException | "Terminal debe estar entre 1 y 99999, recibido: N" | KeyGeneratorService:99 | 422 |
| InvalidReceiptException | "Consecutivo debe estar entre 1 y 9999999999, recibido: N" | KeyGeneratorService:103 | 422 |
| InvalidReceiptException | "La clave generada tiene N dígitos, se esperan 50." | KeyGeneratorService:71 | 422 |

---

## 6. Errores de Identificación

| Excepción | Mensaje | Origen | HTTP |
|-----------|---------|--------|------|
| InvalidReceiptException | "El número de identificación debe contener solo dígitos." | KeyGeneratorService:130 | 422 |
| InvalidReceiptException | "Cédula física debe tener máximo 9 dígitos, recibido: N" | KeyGeneratorService:138 | 422 |
| InvalidReceiptException | "Cédula jurídica debe tener máximo 10 dígitos, recibido: N" | KeyGeneratorService:142 | 422 |
| InvalidReceiptException | "DIMEX debe tener 11 o 12 dígitos, recibido: N" | KeyGeneratorService:147 | 422 |
| InvalidReceiptException | "NITE debe tener máximo 10 dígitos, recibido: N" | KeyGeneratorService:152 | 422 |

---

## 7. Errores de XML

| Excepción | Mensaje | Origen | HTTP |
|-----------|---------|--------|------|
| InvalidReceiptException | "Error generando XML: {detalle}" | XmlGeneratorService:55 | 422 |

**Causa común:** campo requerido por el XML faltante en `$data` (ej: `Emisor.Nombre` null).

---

## 8. Errores de Certificado (.p12)

| Excepción | Mensaje | Cuándo ocurre | HTTP |
|-----------|---------|---------------|------|
| CertificateException | "La ruta del certificado y el PIN son requeridos." | Config vacía | 500 |
| CertificateException | "El certificado no existe en la ruta: /path" | Archivo no encontrado | 500 |
| CertificateException | "No se pudo leer el certificado: /path" | Permisos de archivo | 500 |
| CertificateException | "No se pudo abrir el certificado .p12. Verifique el PIN. OpenSSL: ..." | PIN incorrecto o .p12 corrupto | 500 |
| CertificateException | "El certificado está expirado. Venció el: YYYY-MM-DD" | Certificado vencido | 500 |
| CertificateException | "No se pudo leer la información de expiración del certificado." | .p12 sin info de expiración | 500 |
| CertificateException | "No se pudo parsear el certificado X509." | Certificado malformado | 500 |
| CertificateException | "El certificado no contiene un Issuer válido." | Falta issuer DN | 500 |
| CertificateException | "El certificado no ha sido cargado. Llame a load() primero." | Uso antes de load() | 500 |

---

## 9. Errores de Firma Digital

| Excepción | Mensaje | Cuándo ocurre | HTTP |
|-----------|---------|---------------|------|
| XmlSignerException | "Error cargando certificado para firma: {detalle}" | CertificateException propagada | 500 |
| XmlSignerException | "El XML proporcionado no es válido." | XML malformado | 500 |
| XmlSignerException | "No se encontró el nodo SignedProperties en el XML construido." | Error interno de firma | 500 |
| XmlSignerException | "No se pudo cargar la llave privada del certificado." | Private key inválida | 500 |
| XmlSignerException | "Error al firmar: {openssl_error}" | OpenSSL falla al firmar | 500 |
| XmlSignerException | "Error al serializar el documento XML." | DOMDocument::saveXML falla | 500 |
| XmlSignerException | "No se encontró el tag de cierre del elemento raíz: TAG" | XML sin cierre | 500 |

---

## 10. Errores de Autenticación (IdP)

| Excepción | Mensaje | Cuándo ocurre | HTTP |
|-----------|---------|---------------|------|
| HaciendaException | "Error de autenticación con el IdP de Hacienda: {detalle}" | Credenciales inválidas o IdP caído | 500 |

**Nota:** Si el refresh token falla, se reintenta con authenticate(). Solo lanza excepción si ambos fallan.

---

## 11. Errores de Envío a Hacienda

| Excepción | Mensaje | Cuándo ocurre | HTTP |
|-----------|---------|---------------|------|
| HaciendaException | "Error al enviar comprobante a Hacienda: {body}" | API retorna error (4xx/5xx) | 500 (code=HTTP status) |
| HaciendaException | "Error al consultar estado en Hacienda: {body}" | GET /recepcion/{key} falla | 502 (en ReceiptController) |

**Códigos HTTP de Hacienda comunes:**
- 400: Payload malformado
- 401: Token expirado/inválido
- 403: Sin permisos
- 404: Comprobante no encontrado (en consulta status)
- 500: Error interno de Hacienda

---

## 12. Errores de Webhook

| Excepción | Mensaje | Cuándo ocurre | HTTP |
|-----------|---------|---------------|------|
| — | "Clave inválida." | strlen(clave) !== 50 | 422 |
| HaciendaException | "Comprobante no encontrado para clave: {clave}" | Receipt no existe en DB | 404 |
| HaciendaException | "Webhook rechazado: {reason}" | Verificación de firma/clave falla | 404 |

---

## 13. Errores en Jobs (Async)

| Escenario | Comportamiento | Reintentos |
|-----------|---------------|:----------:|
| Error generando XML | Job falla, reintenta | 3 (30s, 60s, 90s) |
| Error firmando | Job falla, reintenta | 3 |
| Error de autenticación IdP | Job falla, reintenta | 3 |
| Error de Hacienda (5xx) | Job falla, reintenta | 3 |
| Error de Hacienda (4xx) | Job falla, reintenta | 3 |
| Todos los reintentos fallan | `markAsFailed()` → receipt_status = failed | — |

**Log en fallo:** `{JobName}: falló` con id, receipt_type, error message.

---

## Mapa de Propagación de Errores

```
POST /receipts
├── 422 ← StoreReceiptRequest (validación FormRequest)
├── 422 ← ValidateIdentification / DecimalDinero (reglas de formato)
├── 422 ← InvalidReceiptException (tipo, clave, ID, XML, cálculos matemáticos)
│         ├── DetailLineValidator (MontoTotal, SubTotal, ImpuestoNeto, etc.)
│         ├── TaxCalculationValidator (Monto impuesto, Tarifa, Exoneracion)
│         ├── InvoiceSummaryValidator (ResumenFactura totales)
│         ├── TaxBreakdownValidator (TotalDesgloseImpuesto)
│         └── AssortmentValidator (DetalleSurtido cálculos)
├── 500 ← CertificateException (cert no encontrado, expirado, PIN)
├── 500 ← XmlSignerException (firma falla)
├── 500 ← HaciendaException (IdP auth, envío falla)
└── 201 ← éxito

POST /reception
├── 422 ← StoreReceptionRequest (validación)
└── 202 ← siempre (job async)
    └── Job falla silenciosamente → markAsFailed()

POST /webhook
├── 422 ← clave inválida (largo != 50)
├── 404 ← receipt no encontrado o verificación falla
└── 200 ← procesado

GET /receipts/key/{key}/status
├── 404 ← receipt no existe
├── 502 ← Hacienda no responde
└── 200 ← éxito
```
