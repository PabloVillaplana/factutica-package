# Validaciones del Paquete vs XSD de Hacienda v4.4

Este documento compara lo que valida `StoreReceiptRequest` + `ReceiptTypeRules` + `CalculationValidatorService` contra lo que exige el esquema XSD v4.4 del Ministerio de Hacienda.

---

## Leyenda

- **Paquete**: lo que valida Laravel antes de generar XML
- **XSD**: lo que exige el esquema de Hacienda
- OK = match correcto
- GAP = el paquete no valida pero Hacienda si exige
- EXTRA = el paquete valida pero Hacienda no exige (validacion defensiva)

---

## Campos Raiz

| Campo XSD | Requerido XSD | Validacion Paquete | Estado |
|-----------|:------------:|---------------------|--------|
| Clave | Si | nullable (se auto-genera) | OK -- inyectado por InvoicingService |
| ProveedorSistemas | Si | nullable (se auto-genera) | OK -- inyectado desde config |
| CodigoActividadEmisor | Depende | nullable, size:6 por tipo | OK |
| CodigoActividadReceptor | No | nullable | OK |
| NumeroConsecutivo | Si | nullable (se auto-genera) | OK |
| FechaEmision | Si | nullable (se auto-genera) | OK |
| CondicionVenta | Si | required, in:01-08,10,12-15,99 | OK |
| CondicionVentaOtros | Condicional | required_if:CondicionVenta,99, min:5, max:100 | OK |
| PlazoCredito | Condicional | required_if:CondicionVenta,02,10, integer, min:1, max:99999 | OK |
| MedioPago | Depende | nullable (requerido en REP) | OK |

---

## Emisor

| Campo XSD | Requerido XSD | Validacion Paquete | Estado |
|-----------|:------------:|---------------------|--------|
| Nombre | Si | required_with:Emisor, min:5, max:100 | OK |
| Identificacion.Tipo | Si | required_with, in:01-06 | OK |
| Identificacion.Numero | Si | required_with, max:20, ValidateIdentification | OK |
| Registrofiscal8707 | No | nullable, max:12 | OK |
| NombreComercial | No | nullable, min:3, max:80 | OK |
| Ubicacion.Provincia | Condicional | required_with:Ubicacion, in:1-7 | OK |
| Ubicacion.Canton | Condicional | required_with:Ubicacion, regex 2 digitos | OK |
| Ubicacion.Distrito | Condicional | required_with:Ubicacion, regex 2 digitos | OK |
| Ubicacion.Barrio | No | nullable, min:5, max:80 | OK |
| Ubicacion.OtrasSenas | Condicional | required_with:Ubicacion, min:5, max:250 | OK |
| OtrasSenasExtranjero | No | nullable, max:300 | OK |
| Telefono.CodigoPais | Condicional | required_with:Telefono, digits_between:1,3 | OK |
| Telefono.NumTelefono | Condicional | required_with:Telefono, digits_between:8,20 | OK |
| CorreoElectronico | No | nullable, array, max:4, email por item | OK |

---

## Receptor

| Campo XSD | Requerido XSD | Validacion Paquete | Estado |
|-----------|:------------:|---------------------|--------|
| Nombre | Si | required, min:3, max:100 | OK |
| Identificacion.Tipo | Depende tipo | required_with, in:01-06, ValidateIdentification | OK |
| Identificacion.Numero | Depende tipo | required_with, max:20, ValidateIdentification | OK |
| Registrofiscal8707 | No | nullable | OK |
| NombreComercial | No | nullable, min:3, max:80 | OK |
| Ubicacion.Provincia | Condicional | required_with:Ubicacion, in:1-7 | OK |
| Ubicacion.Canton | Condicional | required_with:Ubicacion, regex 2 digitos | OK |
| Ubicacion.Distrito | Condicional | required_with:Ubicacion, regex 2 digitos | OK |
| Ubicacion.Barrio | No | nullable, min:5, max:50 | OK |
| Ubicacion.OtrasSenas | Condicional | required_with:Ubicacion, max:160 | OK |
| OtrasSenasExtranjero | No | nullable, max:300 | OK |
| Telefono.CodigoPais | Condicional | required_with:Telefono, digits_between:1,3 | OK |
| Telefono.NumTelefono | Condicional | required_with:Telefono, digits_between:8,20 | OK |
| CorreoElectronico | No | nullable, array, max:4, email por item | OK |

