<?php

namespace App\Http\Controllers\Api\V1\ThesisDocuments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ThesisDocuments\CreateThesisCommentRequest;
use App\Services\ThesisDocuments\ThesisCommentService;
use Illuminate\Http\Request;

class ThesisCommentController extends Controller
{
    private $service;

    public function __construct(ThesisCommentService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $result  = $this->service->list((int) $id, $request->query->all());

        return response()->json(array_merge($result, ['trace_id' => $traceId]))
            ->header('X-Trace-Id', $traceId);
    }

    public function store(CreateThesisCommentRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $comment = $this->service->create(
            (int) $id,
            $request->user()->id,
            $request->input('comment'),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'data'     => $this->service->formatComment($comment),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }
}
