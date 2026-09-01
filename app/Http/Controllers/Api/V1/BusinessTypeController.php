<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Config\BusinessTypes;
use App\Business;

class BusinessTypeController extends BaseApiController
{
    /**
     * Get all available business types
     * GET /api/v1/business-types
     */
    public function index(Request $request): JsonResponse
    {
        $types = [];
        foreach (BusinessTypes::TYPES as $key => $config) {
            $types[] = [
                'id' => $key,
                'label' => $config['label'],
                'description' => $config['description'],
                'icon' => $config['icon'],
                'color' => $config['color'],
                'enabled_modules' => $config['enabled_modules'],
                'features' => $config['features'],
            ];
        }

        return $this->successResponse([
            'types' => $types,
        ]);
    }

    /**
     * Get current business type and its full configuration
     * GET /api/v1/business-type
     */
    public function show(Request $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);
        $business = Business::find($business_id);

        if (!$business) {
            return $this->errorResponse('Business not found.', 404);
        }

        $businessType = $business->business_type ?? 'retail';
        $typeConfig = BusinessTypes::getConfig($businessType) ?? BusinessTypes::getConfig('retail');

        // Get enabled modules (merge business type defaults with any custom overrides)
        $enabledModules = $typeConfig['enabled_modules'];
        if (!empty($business->enabled_modules)) {
            $businessModules = is_array($business->enabled_modules)
                ? $business->enabled_modules
                : json_decode($business->enabled_modules, true);
            if (is_array($businessModules)) {
                $enabledModules = array_merge($enabledModules, $businessModules);
            }
        }

        return $this->successResponse([
            'business_type' => $businessType,
            'business_type_label' => $typeConfig['label'],
            'pos_layout' => $typeConfig['pos_layout'],
            'enabled_modules' => $enabledModules,
            'features' => $typeConfig['features'],
            'custom_fields' => $typeConfig['custom_fields'] ?? [],
            'color' => $typeConfig['color'],
            'icon' => $typeConfig['icon'],
        ]);
    }

    /**
     * Set/update business type
     * POST /api/v1/business-type
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'business_type' => 'required|string|in:' . implode(',', array_keys(BusinessTypes::TYPES)),
        ]);

        $business_id = $this->getBusinessId($request);
        $business = Business::find($business_id);

        if (!$business) {
            return $this->errorResponse('Business not found.', 404);
        }

        $businessType = $request->input('business_type');
        $typeConfig = BusinessTypes::getConfig($businessType);

        // Update business type
        $business->business_type = $businessType;

        // Store the type-specific config as JSON
        $business->business_type_config = json_encode([
            'type' => $businessType,
            'configured_at' => now()->toISOString(),
        ]);

        // Auto-enable relevant modules
        $existingModules = is_array($business->enabled_modules)
            ? $business->enabled_modules
            : (json_decode($business->enabled_modules, true) ?? []);
        $mergedModules = array_merge($existingModules, $typeConfig['enabled_modules']);
        $business->enabled_modules = json_encode($mergedModules);

        $business->save();

        return $this->successResponse([
            'business_type' => $businessType,
            'business_type_label' => $typeConfig['label'],
            'pos_layout' => $typeConfig['pos_layout'],
            'enabled_modules' => $typeConfig['enabled_modules'],
            'features' => $typeConfig['features'],
            'message' => "Business type set to {$typeConfig['label']}. POS layout and modules updated.",
        ], 'Business type updated successfully');
    }

    /**
     * Get the POS layout for the current business type
     * GET /api/v1/pos-layout
     */
    public function posLayout(Request $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);
        $business = Business::find($business_id);

        if (!$business) {
            return $this->errorResponse('Business not found.', 404);
        }

        $businessType = $business->business_type ?? 'retail';
        $typeConfig = BusinessTypes::getConfig($businessType) ?? BusinessTypes::getConfig('retail');

        return $this->successResponse([
            'business_type' => $businessType,
            'pos_layout' => $typeConfig['pos_layout'],
            'features' => $typeConfig['features'],
            'enabled_modules' => $typeConfig['enabled_modules'],
            'custom_fields' => $typeConfig['custom_fields'] ?? [],
        ]);
    }
}
