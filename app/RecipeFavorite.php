<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RecipeFavorite extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'recipe_id'];

    protected $casts = ['created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }
}
