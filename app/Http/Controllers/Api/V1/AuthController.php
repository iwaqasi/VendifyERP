<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends BaseApiController
{
    /**
     * Login with email and password
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = \App\User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->errorResponse('The provided credentials are incorrect.', 401);
        }

        if ($user->status !== 'active') {
            return $this->errorResponse('Your account has been deactivated.', 403);
        }

        // Business is auto-detected from the user — no need to enter business_id
        return $this->createTokenResponse($user, $request);
    }

    /**
     * Login with staff PIN
     * Uses the existing service_staff_pin field on the users table
     *
     * @bodyParam pin string required 4-digit PIN. Example: 1234
     * @bodyParam business_id int required Business ID. Example: 1
     */
    public function loginByPin(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => 'required|string',
            'business_id' => 'sometimes|integer',
        ]);

        $query = \App\User::where('is_enable_service_staff_pin', 1)
            ->where('service_staff_pin', $request->pin)
            ->where('status', 'active');

        // Optional: filter by business_id if provided (for multi-business setups)
        if ($request->has('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        $user = $query->first();

        if (!$user) {
            return $this->errorResponse('Invalid PIN or no staff member found with this PIN.', 401);
        }

        return $this->createTokenResponse($user, $request);
    }

    /**
     * Create token response for both login methods
     * 
     * Location resolution priority:
     * 1. User's assigned default_location_id (cashier → specific shop)
     * 2. First active location for the business (admin fallback)
     * 
     * If user has an assigned location, POS skips the location picker.
     */
    private function createTokenResponse($user, Request $request): JsonResponse
    {
        $deviceName = $request->input('device_name', 'pos-app');
        $user->tokens()->where('name', $deviceName)->delete();

        // Wave 3: tokens expire. Previously personal access tokens never
        // expired, so a lost device kept API access forever.
        $token = $user->createToken($deviceName);
        $token->token->expires_at = now()->addDays((int) config('pos.token_access_days', 30));
        $token->token->save();

        $business = \App\Business::find($user->business_id);

        // Determine the user's active location
        // Priority: user's assigned location > first active location
        $assignedLocation = null;
        if (!empty($user->default_location_id)) {
            $assignedLocation = \App\BusinessLocation::where('id', $user->default_location_id)
                ->where('business_id', $user->business_id)
                ->where('is_active', 1)
                ->first();
        }

        // Fallback to first active location if no assignment or assigned location is inactive
        if (!$assignedLocation) {
            $assignedLocation = \App\BusinessLocation::where('business_id', $user->business_id)
                ->where('is_active', 1)
                ->first();
        }

        // Fetch locations the user has access to (filtered by permissions)
        $permittedLocations = $user->permitted_locations($user->business_id);
        $locationQuery = \App\BusinessLocation::where('business_id', $user->business_id)
            ->where('is_active', 1);

        if ($permittedLocations !== 'all') {
            $locationQuery->whereIn('id', $permittedLocations);
        }

        $locations = $locationQuery->get(['id', 'name'])->toArray();

        // has_assigned_location tells the POS to skip the location picker
        $hasAssignedLocation = !empty($user->default_location_id) && $assignedLocation !== null;

        return $this->successResponse([
            'access_token' => $token->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => optional($token->token->expires_at)->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->user_full_name ?? trim($user->first_name . ' ' . $user->last_name),
                'email' => $user->email,
                'business_id' => $user->business_id,
                'business_name' => $business->name ?? null,
                'business_slug' => $business->slug ?? null,
                'business_type' => $business->business_type ?? 'retail',
                'default_location_id' => $assignedLocation->id ?? null,
                'default_location_name' => $assignedLocation->name ?? null,
                'has_assigned_location' => $hasAssignedLocation,
                'locations' => $locations,
                'roles' => $user->getRoleNames()->toArray(),
            ],
        ], 'Login successful');
    }

    /**
     * Logout
     */
    public function logout(Request $request): JsonResponse
    {
        // revoke() keeps the row for the device list / audit trail while
        // immediately invalidating the token (delete() erased the history).
        $request->user()->token()->revoke();

        return $this->successResponse(null, 'Logged out successfully');
    }

    /**
     * List the authenticated user's active device sessions.
     * GET /api/v1/auth/devices
     */
    public function devices(Request $request): JsonResponse
    {
        $currentTokenId = $request->user()->token()->id ?? null;

        $devices = $request->user()->tokens()
            ->where('revoked', false)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'last_used_at', 'created_at', 'expires_at'])
            ->map(function ($token) use ($currentTokenId) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'last_used_at' => optional($token->last_used_at)->toIso8601String(),
                    'expires_at' => optional($token->expires_at)->toIso8601String(),
                    'created_at' => optional($token->created_at)->toIso8601String(),
                    'is_current' => $token->id === $currentTokenId,
                ];
            })
            ->values();

        return $this->successResponse($devices);
    }

    /**
     * Revoke a specific device session (remote logout of a lost device).
     * DELETE /api/v1/auth/devices/{id}
     */
    public function revokeDevice(Request $request, string $id): JsonResponse
    {
        // Validate the ID is numeric to avoid unexpected query behaviour.
        if (! ctype_digit((string) $id)) {
            return $this->errorResponse('Invalid device session ID.', 422);
        }

        $currentTokenId = $request->user()->token()->id ?? null;

        if ((string) $id === (string) $currentTokenId) {
            return $this->errorResponse('This is the current session. Use logout instead.', 422);
        }

        $token = $request->user()->tokens()->where('id', (int) $id)->first();

        if (!$token) {
            return $this->errorResponse('Device session not found.', 404);
        }

        $token->revoke();

        return $this->successResponse(null, 'Device session revoked');
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request): JsonResponse
    {
        $request->user()->token()->delete();
        $deviceName = $request->input('device_name', 'pos-app');
        $newToken = $request->user()->createToken($deviceName);

        $newToken->token->expires_at = now()->addDays((int) config('pos.token_access_days', 30));
        $newToken->token->save();

        return $this->successResponse([
            'access_token' => $newToken->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => optional($newToken->token->expires_at)->toIso8601String(),
        ], 'Token refreshed successfully');
    }

    /**
     * Get user profile
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        $business_id = $request->attributes->get('business_id') ?? $user->business_id;

        $business = \App\Business::find($business_id);
        $locations = \App\BusinessLocation::where('business_id', $business_id)
            ->where('is_active', 1)
            ->get(['id', 'name', 'business_id']);

        return $this->successResponse([
            'id' => $user->id,
            'name' => $user->user_full_name ?? trim($user->first_name . ' ' . $user->last_name),
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'business_id' => $user->business_id,
            'business_name' => $business->name ?? null,
            'roles' => $user->getRoleNames()->toArray(),
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            'locations' => $locations,
        ]);
    }

    /**
     * Switch business context
     */
    public function switchBusiness(Request $request): JsonResponse
    {
        $request->validate([
            'business_id' => 'required_without:business_slug|integer',
            'business_slug' => 'required_without:business_id|string',
        ]);

        $user = $request->user();

        // Resolve business by ID or slug
        if (!empty($request->business_slug)) {
            $business = \App\Business::where('slug', $request->business_slug)->first();
        } else {
            $business = \App\Business::find($request->business_id);
        }

        if (!$business || $user->business_id != $business->id) {
            return $this->errorResponse('You do not have access to this business.', 403);
        }

        // Use user's assigned location, fallback to first active
        $location = null;
        if (!empty($user->default_location_id)) {
            $location = \App\BusinessLocation::where('id', $user->default_location_id)
                ->where('business_id', $business->id)
                ->where('is_active', 1)
                ->first();
        }
        if (!$location) {
            $location = \App\BusinessLocation::where('business_id', $business->id)
                ->where('is_active', 1)
                ->first();
        }

        return $this->successResponse([
            'business_id' => $business->id,
            'business_name' => $business->name,
            'business_slug' => $business->slug ?? null,
            'business_type' => $business->business_type ?? 'retail',
            'default_location_id' => $location->id ?? null,
            'default_location_name' => $location->name ?? null,
            'has_assigned_location' => !empty($user->default_location_id) && $location !== null,
        ], 'Business switched successfully');
    }
}
