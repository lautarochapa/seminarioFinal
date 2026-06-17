<?php

namespace App\Services\RecipeImportText;

use App\AuditLog;
use App\Exceptions\RecipeImportUrl\RecipeImportUrlException;
use App\ImportedRecipeCandidate;
use App\Repositories\RecipeImportUrl\RecipeImportUrlRepository;
use App\User;

class RecipeImportTextService
{
    private RecipeImportUrlRepository $repo;
    private RecipeTextParser           $parser;

    public function __construct(RecipeImportUrlRepository $repo, RecipeTextParser $parser)
    {
        $this->repo   = $repo;
        $this->parser = $parser;
    }

    public function import(User $user, string $text, string $ip, string $userAgent): ImportedRecipeCandidate
    {
        if (!$user->hasPermission('recipes.manage') && !$user->hasRole('super_admin') && !$user->hasRole('recipe_admin')) {
            throw RecipeImportUrlException::forbidden();
        }

        $normalized = $this->normalizeText($text);
        $hash       = 'text-import:' . md5($normalized);

        if ($this->repo->findByUrl($hash)) {
            throw RecipeImportUrlException::duplicateUrl();
        }

        $parsed = $this->parser->parse($normalized);

        if (!$parsed) {
            throw RecipeImportUrlException::parseFailed();
        }

        $candidate = $this->repo->createCandidate([
            'source_url'           => $hash,
            'source_site'          => 'text-import',
            'raw_title'            => $parsed['title'],
            'raw_description'      => $parsed['description'],
            'raw_ingredients_json' => !empty($parsed['ingredients']) ? $parsed['ingredients'] : null,
            'raw_steps_json'       => !empty($parsed['steps']) ? $parsed['steps'] : null,
            'raw_image_url'        => null,
            'parsed_recipe_json'   => [
                'name'              => $parsed['title'],
                'description'       => $parsed['description'],
                'servings'          => $parsed['servings'],
                'prep_time_minutes' => $parsed['prep_minutes'],
                'cook_time_minutes' => $parsed['cook_minutes'],
                'ambiguous_flags'   => $parsed['ambiguous_flags'],
                'observations'      => $parsed['observations'],
            ],
            'status' => 'parsed',
        ]);

        AuditLog::create([
            'user_id'    => $user->id,
            'action'     => 'recipe_import_text_created',
            'entity_name'=> 'imported_recipe_candidates',
            'entity_id'  => $candidate->id,
            'old_values' => null,
            'new_values' => [
                'title'          => $parsed['title'],
                'ambiguous_flags'=> $parsed['ambiguous_flags'],
            ],
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return $candidate->fresh();
    }

    private function normalizeText(string $text): string
    {
        $text = preg_replace('/\r\n|\r/', "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }
}
