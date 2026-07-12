<?php

namespace App\Exceptions\RecipeImportUrl;

use RuntimeException;

class RecipeImportUrlException extends RuntimeException
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
        return new self('RECIPE_IMPORT_FORBIDDEN', 'No tenes permiso para importar recetas.', 403);
    }

    public static function invalidUrl(): self
    {
        return new self('RECIPE_IMPORT_INVALID_URL', 'La URL proporcionada no es valida.', 422);
    }

    public static function ssrfBlocked(): self
    {
        return new self('RECIPE_IMPORT_SSRF_BLOCKED', 'La URL apunta a una direccion no permitida.', 422);
    }

    public static function unsupportedSource(): self
    {
        return new self('RECIPE_IMPORT_UNSUPPORTED_SOURCE', 'La fuente no esta soportada. Usa una de las fuentes configuradas.', 422);
    }

    public static function duplicateUrl(): self
    {
        return new self('RECIPE_IMPORT_DUPLICATE', 'Esta URL ya fue importada o esta pendiente de revision.', 409);
    }

    public static function fetchFailed(string $reason = ''): self
    {
        return new self('RECIPE_IMPORT_FETCH_FAILED', 'No se pudo obtener el contenido de la URL.' . ($reason ? ' ' . $reason : ''), 422);
    }

    public static function parseFailed(): self
    {
        return new self('RECIPE_IMPORT_PARSE_FAILED', 'No se pudo extraer una receta del contenido obtenido.', 422);
    }
}
