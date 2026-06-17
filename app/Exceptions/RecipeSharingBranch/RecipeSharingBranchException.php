<?php

namespace App\Exceptions\RecipeSharingBranch;

use RuntimeException;

class RecipeSharingBranchException extends RuntimeException
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
        return new self('RECIPE_FORBIDDEN', 'Solo el autor o un administrador puede realizar esta accion.', 403);
    }

    public static function alreadyPublic(): self
    {
        return new self('RECIPE_ALREADY_PUBLIC', 'La receta ya esta compartida publicamente.', 409);
    }

    public static function alreadyPrivate(): self
    {
        return new self('RECIPE_ALREADY_PRIVATE', 'La receta ya es privada.', 409);
    }
}
