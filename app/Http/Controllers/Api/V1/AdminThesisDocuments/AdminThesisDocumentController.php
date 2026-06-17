<?php

namespace App\Http\Controllers\Api\V1\AdminThesisDocuments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ThesisDocuments\CreateThesisDocumentRequest;
use App\Http\Requests\Api\V1\ThesisDocuments\CreateThesisSectionRequest;
use App\Http\Requests\Api\V1\ThesisDocuments\UpdateThesisDocumentRequest;
use App\Http\Requests\Api\V1\ThesisDocuments\UpdateThesisSectionRequest;
use App\Services\ThesisDocuments\ThesisDocumentAdminService;
use Illuminate\Http\Request;

class AdminThesisDocumentController extends Controller
{
    private $service;

    public function __construct(ThesisDocumentAdminService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $result  = $this->service->list($request->query->all());
        return response()->json(array_merge($result, ['trace_id' => $traceId]))->header('X-Trace-Id', $traceId);
    }

    public function show(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->show((int) $id);
        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }

    public function store(CreateThesisDocumentRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->create($request->validated(), $request->user()->id, $request->ip(), $request->userAgent());
        return response()->json(['data' => $data, 'trace_id' => $traceId], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateThesisDocumentRequest $request, $id)
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

    public function storeSection(CreateThesisSectionRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->createSection((int) $id, $request->validated(), $request->user()->id, $request->ip(), $request->userAgent());
        return response()->json(['data' => $data, 'trace_id' => $traceId], 201)->header('X-Trace-Id', $traceId);
    }

    public function updateSection(UpdateThesisSectionRequest $request, $id, $sectionId)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->updateSection((int) $id, (int) $sectionId, $request->validated(), $request->user()->id, $request->ip(), $request->userAgent());
        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }

    public function destroySection(Request $request, $id, $sectionId)
    {
        $traceId = $request->attributes->get('trace_id');
        $this->service->deleteSection((int) $id, (int) $sectionId, $request->user()->id, $request->ip(), $request->userAgent());
        return response()->json(['data' => null, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }

    public function indexVersions(Request $request, $id)
    {
        $traceId  = $request->attributes->get('trace_id');
        $versions = $this->service->listVersions((int) $id);
        return response()->json(['data' => $versions, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }

    public function storeVersion(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->createVersion((int) $id, $request->user()->id, $request->ip(), $request->userAgent());
        return response()->json(['data' => $data, 'trace_id' => $traceId], 201)->header('X-Trace-Id', $traceId);
    }
}
