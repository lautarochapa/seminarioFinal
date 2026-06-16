<?php

namespace App\Http\Controllers\Api\V1\Products;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Products\StoreBarcodeRequest;
use App\Services\Products\ProductBarcodeService;
use Illuminate\Http\Request;

class AdminProductBarcodeController extends Controller
{
    private $service;

    public function __construct(ProductBarcodeService $service)
    {
        $this->service = $service;
    }

    public function store(StoreBarcodeRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $barcodeRecord = $this->service->addBarcode(
            $request->user()->id,
            (int) $id,
            $request->validated()['barcode'],
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data' => [
                'id' => $barcodeRecord->id,
                'product_id' => $barcodeRecord->product_id,
                'barcode' => $barcodeRecord->barcode,
            ],
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id, $barcodeId)
    {
        $traceId = $request->attributes->get('trace_id');
        $this->service->removeBarcode(
            $request->user()->id,
            (int) $id,
            (int) $barcodeId,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->noContent()->header('X-Trace-Id', $traceId);
    }
}
