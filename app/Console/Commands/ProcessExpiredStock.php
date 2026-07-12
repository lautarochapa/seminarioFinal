<?php

namespace App\Console\Commands;

use App\Services\StockExpiration\ExpiredStockProcessor;
use Illuminate\Console\Command;

class ProcessExpiredStock extends Command
{
    protected $signature = 'stock:process-expired {--limit=200 : Cantidad maxima de items a procesar}';

    protected $description = 'Procesa stock vencido pendiente y registra desperdicio automatico.';

    private $processor;

    public function __construct(ExpiredStockProcessor $processor)
    {
        parent::__construct();
        $this->processor = $processor;
    }

    public function handle()
    {
        $limit = max(1, min((int) $this->option('limit'), 1000));
        $summary = $this->processor->process($limit);

        $this->info('Stock vencido procesado: '.$summary['processed']);
        $this->line('Omitidos: '.$summary['skipped']);
        $this->line('Cantidad descartada: '.$summary['discarded_quantity']);
        $this->line('Perdida estimada: '.$summary['estimated_loss']);

        return 0;
    }
}
