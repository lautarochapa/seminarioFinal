<?php

namespace App\Http\Controllers\Api\V1\Brands;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Brands\StoreBrandRequest;
use App\Http\Requests\Api\V1\Brands\UpdateBrandRequest;
use App\Http\Resources\Api\V1\Brands\BrandResource;
use App\Services\Brands\BrandService;
use Illuminate\Http\Request;

class AdminBrandController extends Controller
{
    private $service;

    public function __construct(BrandService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        return $this->paginated($request, $this->service->list($request->query()));
    }

    public function show(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json(['data' => new BrandResource($this->service->show((int) $id)), 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function store(StoreBrandRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $brand = $this->service->create($request->user()->id, $request->validated(), $request->ip(), $request->userAgent() ?? '');

        return response()->json(['data' => new BrandResource($brand), 'trace_id' => $traceId], 201)
            ->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateBrandRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $brand = $this->service->update($request->user()->id, (int) $id, $request->validated(), $request->ip(), $request->userAgent() ?? '');

        return response()->json(['data' => new BrandResource($brand), 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $brand = $this->service->delete($request->user()->id, (int) $id, $request->ip(), $request->userAgent() ?? '');

        return response()->json(['data' => new BrandResource($brand), 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function restore(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $brand = $this->service->restore($request->user()->id, (int) $id, $request->ip(), $request->userAgent() ?? '');

        return response()->json(['data' => new BrandResource($brand), 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    private function paginated(Request $request, $paginator)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => BrandResource::collection($paginator),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
