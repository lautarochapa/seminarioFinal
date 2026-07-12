<?php

namespace App\Exceptions\RecipeFavoritesCooked;

use RuntimeException;

class RecipeFavoritesCookedException extends RuntimeException
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

    public static function alreadyFavorited(): self
    {
        return new self('RECIPE_ALREADY_FAVORITED', 'Esta receta ya esta en tus favoritos.', 409);
    }

    public static function notFavorited(): self
    {
        return new self('RECIPE_NOT_FAVORITED', 'Esta receta no esta en tus favoritos.', 404);
    }

    public static function familyGroupNotFound(): self
    {
        return new self('FAMILY_GROUP_NOT_FOUND', 'El grupo familiar no existe.', 404);
    }

    public static function familyGroupAccessDenied(): self
    {
        return new self('FAMILY_GROUP_ACCESS_DENIED', 'No sos miembro de este grupo familiar.', 403);
    }

    public static function insufficientStock(): self
    {
        return new self('INSUFFICIENT_STOCK', 'No hay stock suficiente para descontar todos los ingredientes.', 422);
    }

    public static function ingredientMissing(array $details = []): self
    {
        return new self('RECIPE_INGREDIENT_MISSING_STOCK', 'Faltan ingredientes requeridos en el stock.', 422, $details);
    }

    public static function cookInProgress(): self
    {
        return new self('RECIPE_COOK_IN_PROGRESS', 'La receta ya se esta procesando para esta solicitud.', 409);
    }
}
