<?php

namespace App\Services\Ai;

use App\AuditLog;
use App\Exceptions\Ingredients\IngredientException;
use App\Services\FeatureFlags\FeatureFlagService;

class AiFoundationService
{
    const AI_FLAG_KEY = 'module.ai';

    private $flags;
    private $provider;

    public function __construct(FeatureFlagService $flags, AiSuggestionProviderInterface $provider)
    {
        $this->flags = $flags;
        $this->provider = $provider;
    }

    public function aiFlag()
    {
        return $this->flags->findOrFail(self::AI_FLAG_KEY);
    }

    public function testSuggestion($actorId, array $data, $ip, $userAgent)
    {
        if (!$this->flags->enabled(self::AI_FLAG_KEY)) {
            throw new IngredientException('AI_FEATURE_DISABLED', 'La funcionalidad de IA esta desactivada.', 503);
        }

        $result = $this->provider->suggest($data);

        AuditLog::create([
            'user_id' => $actorId,
            'action' => 'ai.test_suggestion',
            'entity_name' => 'ai_foundation',
            'entity_id' => (string) $actorId,
            'old_values' => null,
            'new_values' => [
                'provider' => $result['provider'],
                'context' => $data['context'],
            ],
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return $result;
    }
}
