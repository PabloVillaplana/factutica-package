# Matriz de Cobertura por Tipo de Comprobante

---

## Estado General

| Tipo | Codigo | Validacion Request | Validacion Calculos | XML Gen | Key Gen | Envio | Tests | Sandbox |
|------|--------|:------------------:|:-------------------:|:-------:|:-------:|:-----:|:-----:|:-------:|
| FE   | 01     | OK                 | OK                  | OK      | OK      | OK    | OK    | OK (aceptada)  |
| TE   | 04     | OK                 | OK                  | OK      | OK      | OK    | OK    | OK (aceptado)  |
| NC   | 03     | OK                 | OK                  | OK      | OK      | OK    | OK    | OK (aceptada)  |
| ND   | 02     | OK                 | OK                  | OK      | OK      | OK    | OK    | OK (aceptada)  |
| FEC  | 08     | OK                 | OK                  | OK      | OK      | -     | -     | -       |
| FEE  | 09     | OK                 | OK                  | OK      | OK      | -     | -     | -       |
| REP  | 07     | OK                 | N/A                 | OK      | OK      | OK    | OK    | -       |

**Leyenda:** OK = implementado y verificado | **NO** = no implementado | N/A = no aplica | - = no probado

---

## Validadores Implementados

El sistema de validacion matematica se ejecuta ANTES de consumir consecutivo o persistir en DB, a traves del orquestador `CalculationValidatorService`. Se aplica a todos los tipos de comprobante que incluyen lineas de detalle y/o resumen de factura (todos excepto REP).

### Pipeline de Validacion

```
InvoicingService::createAndSend()
  -> CalculationValidatorService::validate()
       1. DetailLineValidator        (si hay LineaDetalle)
       2. TaxCalculationValidator    (si hay LineaDetalle)
       3. TaxBreakdownValidator      (si hay LineaDetalle)
       4. AssortmentValidator        (si hay LineaDetalle)
       5. InvoiceSummaryValidator    (si hay ResumenFactura)
  -> (continua con consecutivo, clave, XML, firma, envio)
```

### 1. DetailLineValidator

Valida calculos y datos de cada linea de detalle individual.

| Validacion | Formula / Regla |
|---|---|
| UnidadMedida | Debe existir en catalogos de Hacienda (`catalogues.measurement_units`) |
| FormaFarmaceutica | Debe existir en catalogos de Hacienda (`catalogues.pharmaceutical_forms`) |
| NumeroVINoSerie | Requerido para servicios de transporte (CABYS que inicia con 64 o 65) |
| MontoTotal | `Cantidad x PrecioUnitario` |
| MontoDescuento | Cada descuento `<= MontoTotal` |
| SubTotal | `MontoTotal - sum(Descuentos)` |
| BaseImponible | `SubTotal + sum(impuestos selectivos 02,04,05,12)` (excepto si IVACobradoFabrica=01 o codigo impuesto 07) |
| MontoExportacion | `0 <= MontoExportacion <= MontoTotal` |
| ImpuestoNeto | `sum(Monto) - sum(MontoExoneracion) - ImpuestoAsumidoEmisorFabrica` |
| MontoTotalLinea | `SubTotal + ImpuestoNeto` |

**Tolerancia:** 0.01

### 2. TaxCalculationValidator

Valida calculos de impuestos por codigo para cada linea.

| Codigo | Impuesto | Formula |
|--------|----------|---------|
| 01 | IVA | `BaseImponible x (Tarifa / 100)` |
| 02 | Selectivo | `SubTotal x (Tarifa / 100)` |
| 03 | Combustible | `CantidadUnidadMedida x ImpuestoUnidad` |
| 04 | Alcoholico | `Cantidad x Proporcion x ImpuestoUnidad` |
| 05 | Bebidas sin alcohol | `Cantidad x CantidadUnidadMedida x (ImpuestoUnidad / VolumenUnidadConsumo)` |
| 06 | Tabaco | `Cantidad x CantidadUnidadMedida x ImpuestoUnidad` |
| 07 | IVA Bienes Usados | `BaseImponible x (Tarifa / 100) x FactorCalculoIVA` |
| 08 | Cemento | `SubTotal x (Tarifa / 100)` |
| 12 | Impuesto Cemento | `SubTotal x (Tarifa / 100)`, Tarifa fija = 5 |

