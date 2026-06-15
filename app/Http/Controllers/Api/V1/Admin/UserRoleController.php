<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AssignRoleRequest;
use App\Services\Admin\UserRoleService;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    private $service;

    public function __construct(UserRoleService $service)
    {
        $this->service = $service;
    }

    public function store(AssignRoleRequest $request, $userId)
    {
        $traceId = $request->attributes->get('trace_id');

        $this->service->assign(
            $request->user()->id,
            (int) $userId,
            (int) $request->input('role_id'),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => ['user_id' => (int) $userId, 'role_id' => (int) $request->input('role_id')],
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $userId, $roleId)
    {
        $traceId = $request->attributes->get('trace_id');

        $this->service->remove(
            $request->user()->id,
            (int) $userId,
            (int) $roleId,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->noContent()->header('X-Trace-Id', $traceId);
    }
}
