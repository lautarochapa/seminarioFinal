<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Mecanismo unico y reproducible para preparar el escenario de demostracion
 * del flujo principal de usuario.
 *
 * Reemplaza, para la demo, a:
 *   - db:seed --class=DemoDataSeeder
 *   - demo:seed-e2e-recipe-stock-barcode
 *
 * Es idempotente: reejecutarlo restaura el estado inicial del escenario.
 */
class PrepareDemoData extends Command
{
    protected $signature = 'demo:prepare';

    protected $description = 'Prepara el dataset unico y deterministico del escenario de demostracion (idempotente).';

    public function handle()
    {
        require_once database_path('seeds/DemoScenarioSeeder.php');

        $this->call('db:seed', [
            '--class' => 'DemoScenarioSeeder',
            '--force' => true,
        ]);

        return 0;
    }
}
