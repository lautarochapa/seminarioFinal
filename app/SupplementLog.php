<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SupplementLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_supplement_id', 'user_id', 'taken_at', 'dose_quantity', 'dose_unit_id', 'notes', 'created_at'];

    protected $casts = ['taken_at' => 'datetime', 'dose_quantity' => 'decimal:4', 'created_at' => 'datetime'];

    public function userSupplement() { return $this->belongsTo(UserSupplement::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function doseUnit() { return $this->belongsTo(UnitMeasure::class, 'dose_unit_id'); }
}
