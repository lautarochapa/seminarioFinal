<?php

namespace App\Services\Consents;

use App\AuditLog;
use App\Repositories\Consents\UserConsentRepository;
use Illuminate\Support\Facades\DB;

class UserConsentService
{
    const CONSENT_TYPES = [
        'health_data_consent',
        'privacy_consent',
        'professional_access_consent',
        'medical_disclaimer_accepted',
        'terms_accepted',
    ];

    private $repository;

    public function __construct(UserConsentRepository $repository)
    {
        $this->repository = $repository;
    }

    public function show(int $userId): array
    {
        return $this->buildState($userId);
    }

    public function update(int $userId, array $data, string $ip, string $userAgent): array
    {
        return DB::transaction(function () use ($userId, $data, $ip, $userAgent) {
            $current = $this->repository->latestByUser($userId);
            $before = [];
            $after = [];
            $changed = false;

            foreach (self::CONSENT_TYPES as $type) {
                if (!array_key_exists($type, $data)) {
                    continue;
                }

                $currentConsent = $current->get($type);
                $currentAccepted = $currentConsent ? (bool) $currentConsent->accepted : false;
                $newAccepted = (bool) $data[$type];

                if ($currentAccepted === $newAccepted) {
                    continue;
                }

                $before[$type] = $currentAccepted;
                $after[$type] = $newAccepted;
                $changed = true;

                $now = now();
                $this->repository->create([
                    'user_id' => $userId,
                    'consent_type' => $type,
                    'accepted' => $newAccepted,
                    'accepted_at' => $newAccepted ? $now : ($currentConsent ? $currentConsent->accepted_at : null),
                    'revoked_at' => $newAccepted ? null : $now,
                    'ip_address' => $ip,
                    'user_agent' => $userAgent,
                ]);
            }

            if ($changed) {
                AuditLog::create([
                    'user_id' => $userId,
                    'action' => 'user-consents.updated',
                    'entity_name' => 'user_consents',
                    'entity_id' => (string) $userId,
                    'old_values' => $before,
                    'new_values' => $after,
                    'ip_address' => $ip,
                    'user_agent' => $userAgent,
                ]);
            }

            return $this->buildState($userId);
        });
    }

    private function buildState(int $userId): array
    {
        $latest = $this->repository->latestByUser($userId);
        $consents = [];

        foreach (self::CONSENT_TYPES as $type) {
            $consent = $latest->get($type);
            $consents[$type] = [
                'accepted' => $consent ? (bool) $consent->accepted : false,
                'accepted_at' => $consent && $consent->accepted_at ? $consent->accepted_at->toIso8601String() : null,
                'revoked_at' => $consent && $consent->revoked_at ? $consent->revoked_at->toIso8601String() : null,
                'required' => false,
                'modifiable' => true,
            ];
        }

        return [
            'consents' => $consents,
            'required' => [],
        ];
    }
}