Validaciones adicionales:

| Validacion | Regla |
|---|---|
| Consistencia CodigoTarifaIVA/Tarifa | Mapeo fijo: 01->0%, 02->1%, 03->2%, 04->4%, 05->0%, 06->4%, 07->8%, 08->13%, 09->0.5%, 10->0%, 11->0% |
| DatosImpuestoEspecifico | Requerido para codigos 03, 04, 05, 06 |
| MontoExoneracion | `TarifaExonerada% x BaseImponible` |
| TarifaExonerada | No puede exceder Tarifa para tarifas 04, 11 |

### 3. TaxBreakdownValidator

Valida que `TotalDesgloseImpuesto` del `ResumenFactura` coincida con la suma real de impuestos agrupados por `Codigo + CodigoTarifaIVA` desde las lineas de detalle.

| Validacion | Regla |
|---|---|
| TotalMontoImpuesto por codigo+tarifa | Suma de `Monto - MontoExoneracion` de todas las lineas con ese codigo+tarifa |
| Completitud del desglose | No pueden faltar codigos con monto > 0 |

Se omite si `TotalDesgloseImpuesto` no viene declarado (InvoicingService lo auto-genera).

### 4. AssortmentValidator

Valida calculos del `DetalleSurtido` (combos/paquetes/surtidos con 2+ productos de diferente CABYS en una sola linea).

| Validacion | Formula / Regla |
|---|---|
| UnidadMedidaSurtido | Debe existir en catalogos de Hacienda |
| MontoTotalSurtido | `CantidadSurtido x PrecioUnitarioSurtido` |
| DescuentoSurtido | `0 <= MontoDescuentoSurtido <= MontoTotalSurtido` |
| SubTotalSurtido | `MontoTotalSurtido - sum(DescuentosSurtido)` |
| BaseImponibleSurtido | `SubTotalSurtido + sum(impuestos selectivos 02,04,05)` |
| MontoImpuestoSurtido | Segun formula del codigo (01=IVA, 02=selectivo, 04=alcoholico, 05=bebidas, 06=tabaco) |
| DatosImpuestoEspecificoSurtido | Requerido para codigos 04, 05, 06 |
| TarifaSurtido | Requerida para codigos 01, 02, 99 |
| CodigoTarifaIVASurtido | Requerido cuando codigo es 01 |
| Consistencia TarifaSurtido/CodigoTarifaIVASurtido | Mismo mapeo que TaxCalculationValidator |

### 5. InvoiceSummaryValidator

Valida los calculos del `ResumenFactura` contra las lineas de detalle.

**Clasificacion automatica de lineas:**

| Criterio | Clasificacion |
|---|---|
| CABYS inicia con 0-4 | Mercancia |
| CABYS inicia con 5-9 | Servicio |
| CodigoTarifaIVA 02,03,04,06,07,08,09 | Gravado |
| CodigoTarifaIVA 10 | Exento |
| CodigoTarifaIVA 01, 11 | No Sujeto |
| Tiene Exoneracion | Exonerado |

**Validaciones del resumen:**

| Campo | Formula |
|---|---|
| TipoCambio | Debe ser 1 cuando moneda es CRC |
| TotalServGravados | Suma de SubTotal de lineas servicio gravadas |
| TotalServExentos | Suma de SubTotal de lineas servicio exentas |
| TotalServExonerado | Suma de SubTotal de lineas servicio exoneradas |
| TotalServNoSujeto | Suma de SubTotal de lineas servicio no sujetas |
| TotalMercanciasGravadas | Suma de SubTotal de lineas mercancia gravadas |
| TotalMercanciasExentas | Suma de SubTotal de lineas mercancia exentas |
| TotalMercExonerada | Suma de SubTotal de lineas mercancia exoneradas |
| TotalMercNoSujeta | Suma de SubTotal de lineas mercancia no sujetas |
| TotalGravado | `TotalServGravados + TotalMercanciasGravadas` |
| TotalExento | `TotalServExentos + TotalMercanciasExentas` |
| TotalExonerado | `TotalServExonerado + TotalMercExonerada` |
| TotalNoSujeto | `TotalServNoSujeto + TotalMercNoSujeta` |
| TotalDescuentos | Suma de MontoDescuento de todas las lineas |
| TotalImpuesto | Suma de ImpuestoNeto de todas las lineas |
| TotalImpAsumEmisorFabrica | Suma de ImpuestoAsumidoEmisorFabrica de todas las lineas |
| TotalIVADevuelto | Suma de IVA (codigos 01/07) de lineas salud (CABYS 93xx) con pago tarjeta (MedioPago 02) |
| TotalVenta | `TotalGravado + TotalExento + TotalExonerado + TotalNoSujeto` |
| TotalVentaNeta | `TotalVenta - TotalDescuentos` |
| TotalOtrosCargos | Suma de MontoCargo de OtrosCargos |
| TotalComprobante | `TotalVentaNeta + TotalImpuesto + TotalOtrosCargos - TotalIVADevuelto` |

