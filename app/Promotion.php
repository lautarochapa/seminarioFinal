<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promotion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'supermarket_chain_id',
        'supermarket_branch_id',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'valid_from',
        'valid_to',
        'day_of_week',
        'requires_payment_method',
        'status',
    ];

    protected $casts = [
        'discount_value' => 'decimal:4',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'requires_payment_method' => 'boolean',
    ];

    public function chain()
    {
        return $this->belongsTo(SupermarketChain::class, 'supermarket_chain_id');
    }

    public function branch()
    {
        return $this->belongsTo(SupermarketBranch::class, 'supermarket_branch_id');
    }

    public function paymentMethods()
    {
        return $this->belongsToMany(PaymentMethod::class, 'promotion_payment_methods')
            ->withPivot(['created_at']);
    }

    public function prices()
    {
        return $this->hasMany(SupermarketProductPrice::class);
    }
}
