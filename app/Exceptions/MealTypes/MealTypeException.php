<?php

namespace App\Exceptions\MealTypes;

use RuntimeException;

class MealTypeException extends RuntimeException
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
        return new self('MEAL_TYPE_NOT_FOUND', 'El tipo de comida no existe.', 404);
    }

    public static function codeAlreadyExists(): self
    {
        return new self('MEAL_TYPE_CODE_ALREADY_EXISTS', 'El codigo ya esta en uso.', 409);
    }

    public static function nameAlreadyExists(): self
    {
        return new self('MEAL_TYPE_NAME_ALREADY_EXISTS', 'El nombre ya esta en uso.', 409);
    }

    public static function alreadyInactive(): self
    {
        return new self('MEAL_TYPE_ALREADY_INACTIVE', 'El tipo de comida ya esta inactivo.', 409);
    }

    public static function alreadyActive(): self
    {
        return new self('MEAL_TYPE_ALREADY_ACTIVE', 'El tipo de comida ya esta activo.', 409);
    }
}
