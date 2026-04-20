# Invoicing Service — API Contract

> Contrato de la API REST del servicio de facturacion electronica.

---

## Base URL

```
Production:  https://api.invoicing.example.com/v1
Sandbox:     https://sandbox.invoicing.example.com/v1
```

---

## Autenticacion

Todas las requests (excepto `/register` y webhooks entrantes) requieren API key en header:

```
X-Api-Key: tk_live_a1b2c3d4e5f6...
```

La API key identifica al tenant. No hay sesiones, no hay tokens — stateless puro.

### Errores de autenticacion

```json
// 401 — API key ausente o invalida
{
  "error": "unauthorized",
  "message": "API key invalida o ausente"
}

// 403 — API key revocada o tenant suspendido
{
  "error": "forbidden",
  "message": "Cuenta suspendida. Contacte soporte."
}
```

---

## Convenciones

- **Formato:** JSON (request y response)
- **Encoding:** UTF-8
- **Campos:** snake_case
- **Fechas:** ISO 8601 con timezone Costa Rica (`2026-04-05T14:30:00-06:00`)
- **Montos:** Strings con precision decimal (`"1500.25"`) para evitar floating point
- **IDs:** UUID v4
- **Paginacion:** `?page=1&per_page=25` (max 100)
- **Errores:** Estructura uniforme (ver seccion Errores)

---

## Endpoints

### 1. Registro

#### `POST /register`

Crear una cuenta nueva. No requiere autenticacion.

**Request:**

```json
{
  "nombre_empresa": "Distribuidora ABC S.A.",
  "email": "admin@abc.co.cr",
  "password": "...",
  "emisor": {
    "nombre": "Distribuidora ABC S.A.",
    "cedula": "3101234567",
    "tipo_identificacion": "02",
    "nombre_comercial": "ABC Distribuciones",
    "telefono": "22234567",
    "correo_electronico": "facturacion@abc.co.cr",
    "ubicacion": {
      "provincia": "1",
      "canton": "01",
      "distrito": "01",
      "otras_senas": "100m norte del parque central"
    },
    "codigo_actividad": "4690.0"
  }
}
```

**Response: 201**

```json
{
  "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
  "api_key": "tk_test_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6",
  "ambiente": "sandbox",
  "message": "Cuenta creada en modo sandbox. Guarde su API key — no se mostrara de nuevo."
}
```

> **Nota:** La cuenta se crea siempre en sandbox. El cliente debe completar el setup (certificado + credenciales) y solicitar activacion a produccion.

---

### 2. Gestion de cuenta

#### `GET /account`

Ver configuracion actual del tenant.

**Response: 200**

```json
{
  "tenant_id": "550e8400-...",
  "nombre_empresa": "Distribuidora ABC S.A.",
  "email": "admin@abc.co.cr",
  "ambiente": "sandbox",
  "status": "active",
  "emisor": {
    "nombre": "Distribuidora ABC S.A.",
    "cedula": "3101234567",
    "tipo_identificacion": "02",
    "codigo_actividad": "4690.0"
  },
  "certificate": {
    "uploaded": true,
    "fingerprint": "A1:B2:C3:...",
    "expires_at": "2027-03-15",
    "days_until_expiry": 345
  },
  "credentials": {
    "sandbox": { "configured": true },
    "production": { "configured": false }
  },
  "callback_url": "https://mi-app.com/webhooks/invoicing",
  "defaults": {
    "sucursal": "001",
    "terminal": "00001"
  },
  "created_at": "2026-04-05T10:00:00-06:00"
}
```

#### `PUT /settings`

Actualizar configuracion del tenant.

**Request:**

```json
{
  "emisor": {
    "telefono": "22345678",
    "correo_electronico": "nuevo@abc.co.cr"
  },
  "callback_url": "https://mi-app.com/webhooks/invoicing",
  "defaults": {
    "sucursal": "001",
    "terminal": "00001"
  }
}
```

**Response: 200** — Retorna el objeto account actualizado.

---

### 3. Certificados

#### `POST /certificates`

Subir certificado digital (.p12).

**Request:** `multipart/form-data`

