# Comparacion: Cyberfuel (GV Express) vs laravel-paquete-facturacion

**Fecha:** 5 de abril de 2026 (actualizado)
**Basado en:** Request real de produccion de GV Express CR SRL via Cyberfuel API

---

## Contexto

GV Express CR usa Cyberfuel como proveedor de facturacion electronica. Este documento compara el formato de request de Cyberfuel contra el de nuestro paquete `laravel-paquete-facturacion`, identifica que hace cada uno mejor, y propone mejoras concretas.

---

## 1. Estructura general del request

### Cyberfuel

```json
{
    "api_key": "OdP8gPM91cN",
    "referencia_externa": "GVT255915",
    "clave": {
        "sucursal": 1,
        "terminal": 3,
        "tipo": "01",
        "comprobante": 115461,
        "pais": "506",
        "dia": "31",
        "mes": "03",
        "anno": "26",
        "situacion_presentacion": "1",
        "codigo_seguridad": "76538363"
    },
    "encabezado": {
        "codigo_actividad": "630901",
        "fecha": "2026-03-31T17:03:03-06:00",
        "condicion_venta": "01",
        "CodigoActividadReceptor": "5229.0"
    },
    "emisor": { ... },
    "detalle": [ ... ],
    "resumen": { ... },
    "receptor": { ... },
    "envio": {
        "aplica": "1",
        "logo": "data:image/jpg;base64,...",
        "texto": "...",
        "emisor": { "correo": "..." },
        "receptor": { "correo": "..." }
    }
}
```

### Nuestro paquete

```json
{
    "receipt_type": "FE",
    "condicion_venta": "01",
    "codigo_actividad_emisor": "6309.0",
    "codigo_actividad_receptor": "5229.0",
    "receptor": { ... },
    "detalle_servicio": { "linea_detalle": [ ... ] },
    "resumen_factura": { ... }
}
```

### Diferencias estructurales

| Aspecto | Cyberfuel | Nuestro paquete |
|---|---|---|
| Autenticacion | `api_key` en el body | OAuth2 Bearer token (automatico via IdP) |
| Clave | Objeto desglosado que el cliente controla | Auto-generada por KeyGeneratorService (sucursal/terminal configurables) |
| Emisor | Se envia completo en cada request | Auto-inyectado desde config/env |
| Fecha de emision | El cliente la envia | Auto-generada (`now()`) |
| Referencia externa | `referencia_externa: "GVT255915"` | `external_reference` (opcional, max 100 chars) |
| Envio por correo | Bloque `envio` con logo, texto, correos | No existe — solo envia a Hacienda |
| Consecutivo | Parte del objeto `clave` | Auto-generado por ReceiptConsecutive |
| Firma XML | Cyberfuel firma el XML | Nuestro paquete firma con XAdES-EPES |
| PDF | Cyberfuel genera PDF con logo | No generamos PDF |

---

## 2. Mapeo de campos

### Encabezado / Raiz

| Cyberfuel | Nuestro paquete | Notas |
|---|---|---|
| `encabezado.codigo_actividad` | `codigo_actividad_emisor` | Cyberfuel: "630901", Nuestro: "6309.0" (ambos 6 chars) |
| `encabezado.fecha` | Auto-generado | Nosotros no requerimos que el cliente envie fecha |
| `encabezado.condicion_venta` | `condicion_venta` | Mismo valor |
| `encabezado.CodigoActividadReceptor` | `codigo_actividad_receptor` | |
| `clave.sucursal` | `establishment` (opcional, default config) | Ambos soportan multi-sucursal |
| `clave.terminal` | `terminal` (opcional, default config) | Ambos soportan multi-terminal |
| `referencia_externa` | `external_reference` | Opcional, max 100 chars, indexado |

### Detalle de lineas

