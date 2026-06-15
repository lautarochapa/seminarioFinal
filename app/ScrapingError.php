<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ScrapingError extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'scraping_job_id',
        'source_id',
        'error_type',
        'message',
        'stack_trace',
        'context_json',
    ];

    protected $casts = [
        'context_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function job()
    {
        return $this->belongsTo(ScrapingJob::class, 'scraping_job_id');
    }

    public function source()
    {
        return $this->belongsTo(ScrapingSource::class, 'source_id');
    }
}
