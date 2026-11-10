<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'transaction_id' => 'required|integer|exists:transactions,id',
            'gateway'        => 'nullable|string|in:myfatoorah,tap',
            'method'         => 'nullable|string|in:knet,mada,apple_pay,stc_pay,card,all',
            'amount'         => 'nullable|numeric|min:0.001',
            'language'       => 'nullable|string|in:en,ar',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Invalid payment initiation parameters.',
            'errors'  => $validator->errors(),
        ], 422));
    }
}

