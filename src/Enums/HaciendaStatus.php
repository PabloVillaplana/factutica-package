<?php

namespace FactuTica\FactuticaCR\Enums;

enum HaciendaStatus: string
{
    case Pending  = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::Pending  => 'Pendiente',
            self::Accepted => 'Aceptado',
            self::Rejected => 'Rechazado',
        };
    }
}
