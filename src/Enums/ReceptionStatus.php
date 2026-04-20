<?php

namespace FactuTica\FactuticaCR\Enums;

enum ReceptionStatus: string
{
    case Pending           = 'pending';
    case Accepted          = 'accepted';
    case PartiallyAccepted = 'partially_accepted';
    case Rejected          = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::Pending           => 'Pendiente',
            self::Accepted          => 'Aceptado',
            self::PartiallyAccepted => 'Aceptado Parcialmente',
            self::Rejected          => 'Rechazado',
        };
    }
}
