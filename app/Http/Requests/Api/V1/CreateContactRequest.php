<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name'         => 'required|string|max:255',
            'type'         => 'required|string|in:customer,supplier,both',
            'mobile'       => 'nullable|string|max:30',
            'email'        => 'nullable|email|max:100',
            'city'         => 'nullable|string|max:100',
            'state'        => 'nullable|string|max:100',
            'country'      => 'nullable|string|max:100',
            'tax_number'   => 'nullable|string|max:50',
            'credit_limit' => 'nullable|numeric|min:0',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Contact validation failed.',
            'errors'  => $validator->errors(),
        ], 422));
    }
}

