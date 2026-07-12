<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SeedDemoE2ERecipeStockBarcode extends Command
{
    protected $signature = 'demo:seed-e2e-recipe-stock-barcode';

    protected $description = 'Seed the E2E demo scenario for recipes, stock and barcode flows.';

    public function handle()
    {
        require_once database_path('seeds/DemoE2ERecipeStockBarcodeSeeder.php');

        $this->call('db:seed', [
            '--class' => 'DemoE2ERecipeStockBarcodeSeeder',
        ]);

        return 0;
    }
}