| Cyberfuel | Nuestro paquete |
|---|---|
| `detalle[].numero` | `detalle_servicio.linea_detalle[].numero_linea` |
| `detalle[].codigo_hacienda` | `...codigo_cabys` |
| `detalle[].codigo[].tipo` | `...codigo_comercial[].tipo` |
| `detalle[].codigo[].codigo` | `...codigo_comercial[].codigo` |
| `detalle[].cantidad` | `...cantidad` |
| `detalle[].unidad_medida` | `...unidad_medida` |
| `detalle[].detalle` | `...detalle` |
| `detalle[].precio_unitario` | `...precio_unitario` |
| `detalle[].monto_total` | `...monto_total` |
| `detalle[].subtotal` | `...sub_total` |
| `detalle[].baseimponible` | `...base_imponible` |
| `detalle[].impuestos[].codigo` | `...impuesto[].codigo` |
| `detalle[].impuestos[].codigotarifa` | No existe (redundante) |
| `detalle[].impuestos[].codigotarifaiva` | `...impuesto[].codigo_tarifa_iva` |
| `detalle[].impuestos[].tarifa` | `...impuesto[].tarifa` |
| `detalle[].impuestos[].monto` | `...impuesto[].monto` |
| `detalle[].montototallinea` | `...monto_total_linea` |
| `detalle[].impuestoasumidoemisorfabrica` | `...impuesto_asumido_emisor_fabrica` |
| `detalle[].impuestoneto` | `...impuesto_neto` |

### Resumen

| Cyberfuel | Nuestro paquete |
|---|---|
| `resumen.moneda` | `resumen_factura.codigo_tipo_moneda.codigo_moneda` |
| `resumen.tipo_cambio` | `resumen_factura.codigo_tipo_moneda.tipo_cambio` |
| `resumen.totalserviciogravado` | `resumen_factura.total_serv_gravados` |
| `resumen.totalservicioexento` | `resumen_factura.total_serv_exentos` |
| `resumen.totalservicioexonerado` | `resumen_factura.total_serv_exonerado` |
| `resumen.TotalServNoSujeto` | `resumen_factura.total_serv_no_sujeto` |
| `resumen.totalmercaderiagravado` | `resumen_factura.total_mercancias_gravadas` |
| `resumen.totalmercaderiaexento` | `resumen_factura.total_mercancias_exentas` |
| `resumen.totalmercaderiaexonerado` | `resumen_factura.total_merc_exonerada` |
| `resumen.TotalMercNoSujeta` | `resumen_factura.total_merc_no_sujeta` |
| `resumen.totalgravado` | `resumen_factura.total_gravado` |
| `resumen.totalexento` | `resumen_factura.total_exento` |
| `resumen.totalexonerado` | `resumen_factura.total_exonerado` |
| `resumen.TotalNoSujeto` | `resumen_factura.total_no_sujeto` |
| `resumen.totalventa` | `resumen_factura.total_venta` |
| `resumen.totaldescuentos` | `resumen_factura.total_descuentos` |
| `resumen.totalventaneta` | `resumen_factura.total_venta_neta` |
| `resumen.totalimpuestos` | `resumen_factura.total_impuesto` |
| `resumen.totalivadevuelto` | `resumen_factura.total_iva_devuelto` |
| `resumen.totalotroscargos` | `resumen_factura.total_otros_cargos` |
| `resumen.totaldesgloseimpuesto` | `resumen_factura.total_desglose_impuesto` (auto-generado) |
| `resumen.MedioPago` | `resumen_factura.medio_pago` |
| `resumen.totalcomprobante` | `resumen_factura.total_comprobante` |

### Receptor

| Cyberfuel | Nuestro paquete | Notas |
|---|---|---|
| `receptor.nombre` | `receptor.nombre` | |
| `receptor.identificacion.tipo` | `receptor.identificacion.tipo` | |
| `receptor.identificacion.numero` | `receptor.identificacion.numero` | Cyberfuel acepta integer (3101336000) — es un bug, XSD requiere string |
| `receptor.correo_electronico` | `receptor.correo_electronico` | Cyberfuel: string simple. Nosotros: array max 4 |

---

## 3. Que hace Cyberfuel mejor

### 3.1 ~~Clave desglosada — control del cliente~~ — RESUELTO

~~Nosotros hardcodeabamos sucursal=001, terminal=00001.~~

**Resuelto:** El cliente puede enviar `establishment` y `terminal` opcionales en el request. Defaults configurables via `INVOICING_CR_SUCURSAL` y `INVOICING_CR_TERMINAL`. Consecutivos independientes por sucursal+terminal+tipo.

### 3.2 ~~Referencia externa~~ — RESUELTO

~~No teniamos campo para vincular con sistema externo.~~

