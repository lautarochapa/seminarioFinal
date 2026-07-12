<?php

namespace App\Exceptions\RecipeCategories;

use Exception;

class RecipeCategoryException extends Exception
{
    private $errorCode;
    private $httpStatus;
    private $details;

    public function __construct($errorCode, $message, $httpStatus = 422, $details = [])
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
        $this->details = $details;
    }

    public function getErrorCode()
    {
        return $this->errorCode;
    }

    public function getHttpStatus()
    {
        return $this->httpStatus;
    }

    public function getDetails()
    {
        return $this->details;
    }
}
