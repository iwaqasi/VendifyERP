<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'location_id'             => 'required|integer',
            'contact_id'              => 'nullable|integer',
            'local_transaction_id'    => 'nullable|string|max:100',
            'products'                => 'required|array|min:1',
            'products.*.product_id'   => 'required|integer',
            'products.*.variation_id' => 'required|integer',
            'products.*.quantity'     => 'required|numeric|min:0.01',
            'products.*.unit_price'   => 'nullable|numeric|min:0',
            'products.*.discount'     => 'nullable|numeric|min:0',
            'products.*.tax_id'       => 'nullable|integer',
            'products.*.service_staff_id' => 'nullable|integer',
            'payments'                => 'nullable|array',
            'payments.*.amount'       => 'required_with:payments|numeric|min:0',
            'payments.*.method'       => 'required_with:payments|string|in:cash,card,knet,mada,apple_pay,stc_pay,cheque,bank_transfer,other,custom_pay_1,custom_pay_2,custom_pay_3',
            'discount_amount'         => 'nullable|numeric|min:0',
            'discount_type'           => 'nullable|string|in:fixed,percentage',
            'shipping_charges'        => 'nullable|numeric|min:0',
            'tip_amount'              => 'nullable|numeric|min:0',
            'tip_staff_id'            => 'nullable|integer',
            'tip_payment_method'      => 'nullable|string',
            'appointment_id'          => 'nullable|integer',
            'res_waiter_id'           => 'nullable|integer',
        ];
    }

    /**
     * Handle failed validation to return consistent JSON
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation error',
            'errors'  => $validator->errors(),
        ], 422));
    }
}

