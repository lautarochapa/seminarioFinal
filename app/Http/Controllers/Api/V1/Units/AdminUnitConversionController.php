<?php

namespace App\Http\Controllers\Api\V1\Units;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Units\StoreUnitConversionRequest;
use App\Http\Requests\Api\V1\Units\UpdateUnitConversionRequest;
use App\Http\Resources\Api\V1\Admin\AuditLogResource;
use App\Http\Resources\Api\V1\Units\UnitConversionResource;
use App\Services\Admin\AuditAdminService;
use App\Services\Units\UnitConversionService;
use Illuminate\Http\Request;

class AdminUnitConversionController extends Controller
{
    private $service;
    private $auditService;

    public function __construct(UnitConversionService $service, AuditAdminService $auditService)
    {
        $this->service = $service;
        $this->auditService = $auditService;
    }

    public function index(Request $request)
    {
        return $this->paginated($request, $this->service->list($request->query()), UnitConversionResource::class);
    }

    public function show(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json(['data' => new UnitConversionResource($this->service->show((int) $id)), 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function store(StoreUnitConversionRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $conversion = $this->service->create($request->user()->id, $request->validated(), $request->ip(), $request->userAgent() ?? '');

        return response()->json(['data' => new UnitConversionResource($conversion), 'trace_id' => $traceId], 201)
            ->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateUnitConversionRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $conversion = $this->service->update($request->user()->id, (int) $id, $request->validated(), $request->ip(), $request->userAgent() ?? '');

        return response()->json(['data' => new UnitConversionResource($conversion), 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $conversion = $this->service->delete($request->user()->id, (int) $id, $request->ip(), $request->userAgent() ?? '');

        return response()->json(['data' => new UnitConversionResource($conversion), 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function restore(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $conversion = $this->service->restore($request->user()->id, (int) $id, $request->ip(), $request->userAgent() ?? '');

        return response()->json(['data' => new UnitConversionResource($conversion), 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function audit(Request $request, $id)
    {
        return $this->paginated($request, $this->auditService->forEntity('unit_conversions', (int) $id, $request->query()), AuditLogResource::class);
    }

    private function paginated(Request $request, $paginator, $resourceClass)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => $resourceClass::collection($paginator),
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
}
