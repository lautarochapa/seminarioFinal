<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ScrapingJobLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'scraping_job_id',
        'level',
        'message',
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
}
