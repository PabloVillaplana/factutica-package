# Request — Factura Electronica (FE)

`POST /invoicing-cr/receipts`

---

## Ejemplo minimo completo

```json
{
    "receipt_type": "FE",
    "condicion_venta": "01",
    "codigo_actividad_emisor": "6201.0",

    "receptor": {
        "nombre": "Cliente Ejemplo S.A.",
        "identificacion": {
            "tipo": "02",
            "numero": "3101234567"
        },
        "correo_electronico": ["cliente@example.com"]
    },

    "detalle_servicio": {
        "linea_detalle": [
            {
                "numero_linea": 1,
                "codigo_cabys": "8410010000000",
                "cantidad": 2,
                "unidad_medida": "Sp",
                "detalle": "Servicio de desarrollo de software",
                "precio_unitario": "5000.00000",
                "monto_total": "10000.00000",
                "sub_total": "10000.00000",
                "base_imponible": "10000.00000",
                "impuesto": [
                    {
                        "codigo": "01",
                        "codigo_tarifa_iva": "08",
                        "tarifa": 13,
                        "monto": "1300.00000"
                    }
                ],
                "impuesto_neto": "1300.00000",
                "monto_total_linea": "11300.00000"
            }
        ]
    },

    "resumen_factura": {
        "codigo_tipo_moneda": {
            "codigo_moneda": "CRC",
            "tipo_cambio": "1.00000"
        },
        "total_serv_gravados": "10000.00000",
        "total_gravado": "10000.00000",
        "total_venta": "10000.00000",
        "total_venta_neta": "10000.00000",
        "total_impuesto": "1300.00000",
        "total_comprobante": "11300.00000",
        "medio_pago": [
            {
                "tipo_medio_pago": "01",
                "total_medio_pago": "11300.00000"
            }
        ]
    }
}
```

---

## Ejemplo con descuento

```json
{
    "receipt_type": "FE",
    "condicion_venta": "01",
    "codigo_actividad_emisor": "6201.0",

    "receptor": {
        "nombre": "Cliente con Descuento S.A.",
        "identificacion": {
            "tipo": "02",
            "numero": "3109876543"
        },
        "correo_electronico": ["descuento@example.com"]
    },

    "detalle_servicio": {
        "linea_detalle": [
            {
                "numero_linea": 1,
                "codigo_cabys": "8410010000000",
                "cantidad": 1,
                "unidad_medida": "Sp",
                "detalle": "Servicio de consultoria",
                "precio_unitario": "10000.00000",
                "monto_total": "10000.00000",
                "descuento": [
                    {
                        "monto_descuento": "1000.00000",
                        "codigo_descuento": "01",
                        "naturaleza_descuento": "Descuento por pronto pago"
                    }
                ],
                "sub_total": "9000.00000",
                "base_imponible": "9000.00000",
                "impuesto": [
                    {
                        "codigo": "01",
                        "codigo_tarifa_iva": "08",
                        "tarifa": 13,
                        "monto": "1170.00000"
                    }
                ],
                "impuesto_neto": "1170.00000",
                "monto_total_linea": "10170.00000"
            }
        ]
    },

    "resumen_factura": {
        "codigo_tipo_moneda": {
            "codigo_moneda": "CRC",
            "tipo_cambio": "1.00000"
        },
        "total_serv_gravados": "9000.00000",
        "total_gravado": "9000.00000",
        "total_venta": "10000.00000",
        "total_descuentos": "1000.00000",
        "total_venta_neta": "9000.00000",
        "total_impuesto": "1170.00000",
        "total_comprobante": "10170.00000",
        "medio_pago": [
            {
                "tipo_medio_pago": "01",
                "total_medio_pago": "10170.00000"
            }
        ]
    }
}
```

---

## Ejemplo con multiples lineas y USD

