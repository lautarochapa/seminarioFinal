<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AssignPermissionRequest;
use App\Services\Admin\RolePermissionService;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    private $service;

    public function __construct(RolePermissionService $service)
    {
        $this->service = $service;
    }

    public function store(AssignPermissionRequest $request, $roleId)
    {
        $traceId = $request->attributes->get('trace_id');

        $this->service->assign(
            $request->user()->id,
            (int) $roleId,
            (int) $request->input('permission_id'),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => ['role_id' => (int) $roleId, 'permission_id' => (int) $request->input('permission_id')],
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $roleId, $permissionId)
    {
        $traceId = $request->attributes->get('trace_id');

        $this->service->remove(
            $request->user()->id,
            (int) $roleId,
            (int) $permissionId,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->noContent()->header('X-Trace-Id', $traceId);
    }
}
