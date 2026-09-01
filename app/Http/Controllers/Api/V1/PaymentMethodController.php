<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends BaseApiController
{
    /**
     * Get payment methods for the business
     * 
     * @queryParam business_id int required Business ID. Example: 3
     */
    public function index(Request $request): JsonResponse
    {
        $businessId = $request->user()->business_id ?? $request->input('business_id', 1);

        $methods = \DB::table('payment_methods')
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $methods,
        ]);
    }
}