---

## DetalleServicio / LineaDetalle

| Campo XSD | Requerido XSD | Validacion Paquete | Estado |
|-----------|:------------:|---------------------|--------|
| NumeroLinea | Si | required, integer, min:1, max:1000 | OK |
| CodigoCABYS | Si | required, string, size:13 | OK |
| CodigoComercial | No | nullable, array, max:5 | OK |
| CodigoComercial.*.Tipo | Si | required, in:01-04,99 | OK |
| CodigoComercial.*.Codigo | Si | required, min:1, max:20 | OK |
| Cantidad | Si | required, numeric, gte:0, DecimalDinero | OK |
| UnidadMedida | Si | required, string, max:20 + catalogo Hacienda (DetailLineValidator) | OK |
| TipoTransaccion | No | nullable, in:01-13 | OK |
| UnidadMedidaComercial | No | nullable, max:20 | OK |
| Detalle | Si | required, string, min:3, max:200 | OK |
| NumeroVINoSerie | No | nullable, array, max:1000, items max:17 | OK |
| RegistroMedicamento | No | nullable, max:100 | OK |
| FormaFarmaceutica | No | nullable, max:3 + catalogo Hacienda (DetailLineValidator) | OK |
| PrecioUnitario | Si | required, numeric, gte:0, DecimalDinero | OK |
| MontoTotal | Si | required, numeric, DecimalDinero | OK |
| SubTotal | Si | required, numeric, DecimalDinero | OK |
| IVACobradoFabrica | No | nullable, in:01,02 | OK |
| BaseImponible | Si | required, numeric, DecimalDinero | OK |
| MontoExportacion | No | nullable, numeric, DecimalDinero | OK |
| ImpuestoNeto | No | nullable, numeric, DecimalDinero | OK |
| MontoTotalLinea | Si | required, numeric, DecimalDinero | OK |
| ImpuestoAsumidoEmisorFabrica | No | nullable, numeric, DecimalDinero | OK |
| Impuesto.Codigo | Si | required, in:01-08,12,99 | OK |
| Impuesto.CodigoTarifaIVA | Condicional | required_if codigo 01,07, in:01-11 | OK |
| Impuesto.CodigoImpuestoOtro | Condicional | required_if codigo 99 | OK |
| Impuesto.Tarifa | No | nullable, numeric, min:0, max:100 | OK |
| Impuesto.FactorCalculoIVA | Condicional | required_if codigo 08 | OK |
| Impuesto.Monto | Si | required, numeric, DecimalDinero | OK |
| Impuesto.DatosImpuestoEspecifico | Condicional | nullable, array + campos requeridos | OK |
| Descuento.MontoDescuento | Si | required, numeric, gte:0, DecimalDinero | OK |
| Descuento.CodigoDescuento | Si | required, in:01-09,99 | OK |
| Descuento.CodigoDescuentoOtro | Condicional | required_if codigo 99 | OK |
| Descuento.NaturalezaDescuento | No | nullable, min:3, max:80 | OK |
| Exoneracion.TipoDocumentoEX1 | Si | required_with, in:01-11,99 | OK |
| Exoneracion.TipoDocumentoOTRO | Condicional | required_if TipoDocumentoEX1=99 | OK |
| Exoneracion.NumeroDocumento | Si | required_with, min:3, max:40 | OK |
| Exoneracion.Articulo | No | nullable, integer, max:999999 | OK |
| Exoneracion.Inciso | No | nullable, integer, max:999999 | OK |
| Exoneracion.NombreInstitucion | Si | required_with | OK |
| Exoneracion.FechaEmisionEX | Si | required_with | OK |
| Exoneracion.TarifaExonerada | Si | required_with, numeric, min:0, max:100 | OK |
| Exoneracion.MontoExoneracion | Si | required_with, numeric, DecimalDinero | OK |

