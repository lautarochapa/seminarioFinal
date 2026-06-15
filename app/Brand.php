<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'normalized_name',
        'status',
        'nombre',
        'padre',
    ];

    public function getNombreAttribute($value)
    {
        return $value ?: $this->attributes['name'] ?? null;
    }

    public function products() {
        return $this->hasMany(Product::class);
    }
}
