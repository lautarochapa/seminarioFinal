<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ScrapedProductCandidate extends Model
{
    protected $fillable = [
        'scraping_job_id',
        'source_id',
        'raw_name',
        'raw_brand',
        'raw_price',
        'raw_unit_price',
        'raw_image_url',
        'raw_product_url',
        'external_product_id',
        'raw_payload_json',
        'suggested_product_id',
        'suggested_ingredient_id',
        'match_confidence',
        'review_status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'raw_price' => 'decimal:2',
        'raw_unit_price' => 'decimal:4',
        'raw_payload_json' => 'array',
        'match_confidence' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function job()
    {
        return $this->belongsTo(ScrapingJob::class, 'scraping_job_id');
    }

    public function source()
    {
        return $this->belongsTo(ScrapingSource::class, 'source_id');
    }

    public function suggestedProduct()
    {
        return $this->belongsTo(Product::class, 'suggested_product_id');
    }

    public function suggestedIngredient()
    {
        return $this->belongsTo(Ingredient::class, 'suggested_ingredient_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function matchCandidates()
    {
        return $this->hasMany(ProductMatchCandidate::class);
    }
}
