<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductMatchCandidate extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'scraped_product_candidate_id',
        'product_id',
        'match_score',
        'match_reason',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'match_score' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function scrapedProductCandidate()
    {
        return $this->belongsTo(ScrapedProductCandidate::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