**Resuelto:** Implementado `external_reference` (opcional, string max 100 chars, indexado). El cliente envia `"external_reference": "INV-0024"` y se retorna como `"referencia_externa"` en la respuesta. Sirve tanto para uso standalone como para integracion con ERPs (ej: Helix vincula `Invoice → Receipt` y ademas guarda la referencia).

### 3.3 Envio integrado (PDF + correo)

```json
"envio": {
    "aplica": "1",
    "logo": "data:image/jpg;base64,...",
    "texto": "UGFxdWV0ZSAtIEdWVDI1NTkxNQ==",
    "emisor": { "correo": "servicioalcliente@vastago.cr" },
    "receptor": { "correo": "elegantcenter@hotmail.com" }
}
```

**Por que importa:** Genera el PDF de la factura con logo de la empresa y lo envia por correo al receptor automaticamente. El flujo completo es: crear factura → firmar → enviar a Hacienda → generar PDF → enviar correo. Todo en un solo request.

Nosotros solo hacemos: crear → firmar → enviar a Hacienda. El PDF y el correo quedan fuera del paquete.

**Impacto:** Alto — la mayoria de negocios necesitan enviar la factura al cliente.

### 3.4 ~~Nombres de campos mas legibles~~ — RESUELTO

~~Cyberfuel usa snake_case y nosotros PascalCase.~~

**Resuelto:** Nuestro paquete ahora acepta snake_case en la API externa (`condicion_venta`, `precio_unitario`, `monto_total`). Internamente se transforma a PascalCase via `PayloadTransformerService` para mantener fidelidad con el XSD. Tambien acepta PascalCase por backwards compatibility.

### 3.5 Tolerancia en campos de impuestos

Cyberfuel acepta tanto `codigotarifa` como `codigotarifaiva` para el mismo valor. Mas tolerante con el cliente.

**Impacto:** Bajo.

---

## 4. Que hacemos nosotros mejor

### 4.1 El cliente envia menos datos

| Datos | Cyberfuel | Nosotros |
|---|---|---|
| API key / auth | En cada request | Automatico (OAuth2) |
| Clave completa | Cliente la arma | Auto-generada |
| Emisor completo | En cada request | Desde config/env |
| Fecha de emision | En cada request | Auto-generada |
| Consecutivo | Cliente lo controla | Auto-incrementado |
| Firma digital | Cyberfuel firma | Paquete firma (XAdES-EPES) |
| TotalDesgloseImpuesto | Cliente lo calcula | Auto-calculado desde lineas |

**Un request nuestro tiene ~50% menos campos** que uno de Cyberfuel.

### 4.2 Validacion matematica pre-envio

Nosotros validamos ANTES de enviar a Hacienda:

- `MontoTotal = Cantidad x PrecioUnitario`
- `SubTotal = MontoTotal - Descuentos`
- `ImpuestoNeto = sum(Monto) - sum(Exoneracion)`
- `MontoTotalLinea = SubTotal + ImpuestoNeto`
- `TotalVenta = TotalGravado + TotalExento + TotalExonerado + TotalNoSujeto`
- `TotalComprobante = TotalVentaNeta + TotalImpuesto + TotalOtrosCargos - TotalIVADevuelto`
- Consistencia CodigoTarifaIVA vs Tarifa
- Formulas especificas por codigo de impuesto (IVA, selectivo, combustible, etc.)

**5 validadores especializados** (~1,400 lineas de logica) con tolerancia de 0.01.

Cyberfuel procesa el request y si Hacienda rechaza, el cliente se entera despues. No hay validacion preventiva.

### 4.3 Validacion de formato estricta

| Validacion | Cyberfuel | Nosotros |
|---|---|---|
| Cedula fisica (9 digitos) | No valida formato | `ValidateIdentification`: regex por tipo |
| Cedula juridica (10 digitos) | No valida formato | Regex estricto |
| DIMEX (11-12 digitos) | No valida formato | Regex estricto |
| Campos monetarios | Sin validacion | `DecimalDinero`: max 13 enteros, 5 decimales |
| Numero como string | Acepta integer (`3101336000`) | Solo string (per XSD) |
| Campos por tipo | Sin reglas por tipo | `ReceiptTypeRules`: required/prohibited por tipo |

### 4.4 Fidelidad 1:1 con el XSD

Nuestros nombres de campo mapean directamente al XSD v4.4 de Hacienda. No hay traduccion ni ambiguedad — lo que el cliente envia es lo que va al XML.

