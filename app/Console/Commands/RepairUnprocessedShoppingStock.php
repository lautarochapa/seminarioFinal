<?php

namespace App\Console\Commands;

use App\Services\ShoppingListCompletion\ShoppingListCompletionService;
use App\ShoppingList;
use App\ShoppingListItem;
use App\User;
use Illuminate\Console\Command;

class RepairUnprocessedShoppingStock extends Command
{
    protected $signature = 'shopping-lists:repair-unprocessed-stock {--dry-run} {--list-id=} {--search=}';
    protected $description = 'Detecta y repara items comprados sin procesamiento de stock';

    public function handle(ShoppingListCompletionService $service)
    {
        if ($this->option('search')) {
            $term = (string) $this->option('search');
            $found = ShoppingListItem::with(['shoppingList', 'ingredient', 'product'])
                ->where(function ($q) use ($term) {
                    $q->where('free_text_name', 'ILIKE', '%'.$term.'%')->orWhereHas('ingredient', function ($ingredient) use ($term) { $ingredient->where('name', 'ILIKE', '%'.$term.'%'); });
                })->get();
            foreach ($found as $item) {
                $state = $item->purchase_item_id ? 'procesado' : ($item->stock_processed_at ? 'omitido' : 'pendiente');
                $this->line(json_encode(['item_id' => $item->id, 'shopping_list_id' => $item->shopping_list_id, 'list_status' => optional($item->shoppingList)->status, 'status' => $item->status, 'ingredient_id' => $item->ingredient_id, 'ingredient' => optional($item->ingredient)->name, 'product_id' => $item->product_id, 'quantity' => $item->quantity, 'unit_id' => $item->unit_id, 'stock_processed_at' => $item->stock_processed_at, 'purchase_item_id' => $item->purchase_item_id, 'processing_state' => $state], JSON_UNESCAPED_UNICODE));
            }
            if ($found->isEmpty()) { $this->warn('No se encontraron items para la búsqueda.'); }
        }
        $includeLegacy = (bool) $this->option('list-id');
        $pendingScope = function ($q) use ($includeLegacy) {
            $q->where('status', 'purchased')->whereNull('purchase_item_id')->where(function ($state) use ($includeLegacy) {
                $state->whereNull('stock_processed_at');
                if ($includeLegacy) { $state->orWhere(function ($legacy) { $legacy->whereNotNull('ingredient_id')->whereNull('product_id'); }); }
            });
        };
        $query = ShoppingList::where('status', ShoppingList::STATUS_COMPLETED)->whereHas('items', $pendingScope);
        if ($this->option('list-id')) { $query->where('id', (int) $this->option('list-id')); }
        $lists = $query->with(['items' => function ($q) use ($pendingScope) { $pendingScope($q); $q->with(['ingredient', 'product', 'unit']); }])->get();
        if ($lists->isEmpty()) { $this->info('No hay items pendientes de procesamiento.'); return 0; }
        foreach ($lists as $list) {
            $this->line('Lista '.$list->id.' · grupo '.$list->family_group_id.' · '.$list->items->count().' pendiente(s)');
            foreach ($list->items as $item) { $this->line('  #'.$item->id.' '.($item->ingredient ? $item->ingredient->name : ($item->free_text_name ?: 'Sin nombre')).' · '.$item->quantity.' '.optional($item->unit)->symbol); }
        }
        if ($this->option('dry-run')) { $this->info('Dry-run: no se modificaron datos.'); return 0; }
        if (!$this->option('list-id') && !$this->confirm('¿Procesar todas las listas informadas?')) { $this->warn('Cancelado.'); return 1; }
        foreach ($lists as $list) {
            $user = User::find($list->created_by ?: $list->owner_user_id);
            if (!$user) { $this->error('Lista '.$list->id.': no se encontró un usuario actor.'); continue; }
            try {
                $items = $list->items->map(function ($item) { return ['shopping_list_item_id' => $item->id, 'add_to_stock' => true]; })->all();
                $result = $service->repairPendingStock($user, (int) $list->family_group_id, (int) $list->id, ['items' => $items], '127.0.0.1', 'artisan repair-unprocessed-stock');
                $this->info('Lista '.$list->id.': '.$result['summary']['items_added_to_stock_count'].' procesado(s), '.$result['summary']['items_omitted_count'].' omitido(s).');
            } catch (\Throwable $e) { $this->error('Lista '.$list->id.': '.$e->getMessage()); }
        }
        return 0;
    }
}
