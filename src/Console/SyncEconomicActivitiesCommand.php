<?php

namespace FactuTica\FactuticaCR\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use FactuTica\FactuticaCR\Models\EconomicActivity;

/**
 * Importa el catálogo de actividades económicas CIIU4 TRIBU-CR.
 *
 * Uso:
 *   php artisan invoicing:sync-activities
 *   php artisan invoicing:sync-activities --fresh
 */
class SyncEconomicActivitiesCommand extends Command
{
    protected $signature = 'invoicing:sync-activities
                            {--fresh : Eliminar todos los registros antes de importar}';

    protected $description = 'Importa el catálogo de actividades económicas desde el JSON incluido en el paquete';

    public function handle(): int
    {
        $path = dirname(__DIR__, 2).'/data/actividades.json';

        if (! file_exists($path)) {
            $this->error("Archivo no encontrado: {$path}");

            return self::FAILURE;
        }

        $json = file_get_contents($path);

        if ($json === false) {
            $this->error("No se pudo leer el archivo: {$path}");

            return self::FAILURE;
        }

        $items = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Error al parsear JSON: '.json_last_error_msg());

            return self::FAILURE;
        }

        if (! is_array($items) || empty($items)) {
            $this->error('El archivo JSON está vacío o no contiene un array.');

            return self::FAILURE;
        }

        $this->info('Importando actividades económicas...');

        $chunks = array_chunk($items, 500);
        $bar = $this->output->createProgressBar(count($items));
        $bar->start();

        if ($this->option('fresh')) {
            EconomicActivity::truncate();
        }

        DB::transaction(function () use ($chunks, $bar) {
            foreach ($chunks as $chunk) {
                $rows = array_map(fn ($item) => [
                    'codigo'      => $item['codigo'],
                    'descripcion' => $item['descripcion'],
                ], $chunk);

                DB::table('invoicing_cr_economic_activities')->upsert($rows, ['codigo'], ['descripcion']);
                $bar->advance(count($chunk));
            }
        });

        $bar->finish();
        $this->newLine();

        $total = EconomicActivity::count();
        $this->info("Actividades económicas sincronizadas: {$total} códigos.");

        return self::SUCCESS;
    }
}