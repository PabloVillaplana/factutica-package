<?php

namespace FactuTica\FactuticaCR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use FactuTica\FactuticaCR\Enums\HaciendaStatus;
use FactuTica\FactuticaCR\Enums\ReceiptStatus;
use FactuTica\FactuticaCR\Enums\ReceiptType;
use FactuTica\FactuticaCR\Models\Receipt;

class ReceiptFactory extends Factory
{
    protected $model = Receipt::class;

    public function definition(): array
    {
        return [
            'receipt_type'               => ReceiptType::ElectronicInvoice,
            'ui_key'                     => $this->faker->unique()->numerify(str_repeat('#', 50)),
            'consecutive_number'         => $this->faker->numerify(str_repeat('#', 20)),
            'emission_date'              => now(),
            'receipt_status'             => ReceiptStatus::Pending,
            'hacienda_status'            => HaciendaStatus::Pending,
            'sell_condition'             => '01',
            'total_amount'               => $this->faker->randomFloat(3, 100, 100000),
            'tax_amount'                 => $this->faker->randomFloat(3, 10, 13000),
            'total_discount'             => 0,
            'total_voucher'              => $this->faker->randomFloat(3, 100, 113000),
            'currency'                   => 'CRC',
            'issuer_name'                => $this->faker->company(),
            'issuer_number'              => $this->faker->numerify('#########'),
            'issuer_identification_type' => '01',
        ];
    }
}
