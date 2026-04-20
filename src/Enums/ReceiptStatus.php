<?php

namespace FactuTica\FactuticaCR\Enums;

enum ReceiptStatus: string
{
    case Pending  = 'pending';
    case Sent     = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Failed   = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'Pendiente',
            self::Sent     => 'Enviado',
            self::Accepted => 'Aceptado',
            self::Rejected => 'Rechazado',
            self::Failed   => 'Fallido',
        };
    }
}