<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ScrapingJob extends Model
{
    protected $fillable = [
        'source_id',
        'job_type',
        'requested_by',
        'status',
        'parameters_json',
        'started_at',
        'finished_at',
        'total_found',
        'total_created',
        'total_updated',
        'total_pending_review',
        'error_message',
    ];

    protected $casts = [
        'parameters_json' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function source()
    {
        return $this->belongsTo(ScrapingSource::class, 'source_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function logs()
    {
        return $this->hasMany(ScrapingJobLog::class);
    }

    public function candidates()
    {
        return $this->hasMany(ScrapedProductCandidate::class);
    }

    public function alerts()
    {
        return $this->hasMany(ScrapingAlert::class);
    }

    public function errors()
    {
        return $this->hasMany(ScrapingError::class);
    }
}
