<?php

namespace App\Http\Controllers\Api\V1\UserSupplements;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserSupplements\CreateUserSupplementRequest;
use App\Http\Requests\Api\V1\UserSupplements\UpdateUserSupplementRequest;
use App\Services\UserSupplements\UserSupplementService;
use Illuminate\Http\Request;

class UserSupplementController extends Controller
{
    private $service;

    public function __construct(UserSupplementService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->service->list(
            $request->user()->id,
            $request->only(['per_page', 'page'])
        );

        return response()->json([
            'data'     => $paginator->items(),
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

    public function store(CreateUserSupplementRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->create(
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json(['data' => $data, 'trace_id' => $traceId], 201)
            ->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateUserSupplementRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->update(
            $request->user()->id,
            (int) $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json(['data' => $data, 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $this->service->delete(
            $request->user()->id,
            (int) $id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json(null, 204)->header('X-Trace-Id', $traceId);
    }
}
