<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{    
    use SoftDeletes;

    protected $fillable = [
        'name',
        'normalized_name',
        'brand_id',
        'category_id',
        'ingredient_id',
        'default_unit_id',
        'net_quantity',
        'package_unit_id',
        'description',
        'is_verified',
        'is_active',
        'status',
        'origin',
        'created_by_user_id',
        'family_group_id',
        'nombre',
        'codigo',
        'img',
        'habilitado',
        'supply_id',
    ];

    protected $casts = [
        'net_quantity' => 'decimal:4',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function getNombreAttribute($value)
    {
        return $value ?: $this->attributes['name'] ?? null;
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function commercialCategory()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function defaultUnit()
    {
        return $this->belongsTo(UnitMeasure::class, 'default_unit_id');
    }

    public function packageUnit()
    {
        return $this->belongsTo(UnitMeasure::class, 'package_unit_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function familyGroup()
    {
        return $this->belongsTo(FamilyGroup::class);
    }

    public function supply()
    {
        return $this->belongsTo(Supply::class, 'supply_id')->select(array('id', 'nombre', 'medida', 'category_id'));;
    }


    public function scopeWithFilters($query)
    {
        return $query->when(count(request()->input('brands', [])), function ($query) {
                $query->whereIn('brand_id', request()->input('brands'));
            })
            ->when(count(request()->input('supplies', [])), function ($query) {
                $query->whereIn('supply_id', request()->input('supplies'));
            });
    }
















//****+* */





    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function liists()
    {
        return $this->belongsToMany(Liist::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function recipes()
    {
        return $this->belongsToMany(Recipe::class);
    }

    public function diets()
    {
        return $this->belongsToMany(Diet::class);
    }

    public function barcodes()
    {
        return $this->hasMany(ProductBarcode::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function nutrients()
    {
        return $this->belongsToMany(Nutrient::class, 'product_nutrients')
            ->withPivot(['amount_per_100g', 'amount_per_serving', 'serving_size', 'source', 'status'])
            ->withTimestamps();
    }

    public function nutrientValues()
    {
        return $this->hasMany(ProductNutrient::class);
    }

    public function tags()
    {
        return $this->belongsToMany(FoodTag::class, 'product_tags')
            ->withPivot(['created_at']);
    }

    public function aliases()
    {
        return $this->hasMany(ProductAlias::class);
    }

    public function reports()
    {
        return $this->hasMany(ProductReport::class);
    }

    public function supermarketProducts()
    {
        return $this->hasMany(SupermarketProduct::class);
    }

    public function scrapedCandidates()
    {
        return $this->hasMany(ScrapedProductCandidate::class, 'suggested_product_id');
    }

    public function matchCandidates()
    {
        return $this->hasMany(ProductMatchCandidate::class);
    }

    public function priceRefreshRequests()
    {
        return $this->hasMany(PriceRefreshRequest::class);
    }

    public function stockItems()
    {
        return $this->hasMany(StockItem::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockMinimumRules()
    {
        return $this->hasMany(StockMinimumRule::class);
    }

    public function stockAlerts()
    {
        return $this->hasMany(StockAlert::class);
    }

    public function shoppingListItems()
    {
        return $this->hasMany(ShoppingListItem::class);
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function preferences()
    {
        return $this->hasMany(ProductPreference::class);
    }

    public function userSupplements()
    {
        return $this->hasMany(UserSupplement::class);
    }
}
