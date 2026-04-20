<?php

namespace FactuTica\FactuticaCR\Enums;

enum ReceiptType: string
{
    case ElectronicInvoice         = 'FE';
    case ExportInvoice             = 'FEE';
    case PurchaseInvoice           = 'FEC';
    case ElectronicTicket          = 'TE';
    case DebitNote                 = 'ND';
    case CreditNote                = 'NC';
    case ElectronicPaymentReceipt  = 'REP';

    public function label(): string
    {
        return match($this) {
            self::ElectronicInvoice        => 'Factura Electrónica',
            self::ExportInvoice            => 'Factura de Exportación',
            self::PurchaseInvoice          => 'Factura Electrónica de Compra',
            self::ElectronicTicket         => 'Tiquete Electrónico',
            self::DebitNote                => 'Nota de Débito',
            self::CreditNote               => 'Nota de Crédito',
            self::ElectronicPaymentReceipt => 'Comprobante de Recibo Electrónico',
        };
    }
}
