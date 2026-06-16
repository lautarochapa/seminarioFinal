<?php

namespace App\Repositories\ProductReports;

use App\Exceptions\Ingredients\IngredientException;
use App\ProductReport;

class ProductReportRepository
{
    public function paginate(array $filters)
    {
        $query = ProductReport::with(['product', 'user', 'resolver']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('report_type', $filters['type']);
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', (int) $filters['product_id']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['created_from'])) {
            $query->whereDate('product_reports.created_at', '>=', $filters['created_from']);
        }

        if (! empty($filters['created_to'])) {
            $query->whereDate('product_reports.created_at', '<=', $filters['created_to']);
        }

        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('description', 'ILIKE', $search)
                    ->orWhere('report_type', 'ILIKE', $search);
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderBy('product_reports.created_at', 'desc')
            ->orderBy('product_reports.id', 'desc')
            ->paginate($perPage);
    }

    public function findOrFail($id)
    {
        $report = ProductReport::with(['product', 'user', 'resolver'])->find($id);

        if (! $report) {
            throw new IngredientException('PRODUCT_REPORT_NOT_FOUND', 'El reporte solicitado no existe.', 404);
        }

        return $report;
    }

    public function create(array $data)
    {
        return ProductReport::create($data);
    }

    public function resolve(ProductReport $report, int $adminId, string $status)
    {
        $report->status = $status;
        $report->resolved_by = $adminId;
        $report->resolved_at = now();
        $report->save();

        return $report->fresh(['product', 'user', 'resolver']);
    }

    public function hasPendingReport(int $userId, int $productId, string $type)
    {
        return ProductReport::where('user_id', $userId)
            ->where('product_id', $productId)
            ->where('report_type', $type)
            ->where('status', ProductReport::STATUS_OPEN)
            ->exists();
    }
}
