<?php

namespace FactuTica\FactuticaCR\Services\Validators;

use FactuTica\FactuticaCR\Enums\ReceiptType;

/**
 * Reglas de validación específicas por tipo de comprobante.
 *
 * Cada tipo define overrides sobre las reglas base del StoreReceiptRequest:
 *  - required: campos que son obligatorios para este tipo
 *  - prohibited: campos que NO deben enviarse para este tipo
 *  - extra: reglas adicionales específicas del tipo
 */
class ReceiptTypeRules
{
    /**
     * Retorna las reglas específicas para un tipo de comprobante.
     *
     * @return array{required: array, prohibited: array, extra: array}
     */
    public static function for(ReceiptType $type): array
    {
        return match ($type) {
            ReceiptType::ElectronicInvoice        => self::electronicInvoice(),
            ReceiptType::ExportInvoice            => self::exportInvoice(),
            ReceiptType::PurchaseInvoice          => self::purchaseInvoice(),
            ReceiptType::ElectronicTicket         => self::electronicTicket(),
            ReceiptType::CreditNote               => self::creditNote(),
            ReceiptType::DebitNote                => self::debitNote(),
            ReceiptType::ElectronicPaymentReceipt => self::paymentReceipt(),
        };
    }

    /**
     * Convierte las reglas específicas a reglas de Laravel validation.
     */
    public static function toValidationRules(ReceiptType $type): array
    {
        $config = self::for($type);
        $rules = [];

        foreach ($config['required'] as $field) {
            $rules[$field] = ['required'];
        }

        foreach ($config['prohibited'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        foreach ($config['extra'] as $field => $fieldRules) {
            $rules[$field] = $fieldRules;
        }

        return $rules;
    }

    /**
     * FE — Factura Electrónica (01)
     */
    private static function electronicInvoice(): array
    {
        return [
            'required' => [
                'DetalleServicio',
                'CodigoActividadEmisor',
            ],
            'prohibited' => [],
            'extra' => [
                'CodigoActividadEmisor'        => ['required', 'string', 'size:6'],
                'CodigoActividadReceptor'      => ['nullable', 'string', 'size:6'],
                'Receptor.Identificacion'      => ['required', 'array'],
                'Receptor.Identificacion.Tipo' => ['required', 'string', 'in:01,02,03,04,05'],
                'InformacionReferencia'        => ['nullable', 'array', 'max:10'],
            ],
        ];
    }

    /**
     * FEE — Factura Electrónica de Exportación (09)
     */
    private static function exportInvoice(): array
    {
        return [
            'required' => [
                'DetalleServicio',
                'CodigoActividadEmisor',
            ],
            'prohibited' => [
                'CodigoActividadReceptor',
                'Receptor.Ubicacion',
                'DetalleServicio.LineaDetalle.*.IVACobradoFabrica',
                'DetalleServicio.LineaDetalle.*.Impuesto.*.Exoneracion',
                'ResumenFactura.TotalServExonerado',
                'ResumenFactura.TotalServNoSujeto',
                'ResumenFactura.TotalMercExonerada',
                'ResumenFactura.TotalMercNoSujeta',
                'ResumenFactura.TotalExonerado',
                'ResumenFactura.TotalNoSujeto',
                'ResumenFactura.TotalIVADevuelto',
            ],
            'extra' => [
                'CodigoActividadEmisor'        => ['required', 'string', 'size:6'],
                'Receptor.Identificacion.Tipo' => ['required_with:Receptor.Identificacion', 'string', 'in:01,02,03,04,05'],
                'DetalleServicio.LineaDetalle.*.MontoExportacion' => ['nullable', 'numeric'],
                'InformacionReferencia'        => ['nullable', 'array', 'max:10'],
            ],
        ];
    }

    /**
     * FEC — Factura Electrónica de Compra (08)
     */
    private static function purchaseInvoice(): array
    {
        return [
            'required' => [
                'DetalleServicio',
                'CodigoActividadReceptor',
            ],
            'prohibited' => [
                'Receptor.OtrasSenasExtranjero',
                'DetalleServicio.LineaDetalle.*.IVACobradoFabrica',
                'ResumenFactura.TotalIVADevuelto',
            ],
            'extra' => [
                'CodigoActividadReceptor'       => ['required', 'string', 'size:6'],
                'Emisor.Identificacion.Tipo'    => ['required', 'string', 'in:01,02,03,04,05'],
                'Emisor.Ubicacion'              => ['nullable', 'array'],
                'Emisor.CorreoElectronico'      => ['nullable', 'array', 'max:4'],
                'Emisor.OtrasSenasExtranjero'   => ['required_if:Emisor.Identificacion.Tipo,05', 'nullable', 'string', 'min:5', 'max:300'],
                'InformacionReferencia.*.TipoDocIR' => ['required_with:InformacionReferencia', 'string', 'in:01,02,03,04,05,06,07,08,09,10,11,12,14,15,16,17,18,99'],
            ],
        ];
    }

    /**
     * TE — Tiquete Electrónico (04)
     */
    private static function electronicTicket(): array
    {
        return [
            'required' => [
                'DetalleServicio',
                'CodigoActividadEmisor',
            ],
            'prohibited' => [
                'CodigoActividadReceptor',
                'DetalleServicio.LineaDetalle.*.TipoTransaccion',
            ],
            'extra' => [
                'CodigoActividadEmisor'         => ['required', 'string', 'size:6'],
                'Receptor.Identificacion'       => ['nullable', 'array'],
                'Receptor.Identificacion.Tipo'  => ['required_with:Receptor.Identificacion', 'string', 'in:01,02,03,04,05'],
                'Receptor.OtrasSenasExtranjero' => ['nullable', 'string', 'max:160'],
                'InformacionReferencia'         => ['nullable', 'array', 'max:10'],
            ],
        ];
    }

    /**
     * NC — Nota de Crédito (03)
     */
    private static function creditNote(): array
    {
        return [
            'required' => [
                'InformacionReferencia',
            ],
            'prohibited' => [],
            'extra' => [
                'CodigoActividadReceptor'      => ['nullable', 'string', 'size:6'],
                'Emisor.Ubicacion'             => ['nullable', 'array'],
                'Receptor.Identificacion'      => ['nullable', 'array'],
                'Receptor.Identificacion.Tipo' => ['required_with:Receptor.Identificacion', 'string', 'in:01,02,03,04,05'],
                'DetalleServicio.LineaDetalle.*.CodigoCABYS'      => ['nullable', 'string', 'size:13'],
                'DetalleServicio.LineaDetalle.*.MontoExportacion'  => ['nullable', 'numeric'],
            ],
        ];
    }

    /**
     * ND — Nota de Débito (02)
     */
    private static function debitNote(): array
    {
        return self::creditNote();
    }

    /**
     * REP — Comprobante de Recibo Electrónico de Pago (07)
     */
    private static function paymentReceipt(): array
    {
        return [
            'required' => [
                'ResumenFactura.MedioPago',
            ],
            'prohibited' => [
                'CodigoActividadEmisor',
                'CodigoActividadReceptor',
                'Emisor.Registrofiscal8707',
                'Emisor.NombreComercial',
                'Emisor.Ubicacion',
                'Emisor.Telefono',
                'Receptor.Identificacion.Tipo',
                'Receptor.NombreComercial',
                'Receptor.Ubicacion',
                'Receptor.OtrasSenasExtranjero',
                'Receptor.Telefono',
                'CondicionVentaOtros',
                'PlazoCredito',
                'OtrosCargos',
                'Otros',
                'DetalleServicio.LineaDetalle.*.Cantidad',
                'DetalleServicio.LineaDetalle.*.UnidadMedida',
                'DetalleServicio.LineaDetalle.*.PrecioUnitario',
                'DetalleServicio.LineaDetalle.*.CodigoCABYS',
                'DetalleServicio.LineaDetalle.*.BaseImponible',
                'DetalleServicio.LineaDetalle.*.ImpuestoNeto',
                'ResumenFactura.TotalServGravados',
                'ResumenFactura.TotalServExentos',
                'ResumenFactura.TotalServExonerado',
                'ResumenFactura.TotalServNoSujeto',
                'ResumenFactura.TotalMercanciasGravadas',
                'ResumenFactura.TotalMercanciasExentas',
                'ResumenFactura.TotalMercExonerada',
                'ResumenFactura.TotalMercNoSujeta',
                'ResumenFactura.TotalGravado',
                'ResumenFactura.TotalExento',
                'ResumenFactura.TotalExonerado',
                'ResumenFactura.TotalNoSujeto',
                'ResumenFactura.TotalDescuentos',
                'ResumenFactura.TotalVentaNeta',
                'ResumenFactura.TotalOtrosCargos',
                'ResumenFactura.TotalImpAsumEmisorFabrica',
                'ResumenFactura.TotalIVADevuelto',
            ],
            'extra' => [
                'CondicionVenta' => ['required', 'string', 'in:09,11'],
            ],
        ];
    }
}
