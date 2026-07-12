<?php

namespace App\Services\Professional;

use App\AuditLog;
use App\Exceptions\Professional\ProfessionalException;
use App\ProfessionalUserLink;
use App\Repositories\Professional\ProfessionalLinkRepository;
use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProfessionalLinkService
{
    private $linkRepo;

    public function __construct(ProfessionalLinkRepository $linkRepo)
    {
        $this->linkRepo = $linkRepo;
    }

    public function list(int $actorId): Collection
    {
        return $this->linkRepo->listForUser($actorId);
    }

    public function create(int $actorId, array $data, string $ip, string $ua): ProfessionalUserLink
    {
        $professionalId = (int) $data['professional_user_id'];

        if ($actorId === $professionalId) {
            throw new ProfessionalException('PROFESSIONAL_SELF_LINK', 'No puedes vincularte a ti mismo.', 422);
        }

        $professional = User::find($professionalId);
        if (!$professional || !$professional->hasRole('dietologist')) {
            throw new ProfessionalException('PROFESSIONAL_INVALID_ROLE', 'El usuario indicado no es un profesional habilitado.', 422);
        }

        $existing = $this->linkRepo->findActiveLinkForPair($actorId, $professionalId);
        if ($existing) {
            throw new ProfessionalException('PROFESSIONAL_DUPLICATE_LINK', 'Ya existe un vínculo activo con este profesional.', 409);
        }

        $payload = [
            'user_id'              => $actorId,
            'professional_user_id' => $professionalId,
            'can_view_profile'     => isset($data['can_view_profile'])    ? (bool) $data['can_view_profile']    : false,
            'can_view_stock'       => isset($data['can_view_stock'])       ? (bool) $data['can_view_stock']       : false,
            'can_view_meal_plans'  => isset($data['can_view_meal_plans'])  ? (bool) $data['can_view_meal_plans']  : false,
            'can_edit_meal_plans'  => isset($data['can_edit_meal_plans'])  ? (bool) $data['can_edit_meal_plans']  : false,
            'can_view_reports'     => isset($data['can_view_reports'])     ? (bool) $data['can_view_reports']     : false,
            'status'               => 'active',
            'granted_at'           => now(),
        ];

        $link = DB::transaction(function () use ($payload, $actorId, $professionalId, $ip, $ua) {
            $link = $this->linkRepo->create($payload);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'professional-link.created',
                'entity_name' => 'professional_user_links',
                'entity_id'   => (string) $link->id,
                'old_values'  => null,
                'new_values'  => ['professional_user_id' => $professionalId],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $link;
        });

        return $link;
    }

    public function update(int $id, int $actorId, array $data, string $ip, string $ua): ProfessionalUserLink
    {
        $link = $this->linkRepo->findByIdForUser($id, $actorId);

        if (!$link || $link->status !== 'active') {
            throw new ProfessionalException('PROFESSIONAL_LINK_NOT_FOUND', 'El vínculo no existe o no está activo.', 404);
        }

        $allowedKeys = ['can_view_profile', 'can_view_stock', 'can_view_meal_plans', 'can_edit_meal_plans', 'can_view_reports'];
        $changes = [];

        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $data) && (bool) $data[$key] !== (bool) $link->{$key}) {
                $changes[$key] = ['old' => $link->{$key}, 'new' => (bool) $data[$key]];
            }
        }

        $updateData = array_intersect_key($data, array_flip($allowedKeys));
        $updated = $this->linkRepo->update($link, $updateData);

        if (!empty($changes)) {
            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'professional-link.updated',
                'entity_name' => 'professional_user_links',
                'entity_id'   => (string) $updated->id,
                'old_values'  => array_combine(array_keys($changes), array_column(array_values($changes), 'old')),
                'new_values'  => array_combine(array_keys($changes), array_column(array_values($changes), 'new')),
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);
        }

        return $updated;
    }

    public function revoke(int $id, int $actorId, string $ip, string $ua): ProfessionalUserLink
    {
        $link = $this->linkRepo->findByIdForUser($id, $actorId);

        if (!$link || $link->status !== 'active') {
            throw new ProfessionalException('PROFESSIONAL_LINK_NOT_FOUND', 'El vínculo no existe o no está activo.', 404);
        }

        $revoked = $this->linkRepo->revoke($link);

        AuditLog::create([
            'user_id'     => $actorId,
            'action'      => 'professional-link.revoked',
            'entity_name' => 'professional_user_links',
            'entity_id'   => (string) $revoked->id,
            'old_values'  => ['status' => 'active'],
            'new_values'  => ['status' => 'revoked'],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $revoked;
    }
}
