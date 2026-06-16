<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListPermissionsRequest;
use App\Http\Resources\Api\V1\Admin\PermissionResource;
use App\Repositories\Admin\PermissionRepository;

class PermissionAdminController extends Controller
{
    private $repo;

    public function __construct(PermissionRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(ListPermissionsRequest $request)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->repo->paginate($request->validated());

        return response()->json([
            'data'     => PermissionResource::collection($paginator),
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
