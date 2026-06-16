<?php

namespace App\Http\Controllers\Api\V1\Objectives;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Objectives\CatalogObjectiveResource;
use App\Services\Objectives\ObjectiveAdminService;
use Illuminate\Http\Request;

class CatalogObjectiveController extends Controller
{
    private $service;

    public function __construct(ObjectiveAdminService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => CatalogObjectiveResource::collection($this->service->catalog()),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
