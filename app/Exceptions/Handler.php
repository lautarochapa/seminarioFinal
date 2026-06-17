<?php

namespace App\Exceptions;

use App\Exceptions\Auth\AuthException;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Exceptions\HealthPreferences\HealthPreferenceException;
use App\Exceptions\IngredientCategories\IngredientCategoryException;
use App\Exceptions\RecipeCategories\RecipeCategoryException;
use App\Exceptions\RecipeTags\RecipeTagException;
use App\Exceptions\RecipeIngredients\RecipeIngredientException;
use App\Exceptions\RecipeAvailability\RecipeAvailabilityException;
use App\Exceptions\RecipeFavoritesCooked\RecipeFavoritesCookedException;
use App\Exceptions\RecipeSharingBranch\RecipeSharingBranchException;
use App\Exceptions\MealPlanGeneration\MealPlanGenerationException;
use App\Exceptions\MealPlanItems\MealPlanItemException;
use App\Exceptions\MealPlanPortions\MealPlanPortionException;
use App\Exceptions\MealPlans\MealPlanException;
use App\Exceptions\MealTypes\MealTypeException;
use App\Exceptions\RecipeImportCandidates\RecipeImportCandidatesException;
use App\Exceptions\RecipeImportUrl\RecipeImportUrlException;
use App\Exceptions\RecipeSubstitutions\RecipeSubstitutionsException;
use App\Exceptions\RecipeCost\RecipeCostException;
use App\Exceptions\RecipeNutrition\RecipeNutritionException;
use App\Exceptions\RecipeSteps\RecipeStepException;
use App\Exceptions\Recipes\RecipeException;
use App\Exceptions\Ingredients\IngredientException;
use App\Exceptions\Objectives\ObjectivesException;
use App\Exceptions\Rbac\RbacException;
use App\Exceptions\BodyMeasurement\BodyMeasurementException;
use App\Exceptions\Nutrients\NutrientException;
use App\Exceptions\Professional\ProfessionalException;
use App\Exceptions\Units\UnitException;
use App\Exceptions\UserProfile\UserProfileException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [];

    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    public function render($request, Throwable $exception)
    {
        if ($this->isApiRequest($request)) {
            return $this->renderApiException($request, $exception);
        }

        return parent::render($request, $exception);
    }

    private function isApiRequest($request)
    {
        return $request->is('api/v1/*') || $request->expectsJson();
    }

    private function renderApiException($request, Throwable $exception)
    {
        $traceId = $request->attributes->get('trace_id', (string) Str::uuid());

        if ($exception instanceof AuthException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof RbacException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof FamilyGroupException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof ObjectivesException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof HealthPreferenceException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof IngredientCategoryException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof RecipeCategoryException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof RecipeTagException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof RecipeException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof RecipeIngredientException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof MealPlanPortionException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof \App\Exceptions\MealPlanIncompatibilities\MealPlanIncompatibilityException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof MealPlanItemException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof MealPlanGenerationException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof MealPlanException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof MealTypeException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof RecipeImportCandidatesException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof RecipeImportUrlException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof RecipeSubstitutionsException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof RecipeSharingBranchException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof RecipeFavoritesCookedException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof RecipeAvailabilityException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof RecipeCostException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof RecipeNutritionException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof RecipeStepException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof IngredientException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof UnitException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof BodyMeasurementException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof UserProfileException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof ProfessionalException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof NutrientException) {
            return $this->errorJson(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus(),
                $traceId,
                $exception->getDetails()
            );
        }

        if ($exception instanceof ValidationException) {
            return response()->json([
                'error' => [
                    'code'         => 'VALIDATION_ERROR',
                    'message'      => 'La solicitud contiene datos inválidos.',
                    'details'      => [],
                    'field_errors' => $exception->errors(),
                ],
                'trace_id' => $traceId,
            ], 422)->header('X-Trace-Id', $traceId);
        }

        if ($exception instanceof AuthenticationException) {
            return $this->errorJson('AUTH_UNAUTHENTICATED', 'No autenticado.', 401, $traceId);
        }

        if ($exception instanceof AuthorizationException) {
            return $this->errorJson('PERMISSION_DENIED', 'Sin permiso para esta acción.', 403, $traceId);
        }

        if ($exception instanceof ModelNotFoundException) {
            return $this->errorJson('RESOURCE_NOT_FOUND', 'El recurso solicitado no existe.', 404, $traceId);
        }

        if ($exception instanceof ThrottleRequestsException) {
            return $this->errorJson('AUTH_TOO_MANY_ATTEMPTS', 'Demasiados intentos. Intente más tarde.', 429, $traceId);
        }

        $message = app()->environment('production')
            ? 'Error interno del servidor.'
            : $exception->getMessage();

        return $this->errorJson('INTERNAL_ERROR', $message, 500, $traceId);
    }

    private function errorJson($code, $message, $status, $traceId, $details = [])
    {
        return response()->json([
            'error' => [
                'code'         => $code,
                'message'      => $message,
                'details'      => $details,
                'field_errors' => (object) [],
            ],
            'trace_id' => $traceId,
        ], $status)->header('X-Trace-Id', $traceId);
    }
}