### 6. ReceiptTypeRules (validacion de request)

Reglas de validacion especificas por tipo de comprobante aplicadas en `StoreReceiptRequest`. Define campos `required`, `prohibited` y `extra` por tipo. No es un validador matematico sino estructural.

---

## Detalle por Tipo

### FE -- Factura Electronica (01)

| Aspecto | Estado | Notas |
|---------|--------|-------|
| Validacion request | OK | Receptor.Identificacion requerido, campos de linea required per XSD |
| Validacion tipo-especifica | OK | CodigoActividad max 6 chars, InformacionReferencia max 10 |
| Validacion calculos | OK | Pipeline completo: DetailLine + TaxCalculation + TaxBreakdown + Assortment + InvoiceSummary |
| Generacion XML | OK | Root: `FacturaElectronica`, namespace v4.4 |
| Generacion clave 50 digitos | OK | Tipo codigo: 01 |
| Consecutivo 20 digitos | OK | |
| Envio a Hacienda sandbox | OK | Aceptada exitosamente |
| Tests unitarios | OK | XmlGeneratorTest, KeyGeneratorTest |
| Tests feature | OK | ReceiptControllerTest, InvoicingServiceTest |
| Tests integracion | OK | HaciendaServicesTest |
| Tests validadores | OK | 79 tests dedicados (DetailLine 15, Tax 16, Summary 14, Breakdown 7, Assortment 20, Orchestrator 7) |

### TE -- Tiquete Electronico (04)

| Aspecto | Estado | Notas |
|---------|--------|-------|
| Validacion request | OK | Receptor.Identificacion opcional (puede ser anonimo) |
| Validacion tipo-especifica | OK | CodigoActividadReceptor prohibido, OtrasSenasExtranjero max 160 |
| Validacion calculos | OK | Pipeline completo aplica si tiene DetalleServicio/ResumenFactura |
| Generacion XML | OK | Root: `TiqueteElectronico` |
| Generacion clave | OK | Tipo codigo: 04 |
| Envio a Hacienda sandbox | OK | Aceptado exitosamente |
| Tests | OK | Cubierto por XmlGeneratorTest, KeyGeneratorTest |

### NC -- Nota de Credito (03)

| Aspecto | Estado | Notas |
|---------|--------|-------|
| Validacion request | OK | InformacionReferencia requerida (debe referenciar doc original) |
| Validacion tipo-especifica | OK | DetalleServicio opcional |
| Validacion calculos | OK | Pipeline completo; si no hay DetalleServicio, solo valida InvoiceSummary |
| Generacion XML | OK | Root: `NotaCreditoElectronica` |
| Generacion clave | OK | Tipo codigo: 03 |
| Envio a Hacienda sandbox | OK | Aceptada exitosamente (referenciando FE) |
| Tests | OK | Cubierto por XmlGeneratorTest, KeyGeneratorTest |

### ND -- Nota de Debito (02)

| Aspecto | Estado | Notas |
|---------|--------|-------|
| Validacion request | OK | Mismas reglas que NC |
| Validacion calculos | OK | Pipeline completo; mismo comportamiento que NC |
| Generacion XML | OK | Root: `NotaDebitoElectronica` |
| Generacion clave | OK | Tipo codigo: 02 |
| Envio a Hacienda sandbox | OK | Aceptada exitosamente (referenciando FE) |
| Tests | OK | Cubierto por XmlGeneratorTest, KeyGeneratorTest |

### FEC -- Factura Electronica de Compra (08)

