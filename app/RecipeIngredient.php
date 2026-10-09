<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RecipeIngredient extends Model
{
    protected $fillable = ['recipe_id', 'ingredient_id', 'specific_product_id', 'quantity', 'unit_id', 'is_optional', 'notes', 'sort_order'];

    protected $casts = [
        'quantity' => 'decimal:4',
        'is_optional' => 'boolean',
    ];

    public static function validQuantity($quantity, $isOptional): bool
    {
        if (!is_numeric($quantity) || !is_finite((float) $quantity)) {
            return false;
        }
        return (float) $quantity >= 0.0001
            || ((float) $quantity === 0.0 && in_array($isOptional, [true, 1, '1'], true));
    }

    public function isUnquantified(): bool
    {
        return $this->quantity !== null && (float) $this->quantity === 0.0 && $this->is_optional;
    }

    public function quantityLabel(): ?string
    {
        return $this->isUnquantified() ? (trim((string) $this->notes) !== '' ? $this->notes : 'A gusto') : null;
    }

    public function tracksStock(): bool
    {
        return (float) $this->quantity > 0 && !$this->is_optional;
    }

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function specificProduct()
    {
        return $this->belongsTo(Product::class, 'specific_product_id');
    }

    public function unit()
    {
        return $this->belongsTo(UnitMeasure::class, 'unit_id');
    }
}
