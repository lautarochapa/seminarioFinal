<?php

namespace App\Services\Ai;

interface AiSuggestionProviderInterface
{
    public function suggest(array $context): array;
}