---

## ResumenFactura

Los campos del `ResumenFactura` se envian dentro de la estructura del payload XML (no como campos planos del request). `InvoicingService` los usa para poblar el modelo Receipt internamente.

| Campo XSD | Requerido XSD | Validacion Paquete | Estado |
|-----------|:------------:|---------------------|--------|
| CodigoTipoMoneda | Si | required, array | OK |
| CodigoMoneda | Si | required, size:3 | OK |
| TipoCambio | Si | required, numeric, gt:0, DecimalDinero | OK |
| TotalServGravados | No | nullable, numeric, min:0, DecimalDinero | OK |
| TotalServExentos | No | nullable, numeric, min:0, DecimalDinero | OK |
| TotalServExonerado | No | nullable, numeric, min:0, DecimalDinero | OK |
| TotalServNoSujeto | No | nullable, numeric, min:0, DecimalDinero | OK |
| TotalMercanciasGravadas | No | nullable, numeric, min:0, DecimalDinero | OK |
| TotalMercanciasExentas | No | nullable, numeric, min:0, DecimalDinero | OK |
| TotalMercExonerada | No | nullable, numeric, min:0, DecimalDinero | OK |
| TotalMercNoSujeta | No | nullable, numeric, min:0, DecimalDinero | OK |
| TotalGravado | No | nullable, numeric, min:0, DecimalDinero | OK |
| TotalExento | No | nullable, numeric, min:0, DecimalDinero | OK |
| TotalExonerado | No | nullable, numeric, min:0, DecimalDinero | OK |
| TotalNoSujeto | No | nullable, numeric, min:0, DecimalDinero | OK |
| TotalVenta | Si | required, numeric, min:0, DecimalDinero | OK |
| TotalDescuentos | No | nullable, numeric, min:0, DecimalDinero | OK |
| TotalVentaNeta | No | nullable, numeric, min:0, DecimalDinero | OK |
| TotalImpuesto | No | nullable, numeric, min:0, DecimalDinero | OK |
| TotalImpAsumEmisorFabrica | No | nullable, numeric, min:0, DecimalDinero | OK |
| TotalIVADevuelto | No | nullable, numeric, min:0, DecimalDinero | OK |
| TotalOtrosCargos | No | nullable, numeric, min:0, DecimalDinero | OK |
| TotalComprobante | Si | required, numeric, min:0, DecimalDinero | OK |
| MedioPago.TipoMedioPago | Si | required, in:01-07,99 | OK |
| MedioPago.MedioPagoOtros | Condicional | required_if tipo 99 | OK |
| MedioPago.TotalMedioPago | Si | required, numeric, min:0, DecimalDinero | OK |
| TotalDesgloseImpuesto.Codigo | Si | required, in:01-08,12,99 | OK |
| TotalDesgloseImpuesto.CodigoTarifaIVA | No | nullable, in:01-11 | OK |
| TotalDesgloseImpuesto.TotalMontoImpuesto | Si | required, numeric, DecimalDinero | OK |

---

## OtrosCargos

| Campo XSD | Requerido XSD | Validacion Paquete | Estado |
|-----------|:------------:|---------------------|--------|
| TipoDocumentoOC | Si | required, in:01-10,99 | OK |
| TipoDocumentoOTROS | Condicional | required_if tipo 99 | OK |
| IdentificacionTercero | Condicional | required_if tipo 04 | OK |
| NombreTercero | Condicional | required_if tipo 04 | OK |
| Detalle | Si | required, max:160 | OK |
| PorcentajeOC | No | nullable, numeric, DecimalDinero | OK |
| MontoCargo | Si | required, numeric, min:0, DecimalDinero | OK |