Cyberfuel traduce nombres: `totalserviciogravado` → `TotalServGravados`, `totalmercaderiagravado` → `TotalMercanciasGravadas`. Esto introduce una capa de traduccion donde pueden ocurrir errores.

### 4.5 Directo a Hacienda

Nuestro paquete se conecta directamente a la API de Hacienda. Sin intermediarios, sin costos por factura, sin dependencia de terceros. Solo necesita el certificado .p12.

El `ProviderInterface` permite registrar providers adicionales via `ProviderFactoryService::register()` si se necesita en el futuro.

Cyberfuel ES el intermediario — si dejan de operar o suben precios, hay que migrar todo.

### 4.6 Self-hosted / sin costo por factura

Nuestro paquete corre en la infraestructura del cliente. No hay api_key, no hay costo por documento, no hay dependencia de terceros. Solo necesita el certificado .p12 de Hacienda.

### 4.7 Webhook con verificacion

Tenemos `WebhookVerifierService` con verificacion en 2 capas:
1. Validar que la clave contiene el emisorId correcto
2. Verificar firma XML del certificado de Hacienda

---

## 5. Ejemplo: misma factura en ambos formatos

### Cyberfuel (lo que GV Express envia hoy)

```json
{
    "api_key": "OdP8gPM91cN",
    "referencia_externa": "GVT255915",
    "clave": {
        "sucursal": 1,
        "terminal": 3,
        "tipo": "01",
        "comprobante": 115461,
        "pais": "506",
        "dia": "31",
        "mes": "03",
        "anno": "26",
        "situacion_presentacion": "1",
        "codigo_seguridad": "76538363"
    },
    "encabezado": {
        "codigo_actividad": "630901",
        "fecha": "2026-03-31T17:03:03-06:00",
        "condicion_venta": "01",
        "CodigoActividadReceptor": "5229.0"
    },
    "emisor": {
        "nombre": "GV Express CR SRL",
        "identificacion": { "tipo": "02", "numero": "3102717230" },
        "nombre_comercial": "Vastago",
        "ubicacion": {
            "provincia": "2", "canton": "01", "distrito": "09",
            "barrio": "Rio Segundo Centro",
            "sennas": "Municipalidad de Belen 400m Este..."
        },
        "telefono": { "cod_pais": "506", "numero": "40015441" },
        "correo_electronico": ["fe@gvexpress.cr"]
    },
    "receptor": {
        "nombre": "ELEGANT CENTER SA",
        "identificacion": { "tipo": "02", "numero": 3101336000 },
        "correo_electronico": "elegantcenter@hotmail.com"
    },
    "detalle": [
        {
            "numero": "1",
            "codigo_hacienda": "6763000000100",
            "codigo": [{ "tipo": "04", "codigo": "1" }],
            "cantidad": 1,
            "unidad_medida": "Sp",
            "detalle": "Flete Internacional e Impuestos",
            "precio_unitario": "7.20000",
            "monto_total": "7.20000",
            "subtotal": "7.20000",
            "baseimponible": "7.20000",
            "impuestos": [{
                "codigo": "01", "codigotarifa": "01",
                "codigotarifaiva": "01", "tarifa": "0", "monto": "0"
            }],
            "montototallinea": "7.20000",
            "impuestoasumidoemisorfabrica": "0",
            "impuestoneto": "0"
        },
        {
            "numero": "2",
            "codigo_hacienda": "6791000000000",
            "codigo": [{ "tipo": "04", "codigo": "2" }],
            "cantidad": 1,
            "unidad_medida": "Sp",
            "detalle": "Tramites",
            "precio_unitario": "2.00000",
            "monto_total": "2.00000",
            "subtotal": "2.00000",
            "baseimponible": "2.00000",
            "impuestos": [{
                "codigo": "01", "codigotarifa": "08",
                "codigotarifaiva": "08", "tarifa": "13.00", "monto": "0.26000"
            }],
            "montototallinea": "2.26000",
            "impuestoasumidoemisorfabrica": "0",
            "impuestoneto": "0.26000"
        }
    ],
    "resumen": {
        "moneda": "USD",
        "tipo_cambio": "474.00000",
        "totalserviciogravado": "2.00000",
        "totalservicioexento": "0.00000",
        "totalservicioexonerado": "0.00000",
        "TotalServNoSujeto": "7.20000",
        "totalmercaderiagravado": "0.00000",
        "totalmercaderiaexento": "0.00000",
        "totalmercaderiaexonerado": "0.00000",
        "TotalMercNoSujeta": "0.00000",
        "totalgravado": "2.00000",
        "totalexento": "0.00000",
        "totalexonerado": "0.00000",
        "TotalNoSujeto": "7.20000",
        "totalventa": "9.20000",
        "totaldescuentos": "0.00000",
        "totalventaneta": "9.20000",
        "totalimpuestos": "0.26000",
        "totalivadevuelto": "0.00000",
        "totalotroscargos": "0.00000",
        "totaldesgloseimpuesto": [
            { "codigo": "01", "codigotarifaiva": "01", "totalmontoimpuesto": "0.00000" },
            { "codigo": "01", "codigotarifaiva": "08", "totalmontoimpuesto": "0.26000" }
        ],
        "MedioPago": [{ "TipoMedioPago": "01", "totalmediopago": "9.46000" }],
        "totalcomprobante": "9.46000"
    },
    "envio": {
        "aplica": "1",
        "logo": "data:image/jpg;base64,...",
        "texto": "UGFxdWV0ZSAtIEdWVDI1NTkxNQ==",
        "emisor": { "correo": "servicioalcliente@vastago.cr" },
        "receptor": { "correo": "elegantcenter@hotmail.com" }
    }
}
```