```json
{
    "receipt_type": "FE",
    "condicion_venta": "02",
    "plazo_credito": 30,
    "codigo_actividad_emisor": "6201.0",

    "receptor": {
        "nombre": "Empresa Internacional S.A.",
        "identificacion": {
            "tipo": "02",
            "numero": "3101112233"
        },
        "ubicacion": {
            "provincia": "1",
            "canton": "01",
            "distrito": "01",
            "otras_senas": "Edificio ABC, piso 3"
        },
        "telefono": {
            "codigo_pais": "506",
            "num_telefono": "88887777"
        },
        "correo_electronico": ["empresa@example.com"]
    },

    "detalle_servicio": {
        "linea_detalle": [
            {
                "numero_linea": 1,
                "codigo_cabys": "8410010000000",
                "cantidad": 10,
                "unidad_medida": "Sp",
                "detalle": "Horas de desarrollo backend",
                "precio_unitario": "50.00000",
                "monto_total": "500.00000",
                "sub_total": "500.00000",
                "base_imponible": "500.00000",
                "impuesto": [
                    {
                        "codigo": "01",
                        "codigo_tarifa_iva": "08",
                        "tarifa": 13,
                        "monto": "65.00000"
                    }
                ],
                "impuesto_neto": "65.00000",
                "monto_total_linea": "565.00000"
            },
            {
                "numero_linea": 2,
                "codigo_cabys": "8410010000000",
                "cantidad": 5,
                "unidad_medida": "Sp",
                "detalle": "Horas de desarrollo frontend",
                "precio_unitario": "45.00000",
                "monto_total": "225.00000",
                "sub_total": "225.00000",
                "base_imponible": "225.00000",
                "impuesto": [
                    {
                        "codigo": "01",
                        "codigo_tarifa_iva": "08",
                        "tarifa": 13,
                        "monto": "29.25000"
                    }
                ],
                "impuesto_neto": "29.25000",
                "monto_total_linea": "254.25000"
            }
        ]
    },

    "resumen_factura": {
        "codigo_tipo_moneda": {
            "codigo_moneda": "USD",
            "tipo_cambio": "515.21000"
        },
        "total_serv_gravados": "725.00000",
        "total_gravado": "725.00000",
        "total_venta": "725.00000",
        "total_venta_neta": "725.00000",
        "total_impuesto": "94.25000",
        "total_comprobante": "819.25000",
        "medio_pago": [
            {
                "tipo_medio_pago": "02",
                "total_medio_pago": "819.25000"
            }
        ]
    }
}
```

---

## Payload completo despues de procesamiento interno

Tomando el ejemplo minimo, asi queda el objeto `$data` despues de que `PayloadTransformerService` convierte snake_case → PascalCase y `injectGeneratedData()` inyecta los datos auto-generados.
Este es el payload que se persiste en `receipt_payloads` y se usa para generar el XML. Internamente usa PascalCase (nombres del XSD de Hacienda).

Los campos marcados con `← auto` son generados por el paquete.

```json
{
    "receipt_type": "FE",
    "condicion_venta": "01",
    "codigo_actividad_emisor": "6201.0",

    "clave": "50601042600310123456700100001010000000001199999999",
    "numero_consecutivo": "00100001010000000001",
    "fecha_emision": "2026-04-01T10:30:00-06:00",
    "proveedor_sistema": "12345",

    "emisor": {
        "nombre": "Mi Empresa S.A.",
        "identificacion": {
            "tipo": "02",
            "numero": "3101234567"
        },
        "nombre_comercial": "Mi Empresa",
        "correo_electronico": ["facturacion@miempresa.com"],
        "ubicacion": {
            "provincia": "1",
            "canton": "01",
            "distrito": "01",
            "otras_senas": "100 metros norte del parque central"
        },
        "telefono": {
            "codigo_pais": "506",
            "num_telefono": "22223333"
        }
    },

    "receptor": {
        "nombre": "Cliente Ejemplo S.A.",
        "identificacion": {
            "tipo": "02",
            "numero": "3101234567"
        },
        "correo_electronico": ["cliente@example.com"]
    },

    "detalle_servicio": {
        "linea_detalle": [
            {
                "numero_linea": 1,
                "codigo_cabys": "8410010000000",
                "cantidad": 2,
                "unidad_medida": "Sp",
                "detalle": "Servicio de desarrollo de software",
                "precio_unitario": "5000.00000",
                "monto_total": "10000.00000",
                "sub_total": "10000.00000",
                "base_imponible": "10000.00000",
                "impuesto": [
                    {
                        "codigo": "01",
                        "codigo_tarifa_iva": "08",
                        "tarifa": 13,
                        "monto": "1300.00000"
                    }
                ],
                "impuesto_neto": "1300.00000",
                "monto_total_linea": "11300.00000"
            }
        ]
    },

    "resumen_factura": {
        "codigo_tipo_moneda": {
            "codigo_moneda": "CRC",
            "tipo_cambio": "1.00000"
        },
        "total_serv_gravados": "10000.00000",
        "total_gravado": "10000.00000",
        "total_venta": "10000.00000",
        "total_venta_neta": "10000.00000",
        "total_impuesto": "1300.00000",
        "total_comprobante": "11300.00000",
        "medio_pago": [
            {
                "tipo_medio_pago": "01",
                "total_medio_pago": "11300.00000"
            }
        ],
        "total_desglose_impuesto": [
            {
                "codigo": "01",
                "codigo_tarifa_iva": "08",
                "total_monto_impuesto": "1300.00000"
            }
        ]
    }
}
```

