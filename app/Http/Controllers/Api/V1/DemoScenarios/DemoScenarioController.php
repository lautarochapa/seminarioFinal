<?php

namespace App\Http\Controllers\Api\V1\DemoScenarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DemoScenarios\CreateDemoScenarioRequest;
use App\Http\Requests\Api\V1\DemoScenarios\UpdateDemoScenarioRequest;
use App\Services\DemoScenarios\DemoScenarioService;
use Illuminate\Http\Request;

class DemoScenarioController extends Controller
{
    private $service;

    public function __construct(DemoScenarioService $service)
    {
        $this->service = $service;
    }

    public function adminIndex(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $result  = $this->service->listAll($request->query->all());
        return response()->json(array_merge($result, ['trace_id' => $traceId]))->header('X-Trace-Id', $traceId);
    }

    public function adminShow(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->show((int) $id);
        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }

    public function store(CreateDemoScenarioRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->create($request->validated(), $request->user()->id, $request->ip(), $request->userAgent());
        return response()->json(['data' => $data, 'trace_id' => $traceId], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateDemoScenarioRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->update((int) $id, $request->validated(), $request->user()->id, $request->ip(), $request->userAgent());
        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $this->service->delete((int) $id, $request->user()->id, $request->ip(), $request->userAgent());
        return response()->json(['data' => null, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }

    public function publicIndex(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $result  = $this->service->listActive($request->query->all());
        return response()->json(array_merge($result, ['trace_id' => $traceId]))->header('X-Trace-Id', $traceId);
    }

    public function publicShow(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->showActive((int) $id);
        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }
}