| Campo | Tipo | Requerido | Descripcion |
|---|---|---|---|
| `certificate` | file | Si | Archivo .p12 (PKCS#12) |
| `pin` | string | Si | PIN del certificado |

**Response: 201**

```json
{
  "certificate_id": "uuid",
  "fingerprint": "A1:B2:C3:D4:E5:...",
  "subject": "DISTRIBUIDORA ABC SOCIEDAD ANONIMA",
  "expires_at": "2027-03-15",
  "days_until_expiry": 345,
  "is_active": true
}
```

**Errores comunes:**

```json
// 422 — Certificado invalido
{
  "error": "validation_error",
  "message": "El certificado no es un archivo PKCS#12 valido"
}

// 422 — PIN incorrecto
{
  "error": "validation_error",
  "message": "El PIN proporcionado es incorrecto"
}

// 422 — Certificado expirado
{
  "error": "validation_error",
  "message": "El certificado expiro el 2025-01-15"
}
```

#### `GET /certificates`

Listar certificados del tenant (activos e historicos).

#### `DELETE /certificates/{id}`

Desactivar un certificado. No se puede eliminar el unico certificado activo.

---

### 4. Credenciales IDP

#### `POST /credentials`

Configurar credenciales de Hacienda IDP.

**Request:**

```json
{
  "idp_username": "cpj-3101234567@stag.comprobanteselectronicos.go.cr",
  "idp_password": "...",
  "ambiente": "sandbox"
}
```

El servicio intenta autenticarse con Hacienda para validar las credenciales antes de guardarlas.

**Response: 201**

```json
{
  "credential_id": "uuid",
  "ambiente": "sandbox",
  "idp_username": "cpj-310123****",
  "verified": true,
  "message": "Credenciales verificadas exitosamente con Hacienda"
}
```

**Error:**

```json
// 422 — Credenciales invalidas
{
  "error": "validation_error",
  "message": "No se pudo autenticar con Hacienda IDP. Verifique usuario y contrasena."
}
```

---

### 5. API Keys

#### `POST /api-keys`

Generar una API key adicional.

**Request:**

```json
{
  "name": "Backend produccion",
  "ambiente": "production"
}
```

**Response: 201**

```json
{
  "api_key_id": "uuid",
  "name": "Backend produccion",
  "key": "tk_live_x9y8z7w6v5u4t3s2r1q0...",
  "prefix": "tk_live_x9y8z7",
  "ambiente": "production",
  "message": "Guarde esta key — no se mostrara de nuevo."
}
```

#### `GET /api-keys`

Listar API keys del tenant (sin mostrar el key completo).

```json
{
  "data": [
    {
      "api_key_id": "uuid",
      "name": "Backend produccion",
      "prefix": "tk_live_x9y8z7",
      "ambiente": "production",
      "last_used_at": "2026-04-05T14:30:00-06:00",
      "created_at": "2026-04-01T10:00:00-06:00"
    }
  ]
}
```

#### `DELETE /api-keys/{id}`

Revocar una API key. Efecto inmediato.

---

### 6. Comprobantes (Facturacion)

#### `POST /receipts`

Crear, firmar y enviar comprobante electronico a Hacienda.

**Request:** Mismo payload que el paquete actual (snake_case).

```json
{
  "tipo_comprobante": "FE",
  "condicion_venta": "01",
  "medio_pago": ["01"],
  "plazo_credito": null,
  "codigo_moneda": "CRC",
  "tipo_cambio": "1",
  "receptor": {
    "nombre": "Juan Perez",
    "identificacion": {
      "tipo": "01",
      "numero": "123456789"
    },
    "correo_electronico": ["juan@example.com"]
  },
  "detalle_servicio": {
    "linea_detalle": [
      {
        "numero_linea": 1,
        "codigo_comercial": [
          { "tipo": "01", "codigo": "PROD-001" }
        ],
        "cantidad": "2",
        "unidad_medida": "Unid",
        "detalle": "Widget Premium",
        "precio_unitario": "5000.00",
        "monto_total": "10000.00",
        "descuento": [
          {
            "monto_descuento": "1000.00",
            "naturaleza_descuento": "Descuento por volumen"
          }
        ],
        "sub_total": "9000.00",
        "impuesto": [
          {
            "codigo": "01",
            "codigo_tarifa": "08",
            "tarifa": "13.00",
            "factor_iva": "1",
            "monto": "1170.00",
            "impuesto_neto": "1170.00"
          }
        ],
        "impuesto_neto": "1170.00",
        "monto_total_linea": "10170.00"
      }
    ]
  },
  "resumen_factura": {
    "total_mercancias_gravadas": "9000.00",
    "total_mercancias_exentas": "0",
    "total_gravado": "9000.00",
    "total_exento": "0",
    "total_exonerado": "0",
    "total_venta": "9000.00",
    "total_descuentos": "1000.00",
    "total_venta_neta": "9000.00",
    "total_impuesto": "1170.00",
    "total_comprobante": "10170.00"
  },
  "external_reference": "ORDER-2026-001"
}
```

**Campos opcionales adicionales del servicio (no del paquete):**

```json
{
  "sucursal": "002",
  "terminal": "00003"
}
```

Si no se envian, usa los defaults del tenant.

**Response: 202 Accepted**

```json
{
  "id": "uuid",
  "tipo_comprobante": "FE",
  "clave": "50604042600310123456700100001010000000001199999999",
  "numero_consecutivo": "00100001010000000001",
  "fecha_emision": "2026-04-05T14:30:00-06:00",
  "estado_comprobante": "pending",
  "estado_hacienda": "pending",
  "external_reference": "ORDER-2026-001",
  "emisor": {
    "nombre": "Distribuidora ABC S.A.",
    "numero_identificacion": "3101234567"
  },
  "receptor": {
    "nombre": "Juan Perez",
    "numero_identificacion": "123456789"
  },
  "moneda": "CRC",
  "total_comprobante": "10170.00",
  "total_impuesto": "1170.00",
  "created_at": "2026-04-05T14:30:00-06:00"
}
```

> El servicio siempre retorna 202 (accepted for processing). La factura se envia a Hacienda de forma asincrona. El cliente recibe notificacion via webhook cuando Hacienda responde.

#### `GET /receipts`

Listar comprobantes del tenant.

**Query params:**

| Param | Tipo | Default | Descripcion |
|---|---|---|---|
| `type` | string | todos | Filtrar por tipo (`FE`, `TE`, `NC`, etc.) |
| `status` | string | todos | Filtrar por estado (`pending`, `sent`, `accepted`, `rejected`) |
| `from` | date | - | Fecha inicio (ISO 8601) |
| `to` | date | - | Fecha fin (ISO 8601) |
| `external_reference` | string | - | Buscar por referencia externa |
| `page` | int | 1 | Pagina |
| `per_page` | int | 25 | Items por pagina (max 100) |

**Response: 200**

```json
{
  "data": [
    { "...receipt object..." }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 150,
    "last_page": 6
  }
}
```

#### `GET /receipts/{id}`

Ver comprobante por ID.

**Response: 200** — Receipt object completo.

#### `GET /receipts/key/{clave}`

Ver comprobante por clave de 50 digitos.

**Response: 200** — Receipt object completo.

#### `GET /receipts/key/{clave}/status`

Consultar estado actual en Hacienda (query directo a la API de Hacienda).

**Response: 200**

```json
{
  "clave": "50614...",
  "estado_comprobante": "sent",
  "estado_hacienda": "accepted",
  "mensaje_hacienda": "Comprobante aceptado por Hacienda",
  "fecha_respuesta": "2026-04-05T14:31:05-06:00"
}
```

#### `POST /reception`

Recibir un documento electronico de otro emisor y encolar mensaje de respuesta (CA/CAP/CR).

**Request:**

```json
{
  "clave": "50614...",
  "tipo_mensaje": "1",
  "detalle_mensaje": "Aceptado",
  "codigo_actividad": "4690.0",
  "condicion_impuesto": "01",
  "monto_total_impuesto_acreditar": "1170.00",
  "total_factura": "10170.00"
}
```

**Response: 202**

```json
{
  "id": "uuid",
  "clave": "50614...",
  "estado": "pending",
  "message": "Mensaje de recepcion encolado"
}
```

---

### 7. Webhooks entrantes (Hacienda)

#### `POST /webhook`

Endpoint publico donde Hacienda envia respuestas asincronas. Sin autenticacion por API key — se verifica por clave + firma XML.

**Este endpoint no lo consumen los clientes.** Es interno entre Hacienda y el servicio.

---

### 8. Consecutivos

#### `GET /consecutives`

Ver estado de consecutivos del tenant.

**Response: 200**

```json
{
  "data": [
    {
      "tipo_comprobante": "FE",
      "sucursal": "001",
      "terminal": "00001",
      "ultimo_numero": 42
    },
    {
      "tipo_comprobante": "NC",
      "sucursal": "001",
      "terminal": "00001",
      "ultimo_numero": 3
    }
  ]
}
```

#### `PUT /consecutives`

Ajustar consecutivo (util para migracion desde otro sistema).

**Request:**

```json
{
  "tipo_comprobante": "FE",
  "sucursal": "001",
  "terminal": "00001",
  "ultimo_numero": 500
}
```

> Solo permite incrementar, nunca decrementar (para evitar duplicados en Hacienda).

---

## Webhooks salientes (Servicio → Cliente)

Cuando Hacienda responde, el servicio notifica al `callback_url` del tenant:

**Headers:**

```
POST {callback_url}
Content-Type: application/json
X-Webhook-Signature: sha256=a1b2c3d4...
X-Webhook-Event: receipt.accepted
X-Webhook-Id: uuid
X-Webhook-Timestamp: 2026-04-05T14:31:05-06:00
```

**Body:**

```json
{
  "event": "receipt.accepted",
  "receipt_id": "uuid",
  "clave": "50614...",
  "tipo_comprobante": "FE",
  "estado_hacienda": "accepted",
  "mensaje_hacienda": "Comprobante aceptado",
  "external_reference": "ORDER-2026-001",
  "timestamp": "2026-04-05T14:31:05-06:00"
}
```

### Eventos disponibles

| Evento | Cuando se dispara |
|---|---|
| `receipt.accepted` | Hacienda acepto el comprobante |
| `receipt.rejected` | Hacienda rechazo el comprobante |
| `receipt.failed` | Fallo el envio despues de todos los reintentos |
| `certificate.expiring` | Certificado expira en < 30 dias |

### Verificacion de firma

El cliente debe verificar `X-Webhook-Signature`:

```
expected = HMAC-SHA256(webhook_secret, raw_body)
actual   = X-Webhook-Signature (sin prefijo "sha256=")
```

El `webhook_secret` se genera al crear la cuenta y se puede rotar via `PUT /settings`.

### Politica de reintentos

- 3 intentos: inmediato, 30 segundos, 2 minutos
- Se espera HTTP 2xx como confirmacion
- Si los 3 fallan, el evento queda como `delivery_failed`
- El cliente puede consultar el estado manualmente via `GET /receipts/key/{clave}/status`

---

## Errores

Todos los errores siguen la misma estructura:

```json
{
  "error": "error_code",
  "message": "Descripcion legible del error",
  "details": {}
}
```

### Codigos HTTP

| Codigo | Significado |
|---|---|
| `200` | OK |
| `201` | Creado |
| `202` | Aceptado para procesamiento |
| `400` | Bad request — JSON mal formado |
| `401` | No autenticado — API key ausente o invalida |
| `403` | Prohibido — cuenta suspendida o sin permisos |
| `404` | Recurso no encontrado |
| `422` | Error de validacion |
| `429` | Rate limit excedido |
| `500` | Error interno del servicio |

### Errores de validacion (422)

```json
{
  "error": "validation_error",
  "message": "Los datos del comprobante tienen errores de validacion",
  "details": {
    "errors": {
      "detalle_servicio.linea_detalle.0.monto_total": [
        "El monto total de la linea 1 deberia ser 10000.00, se recibio 10500.00 (cantidad * precio_unitario = 2 * 5000.00)"
      ],
      "resumen_factura.total_comprobante": [
        "El total del comprobante deberia ser 10170.00, se recibio 10670.00"
      ]
    }
  }
}
```

> Los errores de validacion matematica son especificos — indican el valor esperado, el recibido, y la formula. Esto es una ventaja clave sobre otros proveedores.

### Rate limiting (429)

```json
{
  "error": "rate_limit_exceeded",
  "message": "Limite de requests excedido. Reintente en 30 segundos.",
  "details": {
    "limit": 100,
    "remaining": 0,
    "reset_at": "2026-04-05T14:31:00-06:00"
  }
}
```

Headers de rate limit en cada response:

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 73
X-RateLimit-Reset: 1743868260
```

---

## Setup completo (ejemplo)

Flujo tipico de un cliente nuevo integrando el servicio:

```bash
# 1. Registrarse
curl -X POST https://api.invoicing.example.com/v1/register \
  -H "Content-Type: application/json" \
  -d '{"nombre_empresa": "Mi Empresa S.A.", "email": "admin@mi.cr", "password": "...", "emisor": {...}}'
# → Recibe api_key (guardarla)

# 2. Subir certificado
curl -X POST https://api.invoicing.example.com/v1/certificates \
  -H "X-Api-Key: tk_test_..." \
  -F "certificate=@certificado.p12" \
  -F "pin=1234"

# 3. Configurar credenciales IDP
curl -X POST https://api.invoicing.example.com/v1/credentials \
  -H "X-Api-Key: tk_test_..." \
  -H "Content-Type: application/json" \
  -d '{"idp_username": "cpj-...@stag.comprobanteselectronicos.go.cr", "idp_password": "...", "ambiente": "sandbox"}'

# 4. Configurar webhook
curl -X PUT https://api.invoicing.example.com/v1/settings \
  -H "X-Api-Key: tk_test_..." \
  -H "Content-Type: application/json" \
  -d '{"callback_url": "https://mi-app.com/webhooks/invoicing"}'

# 5. Emitir primera factura
curl -X POST https://api.invoicing.example.com/v1/receipts \
  -H "X-Api-Key: tk_test_..." \
  -H "Content-Type: application/json" \
  -d '{"tipo_comprobante": "FE", ...}'
# → 202 Accepted

# 6. Recibir webhook cuando Hacienda responde
# POST https://mi-app.com/webhooks/invoicing
# {"event": "receipt.accepted", "clave": "506...", ...}
```