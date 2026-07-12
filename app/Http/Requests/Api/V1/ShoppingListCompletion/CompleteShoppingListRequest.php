<?php

namespace App\Http\Requests\Api\V1\ShoppingListCompletion;

use Illuminate\Foundation\Http\FormRequest;

class CompleteShoppingListRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'stock_location_id' => 'nullable|integer|min:1',
            'items' => 'nullable|array',
            'items.*.shopping_list_item_id' => 'required|integer|min:1',
            'items.*.add_to_stock' => 'required|boolean',
            'items.*.product_id' => 'nullable|integer|min:1',
            'items.*.create_pending_product' => 'nullable|boolean',
            'items.*.name' => 'required_if:items.*.create_pending_product,true|nullable|string|max:180',
            'items.*.brand' => 'nullable|string|max:150',
            'items.*.presentation' => 'nullable|string|max:150',
            'items.*.quantity' => 'nullable|numeric|min:0.0001',
            'items.*.unit_id' => 'nullable|integer|min:1',
            'items.*.stock_location_id' => 'nullable|integer|min:1',
            'items.*.expiration_date' => 'nullable|date',
            'items.*.actual_price' => 'nullable|numeric|min:0',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            $seen = [];
            foreach ($items as $index => $item) {
                $id = $item['shopping_list_item_id'] ?? null;
                if ($id !== null && isset($seen[$id])) {
                    $validator->errors()->add("items.$index.shopping_list_item_id", 'El item esta duplicado en la solicitud.');
                }
                $seen[$id] = true;

                if (!empty($item['product_id']) && !empty($item['create_pending_product'])) {
                    $validator->errors()->add("items.$index.product_id", 'No se puede asociar un producto existente y crear uno pendiente al mismo tiempo.');
                }
            }
        });
    }
}
