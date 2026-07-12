<?php

namespace App\Services\PriceRefreshRequests;

use App\AuditLog;
use App\Exceptions\Ingredients\IngredientException;
use App\Jobs\RunScrapingJob;
use App\PriceRefreshRequest;
use App\Repositories\PriceRefreshRequests\PriceRefreshRequestRepository;
use App\Repositories\Scraping\ScrapingRepository;
use Illuminate\Support\Facades\DB;

class PriceRefreshRequestService
{
    private $requests;
    private $scraping;

    public function __construct(PriceRefreshRequestRepository $requests, ScrapingRepository $scraping)
    {
        $this->requests = $requests;
        $this->scraping = $scraping;
    }

    public function requestRefresh($user, int $productId, array $data, $ip, $userAgent): PriceRefreshRequest
    {
        $product = $this->requests->findActiveProductOrFail($productId);

        if ($this->requests->hasPendingForUserAndProduct($user->id, $product->id)) {
            throw new IngredientException(
                'PRICE_REFRESH_REQUEST_ALREADY_PENDING',
                'Ya existe una solicitud pendiente para este producto.',
                409
            );
        }

        return DB::transaction(function () use ($user, $product, $data, $ip, $userAgent) {
            $request = $this->requests->create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'supermarket_chain_id' => $data['supermarket_chain_id'] ?? null,
                'supermarket_branch_id' => $data['supermarket_branch_id'] ?? null,
                'reason' => $data['reason'] ?? null,
                'status' => 'pending',
                'requested_at' => now(),
            ]);

            $this->audit($user->id, 'price-refresh-request.created', $request->id, null, $this->payload($request), $ip, $userAgent);

            return $request->fresh(['user', 'product']);
        });
    }

    public function listForUser($user, array $filters)
    {
        return $this->requests->paginateForUser($user->id, $filters);
    }

    public function listAdmin(array $filters)
    {
        return $this->requests->paginateAdmin($filters);
    }

    public function process($actor, int $id, $ip, $userAgent): PriceRefreshRequest
    {
        $request = $this->requests->findOrFail($id);

        if ($request->status !== 'pending') {
            throw new IngredientException(
                'PRICE_REFRESH_REQUEST_ALREADY_PROCESSED',
                'La solicitud ya fue procesada.',
                409
            );
        }

        return DB::transaction(function () use ($actor, $request, $ip, $userAgent) {
            $locked = PriceRefreshRequest::where('id', $request->id)->lockForUpdate()->first();

            if (! $locked) {
                throw new IngredientException('PRICE_REFRESH_REQUEST_NOT_FOUND', 'Solicitud de actualizacion de precio no encontrada.', 404);
            }

            if ($locked->status !== 'pending') {
                throw new IngredientException(
                    'PRICE_REFRESH_REQUEST_ALREADY_PROCESSED',
                    'La solicitud ya fue procesada.',
                    409
                );
            }

            $old = $this->payload($locked);
            $source = $this->requests->firstActiveSource();
            $job = null;
            $status = 'failed';

            if ($source && ! $this->scraping->hasActiveJobForSource($source->id)) {
                $job = $this->scraping->createJob([
                    'source_id' => $source->id,
                    'job_type' => 'price_refresh',
                    'requested_by' => $actor->id,
                    'status' => 'pending',
                    'parameters_json' => array_filter([
                        'price_refresh_request_id' => $locked->id,
                        'product_id' => $locked->product_id,
                        'supermarket_chain_id' => $locked->supermarket_chain_id,
                        'supermarket_branch_id' => $locked->supermarket_branch_id,
                        'max_pages' => 1,
                    ], function ($value) {
                        return $value !== null;
                    }),
                ]);

                RunScrapingJob::dispatch($job->id);
                $status = 'queued';
            }

            $updated = $this->requests->update($locked, [
                'status' => $status,
                'processed_at' => now(),
            ]);

            $new = $this->payload($updated);
            if ($job) {
                $new['scraping_job_id'] = $job->id;
            } else {
                $new['process_error'] = 'No hay una fuente activa disponible o ya existe un job activo.';
            }

            $this->audit($actor->id, 'price-refresh-request.processed', $updated->id, $old, $new, $ip, $userAgent);

            return $updated;
        });
    }

    private function payload(PriceRefreshRequest $request): array
    {
        return [
            'user_id' => $request->user_id,
            'product_id' => $request->product_id,
            'supermarket_chain_id' => $request->supermarket_chain_id,
            'supermarket_branch_id' => $request->supermarket_branch_id,
            'reason' => $request->reason,
            'status' => $request->status,
            'requested_at' => $request->requested_at ? (string) $request->requested_at : null,
            'processed_at' => $request->processed_at ? (string) $request->processed_at : null,
        ];
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $userAgent): void
    {
        AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'entity_name' => 'price_refresh_requests',
            'entity_id' => (string) $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
