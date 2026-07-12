<?php

namespace App\Exceptions\MealPlanItems;

use RuntimeException;

class MealPlanItemException extends RuntimeException
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
        return new self('MEAL_PLAN_ITEM_GROUP_NOT_FOUND', 'El grupo familiar no existe o no eres miembro.', 403);
    }

    public static function planNotFound(): self
    {
        return new self('MEAL_PLAN_ITEM_PLAN_NOT_FOUND', 'El plan de comidas no existe para este grupo.', 404);
    }

    public static function notFound(): self
    {
        return new self('MEAL_PLAN_ITEM_NOT_FOUND', 'El item del plan no existe.', 404);
    }

    public static function missingContent(): self
    {
        return new self('MEAL_PLAN_ITEM_MISSING_CONTENT', 'Cada item debe tener receta, descripcion de comida libre o indicar que se come fuera.', 422);
    }

    public static function mealTypeNotFound(int $id): self
    {
        return new self('MEAL_PLAN_ITEM_MEAL_TYPE_NOT_FOUND', "El tipo de comida {$id} no existe o no esta activo.", 422);
    }

    public static function recipeNotFound(int $id): self
    {
        return new self('MEAL_PLAN_ITEM_RECIPE_NOT_FOUND', "La receta {$id} no existe o no esta activa.", 422);
    }

    public static function duplicateItem(): self
    {
        return new self('MEAL_PLAN_ITEM_DUPLICATE', 'Ya existe un item para esa fecha y tipo de comida en este plan.', 409);
    }

    public static function alreadyFinalized(): self
    {
        return new self('MEAL_PLAN_ITEM_ALREADY_FINALIZED', 'El item ya fue finalizado.', 409);
    }

    public static function recipeRequired(): self
    {
        return new self('MEAL_PLAN_ITEM_RECIPE_REQUIRED', 'El item debe tener una receta para marcarse como cocinado.', 422);
    }

    public static function insufficientStock(): self
    {
        return new self('MEAL_PLAN_ITEM_INSUFFICIENT_STOCK', 'No hay stock suficiente para cocinar la receta.', 409);
    }
}
