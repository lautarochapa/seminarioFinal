<?php

namespace App\Http\Controllers\Api\V1\Objectives;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Objectives\CreateObjectiveRequest;
use App\Http\Requests\Api\V1\Objectives\UpdateObjectiveRequest;
use App\Http\Resources\Api\V1\Objectives\ObjectiveResource;
use App\Services\Objectives\ObjectiveAdminService;
use Illuminate\Http\Request;

class AdminObjectiveController extends Controller
{
    private $service;

    public function __construct(ObjectiveAdminService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $paginator = $this->service->list($request->query());

        return response()->json([
            'data' => ObjectiveResource::collection($paginator),
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

    public function show(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => new ObjectiveResource($this->service->show((int) $id)),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(CreateObjectiveRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $objective = $this->service->create(
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data' => new ObjectiveResource($objective),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateObjectiveRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $objective = $this->service->update(
            $request->user()->id,
            (int) $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data' => new ObjectiveResource($objective),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $objective = $this->service->delete(
            $request->user()->id,
            (int) $id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data' => new ObjectiveResource($objective),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function restore(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $objective = $this->service->restore(
            $request->user()->id,
            (int) $id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data' => new ObjectiveResource($objective),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
