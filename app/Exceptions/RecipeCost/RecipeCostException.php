<?php

namespace App\Exceptions\RecipeCost;

use RuntimeException;

class RecipeCostException extends RuntimeException
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

    public static function recipeNotFound(): self
    {
        return new self('RECIPE_NOT_FOUND', 'La receta solicitada no existe.', 404);
    }

    public static function recipeNotVisible(): self
    {
        return new self('RECIPE_NOT_VISIBLE', 'No tenes acceso a esta receta.', 403);
    }

    public static function forbidden(): self
    {
        return new self('RECIPE_COST_FORBIDDEN', 'Sin permiso para esta accion.', 403);
    }

    public static function familyGroupNotFound(): self
    {
        return new self('FAMILY_GROUP_NOT_FOUND', 'El grupo familiar no existe.', 404);
    }

    public static function familyGroupAccessDenied(): self
    {
        return new self('FAMILY_GROUP_ACCESS_DENIED', 'No sos miembro de este grupo familiar.', 403);
    }
}
