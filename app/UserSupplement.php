<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserSupplement extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'product_id', 'ingredient_id', 'supplement_type_id', 'dose_quantity', 'dose_unit_id', 'frequency', 'start_date', 'end_date', 'notes', 'status'];

    protected $casts = ['dose_quantity' => 'decimal:4', 'start_date' => 'date', 'end_date' => 'date'];

    public function user() { return $this->belongsTo(User::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function ingredient() { return $this->belongsTo(Ingredient::class); }
    public function supplementType() { return $this->belongsTo(SupplementType::class); }
    public function doseUnit() { return $this->belongsTo(UnitMeasure::class, 'dose_unit_id'); }
    public function schedules() { return $this->hasMany(SupplementSchedule::class); }
    public function logs() { return $this->hasMany(SupplementLog::class); }
}
