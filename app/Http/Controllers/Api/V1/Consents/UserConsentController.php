<?php

namespace App\Http\Controllers\Api\V1\Consents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Consents\UpdateConsentsRequest;
use App\Http\Resources\Api\V1\Consents\UserConsentStateResource;
use App\Services\Consents\UserConsentService;
use Illuminate\Http\Request;

class UserConsentController extends Controller
{
    private $service;

    public function __construct(UserConsentService $service)
    {
        $this->service = $service;
    }

    public function show(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $state = $this->service->show($request->user()->id);

        return response()->json([
            'data' => new UserConsentStateResource($state),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateConsentsRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $state = $this->service->update(
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?: ''
        );

        return response()->json([
            'data' => new UserConsentStateResource($state),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
