<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Superadmin\Entities\Subscription;

class LicenseController extends BaseApiController
{
    /**
     * Check the current user's subscription license.
     *
     * This endpoint is FAIL-CLOSED: if the Superadmin module is unavailable,
     * config disallows the dev bypass, or any unexpected error occurs, the
     * response reports NO license instead of silently granting access.
     *
     * GET /api/v1/license/check
     */
    public function check(Request $request): JsonResponse
    {
        $user = $request->user();
        $business_id = $user->business_id;

        if (empty($business_id)) {
            return $this->licenseDenied('No business is associated with this account.', 'NO_BUSINESS');
        }

        try {
            $subscription = Subscription::active_subscription($business_id);

            if (! $subscription || ! $subscription->package) {
                return $this->licenseDenied('No active subscription.', 'NO_SUBSCRIPTION');
            }

            $package = $subscription->package;

            return response()->json([
                'success' => true,
                'message' => $package->flutter_pos
                    ? 'POS module is active'
                    : 'POS module is not included in your plan',
                'data' => [
                    'has_pos_license' => (bool) $package->flutter_pos,
                    'has_cms_license' => (bool) $package->flutter_cms,
                    'package_name' => $package->name,
                    'subscription_end_date' => $subscription->end_date,
                ],
            ]);
        } catch (\Exception $e) {
            // Fail-closed: only allow a bypass in non-production environments
            // and ONLY when explicitly enabled via config.
            if (config('app.env') !== 'production' && config('pos.dev_license_bypass')) {
                \Log::warning('License check bypassed via dev flag for business: ' . $business_id);

                return response()->json([
                    'success' => true,
                    'message' => 'License check bypassed (development mode)',
                    'data' => [
                        'has_pos_license' => true,
                        'has_cms_license' => true,
                        'dev_bypass' => true,
                    ],
                ]);
            }

            \Log::error('License check error for business: ' . $business_id . ' | ' . $e->getMessage());

            return $this->licenseDenied('Unable to verify license. Please contact support.', 'LICENSE_CHECK_ERROR', 500);
        }
    }

    /**
     * Build a consistent license-denied JSON response.
     */
    private function licenseDenied(string $message, string $code, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => [
                'has_pos_license' => false,
                'has_cms_license' => false,
                'error_code' => $code,
                'upgrade_url' => '/subscription/upgrade',
            ],
        ], $status);
    }
}