<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateRestaurantOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'order_type'              => 'required|string|in:dine_in,takeaway,delivery,drive_through',
            'table_id'                => 'nullable|integer',
            'contact_id'              => 'nullable|integer',
            'location_id'             => 'nullable|integer',
            'guest_count'             => 'nullable|integer|min:1',
            'items'                   => 'required|array|min:1',
            'items.*.product_id'      => 'required|integer',
            'items.*.variation_id'    => 'required|integer',
            'items.*.item_name'       => 'nullable|string|max:255',
            'items.*.quantity'        => 'required|numeric|min:0.01',
            'items.*.unit_price'      => 'nullable|numeric|min:0',
            'items.*.station'         => 'nullable|string|in:kitchen,bar,dessert,expo',
            'items.*.course_number'   => 'nullable|integer',
            'items.*.notes'           => 'nullable|string|max:500',
            'notes'                   => 'nullable|string|max:1000',
            'delivery_address'        => 'nullable|string|max:500',
            'delivery_phone'          => 'nullable|string|max:50',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Restaurant order validation error.',
            'errors'  => $validator->errors(),
        ], 422));
    }
}

