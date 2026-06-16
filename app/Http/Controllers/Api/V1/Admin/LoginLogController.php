<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListLoginLogsRequest;
use App\Http\Resources\Api\V1\Admin\LoginLogResource;
use App\Services\Admin\LoginLogAdminService;

class LoginLogController extends Controller
{
    private $service;

    public function __construct(LoginLogAdminService $service)
    {
        $this->service = $service;
    }

    public function index(ListLoginLogsRequest $request)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->service->list($request->validated());

        return response()->json([
            'data'     => LoginLogResource::collection($paginator),
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
}
