<?php

namespace App\Http\Resources\Api\V1\ScrapingAlerts;

use Illuminate\Http\Resources\Json\JsonResource;

class ScrapingAlertResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'scraping_job_id' => $this->scraping_job_id,
            'source_id' => $this->source_id,
            'alert_type' => $this->alert_type,
            'message' => $this->sanitize($this->message),
            'severity' => $this->severity,
            'status' => $this->status,
            'source' => $this->whenLoaded('source', function () {
                return $this->source ? [
                    'id' => $this->source->id,
                    'code' => $this->source->code,
                    'name' => $this->source->name,
                ] : null;
            }),
            'job' => $this->whenLoaded('job', function () {
                return $this->job ? [
                    'id' => $this->job->id,
                    'job_type' => $this->job->job_type,
                    'status' => $this->job->status,
                    'created_at' => $this->job->created_at,
                ] : null;
            }),
            'resolver' => $this->whenLoaded('resolver', function () {
                return $this->resolver ? [
                    'id' => $this->resolver->id,
                    'name' => $this->resolver->name,
                    'email' => $this->resolver->email,
                ] : null;
            }),
            'resolved_by' => $this->resolved_by,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
        ];
    }

    private function sanitize($value)
    {
        $value = strip_tags((string) $value);
        $value = preg_replace('/(password|token|secret|api_key|authorization)\s*[:=]\s*\S+/i', '$1=[REDACTED]', $value);
        $value = preg_replace('/(Exception|Trace|Stack trace).*/is', '[REDACTED]', $value);

        return trim($value);
    }
}
