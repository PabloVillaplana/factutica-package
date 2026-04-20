<?php

namespace FactuTica\FactuticaCR\Enums;

enum IdentificationType: string
{
    case Physical     = '01';
    case Legal        = '02';
    case Dimex        = '03';
    case Nite         = '04';
    case Foreign      = '05';
    case NotTaxpayer  = '06';

    public function label(): string
    {
        return match($this) {
            self::Physical    => 'Cédula Física',
            self::Legal       => 'Cédula Jurídica',
            self::Dimex       => 'DIMEX',
            self::Nite        => 'NITE',
            self::Foreign     => 'Extranjero',
            self::NotTaxpayer => 'No Contribuyente',
        };
    }
}
