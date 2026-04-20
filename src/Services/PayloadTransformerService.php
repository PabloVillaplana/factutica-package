<?php

namespace FactuTica\FactuticaCR\Services;

/**
 * Transforms request payload keys from snake_case (external API) to PascalCase (internal/XSD).
 *
 * The external API uses snake_case for REST convention. Internally, the package
 * uses PascalCase to match the Hacienda XSD field names 1:1.
 *
 * Usage: called in FormRequest::prepareForValidation() before validation runs.
 */
class PayloadTransformerService
{
    /**
     * Explicit mapping of snake_case keys to their PascalCase XSD equivalents.
     *
     * Only keys that differ from a simple Str::studly() conversion are listed.
     * Keys not in this map are converted automatically via Str::studly().
     */
    private const KEY_MAP = [
        // Root (non-XSD fields stay as-is)
        'receipt_type' => 'receipt_type',
        'external_reference' => 'external_reference',
        'establishment' => 'establishment',
        'terminal' => 'terminal',
        'condicion_venta' => 'CondicionVenta',
        'condicion_venta_otros' => 'CondicionVentaOtros',
        'plazo_credito' => 'PlazoCredito',
        'codigo_actividad_emisor' => 'CodigoActividadEmisor',
        'codigo_actividad_receptor' => 'CodigoActividadReceptor',
        'clave' => 'Clave',
        'proveedor_sistema' => 'ProveedorSistema',
        'numero_consecutivo' => 'NumeroConsecutivo',
        'fecha_emision' => 'FechaEmision',

        // Emisor / Receptor (top-level)
        'emisor' => 'Emisor',
        'receptor' => 'Receptor',
        'nombre' => 'Nombre',
        'identificacion' => 'Identificacion',
        'tipo' => 'Tipo',
        'numero' => 'Numero',
        'registro_fiscal_8707' => 'Registrofiscal8707',
        'nombre_comercial' => 'NombreComercial',
        'ubicacion' => 'Ubicacion',
        'provincia' => 'Provincia',
        'canton' => 'Canton',
        'distrito' => 'Distrito',
        'barrio' => 'Barrio',
        'otras_senas' => 'OtrasSenas',
        'otras_senas_extranjero' => 'OtrasSenasExtranjero',
        'telefono' => 'Telefono',
        'codigo_pais' => 'CodigoPais',
        'num_telefono' => 'NumTelefono',
        'correo_electronico' => 'CorreoElectronico',

        // DetalleServicio
        'detalle_servicio' => 'DetalleServicio',
        'linea_detalle' => 'LineaDetalle',
        'numero_linea' => 'NumeroLinea',
        'codigo_cabys' => 'CodigoCABYS',
        'codigo_comercial' => 'CodigoComercial',
        'codigo' => 'Codigo',
        'cantidad' => 'Cantidad',
        'unidad_medida' => 'UnidadMedida',
        'tipo_transaccion' => 'TipoTransaccion',
        'unidad_medida_comercial' => 'UnidadMedidaComercial',
        'detalle' => 'Detalle',
        'numero_vin_serie' => 'NumeroVINoSerie',
        'registro_medicamento' => 'RegistroMedicamento',
        'forma_farmaceutica' => 'FormaFarmaceutica',
        'precio_unitario' => 'PrecioUnitario',
        'monto_total' => 'MontoTotal',
        'sub_total' => 'SubTotal',
        'iva_cobrado_fabrica' => 'IVACobradoFabrica',
        'base_imponible' => 'BaseImponible',
        'monto_exportacion' => 'MontoExportacion',
        'impuesto_neto' => 'ImpuestoNeto',
        'monto_total_linea' => 'MontoTotalLinea',
        'impuesto_asumido_emisor_fabrica' => 'ImpuestoAsumidoEmisorFabrica',

        // Impuesto
        'impuesto' => 'Impuesto',
        'codigo_tarifa_iva' => 'CodigoTarifaIVA',
        'codigo_impuesto_otro' => 'CodigoImpuestoOtro',
        'tarifa' => 'Tarifa',
        'factor_calculo_iva' => 'FactorCalculoIVA',
        'monto' => 'Monto',

        // DatosImpuestoEspecifico
        'datos_impuesto_especifico' => 'DatosImpuestoEspecifico',
        'cantidad_unidad_medida' => 'CantidadUnidadMedida',
        'porcentaje' => 'Porcentaje',
        'proporcion' => 'Proporcion',
        'volumen_unidad_consumo' => 'VolumenUnidadConsumo',
        'impuesto_unidad' => 'ImpuestoUnidad',

        // Descuento
        'descuento' => 'Descuento',
        'monto_descuento' => 'MontoDescuento',
        'codigo_descuento' => 'CodigoDescuento',
        'codigo_descuento_otro' => 'CodigoDescuentoOtro',
        'naturaleza_descuento' => 'NaturalezaDescuento',

        // Exoneracion
        'exoneracion' => 'Exoneracion',
        'tipo_documento_ex1' => 'TipoDocumentoEX1',
        'tipo_documento_otro' => 'TipoDocumentoOTRO',
        'numero_documento' => 'NumeroDocumento',
        'articulo' => 'Articulo',
        'inciso' => 'Inciso',
        'nombre_institucion' => 'NombreInstitucion',
        'fecha_emision_ex' => 'FechaEmisionEX',
        'tarifa_exonerada' => 'TarifaExonerada',
        'monto_exoneracion' => 'MontoExoneracion',

        // ResumenFactura
        'resumen_factura' => 'ResumenFactura',
        'codigo_tipo_moneda' => 'CodigoTipoMoneda',
        'codigo_moneda' => 'CodigoMoneda',
        'tipo_cambio' => 'TipoCambio',
        'total_serv_gravados' => 'TotalServGravados',
        'total_serv_exentos' => 'TotalServExentos',
        'total_serv_exonerado' => 'TotalServExonerado',
        'total_serv_no_sujeto' => 'TotalServNoSujeto',
        'total_mercancias_gravadas' => 'TotalMercanciasGravadas',
        'total_mercancias_exentas' => 'TotalMercanciasExentas',
        'total_merc_exonerada' => 'TotalMercExonerada',
        'total_merc_no_sujeta' => 'TotalMercNoSujeta',
        'total_gravado' => 'TotalGravado',
        'total_exento' => 'TotalExento',
        'total_exonerado' => 'TotalExonerado',
        'total_no_sujeto' => 'TotalNoSujeto',
        'total_venta' => 'TotalVenta',
        'total_descuentos' => 'TotalDescuentos',
        'total_venta_neta' => 'TotalVentaNeta',
        'total_impuesto' => 'TotalImpuesto',
        'total_imp_asum_emisor_fabrica' => 'TotalImpAsumEmisorFabrica',
        'total_iva_devuelto' => 'TotalIVADevuelto',
        'total_otros_cargos' => 'TotalOtrosCargos',
        'total_comprobante' => 'TotalComprobante',

        // MedioPago
        'medio_pago' => 'MedioPago',
        'tipo_medio_pago' => 'TipoMedioPago',
        'medio_pago_otros' => 'MedioPagoOtros',
        'total_medio_pago' => 'TotalMedioPago',

        // TotalDesgloseImpuesto
        'total_desglose_impuesto' => 'TotalDesgloseImpuesto',
        'total_monto_impuesto' => 'TotalMontoImpuesto',

        // OtrosCargos
        'otros_cargos' => 'OtrosCargos',
        'tipo_documento_oc' => 'TipoDocumentoOC',
        'tipo_documento_otros' => 'TipoDocumentoOTROS',
        'identificacion_tercero' => 'IdentificacionTercero',
        'nombre_tercero' => 'NombreTercero',
        'porcentaje_oc' => 'PorcentajeOC',
        'monto_cargo' => 'MontoCargo',

        // InformacionReferencia
        'informacion_referencia' => 'InformacionReferencia',
        'tipo_doc_ir' => 'TipoDocIR',
        'tipo_doc_ref_otro' => 'TipoDocRefOTRO',
        'fecha_emision_ir' => 'FechaEmisionIR',
        'codigo_referencia_otro' => 'CodigoReferenciaOTRO',
        'razon' => 'Razon',

        // Otros
        'otros' => 'Otros',
        'otro_texto' => 'OtroTexto',
        'otro_contenido' => 'OtroContenido',
    ];

