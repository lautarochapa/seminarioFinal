<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ScrapingAlert extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'scraping_job_id',
        'source_id',
        'alert_type',
        'message',
        'severity',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
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

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
