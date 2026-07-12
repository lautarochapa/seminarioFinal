<?php

namespace App\Jobs;

use App\ReportExport;
use App\Services\GroupReports\GroupReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;

    private $exportId;
    private $groupId;
    private $userId;
    private $filters;

    public function __construct(int $exportId, int $groupId, int $userId, array $filters = [])
    {
        $this->exportId = $exportId;
        $this->groupId  = $groupId;
        $this->userId   = $userId;
        $this->filters  = $filters;
    }

    public function handle(GroupReportService $service): void
    {
        $export = ReportExport::find($this->exportId);
        if (!$export || in_array($export->status, ['completed', 'failed'])) {
            return;
        }

        $export->status = 'processing';
        $export->save();

        try {
            $data     = $this->fetchReportData($service, $export->report_type);
            $content  = $export->format === 'csv' ? $this->toCsv($data) : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $filename = 'exports/' . $export->id . '_' . $export->report_type . '.' . $export->format;

            Storage::disk('local')->put($filename, $content);

            $export->status      = 'completed';
            $export->file_url    = $filename;
            $export->finished_at = now();
            $export->save();
        } catch (\Exception $e) {
            $export->status      = 'failed';
            $export->finished_at = now();
            $export->save();
        }
    }

    private function fetchReportData(GroupReportService $service, string $reportType): array
    {
        $map = [
            'stock'              => 'stock',
            'stock-value'        => 'stockValue',
            'expiring-products'  => 'expiringProducts',
            'waste'              => 'waste',
            'purchases'          => 'purchases',
            'budget'             => 'budget',
            'budget-vs-actual'   => 'budgetVsActual',
            'recipes-cooked'     => 'recipesCooked',
            'nutrition-estimate' => 'nutritionEstimate',
        ];

        $method = $map[$reportType] ?? null;
        if (!$method) {
            throw new \RuntimeException('Tipo de reporte no soportado: ' . $reportType);
        }

        return $service->{$method}($this->groupId, $this->userId, $this->filters);
    }

    private function toCsv(array $data): string
    {
        $handle = fopen('php://temp', 'r+');

        $flat = $this->flattenForCsv($data);

        if (!empty($flat)) {
            fputcsv($handle, array_keys($flat[0]));
            foreach ($flat as $row) {
                fputcsv($handle, array_values($row));
            }
        } else {
            fputcsv($handle, ['no_data']);
            fputcsv($handle, ['(empty)']);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    private function flattenForCsv(array $data): array
    {
        foreach ($data as $value) {
            if (is_array($value) && !empty($value) && is_array(reset($value))) {
                return array_map(function ($row) {
                    return array_map(function ($v) {
                        return is_array($v) ? json_encode($v) : $v;
                    }, (array) $row);
                }, $value);
            }
        }

        return [array_map(function ($v) {
            return is_array($v) ? json_encode($v) : $v;
        }, $data)];
    }
}
