<?php

namespace FactuTica\FactuticaCR\Enums;

enum CurrencyType: string
{
    case Colones = 'CRC';
    case Dollars = 'USD';
    case Euros   = 'EUR';

    public function label(): string
    {
        return match($this) {
            self::Colones => 'Colones Costarricenses',
            self::Dollars => 'Dólares Americanos',
            self::Euros   => 'Euros',
        };
    }
}
