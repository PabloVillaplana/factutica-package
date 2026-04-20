<?php

namespace FactuTica\FactuticaCR\Console;

use Illuminate\Console\Command;
use FactuTica\FactuticaCR\Enums\ReceiptType;
use FactuTica\FactuticaCR\Models\ReceiptConsecutive;

/**
 * Configura el consecutivo inicial para una combinación de tipo+sucursal+terminal.
 *
 * Uso:
 *   php artisan invoicing:set-consecutive FE 100
 *   php artisan invoicing:set-consecutive FE 100 --establishment=2 --terminal=3
 *   php artisan invoicing:set-consecutive --list
 */
class SetConsecutiveCommand extends Command
{
    protected $signature = 'invoicing:set-consecutive
                            {type? : Tipo de comprobante (FE, TE, NC, ND, FEC, FEE, REP)}
                            {number? : Último número consecutivo usado}
                            {--establishment=1 : Sucursal (1-999)}
                            {--terminal=1 : Terminal/caja (1-99999)}
                            {--list : Mostrar todos los consecutivos actuales}';

    protected $description = 'Configura el consecutivo inicial para un tipo de comprobante, sucursal y terminal';

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listConsecutives();
        }

        $type = $this->argument('type');
        $number = $this->argument('number');

        if (! $type || $number === null) {
            $this->error('Uso: php artisan invoicing:set-consecutive {tipo} {numero} [--establishment=1] [--terminal=1]');
            $this->line('');
            $this->line('Tipos válidos: '.implode(', ', array_column(ReceiptType::cases(), 'value')));
            $this->line('');
            $this->line('Ejemplo: php artisan invoicing:set-consecutive FE 100');
            $this->line('         php artisan invoicing:set-consecutive FE 50 --establishment=2 --terminal=3');
            $this->line('         php artisan invoicing:set-consecutive --list');

            return self::FAILURE;
        }

        $receiptType = ReceiptType::tryFrom($type);

        if (! $receiptType) {
            $this->error("Tipo de comprobante no válido: [{$type}]");
            $this->line('Tipos válidos: '.implode(', ', array_column(ReceiptType::cases(), 'value')));

            return self::FAILURE;
        }

        $establishment = (int) $this->option('establishment');
        $terminal = (int) $this->option('terminal');
        $number = (int) $number;

        if ($number < 0) {
            $this->error('El número debe ser mayor o igual a 0.');

            return self::FAILURE;
        }

        $record = ReceiptConsecutive::updateOrCreate(
            [
                'receipt_type' => $receiptType,
                'establishment' => $establishment,
                'terminal' => $terminal,
            ],
            ['last_number' => $number]
        );

        $this->info("Consecutivo actualizado:");
        $this->table(
            ['Tipo', 'Sucursal', 'Terminal', 'Último número', 'Siguiente'],
            [[$receiptType->value, $establishment, $terminal, $number, $number + 1]]
        );

        return self::SUCCESS;
    }

    private function listConsecutives(): int
    {
        $records = ReceiptConsecutive::orderBy('receipt_type')
            ->orderBy('establishment')
            ->orderBy('terminal')
            ->get();

        if ($records->isEmpty()) {
            $this->info('No hay consecutivos registrados.');

            return self::SUCCESS;
        }

        $this->table(
            ['Tipo', 'Sucursal', 'Terminal', 'Último número', 'Siguiente'],
            $records->map(fn ($r) => [
                $r->receipt_type->value,
                $r->establishment,
                $r->terminal,
                $r->last_number,
                $r->last_number + 1,
            ])
        );

        return self::SUCCESS;
    }
}
