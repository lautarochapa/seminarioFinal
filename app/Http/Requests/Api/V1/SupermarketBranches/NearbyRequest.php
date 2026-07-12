<?php

namespace App\Http\Requests\Api\V1\SupermarketBranches;

use Illuminate\Foundation\Http\FormRequest;

class NearbyRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'lat'    => ['required', 'numeric', 'between:-90,90'],
            'lng'    => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['required', 'numeric', 'min:0.1', 'max:500'],
        ];
    }
}
