<?php

namespace App\Http\Controllers\Api\V1\RecipeFavoritesCooked;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecipeFavoritesCooked\CookRecipeRequest;
use App\Http\Resources\Api\V1\RecipeFavoritesCooked\RecipeCookLogResource;
use App\Http\Resources\Api\V1\RecipeFavoritesCooked\RecipeFavoriteResource;
use App\Services\RecipeFavoritesCooked\RecipeFavoritesCookedService;
use Illuminate\Http\Request;

class RecipeFavoritesCookedController extends Controller
{
    private RecipeFavoritesCookedService $service;

    public function __construct(RecipeFavoritesCookedService $service)
    {
        $this->service = $service;
    }

    public function addFavorite(Request $request, $recipeId)
    {
        $traceId = $request->attributes->get('trace_id');
        $this->service->addFavorite(
            $request->user(),
            (int) $recipeId,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json(['data' => ['favorited' => true], 'trace_id' => $traceId], 201)
            ->header('X-Trace-Id', $traceId);
    }

    public function removeFavorite(Request $request, $recipeId)
    {
        $traceId = $request->attributes->get('trace_id');
        $this->service->removeFavorite(
            $request->user(),
            (int) $recipeId,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json(['data' => ['favorited' => false], 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function listFavorites(Request $request)
    {
        $this->validate($request, [
            'page'     => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $traceId  = $request->attributes->get('trace_id');
        $page     = (int) $request->query('page', 1);
        $perPage  = min((int) $request->query('per_page', 20), 100);
        $result   = $this->service->listFavorites($request->user(), $page, $perPage);
        $paginator = $result['paginator'];

        return response()->json([
            'data'     => RecipeFavoriteResource::collection($paginator->items()),
            'meta'     => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function cook(CookRecipeRequest $request, $recipeId)
    {
        $traceId = $request->attributes->get('trace_id');
        $result  = $this->service->cook(
            $request->user(),
            (int) $recipeId,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json(['data' => $result, 'trace_id' => $traceId], 201)
            ->header('X-Trace-Id', $traceId);
    }

    public function listCooked(Request $request)
    {
        $this->validate($request, [
            'page'     => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $traceId   = $request->attributes->get('trace_id');
        $page      = (int) $request->query('page', 1);
        $perPage   = min((int) $request->query('per_page', 20), 100);
        $result    = $this->service->listCooked($request->user(), $page, $perPage);
        $paginator = $result['paginator'];

        return response()->json([
            'data'     => RecipeCookLogResource::collection($paginator->items()),
            'meta'     => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
