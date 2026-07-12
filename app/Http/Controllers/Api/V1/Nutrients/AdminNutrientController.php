<?php

namespace App\Http\Controllers\Api\V1\Nutrients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Nutrients\StoreNutrientRequest;
use App\Http\Requests\Api\V1\Nutrients\UpdateNutrientRequest;
use App\Http\Resources\Api\V1\Admin\AuditLogResource;
use App\Http\Resources\Api\V1\Nutrients\NutrientResource;
use App\Services\Admin\AuditAdminService;
use App\Services\Nutrients\NutrientService;
use Illuminate\Http\Request;

class AdminNutrientController extends Controller
{
    private $service;
    private $auditService;

    public function __construct(NutrientService $service, AuditAdminService $auditService)
    {
        $this->service      = $service;
        $this->auditService = $auditService;
    }

    public function index(Request $request)
    {
        return $this->paginated($request, $this->service->list($request->query()), NutrientResource::class);
    }

    public function show(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data'     => new NutrientResource($this->service->show((int) $id)),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(StoreNutrientRequest $request)
    {
        $traceId  = $request->attributes->get('trace_id');
        $nutrient = $this->service->create(
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new NutrientResource($nutrient),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateNutrientRequest $request, $id)
    {
        $traceId  = $request->attributes->get('trace_id');
        $nutrient = $this->service->update(
            $request->user()->id,
            (int) $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new NutrientResource($nutrient),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id)
    {
        $traceId  = $request->attributes->get('trace_id');
        $nutrient = $this->service->delete(
            $request->user()->id,
            (int) $id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new NutrientResource($nutrient),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function restore(Request $request, $id)
    {
        $traceId  = $request->attributes->get('trace_id');
        $nutrient = $this->service->restore(
            $request->user()->id,
            (int) $id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new NutrientResource($nutrient),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function audit(Request $request, $id)
    {
        return $this->paginated(
            $request,
            $this->auditService->forEntity('nutrients', (int) $id, $request->query()),
            AuditLogResource::class
        );
    }

    private function paginated(Request $request, $paginator, $resourceClass)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data'  => $resourceClass::collection($paginator),
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