---

## InformacionReferencia

| Campo XSD | Requerido XSD | Validacion Paquete | Estado |
|-----------|:------------:|---------------------|--------|
| TipoDocIR | Si | required, in:01-12,14,15,17,18,99 | OK |
| TipoDocRefOTRO | Condicional | required_if TipoDocIR=99 | OK |
| Numero | No | nullable, max:50 | OK |
| FechaEmisionIR | Si | required | OK |
| Codigo | No | nullable, in:01,02,04-12,99 | OK |
| CodigoReferenciaOTRO | Condicional | required_if Codigo=99 | OK |
| Razon | No | nullable, min:3, max:180 | OK |

---

## Custom Rules

### ValidateIdentification

Regla reutilizable (`Rules\ValidateIdentification`) que valida el formato del numero de identificacion segun el tipo. Se aplica tanto a Emisor como a Receptor.

| Tipo | Formato esperado |
|------|-----------------|
| 01 Fisica | 9 digitos, sin cero inicial |
| 02 Juridica | exactamente 10 digitos |
| 03 DIMEX | 11 o 12 digitos, sin cero inicial |
| 04 NITE | exactamente 10 digitos |
| 05 Extranjero | alfanumerico, max 20 |
| 06 No contribuyente | alfanumerico, max 20 |

### DecimalDinero

Regla reutilizable (`Rules\DecimalDinero`) que valida el formato `DecimalDineroType` del XSD: maximo 13 digitos enteros y 5 decimales. Se aplica a todos los campos monetarios del comprobante.

### ServiceDetailRequired

Regla (`Rules\ServiceDetailRequired`) que valida que DetalleServicio sea requerido excepto cuando OtrosCargos contiene tipos 04, 08, 09 o 10 (casos donde Hacienda permite facturas sin lineas de detalle).

---

## Validaciones Cross-Field (CondicionVenta)

| Regla | Validacion | Estado |
|-------|-----------|--------|
| CondicionVenta=99 requiere CondicionVentaOtros | required_if:CondicionVenta,99 | OK |
| CondicionVenta=02,10 requiere PlazoCredito | required_if:CondicionVenta,02,10 | OK |

---

## Validaciones Matematicas (CalculationValidatorService)

Orquestador que ejecuta 5 validadores en orden antes de persistir en DB. Se lanza `InvalidReceiptException` si algun calculo no cuadra. Tolerancia: 0.01.

### DetailLineValidator -- Calculos por linea

| Regla XSD | Validacion Paquete | Estado |
|-----------|---------------------|--------|
| MontoTotal = Cantidad x PrecioUnitario | `validateMontoTotal()` | OK |
| SubTotal = MontoTotal - sum(Descuentos) | `validateSubTotal()` | OK |
| BaseImponible = SubTotal + sum(impuestos selectivos 02,04,05,12) | `validateBaseImponible()` (skip si IVACobradoFabrica=01 o codigo 07) | OK |
| ImpuestoNeto = sum(Monto) - sum(MontoExoneracion) - ImpuestoAsumidoEmisorFabrica | `validateImpuestoNeto()` | OK |
| MontoTotalLinea = SubTotal + ImpuestoNeto | `validateMontoTotalLinea()` | OK |
| MontoDescuento <= MontoTotal | `validateDescuentoBounds()` | OK |
| 0 <= MontoExportacion <= MontoTotal | `validateMontoExportacion()` | OK |
| UnidadMedida en catalogo Hacienda | `validateUnidadMedida()` | OK -- EXTRA |
| FormaFarmaceutica en catalogo Hacienda | `validateFormaFarmaceutica()` | OK -- EXTRA |
| NumeroVINoSerie requerido para CABYS 64xx/65xx (transporte) | `validateVinForTransport()` | OK -- EXTRA |

