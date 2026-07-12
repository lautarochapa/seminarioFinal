<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'issuer',
        'status',
    ];

    public function promotions()
    {
        return $this->belongsToMany(Promotion::class, 'promotion_payment_methods')
            ->withPivot(['created_at']);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_payment_methods')
            ->withPivot(['alias', 'status'])
            ->withTimestamps();
    }
}
