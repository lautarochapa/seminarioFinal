<?php

namespace App\Http\Controllers\Api\V1\HealthPreferences;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\HealthPreferences\HealthPreferenceRequest;
use App\Http\Resources\Api\V1\HealthPreferences\HealthPreferenceResource;
use App\Services\HealthPreferences\HealthPreferenceAdminService;
use App\Support\HealthPreferenceTypes;
use Illuminate\Http\Request;

class AdminHealthPreferenceController extends Controller
{
    private $service;

    public function __construct(HealthPreferenceAdminService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, $type)
    {
        $traceId = $request->attributes->get('trace_id');
        $paginator = $this->service->list($this->type($type), $request->query());

        return response()->json([
            'data' => HealthPreferenceResource::collection($paginator),
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

    public function show(Request $request, $type, $id)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => new HealthPreferenceResource($this->service->show($this->type($type), (int) $id)),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(HealthPreferenceRequest $request, $type)
    {
        $traceId = $request->attributes->get('trace_id');
        $item = $this->service->create($this->type($type), $request->user()->id, $request->validated(), $request->ip(), $request->userAgent() ?? '');

        return response()->json([
            'data' => new HealthPreferenceResource($item),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(HealthPreferenceRequest $request, $type, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $item = $this->service->update($this->type($type), $request->user()->id, (int) $id, $request->validated(), $request->ip(), $request->userAgent() ?? '');

        return response()->json([
            'data' => new HealthPreferenceResource($item),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $type, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $item = $this->service->delete($this->type($type), $request->user()->id, (int) $id, $request->ip(), $request->userAgent() ?? '');

        return response()->json([
            'data' => new HealthPreferenceResource($item),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function restore(Request $request, $type, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $item = $this->service->restore($this->type($type), $request->user()->id, (int) $id, $request->ip(), $request->userAgent() ?? '');

        return response()->json([
            'data' => new HealthPreferenceResource($item),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    private function type($type)
    {
        return HealthPreferenceTypes::get($type);
    }
}
