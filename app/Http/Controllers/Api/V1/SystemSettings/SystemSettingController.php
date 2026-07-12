<?php

namespace App\Http\Controllers\Api\V1\SystemSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SystemSettings\UpdateSystemSettingRequest;
use App\Http\Resources\Api\V1\SystemSettings\SystemSettingResource;
use App\Services\SystemSettings\SystemSettingService;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    private $service;

    public function __construct(SystemSettingService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $paginator = $this->service->list($request->query());
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => SystemSettingResource::collection($paginator),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateSystemSettingRequest $request, $key)
    {
        $setting = $this->service->update(
            $request->user()->id,
            $key,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => new SystemSettingResource($setting),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
