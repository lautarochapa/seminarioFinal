<?php

namespace App\Services\Products;

use App\AuditLog;
use App\Exceptions\Ingredients\IngredientException;
use App\Repositories\Products\ProductRepository;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ProductImageService
{
    private $products;

    public function __construct(ProductRepository $products)
    {
        $this->products = $products;
    }

    public function addImage(int $actorId, int $productId, array $data, string $ip, string $userAgent)
    {
        $product = $this->products->findOrFail($productId);

        if ($product->status !== 'active' || ! $product->is_active || $product->trashed()) {
            throw new IngredientException('PRODUCT_NOT_FOUND', 'El producto solicitado no existe.', 404);
        }

        $isPrimary = (bool) ($data['is_primary'] ?? false);

        return DB::transaction(function () use ($actorId, $product, $data, $isPrimary, $ip, $userAgent) {
            if ($isPrimary) {
                $this->products->clearPrimaryImages($product->id);
            }

            $image = $this->products->addImage($product, [
                'image_url' => $data['image_url'],
                'source' => $data['source'] ?? null,
                'is_primary' => $isPrimary,
            ]);

            AuditLog::create([
                'user_id' => $actorId,
                'action' => 'product_image.created',
                'entity_name' => 'product_images',
                'entity_id' => (string) $image->id,
                'old_values' => null,
                'new_values' => ['product_id' => $product->id, 'image_url' => $data['image_url'], 'is_primary' => $isPrimary],
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]);

            return $image;
        });
    }

    public function removeImage(int $actorId, int $productId, int $imageId, string $ip, string $userAgent)
    {
        $this->products->findOrFail($productId);
        $image = $this->products->findActiveImageForProduct($productId, $imageId);

        DB::transaction(function () use ($actorId, $image, $ip, $userAgent) {
            $old = ['product_id' => $image->product_id, 'image_url' => $image->image_url, 'is_primary' => $image->is_primary];
            $this->products->deactivateImage($image);

            if (! Str::startsWith($image->image_url, 'http')) {
                Storage::disk('public')->delete($image->image_url);
            }

            AuditLog::create([
                'user_id' => $actorId,
                'action' => 'product_image.deleted',
                'entity_name' => 'product_images',
                'entity_id' => (string) $image->id,
                'old_values' => $old,
                'new_values' => null,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]);
        });
    }
}