### TaxCalculationValidator -- Calculos de impuestos

| Regla XSD | Validacion Paquete | Estado |
|-----------|---------------------|--------|
| IVA (01): Monto = BaseImponible x (Tarifa / 100) | `validateTaxAmount()` | OK |
| Selectivo (02): Monto = SubTotal x (Tarifa / 100) | `validateTaxAmount()` | OK |
| Combustible (03): Monto = CantidadUnidadMedida x ImpuestoUnidad | `validateTaxAmount()` | OK |
| Alcoholico (04): Monto = Cantidad x Proporcion x ImpuestoUnidad | `validateTaxAmount()` | OK |
| Bebidas sin alc. (05): Monto = Cantidad x CantUM x (ImpUnidad / Volumen) | `validateTaxAmount()` | OK |
| Tabaco (06): Monto = Cantidad x CantidadUnidadMedida x ImpuestoUnidad | `validateTaxAmount()` | OK |
| IVA Bienes Usados (07): Monto = BaseImponible x (Tarifa / 100) | `validateTaxAmount()` | OK |
| Cemento (08): Monto = SubTotal x (Tarifa / 100) | `validateTaxAmount()` | OK |
| Impuesto Cemento (12): Monto = SubTotal x (Tarifa / 100), Tarifa debe ser 5 | `validateTaxAmount()` + `validateDatosEspecificos()` | OK |
| CodigoTarifaIVA corresponde con Tarifa (01,07) | `validateTariffConsistency()` con mapeo completo | OK |
| DatosImpuestoEspecifico requerido para codigos 03,04,05,06 | `validateDatosEspecificos()` | OK |
| MontoExoneracion = TarifaExonerada% x BaseImponible | `validateExoneracion()` | OK |
| TarifaExonerada <= Tarifa para tarifas 04,11 | `validateExoneracion()` | OK |

### InvoiceSummaryValidator -- Calculos del resumen

| Regla XSD | Validacion Paquete | Estado |
|-----------|---------------------|--------|
| TotalVenta = TotalGravado + TotalExento + TotalExonerado + TotalNoSujeto | `validateTotalVenta()` | OK |
| TotalVentaNeta = TotalVenta - TotalDescuentos | `validateTotalVentaNeta()` | OK |
| TotalComprobante = TotalVentaNeta + TotalImpuesto + TotalOtrosCargos - TotalIVADevuelto | `validateTotalComprobante()` | OK |
| TotalDescuentos = sum de MontoDescuento de todas las lineas | `validateTotalDescuentos()` | OK |
| TotalImpuesto = sum de ImpuestoNeto de todas las lineas | `validateTotalImpuesto()` | OK |
| TotalImpAsumEmisorFabrica = sum de ImpuestoAsumidoEmisorFabrica de lineas | `validateTotalImpAsumEmisorFabrica()` | OK |
| TotalIVADevuelto para servicios de salud (CABYS 93xx) con tarjeta (MedioPago 02) | `validateTotalIVADevuelto()` | OK |
| TotalOtrosCargos = sum de MontoCargo | `validateTotalOtrosCargos()` | OK |
| TipoCambio = 1 cuando moneda es CRC | `validateCurrencyExchangeRate()` | OK -- EXTRA |
| 8 subtotales servicio/mercancia calculados desde lineas por CABYS y CodigoTarifaIVA | `validateServiceMerchandiseTotals()` + `calculateFromLines()` | OK |
| 4 totales agregados (Gravado, Exento, Exonerado, NoSujeto) | `validateAggregatedTotals()` | OK |

Clasificacion de lineas:
- CABYS 0-4 = Mercancia, CABYS 5-9 = Servicio
- Tarifas gravadas: 02,03,04,06,07,08,09
- Tarifa exenta: 10
- Tarifas no sujetas: 01, 11
- Exonerado: tiene Exoneracion en algun impuesto

