<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListAuditLogsRequest;
use App\Http\Resources\Api\V1\Admin\AuditLogResource;
use App\Services\Admin\AuditAdminService;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    private $auditService;

    public function __construct(AuditAdminService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function index(ListAuditLogsRequest $request)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->auditService->list($request->validated());

        return $this->paginatedResponse($paginator, $traceId);
    }

    public function forResource(Request $request, $resource, $id)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->auditService->forResource($resource, (int) $id, $request->all());

        return $this->paginatedResponse($paginator, $traceId);
    }

    private function paginatedResponse($paginator, $traceId)
    {
        return response()->json([
            'data'     => AuditLogResource::collection($paginator),
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
