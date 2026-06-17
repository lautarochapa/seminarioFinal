<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Notifications\UpdateNotificationPreferencesRequest;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private $service;

    public function __construct(NotificationService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->service->list(
            $request->user()->id,
            $request->only(['type', 'status', 'channel', 'per_page', 'page'])
        );

        return response()->json([
            'data'     => $paginator->items(),
            'meta'     => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
            'links'    => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function unreadCount(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $count   = $this->service->unreadCount($request->user()->id);

        return response()->json(['data' => ['unread_count' => $count], 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function markAsRead(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->markAsRead(
            $request->user()->id,
            (int) $id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json(['data' => $data, 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function readAll(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $count   = $this->service->markAllAsRead(
            $request->user()->id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json(['data' => ['marked_count' => $count], 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function getPreferences(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->getPreferences($request->user()->id);

        return response()->json(['data' => $data, 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function updatePreferences(UpdateNotificationPreferencesRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->updatePreferences(
            $request->user()->id,
            $request->input('preferences'),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json(['data' => $data, 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }
}
