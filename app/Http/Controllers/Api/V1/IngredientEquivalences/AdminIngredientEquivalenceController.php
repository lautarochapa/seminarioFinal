<?php

namespace App\Http\Controllers\Api\V1\IngredientEquivalences;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IngredientEquivalences\StoreIngredientEquivalenceRequest;
use App\Http\Requests\Api\V1\IngredientEquivalences\UpdateIngredientEquivalenceRequest;
use App\Http\Resources\Api\V1\Admin\AuditLogResource;
use App\Http\Resources\Api\V1\Ingredients\IngredientEquivalenceResource;
use App\Services\Admin\AuditAdminService;
use App\Services\IngredientEquivalences\IngredientEquivalenceService;
use Illuminate\Http\Request;

class AdminIngredientEquivalenceController extends Controller
{
    private $service;
    private $auditService;

    public function __construct(IngredientEquivalenceService $service, AuditAdminService $auditService)
    {
        $this->service = $service;
        $this->auditService = $auditService;
    }

    public function index(Request $request)
    {
        return $this->paginated($request, $this->service->list($request->query()), IngredientEquivalenceResource::class);
    }

    public function show(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json(['data' => new IngredientEquivalenceResource($this->service->show((int) $id)), 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function store(StoreIngredientEquivalenceRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $equivalence = $this->service->create($request->user()->id, $request->validated(), $request->ip(), $request->userAgent() ?? '');

        return response()->json(['data' => new IngredientEquivalenceResource($equivalence), 'trace_id' => $traceId], 201)
            ->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateIngredientEquivalenceRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $equivalence = $this->service->update($request->user()->id, (int) $id, $request->validated(), $request->ip(), $request->userAgent() ?? '');

        return response()->json(['data' => new IngredientEquivalenceResource($equivalence), 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $equivalence = $this->service->delete($request->user()->id, (int) $id, $request->ip(), $request->userAgent() ?? '');

        return response()->json(['data' => new IngredientEquivalenceResource($equivalence), 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function restore(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $equivalence = $this->service->restore($request->user()->id, (int) $id, $request->ip(), $request->userAgent() ?? '');

        return response()->json(['data' => new IngredientEquivalenceResource($equivalence), 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function audit(Request $request, $id)
    {
        return $this->paginated($request, $this->auditService->forEntity('ingredient_equivalences', (int) $id, $request->query()), AuditLogResource::class);
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