### TaxBreakdownValidator -- Desglose de impuestos

Valida que `TotalDesgloseImpuesto` del ResumenFactura coincida con la suma real de impuestos agrupados por `Codigo` + `CodigoTarifaIVA` desde las lineas de detalle.

| Regla | Validacion Paquete | Estado |
|-------|---------------------|--------|
| TotalMontoImpuesto por codigo+tarifa = sum(Monto - MontoExoneracion) de lineas | `validate()` agrupa por clave codigo-tarifa | OK |
| Codigos faltantes en desglose | Detecta codigos con monto > 0 no declarados | OK |
| Si no se declara, InvoicingService lo auto-genera | Skip cuando vacio | OK |

### AssortmentValidator -- DetalleSurtido (combos/paquetes)

Valida calculos de lineas de surtido (`DetalleSurtido.LineaDetalleSurtido`):

| Regla | Validacion Paquete | Estado |
|-------|---------------------|--------|
| MontoTotalSurtido = CantidadSurtido x PrecioUnitarioSurtido | `validateMontoTotal()` | OK |
| SubTotalSurtido = MontoTotalSurtido - sum(DescuentosSurtido) | `validateSubTotal()` | OK |
| BaseImponibleSurtido = SubTotalSurtido + sum(selectivos 02,04,05) | `validateBaseImponible()` | OK |
| MontoDescuentoSurtido en rango [0, MontoTotalSurtido] | `validateDescuentoBounds()` | OK |
| UnidadMedidaSurtido en catalogo Hacienda | `validateUnidadMedida()` | OK |
| MontoImpuestoSurtido segun formula por codigo | `validateImpuestos()` + `calculateExpectedTax()` | OK |
| TarifaSurtido requerida para codigos 01,02,99 | `validateImpuestos()` | OK |
| CodigoTarifaIVASurtido requerido para codigo 01 | `validateImpuestos()` | OK |
| Consistencia TarifaSurtido vs CodigoTarifaIVASurtido | `validateImpuestos()` con IVA_TARIFF_MAP | OK |
| DatosImpuestoEspecificoSurtido requerido para codigos 04,05,06 | `validateImpuestos()` | OK |
| Descuento regalia/bonificacion usa MontoTotalSurtido como base IVA | `hasSpecialDiscount()` | OK |

---

## Restricciones por Tipo vs XSD

### FE -- Restricciones correctamente implementadas
| Restriccion XSD | Paquete | Estado |
|-----------------|---------|--------|
| Receptor.Identificacion requerido | required, array | OK |
| Receptor.Identificacion.Tipo in 01-05 (no 06) | in:01,02,03,04,05 | OK |
| InformacionReferencia max 10 | max:10 | OK |
| DetalleServicio requerido | required | OK |
| CodigoActividadEmisor requerido | required, size:6 | OK |

### TE -- Restricciones correctamente implementadas
| Restriccion XSD | Paquete | Estado |
|-----------------|---------|--------|
| Receptor.Identificacion opcional | nullable | OK |
| CodigoActividadReceptor prohibido | prohibited | OK |
| TipoTransaccion prohibido | prohibited | OK |

### NC/ND -- Restricciones correctamente implementadas
| Restriccion XSD | Paquete | Estado |
|-----------------|---------|--------|
| InformacionReferencia requerida | required | OK |
| DetalleServicio opcional | No en required | OK |

### FEC -- Restricciones correctamente implementadas
| Restriccion XSD | Paquete | Estado |
|-----------------|---------|--------|
| Emisor.Identificacion.Tipo in 01-05 | required, in:01-05 | OK |
| OtrasSenasExtranjero requerido si tipo=05 | required_if:05 | OK |

### FEE -- Restricciones correctamente implementadas
| Restriccion XSD | Paquete | Estado |
|-----------------|---------|--------|
| Receptor.Ubicacion prohibida | prohibited | OK |
| Totales de exoneracion/no sujeto prohibidos | prohibited (9 campos) | OK |

