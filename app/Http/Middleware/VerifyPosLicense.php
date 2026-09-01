<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VerifyPosLicense
{
    /**
     * Verify the business has an active subscription with Flutter POS module enabled.
     *
     * License check flow:
     * 1. Get the user's business_id
     * 2. Find the active subscription for this business
     * 3. Check if the package has flutter_pos = 1
     * 4. If not, return 403 with upgrade message
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $this->forbidden('Authentication required.');
        }

        $business_id = $user->business_id;

        if (!$business_id) {
            return $this->forbidden('No business associated with this account.');
        }

        // Check for active subscription
        try {
            $subscription = \Modules\Superadmin\Entities\Subscription::active_subscription($business_id);

            if (!$subscription) {
                return $this->forbidden(
                    'No active subscription. Please subscribe to use VendifyPOS.',
                    'NO_SUBSCRIPTION'
                );
            }

            // Check if the package includes Flutter POS
            $package = $subscription->package;

            if (!$package) {
                return $this->forbidden(
                    'Invalid subscription package. Please contact support.',
                    'INVALID_PACKAGE'
                );
            }

            if (!$package->flutter_pos) {
                return $this->forbidden(
                    'VendifyPOS module is not included in your current plan. ' .
                    'Please upgrade to a plan that includes POS App access.',
                    'POS_NOT_INCLUDED'
                );
            }

            // Check if subscription hasn't expired
            if ($subscription->end_date && \Carbon\Carbon::parse($subscription->end_date)->isPast()) {
                return $this->forbidden(
                    'Your subscription has expired. Please renew to continue using VendifyPOS.',
                    'SUBSCRIPTION_EXPIRED'
                );
            }

        } catch (\Exception $e) {
            // If Superadmin module isn't installed, allow access (development mode)
            if (str_contains($e->getMessage(), 'Class not found') ||
                str_contains($e->getMessage(), 'does not exist')) {
                \Log::warning('Superadmin module not found. POS license check bypassed for business: ' . $business_id);
                return $next($request);
            }

            \Log::error('POS License check error: ' . $e->getMessage());
            return $this->forbidden('Unable to verify license. Please contact support.', 'LICENSE_CHECK_ERROR');
        }

        // Attach license info to request for downstream use
        $request->attributes->set('pos_license', [
            'subscription_id' => $subscription->id,
            'package_id' => $package->id,
            'package_name' => $package->name,
            'end_date' => $subscription->end_date,
        ]);

        return $next($request);
    }

    /**
     * Return a JSON forbidden response
     */
    private function forbidden(string $message, string $code = 'FORBIDDEN'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => $code,
            'upgrade_url' => '/subscription/upgrade',
        ], 403);
    }
}
