<?php

namespace App\Exceptions\MealPlanPortions;

use RuntimeException;

class MealPlanPortionException extends RuntimeException
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
        return new self('MEAL_PLAN_PORTION_GROUP_NOT_FOUND', 'El grupo familiar no existe o no eres miembro.', 403);
    }

    public static function itemNotFound(): self
    {
        return new self('MEAL_PLAN_PORTION_ITEM_NOT_FOUND', 'El item del plan no existe en este contexto.', 404);
    }

    public static function notFound(): self
    {
        return new self('MEAL_PLAN_PORTION_NOT_FOUND', 'La porcion no existe.', 404);
    }

    public static function memberNotInGroup(): self
    {
        return new self('MEAL_PLAN_PORTION_MEMBER_NOT_IN_GROUP', 'El usuario indicado no es miembro activo del grupo.', 422);
    }

    public static function duplicatePortion(): self
    {
        return new self('MEAL_PLAN_PORTION_DUPLICATE', 'El usuario ya tiene una porcion asignada para este item.', 409);
    }
}
