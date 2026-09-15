<?php

namespace App\Console\Commands;

use App\Product;
use App\Scraping\Parsers\PackagePresentationParser;
use App\UnitMeasure;
use Illuminate\Console\Command;

/**
 * Productos historicos (creados antes de que el scraping empezara a poblar
 * net_quantity/package_unit_id via PackagePresentationParser) pueden tener
 * esos campos en null, lo que deja a StockEntrySuggestionService sin senal
 * de presentacion para sugerir cantidad al escanear. Este comando reutiliza
 * el mismo parser para completar unicamente los casos inequivocos (nombre
 * con cantidad+unidad al final, ej. "... 520 g") y una UnitMeasure activa
 * real para esa unidad. No inventa nada: si el nombre no es inequivoco o no
 * hay UnitMeasure correspondiente, el producto se deja intacto para revision
 * manual (admin/candidate).
 */
class BackfillProductPackagePresentation extends Command
{
    protected $signature = 'products:backfill-package-presentation {--apply}';

    protected $description = 'Completa net_quantity/package_unit_id de productos con presentacion inequivoca en el nombre. Sin --apply solo reporta (dry-run), no persiste nada.';

    public function handle(PackagePresentationParser $parser): int
    {
        $apply = (bool) $this->option('apply');

        $products = Product::where('status', 'active')
            ->whereNull('net_quantity')
            ->whereNull('package_unit_id')
            ->get(['id', 'name', 'default_unit_id']);

        $matched = [];
        $skippedNoUnit = [];

        foreach ($products as $product) {
            $result = $parser->parse((string) $product->name);
            if (! $result) {
                continue;
            }

            $unit = $this->findUnit((string) $result['package_unit_code']);
            if (! $unit) {
                $skippedNoUnit[] = ['id' => $product->id, 'name' => $product->name, 'unit_code' => $result['package_unit_code']];
                continue;
            }

            $matched[] = [
                'id' => $product->id,
                'name' => $product->name,
                'net_quantity' => $result['net_quantity'],
                'unit_id' => $unit->id,
                'unit_symbol' => $unit->symbol ?: $unit->code,
                'default_unit_id' => $product->default_unit_id,
            ];
        }

        $this->info(
            ($apply ? 'APLICANDO' : 'DRY-RUN') . ': ' . count($matched) . ' producto(s) con presentacion inequivoca'
            . ' de ' . $products->count() . ' activos sin net_quantity/package_unit_id.'
        );
        foreach ($matched as $row) {
            $this->line('#' . $row['id'] . ' ' . $row['name'] . ' -> ' . $row['net_quantity'] . ' ' . $row['unit_symbol']);
        }

        if ($skippedNoUnit) {
            $this->warn(count($skippedNoUnit) . ' con presentacion detectada pero sin UnitMeasure activa correspondiente (no se tocan):');
            foreach ($skippedNoUnit as $row) {
                $this->line('#' . $row['id'] . ' ' . $row['name'] . ' (' . $row['unit_code'] . ')');
            }
        }

        if (! $apply) {
            $this->comment('Dry-run: no se modifico ningun producto. Ejecutar con --apply para persistir.');
            return 0;
        }

        foreach ($matched as $row) {
            Product::where('id', $row['id'])
                ->whereNull('net_quantity')
                ->whereNull('package_unit_id')
                ->update([
                    'net_quantity' => $row['net_quantity'],
                    'package_unit_id' => $row['unit_id'],
                    'default_unit_id' => $row['default_unit_id'] ?: $row['unit_id'],
                ]);
        }
        $this->info('Aplicado: ' . count($matched) . ' producto(s) actualizado(s).');

        return 0;
    }

    private function findUnit(string $code): ?UnitMeasure
    {
        $aliases = [
            'g' => ['g', 'gr'],
            'kg' => ['kg', 'kilo'],
            'ml' => ['ml'],
            'l' => ['l', 'lt'],
        ];
        $values = $aliases[$code] ?? [$code];

        return UnitMeasure::where('status', 'active')
            ->where(function ($query) use ($values) {
                foreach ($values as $value) {
                    $query->orWhereRaw('lower(code) = ?', [$value])
                        ->orWhereRaw('lower(symbol) = ?', [$value]);
                }
            })
            ->first();
    }
}