| Aspecto | Estado | Notas |
|---------|--------|-------|
| Validacion request | OK | Emisor.Identificacion.Tipo restringido a 01-05 |
| Validacion tipo-especifica | OK | TipoDocIR con 18+ valores validos, OtrasSenasExtranjero para extranjeros |
| Validacion calculos | OK | Pipeline completo; IVACobradoFabrica prohibido por ReceiptTypeRules |
| Generacion XML | OK | Root: `FacturaElectronicaCompras` |
| Generacion clave | OK | Tipo codigo: 08 |
| Envio a Hacienda sandbox | **No probado** | |
| Tests | **No probado** | |

### FEE -- Factura Electronica de Exportacion (09)

| Aspecto | Estado | Notas |
|---------|--------|-------|
| Validacion request | OK | Receptor.Ubicacion prohibida, multiples totales prohibidos |
| Validacion tipo-especifica | OK | MontoExportacion habilitado en lineas |
| Validacion calculos | OK | Pipeline completo; Exoneracion prohibida, totales exonerado/no sujeto prohibidos por ReceiptTypeRules |
| Generacion XML | OK | Root: `FacturaElectronicaExportacion` |
| Generacion clave | OK | Tipo codigo: 09 |
| Envio a Hacienda sandbox | **No probado** | |
| Tests | **No probado** | |

### REP -- Comprobante de Recibo Electronico de Pago (07)

| Aspecto | Estado | Notas |
|---------|--------|-------|
| Validacion request | OK | MedioPago requerido, estructura minima (sin detalle) |
| Validacion tipo-especifica | OK | CondicionVenta restringido a 09,11. Prohibe 30+ campos |
| Validacion calculos | N/A | No aplica: REP no tiene DetalleServicio ni ResumenFactura con calculos |
| Generacion XML | **NO** | No esta en ROOT_ELEMENTS. Lanza InvalidReceiptException |
| Generacion clave | OK | Tipo codigo: 07 |
| Envio a Hacienda | **NO** | Bloqueado por falta de XML |
| Tests | **NO** | |

---

## Flujos Transversales

| Flujo | Estado | Cobertura Tests |
|-------|--------|:---------------:|
| Crear y enviar (sync) | OK | OK (InvoicingServiceTest, ReceiptControllerTest) |
| Crear y enviar (async) | OK | OK (ReceiptControllerTest async mode) |
| Validacion matematica (pipeline) | OK | OK (79 tests dedicados) |
| Webhook Hacienda | OK | OK (WebhookControllerTest) |
| Recepcion MensajeReceptor (async) | OK | OK (ReceptionControllerTest, 9 tests) |
| Consulta status a Hacienda | OK | OK (indirecto via ReceiptControllerTest) |
| Job SendReceiptToProviderJob | OK | Indirecto (mismo flujo que InvoicingServiceTest) |
| Job SendSentReceiptToProviderJob | OK | Indirecto (dispatch verificado en ReceptionControllerTest) |
| OAuth2 token refresh | OK | OK (TokenDataTest) |
| Firma XAdES-EPES | OK | OK (integracion) |
| Verificacion webhook | OK | Indirecto (WebhookControllerTest) |
| Seguridad XML | OK | OK (XmlSecurityTest, 8 tests) |

### Detalle del Pipeline de Validacion

| Validador | Alcance | Tests |
|-----------|---------|:-----:|
| CalculationValidatorService | Orquestador: ejecuta los 5 validadores en orden | OK (7 tests) |
| DetailLineValidator | 10 validaciones por linea (MontoTotal, SubTotal, BaseImponible, ImpuestoNeto, etc.) | OK (15 tests) |
| TaxCalculationValidator | 8 codigos de impuesto + consistencia tarifa + exoneracion + datos especificos | OK (16 tests) |
| TaxBreakdownValidator | TotalDesgloseImpuesto vs suma real por codigo+tarifa | OK (7 tests) |
| AssortmentValidator | Calculos de DetalleSurtido (combos/paquetes) | OK (20 tests) |
| InvoiceSummaryValidator | 21 validaciones del ResumenFactura (8 subtotales + 4 agregados + 9 totales) | OK (14 tests) |
| ReceiptTypeRules | Reglas required/prohibited/extra por tipo (usado en StoreReceiptRequest) | OK (indirecto via ReceiptControllerTest) |
| Rules (DecimalDinero, ValidateIdentification, ServiceDetailRequired) | Reglas de validacion custom | OK (35 tests) |
