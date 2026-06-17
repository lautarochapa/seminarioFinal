<?php

namespace App\Exceptions\MealPlanIncompatibilities;

use RuntimeException;

class MealPlanIncompatibilityException extends RuntimeException
{
    private string $errorCode;
    private int    $httpStatus;
    private array  $details;

    public function __construct(string $errorCode, string $message, int $httpStatus = 400, array $details = [])
    {
        parent::__construct($message);
        $this->errorCode  = $errorCode;
        $this->httpStatus = $httpStatus;
        $this->details    = $details;
    }

    public function getErrorCode(): string { return $this->errorCode; }
    public function getHttpStatus(): int   { return $this->httpStatus; }
    public function getDetails(): array    { return $this->details; }

    public static function groupNotFound(): self
    {
        return new self('MEAL_PLAN_INCOMPATIBILITY_GROUP_NOT_FOUND', 'El grupo familiar no existe o no eres miembro.', 403);
    }

    public static function planNotFound(): self
    {
        return new self('MEAL_PLAN_INCOMPATIBILITY_PLAN_NOT_FOUND', 'El plan de comidas no existe para este grupo.', 404);
    }
}
