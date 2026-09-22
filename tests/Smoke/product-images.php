<?php

// Included by initialize-cloud.php inside a disposable local database.
function checkProductImages(App\Product $product, App\FamilyGroup $group, int $userId, int $unitId): void
{
    $url = 'https://cdn.example.com/product-front.jpg';
    $primary = App\ProductImage::create(['product_id' => $product->id, 'image_url' => $url, 'is_primary' => true, 'status' => 'active']);
    App\ProductImage::create(['product_id' => $product->id, 'image_url' => 'https://cdn.example.com/removed.jpg', 'is_primary' => false, 'status' => 'inactive']);
    $repo = app(App\Repositories\HouseholdStock\HouseholdStockRepository::class);
    $page = $repo->paginateForGroup($group->id, ['product_id' => $product->id]);
    checkCloudSetup($page->count() > 0, 'Falta stock de prueba.');
    Illuminate\Support\Facades\DB::enableQueryLog();
    Illuminate\Support\Facades\DB::flushQueryLog();
    $stockData = json_decode(App\Http\Resources\Api\V1\HouseholdStock\StockItemResource::collection($page)->toJson(), true);
    checkCloudSetup(count(Illuminate\Support\Facades\DB::getQueryLog()) === 0, 'Serializar fotos no debe hacer consultas por producto.');
    Illuminate\Support\Facades\DB::disableQueryLog();
    foreach ($stockData as $item) {
        checkCloudSetup(count($item['product']['images']) === 1, 'Stock incluye foto desactivada.');
        checkCloudSetup($item['product']['images'][0]['image_url'] === $url, 'Stock omite image_url.');
    }
    $catalog = app(App\Repositories\Products\ProductRepository::class)->findOrFail($product->id);
    $data = json_decode((new App\Http\Resources\Api\V1\Products\ProductResource($catalog))->toJson(), true);
    checkCloudSetup(count($data['images']) === 1 && $data['images'][0]['is_primary'] === true, 'Catalogo debe entregar solo fotos activas.');
    $detail = $repo->findInGroupOrFail($group->id, $page->first()->id);
    checkCloudSetup($detail->product->relationLoaded('images'), 'Detalle debe cargar fotos por anticipado.');
    $updated = $repo->update($detail, []);
    checkCloudSetup($updated->product->relationLoaded('images'), 'Edicion pierde fotos.');
    [$created] = app(App\Services\HouseholdStock\HouseholdStockService::class)->create($group->id, $userId, [
        'product_id' => $product->id, 'unit_id' => $unitId, 'quantity' => 1, 'expiration_date' => '2031-01-01',
    ], '127.0.0.1', 'Product photo smoke');
    checkCloudSetup($created->product->relationLoaded('images'), 'Alta de stock pierde fotos.');
    $primary->update(['status' => 'inactive']);
    $empty = json_decode((new App\Http\Resources\Api\V1\HouseholdStock\StockItemResource($repo->findInGroupOrFail($group->id, $detail->id)))->toJson(), true);
    checkCloudSetup($empty['product']['images'] === [], 'Un producto sin fotos activas debe devolver lista vacia.');
    echo 'OK: fotos activas en catalogo y stock, alta/edicion, sin fotos y sin consultas N+1 al serializar.'.PHP_EOL;
}