**Campos inyectados automaticamente:**

| Campo | Origen |
|---|---|
| `clave` | `KeyGeneratorService` — 50 digitos (pais + fecha + emisor + consecutivo + tipo + seguridad) |
| `numero_consecutivo` | `KeyGeneratorService` — 20 digitos (sucursal + terminal + tipo + secuencial) |
| `fecha_emision` | `now()` en formato ISO 8601 con timezone |
| `proveedor_sistema` | `config('invoicing.proveedor_sistemas')` |
| `emisor` | Completo desde `config('invoicing.emisor.*')` |
| `codigo_actividad_emisor` | Fallback a `config('invoicing.emisor.actividad_economica')` si no viene |
| `total_desglose_impuesto` | Calculado agrupando impuestos de las lineas por Codigo + CodigoTarifaIVA |

---

## Modelo Receipt (lo que se guarda en DB)

El paquete extrae los datos del payload XML para poblar el modelo. El cliente no envia estos campos.

```
receipts
├── id
├── receipt_type              ← "FE" (del request)
├── ui_key                    ← "5060104260031..." (Clave generada)
├── external_reference        ← "INV-0024" (del request, opcional)
├── consecutive_number        ← "00100001010000000001" (generado)
├── emission_date             ← "2026-04-01 10:30:00" (generado)
├── receipt_status            ← "sent"
├── hacienda_status           ← "pending"
├── sell_condition            ← "01" (de CondicionVenta)
├── total_amount              ← 10000.00 (de resumen_factura.total_venta)
├── tax_amount                ← 1300.00 (de resumen_factura.total_impuesto)
├── total_discount            ← 0.00 (de resumen_factura.total_descuentos)
├── total_voucher             ← 11300.00 (de resumen_factura.total_comprobante)
├── currency                  ← "CRC" (de resumen_factura.codigo_tipo_moneda.codigo_moneda)
├── exchange_rate             ← 1.00 (de resumen_factura.codigo_tipo_moneda.tipo_cambio)
├── issuer_name               ← "Mi Empresa S.A." (de emisor.nombre / config)
├── issuer_number             ← "3101234567" (de config)
├── issuer_identification_type ← "02" (de config)
├── receiver_name             ← "Cliente Ejemplo S.A." (de receptor.nombre)
├── receiver_number           ← "3101234567" (de receptor.identificacion.numero)
├── receiver_identification_type ← "02" (de receptor.identificacion.tipo)
├── signed_xml                ← (XML firmado, se guarda al enviar)
├── sent_to_hacienda_at       ← (timestamp al enviar)
├── created_at
└── updated_at
```

---

## Payload enviado a la API de Hacienda

Despues de generar y firmar el XML, el paquete construye este payload para el endpoint de recepcion de Hacienda:

