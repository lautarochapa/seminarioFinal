<?php

namespace App\Exceptions\MealPlanGeneration;

use RuntimeException;

class MealPlanGenerationException extends RuntimeException
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
        return new self('MEAL_PLAN_GENERATION_GROUP_NOT_FOUND', 'El grupo familiar no existe o no eres miembro.', 403);
    }

    public static function notFound(): self
    {
        return new self('MEAL_PLAN_GENERATION_NOT_FOUND', 'El plan de comidas no existe.', 404);
    }

    public static function alreadyApproved(): self
    {
        return new self('MEAL_PLAN_GENERATION_ALREADY_APPROVED', 'El plan ya fue aprobado.', 409);
    }

    public static function cannotApprove(): self
    {
        return new self('MEAL_PLAN_GENERATION_CANNOT_APPROVE', 'Solo se pueden aprobar planes en estado pendiente.', 409);
    }

    public static function cannotRegenerate(): self
    {
        return new self('MEAL_PLAN_GENERATION_CANNOT_REGENERATE', 'No se puede regenerar un plan aprobado.', 409);
    }

    public static function noRecipesAvailable(): self
    {
        return new self('MEAL_PLAN_GENERATION_NO_RECIPES_AVAILABLE', 'No hay recetas activas disponibles para generar el plan.', 422);
    }
}
