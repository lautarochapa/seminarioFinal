<?php

namespace App\Http\Controllers\Api\V1\ThesisDocuments;

use App\Http\Controllers\Controller;
use App\Services\ThesisDocuments\ThesisDocumentService;
use Illuminate\Http\Request;

class ThesisDocumentController extends Controller
{
    private $service;

    public function __construct(ThesisDocumentService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $result  = $this->service->list($request->query->all());

        return response()->json(array_merge($result, ['trace_id' => $traceId]))
            ->header('X-Trace-Id', $traceId);
    }

    public function show(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->show((int) $id);

        return response()->json(['data' => $data, 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function sections(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->sections((int) $id);

        return response()->json(['data' => $data, 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }
}
