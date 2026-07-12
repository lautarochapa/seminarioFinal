<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recipe extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'normalized_name',
        'description',
        'source_type',
        'owner_user_id',
        'is_public',
        'is_official',
        'is_verified',
        'status',
        'servings',
        'prep_time_minutes',
        'cook_time_minutes',
        'difficulty',
        'category_id',
        'source_url',
        'source_site',
        'source_author',
        'branched_from_recipe_id',
        'nombre',
        'descripcion',
        'tiempo',
        'img',
        'video',
        'porcion',
        'calorias',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_official' => 'boolean',
        'is_verified' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function getNombreAttribute($value)
    {
        return $value ?: $this->attributes['name'] ?? null;
    }

    public function utensils()
    {
        return $this->belongsToMany(Utensil::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function category()
    {
        return $this->belongsTo(RecipeCategory::class, 'category_id');
    }

    public function branchedFrom()
    {
        return $this->belongsTo(Recipe::class, 'branched_from_recipe_id');
    }

    public function branches()
    {
        return $this->hasMany(Recipe::class, 'branched_from_recipe_id');
    }

    public function ingredients()
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function steps()
    {
        return $this->hasMany(RecipeStep::class)->orderBy('step_number');
    }

    public function images()
    {
        return $this->hasMany(RecipeImage::class);
    }

    public function tags()
    {
        return $this->belongsToMany(RecipeTag::class, 'recipe_tag_pivot')
            ->withPivot(['created_at']);
    }

    public function nutrition()
    {
        return $this->hasOne(RecipeNutrition::class);
    }

    public function costSnapshots()
    {
        return $this->hasMany(RecipeCostSnapshot::class);
    }

    public function sources()
    {
        return $this->hasMany(RecipeSource::class);
    }

    public function favorites()
    {
        return $this->hasMany(RecipeFavorite::class);
    }

    public function cookLogs()
    {
        return $this->hasMany(RecipeCookLog::class);
    }

    public function substitutions()
    {
        return $this->hasMany(RecipeSubstitution::class);
    }

    public function mealPlanItems()
    {
        return $this->hasMany(MealPlanItem::class);
    }

    public function mealPlanSuggestions()
    {
        return $this->hasMany(MealPlanSuggestion::class);
    }
}
