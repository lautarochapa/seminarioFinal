<?php

namespace App\Http\Controllers\Api\V1\HealthPreferences;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\HealthPreferences\CatalogHealthPreferenceResource;
use App\Services\HealthPreferences\HealthPreferenceAdminService;
use App\Support\HealthPreferenceTypes;
use Illuminate\Http\Request;

class CatalogHealthPreferenceController extends Controller
{
    private $service;

    public function __construct(HealthPreferenceAdminService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, $type)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => CatalogHealthPreferenceResource::collection($this->service->catalog(HealthPreferenceTypes::get($type))),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
