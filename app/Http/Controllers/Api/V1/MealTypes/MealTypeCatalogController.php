<?php

namespace App\Http\Controllers\Api\V1\MealTypes;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MealTypes\MealTypeResource;
use App\Services\MealTypes\MealTypeService;
use Illuminate\Http\Request;

class MealTypeCatalogController extends Controller
{
    private MealTypeService $service;

    public function __construct(MealTypeService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        return response()->json([
            'data'     => MealTypeResource::collection($this->service->catalog()),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
