<?php

namespace App\Http\Controllers\Api\V1\UserHome;

use App\Http\Controllers\Controller;
use App\Services\UserHome\UserHomeSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserHomeController extends Controller
{
    private $service;

    public function __construct(UserHomeSummaryService $service)
    {
        $this->service = $service;
    }

    public function show(Request $request): JsonResponse
    {
        $groupId = $request->query('family_group_id');

        return response()->json([
            'data' => $this->service->summary($request->user(), $groupId ? (int) $groupId : null),
        ]);
    }
}
