<?php

namespace App\Http\Controllers\Api\V1\MealTypes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MealTypes\StoreMealTypeRequest;
use App\Http\Requests\Api\V1\MealTypes\UpdateMealTypeRequest;
use App\Http\Resources\Api\V1\MealTypes\MealTypeResource;
use App\Services\MealTypes\MealTypeService;
use Illuminate\Http\Request;

class MealTypeAdminController extends Controller
{
    private MealTypeService $service;

    public function __construct(MealTypeService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->service->list($request->query());

        return response()->json([
            'data'     => MealTypeResource::collection($paginator->items()),
            'meta'     => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
            'links'    => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function show(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        return response()->json([
            'data'     => new MealTypeResource($this->service->show((int) $id)),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(StoreMealTypeRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $mt      = $this->service->create(
            $request->user(),
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new MealTypeResource($mt),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateMealTypeRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $mt      = $this->service->update(
            $request->user(),
            (int) $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new MealTypeResource($mt),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $mt      = $this->service->destroy(
            $request->user(),
            (int) $id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new MealTypeResource($mt),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function restore(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $mt      = $this->service->restore(
            $request->user(),
            (int) $id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new MealTypeResource($mt),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
