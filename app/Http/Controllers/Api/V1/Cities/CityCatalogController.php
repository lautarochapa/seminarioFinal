<?php

namespace App\Http\Controllers\Api\V1\Cities;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Cities\CityResource;
use App\Services\Cities\CityService;
use Illuminate\Http\Request;

class CityCatalogController extends Controller
{
    private $service;

    public function __construct(CityService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $cities  = $this->service->catalog();

        return response()->json([
            'data'     => CityResource::collection($cities),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
