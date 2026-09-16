<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InitializeDatabase extends Command
{
    protected $signature = 'app:initialize-database
        {--plan : Lista los pasos sin conectarse a la base}
        {--seed-catalogs : Carga los catalogos iniciales sin datos personales}
        {--force : Confirma la ejecucion en QA o produccion}';

    protected $description = 'Inicializa o actualiza el esquema PostgreSQL actual sin borrar datos ni ejecutar tablas legacy incompatibles.';

    public function handle()
    {
        // Only these legacy foundations are shared with the current schema.
        $paths = [
            'database/migrations/2014_10_12_000000_create_users_table.php',
            'database/migrations/2014_10_12_100000_create_password_resets_table.php',
            'database/migrations/2019_08_19_000000_create_failed_jobs_table.php',
            'database/migrations/2020_07_02_230832_create_profiles_table.php',
        ];

        $current = glob(database_path('migrations/2026_*.php'));
        sort($current, SORT_STRING);
        foreach ($current as $path) {
            $paths[] = 'database/migrations/'.basename($path);
        }

        $seeders = [
            'IngredientCatalogSeeder',
            'IngredientCategoryTaxonomySeeder',
            'IngredientSupportingTaxonomiesSeeder',
            'MealTypeSeeder',
            'SupplementTypeSeeder',
            'SupermarketCatalogSeeder',
            'SupportingSystemSeeder',
            'ScrapingSourceSeeder',
            'UserProfileCatalogSeeder',
        ];

        if ($this->option('plan')) {
            foreach ($paths as $path) {
                $this->line($path);
            }
            if ($this->option('seed-catalogs')) {
                foreach ($seeders as $seeder) {
                    $this->line('Seed: '.$seeder);
                }
            }
            return 0;
        }

        if (config('database.default') !== 'pgsql') {
            $this->error('Este inicializador requiere DB_CONNECTION=pgsql.');
            return 1;
        }

        if (! app()->environment(['local', 'testing']) && ! $this->option('force')) {
            $this->error('Revisa el destino y usa --force para inicializar QA o produccion.');
            return 1;
        }

        $this->info('Aplicando solamente migraciones pendientes del esquema actual.');
        $status = $this->call('migrate', ['--path' => $paths, '--force' => true]);
        if ($status !== 0) {
            return $status;
        }

        if ($this->option('seed-catalogs')) {
            foreach ($seeders as $seeder) {
                $this->info('Cargando catalogo: '.$seeder);
                require_once database_path('seeds/'.$seeder.'.php');
                $status = $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
                if ($status !== 0) {
                    return $status;
                }
            }
        }

        $this->info('Base lista. En QA/produccion no se crean cuentas demo con claves publicas.');
        return 0;
    }
}
