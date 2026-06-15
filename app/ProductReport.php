<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductReport extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'report_type',
        'description',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
