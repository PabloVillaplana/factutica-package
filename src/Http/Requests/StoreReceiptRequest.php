<?php

namespace FactuTica\FactuticaCR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use FactuTica\FactuticaCR\Constants;
use FactuTica\FactuticaCR\Enums\ReceiptType;
use FactuTica\FactuticaCR\Rules\DecimalDinero;
use FactuTica\FactuticaCR\Rules\ValidateIdentification;
use FactuTica\FactuticaCR\Services\PayloadTransformerService;
use FactuTica\FactuticaCR\Services\Validators\ReceiptTypeRules;

class StoreReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $transformer = app(PayloadTransformerService::class);
        $this->replace($transformer->toPascalCase($this->all()));
    }

    public function rules(): array
    {
        $base = $this->baseRules();

        $type = ReceiptType::tryFrom($this->input('receipt_type', ''));

        if ($type) {
            $typeRules = ReceiptTypeRules::toValidationRules($type);
            $base = array_merge($base, $typeRules);
        }

        return $base;
    }

    /**
     * Reglas base compartidas por todos los tipos de comprobante.
     * Alineadas con el XSD v4.4 de Hacienda y las validaciones del paquete original.
     */
    private function baseRules(): array
    {
        $d = new DecimalDinero;

        return [
            // ─── Tipo de comprobante ─────────────────────────────────────
            'receipt_type' => ['required', 'string', 'in:'.implode(',', array_column(ReceiptType::cases(), 'value'))],
            'external_reference' => ['nullable', 'string', 'max:100'],
            'establishment' => ['nullable', 'integer', 'min:1', 'max:999'],
            'terminal' => ['nullable', 'integer', 'min:1', 'max:99999'],

            // ─── Datos de control (auto-generados por InvoicingService) ──
            'Clave'                   => ['nullable', 'string', 'size:'.Constants::CLAVE_LENGTH],
            'ProveedorSistema'        => ['nullable', 'string', 'max:20'],
            'NumeroConsecutivo'       => ['nullable', 'string', 'size:20'],
            'FechaEmision'            => ['nullable', 'date_format:Y-m-d\TH:i:sP'],
            'CondicionVenta'          => ['required', 'string', 'in:01,02,03,04,05,06,07,08,09,10,11,12,13,14,15,99'],
            'CondicionVentaOtros'     => ['required_if:CondicionVenta,99', 'nullable', 'string', 'min:5', 'max:100'],
            'PlazoCredito'            => ['required_if:CondicionVenta,02,10,11', 'nullable', 'integer', 'min:1', 'max:99999'],
            'CodigoActividadEmisor'   => ['nullable', 'string', 'size:6'],
            'CodigoActividadReceptor' => ['nullable', 'string', 'size:6'],

            // ─── Emisor (auto-completado desde config si no se envía) ───
            'Emisor'                            => ['nullable', 'array'],
            'Emisor.Nombre'                     => ['required_with:Emisor', 'string', 'min:5', 'max:100'],
            'Emisor.Identificacion'             => ['required_with:Emisor', 'array'],
            'Emisor.Identificacion.Tipo'        => ['required_with:Emisor.Identificacion', 'string', 'in:01,02,03,04,05,06'],
            'Emisor.Identificacion.Numero'      => ['required_with:Emisor.Identificacion', 'string', 'max:20', new ValidateIdentification('Emisor.Identificacion.Tipo')],
            'Emisor.Registrofiscal8707'         => ['nullable', 'string', 'max:12'],
            'Emisor.NombreComercial'            => ['nullable', 'string', 'min:3', 'max:80'],
            'Emisor.Ubicacion'                  => ['nullable', 'array'],
            'Emisor.Ubicacion.Provincia'        => ['required_with:Emisor.Ubicacion', 'string', 'in:1,2,3,4,5,6,7'],
            'Emisor.Ubicacion.Canton'           => ['required_with:Emisor.Ubicacion', 'string', 'regex:/^[0-9]{2}$/'],
            'Emisor.Ubicacion.Distrito'         => ['required_with:Emisor.Ubicacion', 'string', 'regex:/^[0-9]{2}$/'],
            'Emisor.Ubicacion.Barrio'           => ['nullable', 'string', 'min:5', 'max:80'],
            'Emisor.Ubicacion.OtrasSenas'       => ['required_with:Emisor.Ubicacion', 'string', 'min:5', 'max:250'],
            'Emisor.OtrasSenasExtranjero'       => ['nullable', 'string', 'max:300'],
            'Emisor.Telefono'                   => ['nullable', 'array'],
            'Emisor.Telefono.CodigoPais'        => ['required_with:Emisor.Telefono', 'string', 'digits_between:1,3'],
            'Emisor.Telefono.NumTelefono'       => ['required_with:Emisor.Telefono', 'string', 'digits_between:8,20'],
            'Emisor.CorreoElectronico'          => ['nullable', 'array', 'max:4'],
            'Emisor.CorreoElectronico.*'        => ['email', 'max:160'],

            // ─── Receptor ────────────────────────────────────────────────
            'Receptor'                            => ['required', 'array'],
            'Receptor.Nombre'                     => ['required', 'string', 'min:3', 'max:100'],
            'Receptor.Identificacion'             => ['nullable', 'array'],
            'Receptor.Identificacion.Tipo'        => ['required_with:Receptor.Identificacion', 'string', 'in:01,02,03,04,05,06'],
            'Receptor.Identificacion.Numero'      => ['required_with:Receptor.Identificacion', 'string', 'max:20', new ValidateIdentification('Receptor.Identificacion.Tipo')],
            'Receptor.Registrofiscal8707'         => ['nullable', 'string'],
            'Receptor.NombreComercial'            => ['nullable', 'string', 'min:3', 'max:80'],
            'Receptor.Ubicacion'                  => ['nullable', 'array'],
            'Receptor.Ubicacion.Provincia'        => ['required_with:Receptor.Ubicacion', 'string', 'in:1,2,3,4,5,6,7'],
            'Receptor.Ubicacion.Canton'           => ['required_with:Receptor.Ubicacion', 'string', 'regex:/^[0-9]{2}$/'],
            'Receptor.Ubicacion.Distrito'         => ['required_with:Receptor.Ubicacion', 'string', 'regex:/^[0-9]{2}$/'],
            'Receptor.Ubicacion.Barrio'           => ['nullable', 'string', 'min:5', 'max:50'],
            'Receptor.Ubicacion.OtrasSenas'       => ['required_with:Receptor.Ubicacion', 'string', 'max:160'],
            'Receptor.OtrasSenasExtranjero'       => ['nullable', 'string', 'max:300'],
            'Receptor.Telefono'                   => ['nullable', 'array'],
            'Receptor.Telefono.CodigoPais'        => ['required_with:Receptor.Telefono', 'string', 'digits_between:1,3'],
            'Receptor.Telefono.NumTelefono'       => ['required_with:Receptor.Telefono', 'string', 'digits_between:8,20'],
            'Receptor.CorreoElectronico'          => ['nullable', 'array', 'max:4'],
            'Receptor.CorreoElectronico.*'        => ['email', 'max:160'],

            // ─── Detalle servicio ────────────────────────────────────────
            'DetalleServicio'                                       => ['nullable', 'array'],
            'DetalleServicio.LineaDetalle'                           => ['required_with:DetalleServicio', 'array', 'min:1', 'max:1000'],
            'DetalleServicio.LineaDetalle.*.NumeroLinea'             => ['required', 'integer', 'min:1', 'max:1000'],
            'DetalleServicio.LineaDetalle.*.CodigoCABYS'            => array_filter([
                'required', 'string', 'size:13',
                config('invoicing-cr.invoicing.validate_cabys') ? 'exists:invoicing_cr_cabys,codigo' : null,
            ]),
            'DetalleServicio.LineaDetalle.*.CodigoComercial'        => ['nullable', 'array', 'max:5'],
            'DetalleServicio.LineaDetalle.*.CodigoComercial.*.Tipo' => ['required', 'string', 'in:01,02,03,04,99'],
            'DetalleServicio.LineaDetalle.*.CodigoComercial.*.Codigo' => ['required', 'string', 'min:1', 'max:20'],
            'DetalleServicio.LineaDetalle.*.Cantidad'               => ['required', 'numeric', 'gte:0', new DecimalDinero],
            'DetalleServicio.LineaDetalle.*.UnidadMedida'           => ['required', 'string', 'max:20'],
            'DetalleServicio.LineaDetalle.*.TipoTransaccion'        => ['nullable', 'string', 'in:01,02,03,04,05,06,07,08,09,10,11,12,13'],
            'DetalleServicio.LineaDetalle.*.UnidadMedidaComercial'  => ['nullable', 'string', 'max:20'],
            'DetalleServicio.LineaDetalle.*.Detalle'                => ['required', 'string', 'min:3', 'max:200'],
            'DetalleServicio.LineaDetalle.*.NumeroVINoSerie'        => ['nullable', 'array', 'max:1000'],
            'DetalleServicio.LineaDetalle.*.NumeroVINoSerie.*'      => ['string', 'max:17'],
            'DetalleServicio.LineaDetalle.*.RegistroMedicamento'    => ['nullable', 'string', 'max:100'],
            'DetalleServicio.LineaDetalle.*.FormaFarmaceutica'      => ['nullable', 'string', 'max:3'],
            'DetalleServicio.LineaDetalle.*.PrecioUnitario'         => ['required', 'numeric', 'gte:0', $d],
            'DetalleServicio.LineaDetalle.*.MontoTotal'             => ['required', 'numeric', $d],
            'DetalleServicio.LineaDetalle.*.SubTotal'               => ['required', 'numeric', $d],
            'DetalleServicio.LineaDetalle.*.IVACobradoFabrica'      => ['nullable', 'string', 'in:01,02'],
            'DetalleServicio.LineaDetalle.*.BaseImponible'          => ['required', 'numeric', $d],
            'DetalleServicio.LineaDetalle.*.MontoExportacion'       => ['nullable', 'numeric', $d],
            'DetalleServicio.LineaDetalle.*.ImpuestoNeto'           => ['nullable', 'numeric', $d],
            'DetalleServicio.LineaDetalle.*.MontoTotalLinea'        => ['required', 'numeric', $d],
            'DetalleServicio.LineaDetalle.*.ImpuestoAsumidoEmisorFabrica' => ['nullable', 'numeric', $d],

            // ─── Impuestos por línea ─────────────────────────────────────
            'DetalleServicio.LineaDetalle.*.Impuesto'                      => ['nullable', 'array', 'min:1', 'max:1000'],
            'DetalleServicio.LineaDetalle.*.Impuesto.*.Codigo'             => ['required', 'string', 'in:01,02,03,04,05,06,07,08,12,99'],
            'DetalleServicio.LineaDetalle.*.Impuesto.*.CodigoTarifaIVA'    => ['required_if:DetalleServicio.LineaDetalle.*.Impuesto.*.Codigo,01,07', 'nullable', 'string', 'in:01,02,03,04,05,06,07,08,09,10,11'],
            'DetalleServicio.LineaDetalle.*.Impuesto.*.CodigoImpuestoOtro' => ['required_if:DetalleServicio.LineaDetalle.*.Impuesto.*.Codigo,99', 'nullable', 'string', 'min:5', 'max:100'],
            'DetalleServicio.LineaDetalle.*.Impuesto.*.Tarifa'             => ['nullable', 'numeric', 'min:0', 'max:100'],
            'DetalleServicio.LineaDetalle.*.Impuesto.*.FactorCalculoIVA'   => ['required_if:DetalleServicio.LineaDetalle.*.Impuesto.*.Codigo,08', 'nullable', 'numeric', 'min:0', 'max:9.9999'],
            'DetalleServicio.LineaDetalle.*.Impuesto.*.Monto'              => ['required', 'numeric', $d],

            // ─── Datos impuesto específico ───────────────────────────────
            'DetalleServicio.LineaDetalle.*.Impuesto.*.DatosImpuestoEspecifico' => ['nullable', 'array'],
            'DetalleServicio.LineaDetalle.*.Impuesto.*.DatosImpuestoEspecifico.CantidadUnidadMedida' => ['required_with:DetalleServicio.LineaDetalle.*.Impuesto.*.DatosImpuestoEspecifico', 'numeric'],
            'DetalleServicio.LineaDetalle.*.Impuesto.*.DatosImpuestoEspecifico.Porcentaje'           => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'DetalleServicio.LineaDetalle.*.Impuesto.*.DatosImpuestoEspecifico.Proporcion'           => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'DetalleServicio.LineaDetalle.*.Impuesto.*.DatosImpuestoEspecifico.VolumenUnidadConsumo' => ['nullable', 'numeric', 'min:0'],
            'DetalleServicio.LineaDetalle.*.Impuesto.*.DatosImpuestoEspecifico.ImpuestoUnidad'       => ['required_with:DetalleServicio.LineaDetalle.*.Impuesto.*.DatosImpuestoEspecifico', 'numeric', $d],

            // ─── Descuentos por línea ────────────────────────────────────
            'DetalleServicio.LineaDetalle.*.Descuento'                            => ['nullable', 'array', 'max:5'],
            'DetalleServicio.LineaDetalle.*.Descuento.*.MontoDescuento'           => ['required', 'numeric', 'gte:0', $d],
            'DetalleServicio.LineaDetalle.*.Descuento.*.CodigoDescuento'          => ['required', 'string', 'in:01,02,03,04,05,06,07,08,09,99'],
            'DetalleServicio.LineaDetalle.*.Descuento.*.CodigoDescuentoOtro'      => ['required_if:DetalleServicio.LineaDetalle.*.Descuento.*.CodigoDescuento,99', 'nullable', 'string', 'min:5', 'max:100'],
            'DetalleServicio.LineaDetalle.*.Descuento.*.NaturalezaDescuento'      => ['nullable', 'string', 'min:3', 'max:80'],

            // ─── Exoneración ─────────────────────────────────────────────
            'DetalleServicio.LineaDetalle.*.Impuesto.*.Exoneracion'                    => ['nullable', 'array'],
            'DetalleServicio.LineaDetalle.*.Impuesto.*.Exoneracion.TipoDocumentoEX1'   => ['required_with:DetalleServicio.LineaDetalle.*.Impuesto.*.Exoneracion', 'string', 'in:01,02,03,04,05,06,07,08,09,10,11,99'],
            'DetalleServicio.LineaDetalle.*.Impuesto.*.Exoneracion.TipoDocumentoOTRO'  => ['required_if:DetalleServicio.LineaDetalle.*.Impuesto.*.Exoneracion.TipoDocumentoEX1,99', 'nullable', 'string', 'min:5', 'max:100'],
            'DetalleServicio.LineaDetalle.*.Impuesto.*.Exoneracion.NumeroDocumento'    => ['required_with:DetalleServicio.LineaDetalle.*.Impuesto.*.Exoneracion', 'string', 'min:3', 'max:40'],
            'DetalleServicio.LineaDetalle.*.Impuesto.*.Exoneracion.Articulo'           => ['nullable', 'integer', 'max:999999'],
            'DetalleServicio.LineaDetalle.*.Impuesto.*.Exoneracion.Inciso'             => ['nullable', 'integer', 'max:999999'],
            'DetalleServicio.LineaDetalle.*.Impuesto.*.Exoneracion.NombreInstitucion'  => ['required_with:DetalleServicio.LineaDetalle.*.Impuesto.*.Exoneracion', 'string'],
            'DetalleServicio.LineaDetalle.*.Impuesto.*.Exoneracion.FechaEmisionEX'     => ['required_with:DetalleServicio.LineaDetalle.*.Impuesto.*.Exoneracion', 'string'],
            'DetalleServicio.LineaDetalle.*.Impuesto.*.Exoneracion.TarifaExonerada'    => ['required_with:DetalleServicio.LineaDetalle.*.Impuesto.*.Exoneracion', 'numeric', 'min:0', 'max:100'],
            'DetalleServicio.LineaDetalle.*.Impuesto.*.Exoneracion.MontoExoneracion'   => ['required_with:DetalleServicio.LineaDetalle.*.Impuesto.*.Exoneracion', 'numeric', $d],

            // ─── Resumen factura ─────────────────────────────────────────
            'ResumenFactura'                                        => ['required', 'array'],
            'ResumenFactura.CodigoTipoMoneda'                       => ['required', 'array'],
            'ResumenFactura.CodigoTipoMoneda.CodigoMoneda'          => ['required', 'string', 'size:3'],
            'ResumenFactura.CodigoTipoMoneda.TipoCambio'            => ['required', 'numeric', 'gt:0', $d],
            'ResumenFactura.TotalServGravados'                      => ['nullable', 'numeric', 'min:0', $d],
            'ResumenFactura.TotalServExentos'                       => ['nullable', 'numeric', 'min:0', $d],
            'ResumenFactura.TotalServExonerado'                     => ['nullable', 'numeric', 'min:0', $d],
            'ResumenFactura.TotalServNoSujeto'                      => ['nullable', 'numeric', 'min:0', $d],
            'ResumenFactura.TotalMercanciasGravadas'                => ['nullable', 'numeric', 'min:0', $d],
            'ResumenFactura.TotalMercanciasExentas'                 => ['nullable', 'numeric', 'min:0', $d],
            'ResumenFactura.TotalMercExonerada'                     => ['nullable', 'numeric', 'min:0', $d],
            'ResumenFactura.TotalMercNoSujeta'                      => ['nullable', 'numeric', 'min:0', $d],
            'ResumenFactura.TotalGravado'                           => ['nullable', 'numeric', 'min:0', $d],
            'ResumenFactura.TotalExento'                            => ['nullable', 'numeric', 'min:0', $d],
            'ResumenFactura.TotalExonerado'                         => ['nullable', 'numeric', 'min:0', $d],
            'ResumenFactura.TotalNoSujeto'                          => ['nullable', 'numeric', 'min:0', $d],
            'ResumenFactura.TotalVenta'                             => ['required', 'numeric', 'min:0', $d],
            'ResumenFactura.TotalDescuentos'                        => ['nullable', 'numeric', 'min:0', $d],
            'ResumenFactura.TotalVentaNeta'                         => ['nullable', 'numeric', 'min:0', $d],
            'ResumenFactura.TotalImpuesto'                          => ['nullable', 'numeric', 'min:0', $d],
            'ResumenFactura.TotalImpAsumEmisorFabrica'              => ['nullable', 'numeric', 'min:0', $d],
            'ResumenFactura.TotalIVADevuelto'                       => ['nullable', 'numeric', 'min:0', $d],
            'ResumenFactura.TotalOtrosCargos'                       => ['nullable', 'numeric', 'min:0', $d],
            'ResumenFactura.TotalComprobante'                       => ['required', 'numeric', 'min:0', $d],

            // ─── Medio de pago ───────────────────────────────────────────
            'ResumenFactura.MedioPago'                  => ['nullable', 'array', 'max:4'],
            'ResumenFactura.MedioPago.*.TipoMedioPago'  => ['required', 'string', 'in:01,02,03,04,05,06,07,99'],
            'ResumenFactura.MedioPago.*.MedioPagoOtros' => ['required_if:ResumenFactura.MedioPago.*.TipoMedioPago,99', 'nullable', 'string', 'min:3', 'max:100'],
            'ResumenFactura.MedioPago.*.TotalMedioPago' => ['required', 'numeric', 'min:0', $d],

            // ─── Desglose de impuesto ────────────────────────────────────
            'ResumenFactura.TotalDesgloseImpuesto'                        => ['nullable', 'array', 'max:1000'],
            'ResumenFactura.TotalDesgloseImpuesto.*.Codigo'               => ['required', 'string', 'in:01,02,03,04,05,06,07,08,12,99'],
            'ResumenFactura.TotalDesgloseImpuesto.*.CodigoTarifaIVA'      => ['nullable', 'string', 'in:01,02,03,04,05,06,07,08,09,10,11'],
            'ResumenFactura.TotalDesgloseImpuesto.*.TotalMontoImpuesto'   => ['required', 'numeric', $d],

            // ─── Otros cargos ────────────────────────────────────────────
            'OtrosCargos'                                   => ['nullable', 'array', 'max:15'],
            'OtrosCargos.*.TipoDocumentoOC'                 => ['required', 'string', 'in:01,02,03,04,05,06,07,08,09,10,99'],
            'OtrosCargos.*.TipoDocumentoOTROS'              => ['required_if:OtrosCargos.*.TipoDocumentoOC,99', 'nullable', 'string', 'min:5', 'max:100'],
            'OtrosCargos.*.IdentificacionTercero'           => ['required_if:OtrosCargos.*.TipoDocumentoOC,04', 'nullable', 'array'],
            'OtrosCargos.*.IdentificacionTercero.Tipo'      => ['required_with:OtrosCargos.*.IdentificacionTercero', 'string', 'in:01,02,03,04,05'],
            'OtrosCargos.*.IdentificacionTercero.Numero'    => ['required_with:OtrosCargos.*.IdentificacionTercero', 'string'],
            'OtrosCargos.*.NombreTercero'                   => ['required_if:OtrosCargos.*.TipoDocumentoOC,04', 'nullable', 'string', 'min:5', 'max:100'],
            'OtrosCargos.*.Detalle'                         => ['required', 'string', 'max:160'],
            'OtrosCargos.*.PorcentajeOC'                    => ['nullable', 'numeric', $d],
            'OtrosCargos.*.MontoCargo'                      => ['required', 'numeric', 'min:0', $d],

            // ─── Información de referencia ───────────────────────────────
            'InformacionReferencia'                    => ['nullable', 'array', 'max:10'],
            'InformacionReferencia.*.TipoDocIR'        => ['required', 'string', 'in:01,02,03,04,05,06,07,08,09,10,11,12,14,15,17,18,99'],
            'InformacionReferencia.*.TipoDocRefOTRO'   => ['required_if:InformacionReferencia.*.TipoDocIR,99', 'nullable', 'string', 'min:5', 'max:100'],
            'InformacionReferencia.*.Numero'           => ['nullable', 'string', 'max:50'],
            'InformacionReferencia.*.FechaEmisionIR'   => ['required', 'string'],
            'InformacionReferencia.*.Codigo'           => ['nullable', 'string', 'in:01,02,04,05,06,07,08,09,10,11,12,99'],
            'InformacionReferencia.*.CodigoReferenciaOTRO' => ['required_if:InformacionReferencia.*.Codigo,99', 'nullable', 'string', 'min:5', 'max:100'],
            'InformacionReferencia.*.Razon'            => ['nullable', 'string', 'min:3', 'max:180'],

            // ─── Otros ───────────────────────────────────────────────────
            'Otros'                 => ['nullable', 'array'],
            'Otros.OtroTexto'       => ['nullable', 'array'],
            'Otros.OtroContenido'   => ['nullable', 'array'],

        ];
    }
}
