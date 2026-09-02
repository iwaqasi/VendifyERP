<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifyPosLicense
{
    /**
     * Verify the business has an active subscription with Flutter POS module enabled.
     *
     * License check flow (FAIL-CLOSED):
     * 1. Get the user's business_id
     * 2. Find the active subscription for this business
     * 3. Check the package has flutter_pos = 1 and the subscription hasn't expired
     * 4. Any unexpected error DENIES access, except when an explicit dev flag is
     *    set AND the application is not running in production.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return $this->forbidden('Authentication required.');
        }

        $business_id = $user->business_id;

        if (! $business_id) {
            return $this->forbidden('No business associated with this account.', 'NO_BUSINESS');
        }

        // --------------------------------------------------------------------
        // Explicit dev bypass — ONLY outside production and only when enabled.
        // Never triggered by exceptions, missing classes, or DB failures.
        // --------------------------------------------------------------------
        if (config('app.env') !== 'production' && config('pos.dev_license_bypass')) {
            \Log::warning('POS license check bypassed via dev flag for business: ' . $business_id);

            return $next($request);
        }

        try {
            $subscription = \Modules\Superadmin\Entities\Subscription::active_subscription($business_id);

            if (! $subscription) {
                return $this->forbidden(
                    'No active subscription. Please subscribe to use VendifyPOS.',
                    'NO_SUBSCRIPTION'
                );
            }

            $package = $subscription->package;

            if (! $package) {
                return $this->forbidden(
                    'Invalid subscription package. Please contact support.',
                    'INVALID_PACKAGE'
                );
            }

            if (! $package->flutter_pos) {
                return $this->forbidden(
                    'VendifyPOS module is not included in your current plan. Please upgrade to a plan that includes POS App access.',
                    'POS_NOT_INCLUDED'
                );
            }

            if ($subscription->end_date && Carbon::parse($subscription->end_date)->isPast()) {
                return $this->forbidden(
                    'Your subscription has expired. Please renew to continue using VendifyPOS.',
                    'SUBSCRIPTION_EXPIRED'
                );
            }
        } catch (\Exception $e) {
            // Fail-closed: log and deny. A broken Superadmin module or DB issue
            // must NEVER accidentally grant premium access.
            \Log::error('POS License check error: business=' . $business_id . ' | ' . $e->getMessage());

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
