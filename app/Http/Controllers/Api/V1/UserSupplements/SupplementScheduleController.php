<?php

namespace App\Http\Controllers\Api\V1\UserSupplements;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserSupplements\CreateSupplementScheduleRequest;
use App\Http\Requests\Api\V1\UserSupplements\UpdateSupplementScheduleRequest;
use App\Http\Requests\Api\V1\UserSupplements\CreateSupplementLogRequest;
use App\Services\UserSupplements\SupplementScheduleService;
use Illuminate\Http\Request;

class SupplementScheduleController extends Controller
{
    private $service;

    public function __construct(SupplementScheduleService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->listSchedules($request->user()->id, (int) $id);

        return response()->json(['data' => $data, 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function store(CreateSupplementScheduleRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->createSchedule(
            $request->user()->id,
            (int) $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json(['data' => $data, 'trace_id' => $traceId], 201)
            ->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateSupplementScheduleRequest $request, $id, $scheduleId)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->updateSchedule(
            $request->user()->id,
            (int) $id,
            (int) $scheduleId,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json(['data' => $data, 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id, $scheduleId)
    {
        $traceId = $request->attributes->get('trace_id');
        $this->service->deleteSchedule(
            $request->user()->id,
            (int) $id,
            (int) $scheduleId,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json(null, 204)->header('X-Trace-Id', $traceId);
    }

    public function storeLog(CreateSupplementLogRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->createLog(
            $request->user()->id,
            (int) $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json(['data' => $data, 'trace_id' => $traceId], 201)
            ->header('X-Trace-Id', $traceId);
    }
}
