<?php

namespace App\Http\Controllers\Api\V1\PaymentMethods;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PaymentMethods\StoreUserPaymentMethodRequest;
use App\Http\Resources\Api\V1\PaymentMethods\UserPaymentMethodResource;
use App\Services\PaymentMethods\PaymentMethodService;
use Illuminate\Http\Request;

class UserPaymentMethodController extends Controller
{
    private $service;

    public function __construct(PaymentMethodService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $methods = $this->service->userMethods($request->user()->id);

        return response()->json([
            'data'     => UserPaymentMethodResource::collection($methods),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(StoreUserPaymentMethodRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $upm     = $this->service->addUserMethod(
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new UserPaymentMethodResource($upm),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $upm     = $this->service->removeUserMethod(
            $request->user()->id,
            (int) $id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new UserPaymentMethodResource($upm),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
