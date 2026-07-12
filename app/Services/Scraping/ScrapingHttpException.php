<?php

namespace App\Services\Scraping;

class ScrapingHttpException extends \RuntimeException
{
    private $statusCode;
    private $retryAfterSeconds;
    private $transient;

    public function __construct(string $message, ?int $statusCode = null, ?int $retryAfterSeconds = null, bool $transient = false)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->retryAfterSeconds = $retryAfterSeconds;
        $this->transient = $transient;
    }

    public function statusCode(): ?int
    {
        return $this->statusCode;
    }

    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }

    public function isTransient(): bool
    {
        return $this->transient;
    }
}
