<?php

namespace App\Http\Resources\Api\V1\Scraping;

use Illuminate\Http\Resources\Json\JsonResource;

class ScrapingJobLogResource extends JsonResource
{
    private static array $sensitiveKeys = [
        'token', 'cookie', 'password', 'secret', 'auth', 'credential', 'key',
    ];

    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'level'      => $this->level,
            'message'    => $this->message,
            'context'    => $this->sanitizeContext($this->context_json),
            'created_at' => $this->created_at,
        ];
    }

    private function sanitizeContext(?array $context): ?array
    {
        if ($context === null) {
            return null;
        }

        return array_filter($context, function ($key) {
            foreach (self::$sensitiveKeys as $s) {
                if (stripos($key, $s) !== false) {
                    return false;
                }
            }
            return true;
        }, ARRAY_FILTER_USE_KEY);
    }
}