```json
{
    "clave": "50601042600310123456700100001010000000001199999999",
    "fecha": "2026-04-01T10:30:00-06:00",
    "emisor": {
        "tipoIdentificacion": "02",
        "numeroIdentificacion": "3101234567"
    },
    "receptor": {
        "tipoIdentificacion": "02",
        "numeroIdentificacion": "3101234567"
    },
    "comprobanteXml": "PEZhY3R1cmFF... (XML firmado en base64)",
    "callbackUrl": "https://midominio.com/api/invoicing-cr/webhook"
}
```

---

## Respuesta del paquete (201)

```json
{
    "mensaje": "Comprobante creado y enviado.",
    "data": {
        "id": 1,
        "tipo_comprobante": "FE",
        "clave": "50601042600310123456700100001010000000001199999999",
        "referencia_externa": "INV-0024",
        "numero_consecutivo": "00100001010000000001",
        "fecha_emision": "2026-04-01T10:30:00-06:00",
        "enviado_hacienda_en": "2026-04-01T10:30:01-06:00",
        "estado_comprobante": "sent",
        "estado_hacienda": "pending",
        "emisor": {
            "nombre": "Mi Empresa S.A.",
            "numero": "3101234567",
            "tipo_identificacion": "02"
        },
        "receptor": {
            "nombre": "Cliente Ejemplo S.A.",
            "numero": "3101234567",
            "tipo_identificacion": "02"
        },
        "montos": {
            "total_venta": 10000,
            "total_impuesto": 1300,
            "total_descuentos": 0,
            "total_comprobante": 11300,
            "moneda": "CRC",
            "tipo_cambio": 1
        },
        "condicion_venta": "01",
        "creado_en": "2026-04-01T10:30:00-06:00",
        "actualizado_en": "2026-04-01T10:30:01-06:00"
    }
}
```

---

## Campos requeridos para FE

| Campo | Tipo | Descripcion |
|---|---|---|
| `receipt_type` | `"FE"` | Tipo de comprobante |
| `condicion_venta` | string | `01`=Contado, `02`=Credito, `03`=Consignacion, ..., `99`=Otros |
| `codigo_actividad_emisor` | string(6) | Codigo actividad economica (ej: `6201.0`) |
| `receptor.nombre` | string | Nombre del receptor |
| `receptor.identificacion.tipo` | string | `01`=Fisica, `02`=Juridica, `03`=DIMEX, `04`=NITE, `05`=Extranjero |
| `receptor.identificacion.numero` | string | Numero de cedula/identificacion |
| `detalle_servicio.linea_detalle` | array | Al menos 1 linea |
| `linea_detalle.*.numero_linea` | integer | Consecutivo desde 1 |
| `linea_detalle.*.codigo_cabys` | string(13) | Codigo CABYS de 13 digitos |
| `linea_detalle.*.cantidad` | numeric | Cantidad (DecimalDineroType) |
| `linea_detalle.*.unidad_medida` | string | `Sp`=Servicio, `Unid`=Unidad, etc. |
| `linea_detalle.*.detalle` | string | Descripcion del producto/servicio |
| `linea_detalle.*.precio_unitario` | numeric | Precio unitario |
| `linea_detalle.*.monto_total` | numeric | Cantidad x PrecioUnitario |
| `linea_detalle.*.sub_total` | numeric | MontoTotal - Descuentos |
| `linea_detalle.*.base_imponible` | numeric | Base para calculo de impuestos |
| `linea_detalle.*.monto_totalLinea` | numeric | SubTotal + ImpuestoNeto |
| `resumen_factura.codigo_tipo_moneda.codigo_moneda` | string(3) | `CRC`, `USD`, `EUR` |
| `resumen_factura.codigo_tipo_moneda.tipo_cambio` | numeric | Mayor a 0 (usar `1` para CRC) |
| `resumen_factura.total_venta` | numeric | Suma de MontoTotal de todas las lineas |
| `resumen_factura.total_comprobante` | numeric | Total final del comprobante |

## Campos condicionales

