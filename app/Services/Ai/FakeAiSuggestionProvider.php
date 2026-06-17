<?php

namespace App\Services\Ai;

class FakeAiSuggestionProvider implements AiSuggestionProviderInterface
{
    public function suggest(array $context): array
    {
        $text = trim((string) ($context['context'] ?? 'contexto'));

        return [
            'provider' => 'fake',
            'suggestion' => 'Sugerencia simulada para: '.$text,
            'confidence' => 1.0,
        ];
    }
}
