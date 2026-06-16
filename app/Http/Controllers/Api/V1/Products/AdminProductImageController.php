<?php

namespace App\Http\Controllers\Api\V1\Products;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Products\StoreProductImageRequest;
use App\Http\Resources\Api\V1\Products\ProductImageResource;
use App\Services\Products\ProductImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminProductImageController extends Controller
{
    private $service;

    public function __construct(ProductImageService $service)
    {
        $this->service = $service;
    }

    public function store(StoreProductImageRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $ext = strtolower($file->getClientOriginalExtension());
            $imageUrl = $file->storeAs('products/images', Str::uuid() . '.' . $ext, 'public');
        } else {
            $imageUrl = $request->input('url');
        }

        $validated = $request->validated();
        $image = $this->service->addImage(
            $request->user()->id,
            (int) $id,
            [
                'image_url' => $imageUrl,
                'source' => $validated['source'] ?? null,
                'is_primary' => (bool) ($validated['is_primary'] ?? false),
            ],
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data' => new ProductImageResource($image),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id, $imageId)
    {
        $traceId = $request->attributes->get('trace_id');

        $this->service->removeImage(
            $request->user()->id,
            (int) $id,
            (int) $imageId,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->noContent()->header('X-Trace-Id', $traceId);
    }
}
