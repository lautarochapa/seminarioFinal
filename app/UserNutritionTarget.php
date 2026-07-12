<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserNutritionTarget extends Model
{
    protected $fillable = [
        'user_id',
        'daily_calories',
        'daily_protein_g',
        'daily_carbs_g',
        'daily_fat_g',
        'daily_sodium_mg',
        'daily_sugar_g',
    ];

    protected $casts = [
        'daily_protein_g' => 'decimal:2',
        'daily_carbs_g' => 'decimal:2',
        'daily_fat_g' => 'decimal:2',
        'daily_sugar_g' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
