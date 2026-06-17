<?php

namespace App\Repositories\RecipeImportUrl;

use App\ImportedRecipeCandidate;

class RecipeImportUrlRepository
{
    const DUPLICATE_STATUSES = ['pending', 'parsed', 'approved'];

    public function findByUrl(string $url): ?ImportedRecipeCandidate
    {
        return ImportedRecipeCandidate::where('source_url', $url)
            ->whereIn('status', self::DUPLICATE_STATUSES)
            ->first();
    }

    public function createCandidate(array $data): ImportedRecipeCandidate
    {
        return ImportedRecipeCandidate::create($data);
    }

    public function updateCandidate(ImportedRecipeCandidate $candidate, array $data): void
    {
        $candidate->fill($data);
        $candidate->save();
    }
}