| Campo | Condicion |
|---|---|
| `condicion_venta_otros` | Requerido si `condicion_venta` = `99` |
| `plazo_credito` | Requerido si `condicion_venta` = `02` o `10` |
| `linea_detalle.*.impuesto.*.codigo_tarifa_iva` | Requerido si impuesto.codigo = `01` o `07` |
| `linea_detalle.*.impuesto.*.factor_calculo_iva` | Requerido si impuesto.codigo = `08` |

## Campos opcionales

| Campo | Descripcion |
|---|---|
| `receptor.ubicacion` | Provincia, Canton, Distrito, OtrasSenas |
| `receptor.telefono` | CodigoPais, NumTelefono |
| `receptor.correo_electronico` | Array de emails (max 4) |
| `receptor.nombreComercial` | Nombre comercial del receptor |
| `linea_detalle.*.codigo_comercial` | Array de codigos comerciales (max 5) |
| `linea_detalle.*.descuento` | Array de descuentos (max 5) |
| `linea_detalle.*.impuesto.*.exoneracion` | Datos de exoneracion |
| `otros_cargos` | Cargos adicionales (max 15) |
| `informacion_referencia` | Referencias a otros documentos (max 10) |
| `otros` | otro_texto, otro_contenido |
| `external_reference` | Referencia al sistema externo del cliente (max 100 chars) |

## Auto-generados por el paquete (NO enviar)

- `clave` — clave de 50 digitos
- `numero_consecutivo` — consecutivo de 20 digitos
- `fecha_emision` — fecha/hora actual
- `emisor` — datos del emisor desde variables de entorno
- `proveedor_sistema` — codigo del proveedor de sistemas
- `total_desglose_impuesto` — calculado automaticamente de los impuestos de las lineas

## Formulas de calculo

```
MontoTotal      = Cantidad x PrecioUnitario
SubTotal        = MontoTotal - sum(descuento.*.monto_descuento)
impuesto.monto  = SubTotal x (Tarifa / 100)
ImpuestoNeto    = sum(Impuesto.*.Monto) - sum(Exoneracion.*.MontoExoneracion)
MontoTotalLinea  = SubTotal + ImpuestoNeto

TotalVenta       = sum(linea_detalle.*.monto_total)
TotalDescuentos  = sum(linea_detalle.*.descuento.*.MontoDescuento)
TotalVentaNeta   = TotalVenta - TotalDescuentos
TotalImpuesto    = sum(LineaDetalle.*.ImpuestoNeto)
TotalComprobante = TotalVentaNeta + TotalImpuesto + TotalOtrosCargos - TotalIVADevuelto
```

## Codigos de referencia

**CondicionVenta:** `01`=Contado, `02`=Credito, `03`=Consignacion, `04`=Apartado, `05`=Arrendamiento con opcion de compra, `06`=Arrendamiento en funcion financiera, `07`=Cobro a favor de tercero, `08`=Servicios prestados al Estado a credito, `10`=Plazo fijo, `12`=Otros creditos, `13`=Muestra, `14`=Autofactura, `15`=Formato libre, `99`=Otros

**TipoMedioPago:** `01`=Efectivo, `02`=Tarjeta, `03`=Cheque, `04`=Transferencia/deposito, `05`=Recaudado por terceros, `06`=Otros sistemas de pago, `07`=Compensacion, `99`=Otros

**impuesto.codigo:** `01`=IVA, `02`=Selectivo de consumo, `03`=Unico a combustibles, `04`=Bebidas alcoholicas, `05`=Bebidas envasadas sin alcohol, `06`=Productos de tabaco, `07`=IVA calculo especial, `08`=IVA regimen bienes usados, `12`=Impuesto especifico, `99`=Otros

**CodigoTarifaIVA:** `01`=Exento, `02`=Tarifa reducida 1%, `03`=Tarifa reducida 2%, `04`=Tarifa reducida 4%, `05`=Transitorio 0%, `06`=Transitorio 4%, `07`=Transitorio 8%, `08`=Tarifa general 13%, `09`=Tarifa diferenciada, `10`=Tarifa reducida proporcional, `11`=Calculo especial
