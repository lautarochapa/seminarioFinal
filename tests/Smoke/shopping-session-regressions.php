<?php

// Included only by initialize-cloud.php, inside its disposable local database.
function checkShoppingSessionRegressions($user, $group, $product, $unitId, $budget)
{
    $service = app(App\Services\ShoppingSessions\ShoppingSessionService::class);
    $items = app(App\Services\ShoppingListItems\ShoppingListItemService::class);
    $beforeSpent = app(App\Services\Budgets\BudgetSummaryService::class)->summary($group->id, $budget->id, $user->id)['spent_amount'];
    $make = function () use ($user, $group, $product, $unitId, $service) {
        $list = App\ShoppingList::create(['family_group_id' => $group->id, 'created_by' => $user->id, 'source_type' => 'manual', 'status' => 'active']);
        $item = App\ShoppingListItem::create(['shopping_list_id' => $list->id, 'product_id' => $product->id, 'unit_id' => $unitId, 'quantity' => 0.5, 'estimated_price' => 900, 'status' => 'pending']);
        $session = $service->start($user, $group->id, $list->id, '127.0.0.1', 'Android regression');
        return [$list, $item, $session];
    };
    $finish = function ($session) use ($service, $user, $group) {
        return $service->finish($user, $group->id, $session->id, [], '127.0.0.1', 'Android regression');
    };

    [$list, $item, $session] = $make();
    $beforeStock = (float) App\StockItem::where('family_group_id', $group->id)->where('product_id', $product->id)->sum('quantity');
    $items->update($user, $group->id, $list->id, $item->id, ['status' => 'purchased'], '127.0.0.1', 'Android regression');
    $items->update($user, $group->id, $list->id, $item->id, ['actual_price' => 1000], '127.0.0.1', 'Android regression');
    checkCloudSetup($session->scans()->count() === 0, 'El caso AND-17 debe reproducirse sin registros de escaneo.');
    $result = $finish($session);
    $purchase = App\Purchase::findOrFail($result['summary']['purchase_id']);
    checkCloudSetup($purchase->items()->count() === 1 && (float) $purchase->actual_total === 500.0, 'AND-17: se perdio el articulo manual o su importe.');
    checkCloudSetup((float) $purchase->estimated_total === 450.0, 'Estimado incorrecto para cantidad fraccionaria.');
    checkCloudSetup((float) App\StockItem::where('family_group_id', $group->id)->where('product_id', $product->id)->sum('quantity') === $beforeStock + 0.5, 'AND-17: stock sin incremento.');
    $summary = app(App\Services\Budgets\BudgetSummaryService::class)->summary($group->id, $budget->id, $user->id);
    checkCloudSetup($summary['spent_amount'] === $beforeSpent + 500.0, 'AND-17: presupuesto no actualizado.');
    try {
        $finish($session);
        throw new RuntimeException('Se permitio finalizar la misma sesion dos veces.');
    } catch (App\Exceptions\FamilyGroup\FamilyGroupException $expected) {
        checkCloudSetup(App\Purchase::where('shopping_list_id', $list->id)->count() === 1, 'El cierre repetido duplica compra.');
    }

    [$list, $item, $session] = $make();
    $item->update(['status' => 'purchased', 'actual_price' => 12.50]);
    App\ShoppingSessionScan::create(['shopping_session_id' => $session->id, 'shopping_list_item_id' => $item->id, 'product_id' => $product->id, 'barcode' => 'qa', 'quantity' => 2, 'price' => 1000, 'scan_result' => 'matched']);
    $result = $finish($session);
    $purchase = App\Purchase::findOrFail($result['summary']['purchase_id']);
    checkCloudSetup($purchase->items()->count() === 1 && (float) $purchase->actual_total === 25.0, 'Escaneo contado dos veces o precio posterior ignorado.');

    [$list, $item, $session] = $make();
    App\ShoppingSessionScan::create(['shopping_session_id' => $session->id, 'shopping_list_item_id' => $item->id, 'product_id' => $product->id, 'barcode' => 'qa-unchecked', 'quantity' => 2, 'price' => 1000, 'scan_result' => 'matched']);
    $result = $finish($session);
    checkCloudSetup(App\Purchase::findOrFail($result['summary']['purchase_id'])->items()->count() === 0, 'Un item desmarcado no debe comprarse por un escaneo anterior.');

    [$list, $item, $session] = $make();
    $item->update(['status' => 'purchased', 'actual_price' => 0]);
    $result = $finish($session);
    $purchase = App\Purchase::findOrFail($result['summary']['purchase_id']);
    checkCloudSetup($purchase->items()->count() === 1 && (float) $purchase->items()->first()->unit_price === 0.0, 'Precio cero reemplazado por estimado.');

    [$list, $item, $session] = $make();
    $item->update(['status' => 'purchased']);
    App\ShoppingSessionScan::create(['shopping_session_id' => $session->id, 'shopping_list_item_id' => $item->id, 'product_id' => $product->id, 'barcode' => 'qa-zero', 'quantity' => 0, 'scan_result' => 'matched']);
    $result = $finish($session);
    checkCloudSetup($result['summary']['stock_skipped_count'] === 1 && $result['summary']['stock_warnings'][0]['reason'] === 'ZERO_QUANTITY', 'Una cantidad cero no debe reemplazarse por la lista.');

    [$list, $item, $session] = $make();
    $item->update(['status' => 'purchased', 'product_id' => null]);
    $result = $finish($session);
    checkCloudSetup($result['summary']['stock_skipped_count'] === 1 && $result['summary']['stock_warnings'][0]['reason'] === 'ITEM_WITHOUT_PRODUCT', 'Debe informarse un articulo sin producto.');

    [$list, $item, $session] = $make();
    $item->update(['status' => 'purchased']);
    $list->update(['status' => App\ShoppingList::STATUS_COMPLETED]);
    try {
        $finish($session);
        throw new RuntimeException('Se permitio comprar una lista ya completada por otro flujo.');
    } catch (App\Exceptions\FamilyGroup\FamilyGroupException $expected) {
        checkCloudSetup(App\Purchase::where('shopping_list_id', $list->id)->count() === 0, 'El cierre de una lista completada crea otra compra.');
    }
    echo 'OK: AND-17, compra manual, escaneo sin duplicados, precio editado/cero, pendientes, stock, presupuesto y rechazo de cierres repetidos.'.PHP_EOL;
}