### Nuestro paquete (misma factura)

```json
{
    "receipt_type": "FE",
    "condicion_venta": "01",
    "codigo_actividad_emisor": "6309.0",
    "codigo_actividad_receptor": "5229.0",
    "receptor": {
        "nombre": "ELEGANT CENTER SA",
        "identificacion": { "tipo": "02", "numero": "3101336000" },
        "correo_electronico": ["elegantcenter@hotmail.com"]
    },
    "detalle_servicio": {
        "linea_detalle": [
            {
                "numero_linea": 1,
                "codigo_cabys": "6763000000100",
                "codigo_comercial": [{ "tipo": "04", "codigo": "1" }],
                "cantidad": 1,
                "unidad_medida": "Sp",
                "detalle": "Flete Internacional e Impuestos",
                "precio_unitario": "7.20000",
                "monto_total": "7.20000",
                "sub_total": "7.20000",
                "base_imponible": "7.20000",
                "impuesto": [{
                    "codigo": "01",
                    "codigo_tarifa_iva": "01",
                    "tarifa": "0",
                    "monto": "0"
                }],
                "impuesto_neto": "0",
                "monto_total_linea": "7.20000"
            },
            {
                "numero_linea": 2,
                "codigo_cabys": "6791000000000",
                "codigo_comercial": [{ "tipo": "04", "codigo": "2" }],
                "cantidad": 1,
                "unidad_medida": "Sp",
                "detalle": "Tramites",
                "precio_unitario": "2.00000",
                "monto_total": "2.00000",
                "sub_total": "2.00000",
                "base_imponible": "2.00000",
                "impuesto": [{
                    "codigo": "01",
                    "codigo_tarifa_iva": "08",
                    "tarifa": "13.00",
                    "monto": "0.26000"
                }],
                "impuesto_neto": "0.26000",
                "monto_total_linea": "2.26000"
            }
        ]
    },
    "resumen_factura": {
        "codigo_tipo_moneda": { "codigo_moneda": "USD", "tipo_cambio": "474.00000" },
        "total_serv_gravados": "2.00000",
        "total_serv_exentos": "0.00000",
        "total_serv_no_sujeto": "7.20000",
        "total_gravado": "2.00000",
        "total_exento": "0.00000",
        "total_no_sujeto": "7.20000",
        "total_venta": "9.20000",
        "total_descuentos": "0.00000",
        "total_venta_neta": "9.20000",
        "total_impuesto": "0.26000",
        "total_comprobante": "9.46000",
        "medio_pago": [{ "tipo_medio_pago": "01", "total_medio_pago": "9.46000" }]
    }
}
```

**Diferencia visible:** ~180 lineas (Cyberfuel) vs ~60 lineas (nuestro). El cliente escribe 1/3 del JSON.

---

