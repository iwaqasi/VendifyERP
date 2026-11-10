<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'customer_name'            => 'required|string|max:255',
            'customer_phone'           => 'nullable|string|max:30',
            'customer_email'           => 'nullable|email|max:100',
            'service_name'             => 'required|string|max:255',
            'service_price'            => 'nullable|numeric|min:0',
            'appointment_start'        => 'required|date',
            'service_duration_minutes' => 'nullable|integer|min:1',
            'staff_id'                 => 'nullable|integer',
            'contact_id'               => 'nullable|integer',
            'location_id'              => 'nullable|integer',
            'notes'                    => 'nullable|string|max:1000',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Appointment validation error.',
            'errors'  => $validator->errors(),
        ], 422));
    }
}

