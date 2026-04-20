<?php

namespace FactuTica\FactuticaCR\Services\Validators;

use FactuTica\FactuticaCR\Exceptions\InvalidReceiptException;

/**
 * Orquestador de validaciones matemáticas.
 *
 * Ejecuta en orden:
 *   1. DetailLineValidator    → cálculos por línea (MontoTotal, SubTotal, BaseImponible, ImpuestoNeto)
 *   2. TaxCalculationValidator → cálculos de impuestos por código (IVA, selectivo, combustible, etc.)
 *   3. InvoiceSummaryValidator → cálculos del resumen (TotalVenta, TotalComprobante, etc.)
 *
 * Se ejecuta ANTES de consumir consecutivo o persistir en DB.
 *
 * @throws InvalidReceiptException Si algún cálculo no cuadra.
 */
class CalculationValidatorService
{
    public function __construct(
        private readonly DetailLineValidator $detailLineValidator,
        private readonly TaxCalculationValidator $taxCalculationValidator,
        private readonly InvoiceSummaryValidator $invoiceSummaryValidator,
        private readonly TaxBreakdownValidator $taxBreakdownValidator,
        private readonly AssortmentValidator $assortmentValidator,
    ) {}

    public function validate(array $data): void
    {
        if (! empty($data['DetalleServicio']['LineaDetalle'])) {
            $this->detailLineValidator->validate($data);
            $this->taxCalculationValidator->validate($data);
            $this->taxBreakdownValidator->validate($data);
            $this->assortmentValidator->validate($data);
        }

        if (! empty($data['ResumenFactura'])) {
            $this->invoiceSummaryValidator->validate($data);
        }
    }
}
