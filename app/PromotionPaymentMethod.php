<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PromotionPaymentMethod extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'promotion_id',
        'payment_method_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
