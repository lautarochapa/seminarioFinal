<?php

namespace App\Exceptions\RecipeImportCandidates;

use RuntimeException;

class RecipeImportCandidatesException extends RuntimeException
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

    public static function forbidden(): self
    {
        return new self('IMPORT_CANDIDATE_FORBIDDEN', 'No tenes permiso para gestionar candidatas de importacion.', 403);
    }

    public static function candidateNotFound(): self
    {
        return new self('IMPORT_CANDIDATE_NOT_FOUND', 'La candidata de importacion no existe.', 404);
    }

    public static function alreadyFinalized(): self
    {
        return new self('IMPORT_CANDIDATE_ALREADY_FINALIZED', 'Esta candidata ya fue procesada y no puede modificarse.', 409);
    }

    public static function invalidForApproval(string $reason): self
    {
        return new self('IMPORT_CANDIDATE_INVALID_FOR_APPROVAL', $reason, 422);
    }

    public static function missingIngredientMappings(array $unmapped): self
    {
        return new self(
            'IMPORT_CANDIDATE_MISSING_MAPPINGS',
            'Hay ingredientes sin mapear. Todos los ingredientes deben estar asociados antes de aprobar.',
            422,
            ['unmapped_indices' => $unmapped]
        );
    }

    public static function ingredientNotFound(): self
    {
        return new self('IMPORT_CANDIDATE_INGREDIENT_NOT_FOUND', 'El ingrediente especificado no existe.', 404);
    }

    public static function unitNotFound(): self
    {
        return new self('IMPORT_CANDIDATE_UNIT_NOT_FOUND', 'La unidad de medida especificada no existe.', 404);
    }

    public static function notApproved(): self
    {
        return new self('IMPORT_CANDIDATE_NOT_APPROVED', 'La candidata debe estar aprobada antes de crear la receta.', 422);
    }

    public static function duplicateRecipe(): self
    {
        return new self('IMPORT_CANDIDATE_DUPLICATE_RECIPE', 'Ya existe una receta con esta URL de origen.', 409);
    }
}
