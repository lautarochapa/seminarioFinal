<?php

namespace App\Http\Requests\Api\V1\RecipeSearch;

use Illuminate\Foundation\Http\FormRequest;

class RecipeSearchRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'search'               => 'sometimes|string|max:200',
            'category_id'          => 'sometimes|integer|min:1',
            'tag_id'               => 'sometimes|integer|min:1',
            'tag_code'             => 'sometimes|string|max:100',
            'ingredient_id'        => 'sometimes|integer|min:1',
            'exclude_ingredient_id'=> 'sometimes|integer|min:1',
            'difficulty'           => 'sometimes|string|in:easy,medium,hard',
            'max_prep_time'        => 'sometimes|integer|min:1',
            'max_cook_time'        => 'sometimes|integer|min:1',
            'max_total_time'       => 'sometimes|integer|min:1',
            'source_type'          => 'sometimes|string|in:user,official,imported,scraped',
            'is_official'          => 'sometimes|boolean',
            'sort'                 => 'sometimes|string|in:name,created_at,difficulty,prep_time_minutes,cook_time_minutes',
            'order'                => 'sometimes|string|in:asc,desc',
            'page'                 => 'sometimes|integer|min:1',
            'per_page'             => 'sometimes|integer|min:1|max:100',
        ];
    }
}
