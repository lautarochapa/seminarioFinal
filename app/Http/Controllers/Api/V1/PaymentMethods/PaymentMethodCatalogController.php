<?php

namespace App\Http\Controllers\Api\V1\PaymentMethods;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PaymentMethods\PaymentMethodResource;
use App\Services\PaymentMethods\PaymentMethodService;
use Illuminate\Http\Request;

class PaymentMethodCatalogController extends Controller
{
    private $service;

    public function __construct(PaymentMethodService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $methods = $this->service->catalog($request->query());

        return response()->json([
            'data'     => PaymentMethodResource::collection($methods),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
