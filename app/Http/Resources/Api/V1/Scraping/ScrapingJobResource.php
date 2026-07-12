<?php

namespace App\Http\Resources\Api\V1\Scraping;

use Illuminate\Http\Resources\Json\JsonResource;

class ScrapingJobResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'source_id'            => $this->source_id,
            'source'               => $this->whenLoaded('source', function () {
                return [
                    'id'   => $this->source->id,
                    'code' => $this->source->code,
                    'name' => $this->source->name,
                ];
            }),
            'job_type'             => $this->job_type,
            'status'               => $this->status,
            'parameters'           => $this->parameters_json,
            'started_at'           => $this->started_at,
            'finished_at'          => $this->finished_at,
            'total_found'          => $this->total_found,
            'total_created'        => $this->total_created,
            'total_updated'        => $this->total_updated,
            'total_pending_review' => $this->total_pending_review,
            'error_message'        => $this->error_message,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
