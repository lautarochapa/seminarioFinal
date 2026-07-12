<?php

namespace App\Exceptions\MealPlans;

use RuntimeException;

class MealPlanException extends RuntimeException
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

    public static function notFound(): self
    {
        return new self('MEAL_PLAN_NOT_FOUND', 'El plan de comidas no existe.', 404);
    }

    public static function accessDenied(): self
    {
        return new self('MEAL_PLAN_ACCESS_DENIED', 'No tenes acceso a este plan de comidas.', 403);
    }

    public static function groupNotFound(): self
    {
        return new self('MEAL_PLAN_GROUP_NOT_FOUND', 'El grupo familiar no existe o no eres miembro.', 403);
    }

    public static function itemMissingContent(int $index): self
    {
        return new self(
            'MEAL_PLAN_ITEM_MISSING_CONTENT',
            'Cada entrada debe tener receta, descripcion de comida libre o indicar que se come fuera.',
            422,
            ['item_index' => $index]
        );
    }

    public static function mealTypeNotFound(int $id): self
    {
        return new self('MEAL_PLAN_MEAL_TYPE_NOT_FOUND', "El tipo de comida {$id} no existe o no esta activo.", 422);
    }

    public static function recipeNotFound(int $id): self
    {
        return new self('MEAL_PLAN_RECIPE_NOT_FOUND', "La receta {$id} no existe o no esta activa.", 422);
    }
}