### REP -- Restricciones implementadas
| Restriccion XSD | Paquete | Estado |
|-----------------|---------|--------|
| MedioPago requerido | required | OK |
| CondicionVenta in 09,11 | in:09,11 | OK |
| 30+ campos prohibidos | prohibited | OK |
| Receptor.Identificacion.Tipo prohibido | prohibited | **GAP** -- base rules lo requieren con required_with, conflicto |
| Generacion XML | **NO IMPLEMENTADO** | **GAP CRITICO** |

---

## TotalMedioPago vs TotalComprobante

| Regla XSD | Validacion Paquete | Estado |
|-----------|---------------------|--------|
| sum(TotalMedioPago) = TotalComprobante | **No validado** | **GAP** |

> Hacienda puede rechazar si la suma de medios de pago no cuadra con el total del comprobante.

---

## Resumen de Gaps

### Criticos (causan rechazo de Hacienda)
1. REP sin generacion XML

### Medios (podrian causar rechazo)
2. TotalMedioPago vs TotalComprobante no validado
3. Conflicto validacion REP: Receptor.Identificacion.Tipo prohibido vs required_with

### Resueltos

- ~~Receptor.CorreoElectronico como string en vez de array~~ -- ahora array, max:4, email por item
- ~~TipoCambio permite 0~~ -- ahora gt:0 con DecimalDinero
- ~~Sin validacion matematica de totales~~ -- ahora CalculationValidatorService con 5 validadores
- ~~MontoTotal no validado~~ -- ahora DetailLineValidator: Cantidad x PrecioUnitario
- ~~SubTotal no validado~~ -- ahora DetailLineValidator: MontoTotal - sum(Descuentos)
- ~~MontoTotalLinea no validado~~ -- ahora DetailLineValidator: SubTotal + ImpuestoNeto
- ~~TotalVenta no validado~~ -- ahora InvoiceSummaryValidator: TotalGravado + TotalExento + TotalExonerado + TotalNoSujeto
- ~~TotalVentaNeta no validado~~ -- ahora InvoiceSummaryValidator: TotalVenta - TotalDescuentos
- ~~TotalComprobante no validado~~ -- ahora InvoiceSummaryValidator: TotalVentaNeta + TotalImpuesto + TotalOtrosCargos - TotalIVADevuelto
- ~~Cantidad, UnidadMedida, PrecioUnitario, MontoTotal, SubTotal, BaseImponible nullable~~ -- ahora required
- ~~CondicionVenta acepta cualquier string~~ -- ahora validado contra enum XSD (01-08,10,12-15,99)
- ~~Campos planos redundantes en request (sell_condition, total_amount, currency, etc.)~~ -- eliminados; InvoicingService los deriva del payload XML
- ~~Precision decimal 3 decimales~~ -- ahora DecimalDinero (13 enteros, 5 decimales)
- ~~OtroTexto con estructura incorrecta~~ -- codigo es atributo XML
- ~~Descuento en posicion incorrecta~~ -- ahora entre MontoTotal y SubTotal
- ~~PartidaArancelaria incluido~~ -- eliminado (no existe en XSD v4.4)
- ~~Sin validacion de formato de identificacion~~ -- ahora ValidateIdentification por tipo (01-06)
- ~~Sin validacion cross-field CondicionVenta~~ -- ahora CondicionVentaOtros required_if 99, PlazoCredito required_if 02,10
- ~~Sin validacion de impuestos por formula~~ -- ahora TaxCalculationValidator con formulas por codigo
- ~~Sin validacion de desglose de impuestos~~ -- ahora TaxBreakdownValidator agrupa por codigo+tarifa
- ~~Sin validacion de surtidos~~ -- ahora AssortmentValidator con calculos completos
- ~~Sin validacion de catalogos (UnidadMedida, FormaFarmaceutica)~~ -- ahora DetailLineValidator contra config catalogues
