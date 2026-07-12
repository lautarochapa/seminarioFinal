<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductPreference extends Model
{
    protected $fillable = ['family_group_id', 'user_id', 'product_id', 'brand_id', 'preference_type', 'priority', 'notes'];

    public function familyGroup() { return $this->belongsTo(FamilyGroup::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function brand() { return $this->belongsTo(Brand::class); }
}