    /**
     * Transform a snake_case payload to PascalCase (XSD format).
     * Also accepts PascalCase keys as-is for backwards compatibility.
     */
    public function toPascalCase(array $data): array
    {
        return $this->transformKeys($data);
    }

    private function transformKeys(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $newKey = $this->mapKey($key);

            if (is_array($value) && ! $this->isSequentialArray($value)) {
                $value = $this->transformKeys($value);
            } elseif (is_array($value) && $this->isSequentialArray($value)) {
                $value = array_map(function ($item) {
                    return is_array($item) ? $this->transformKeys($item) : $item;
                }, $value);
            }

            $result[$newKey] = $value;
        }

        return $result;
    }

    private function mapKey(string $key): string
    {
        // If key is already PascalCase (starts with uppercase or is in PascalCase values), keep it
        if (in_array($key, self::KEY_MAP, true)) {
            return $key;
        }

        // Look up in the map
        if (isset(self::KEY_MAP[$key])) {
            return self::KEY_MAP[$key];
        }

        // If key starts with uppercase, assume it's already PascalCase
        if (! empty($key) && ctype_upper($key[0])) {
            return $key;
        }

        // Fallback: keep as-is (unknown keys pass through)
        return $key;
    }

    private function isSequentialArray(array $array): bool
    {
        return array_is_list($array);
    }
}