## 6. Bugs o inconsistencias detectadas en Cyberfuel

| Problema | Detalle |
|---|---|
| `receptor.identificacion.numero` como integer | Envia `3101336000` (numero) en vez de `"3101336000"` (string). El XSD define Numero como `xs:string` |
| Nombres inconsistentes | Mezcla snake_case (`totalserviciogravado`) con PascalCase (`TotalServNoSujeto`, `TotalMercNoSujeta`, `MedioPago`) en el mismo objeto |
| `codigotarifa` redundante | Envia `codigotarifa: "08"` y `codigotarifaiva: "08"` — son el mismo dato |
| `correo_electronico` del receptor como string | Envia `"elegantcenter@hotmail.com"` (string) pero el XSD permite 0-4 ocurrencias (array) |
| `codigo_actividad` formato distinto | Envia `"630901"` (sin punto) vs `"6309.0"` (con punto) — ambos son 6 chars pero formato inconsistente con `CodigoActividadReceptor: "5229.0"` |

---

## 7. Features a adoptar de Cyberfuel

### 7.1 ~~Referencia externa~~ — IMPLEMENTADO

**Implementado en commit `606fd59`.** Campo `external_reference` opcional (string, max 100, indexado) en la tabla `receipts`. El cliente envia `"external_reference": "INV-0024"` y se retorna como `"referencia_externa"` en el ReceiptResource.

### 7.2 ~~Control de sucursal y terminal~~ — IMPLEMENTADO

**Implementado.** El cliente puede enviar `establishment` y `terminal` opcionales. Defaults configurables via env. Consecutivos independientes por sucursal+terminal+tipo.

### 7.3 Generacion de PDF (Prioridad: Alta, pero complejo)

**Que:** Generar PDF de la factura con logo y datos del comprobante.

**Por que:** La mayoria de negocios necesitan enviar la factura al cliente en formato legible. Sin PDF, el paquete solo cubre la mitad del flujo.

**Esfuerzo:** Alto — requiere template engine (Blade/DOMPDF), logo storage, diseño del PDF, posible generacion asincrona.

**Alternativa:** Proveer un evento `ReceiptSent` para que la app host genere el PDF con su propia logica. Asi el paquete no impone un diseño de PDF pero habilita la integracion.

### 7.4 Envio por correo (Prioridad: Media)

**Que:** Enviar el PDF + XML al receptor por correo.

**Por que:** Complementa la generacion de PDF. El flujo completo seria: crear → firmar → enviar a Hacienda → generar PDF → enviar correo.

**Esfuerzo:** Medio — depende de que el PDF ya exista. Usar Mailable de Laravel + queue.

**Alternativa:** Evento `ReceiptAccepted` para que la app host envie el correo con su propio template y logica.

---

## 8. Resumen comparativo

| Criterio | Cyberfuel | Nuestro paquete | Ganador |
|---|:---:|:---:|:---:|
| Simplicidad del request | - | **50% menos campos** | Nosotros |
| Validacion pre-envio | No tiene | **5 validadores, 1400 lineas** | Nosotros |
| Validacion de formato | Basica | **Estricta (cedula, decimal, tipo)** | Nosotros |
| Fidelidad XSD | Traduce nombres | **1:1 con XSD** | Nosotros |
| Independencia proveedor | ES el intermediario | **Directo a Hacienda** | Nosotros |
| Costo | Por factura | **Sin costo** | Nosotros |
| Control sucursal/terminal | Cliente controla | **Cliente controla** (opcional, con defaults) | Empate |
| Referencia externa | Tiene | **Tiene** (`external_reference`) | Empate |
| PDF con logo | **Integrado** | No tiene | Cyberfuel |
| Envio por correo | **Integrado** | No tiene | Cyberfuel |
| Naming convention | snake_case (inconsistente) | **snake_case** (API) + PascalCase (interno/XSD) | Nosotros |

**Resultado:** Nuestro paquete es tecnicamente superior en validacion, seguridad, y arquitectura. Directo a Hacienda sin intermediarios. Ambos usan snake_case en la API externa (nosotros transformamos internamente a PascalCase via `PayloadTransformerService` para mantener fidelidad con el XSD). Cyberfuel gana en features de negocio (PDF, correo) que la app host puede implementar usando los eventos del paquete (`ReceiptAccepted`, `ReceiptSent`).
