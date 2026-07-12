<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductReport extends Model
{
    const TYPE_INCORRECT_PRICE = 'incorrect_price';
    const TYPE_INCORRECT_PRODUCT_DATA = 'incorrect_product_data';
    const TYPE_INCORRECT_NUTRITION = 'incorrect_nutrition';
    const TYPE_DUPLICATE_PRODUCT = 'duplicate_product';
    const TYPE_OTHER = 'other';

    const VALID_TYPES = [
        self::TYPE_INCORRECT_PRICE,
        self::TYPE_INCORRECT_PRODUCT_DATA,
        self::TYPE_INCORRECT_NUTRITION,
        self::TYPE_DUPLICATE_PRODUCT,
        self::TYPE_OTHER,
    ];

    const STATUS_OPEN = 'open';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_REJECTED = 'rejected';

    const VALID_RESOLVE_STATUSES = [
        self::STATUS_RESOLVED,
        self::STATUS_REJECTED,
    ];

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
