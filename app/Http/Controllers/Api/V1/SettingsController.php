<?php

namespace App\Http\Controllers\Api\V1;

use App\Business;
use App\BusinessLocation;
use App\System;
use App\TypesOfService;
use App\Restaurant\ResTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends BaseApiController
{
    /**
     * Get business settings
     */
    public function business(Request $request)
    {
        $business_id = $this->getBusinessId($request);

        $business = Business::with([
            'locations:id,business_id,name,invoice_scheme_id,invoice_layout_id,is_active',
        ])->find($business_id);

        if (!$business) {
            return $this->errorResponse('Business not found.', 404);
        }

        return $this->successResponse([
            'id' => $business->id,
            'name' => $business->name,
            'start_date' => $business->start_date,
            'business_type' => $business->business_type,
            'default_currency' => $business->currency_id,
            'currency' => $business->currency->symbol ?? null,
            'currency_code' => $business->currency->code ?? null,
            'currency_subunit' => $business->currency->subunit ?? 2,
            'tax_rate_id' => $business->tax_rate_id,
            'invoice_scheme_id' => $business->invoice_scheme_id,
            'default_sales_discount' => $business->default_sales_discount,
            'default_sales_tax' => $business->default_sales_tax,
            'default_sales_discount_type' => $business->default_sales_discount_type,
            'enable_price_tax' => $business->enable_price_tax ?? false,
            'accounting_method' => $business->accounting_method,
            'theme_color' => $business->theme_color,
            'timezone' => $business->timezone,
            'date_format' => $business->date_format,
            'time_format' => $business->time_format,
            'cashier_pin' => $business->cashier_pin ?? null,
            'locations' => $business->locations->map(function ($loc) {
                return [
                    'id' => $loc->id,
                    'name' => $loc->name,
                    'is_active' => $loc->is_active,
                ];
            }),
        ]);
    }

    /**
     * Get all active business locations
     */
    public function locations(Request $request)
    {
        $business_id = $this->getBusinessId($request);

        $locations = BusinessLocation::where('business_id', $business_id)
            ->where('is_active', 1)
            ->get([
                'id', 'name', 'city', 'state', 'country',
                'zip_code', 'mobile', 'email',
                'invoice_scheme_id', 'invoice_layout_id',
                'receipt_printer_type', 'printer_id',
                'selling_price_group_id',
            ]);

        return $this->successResponse($locations);
    }

    /**
     * Get available payment methods
     */
    public function paymentMethods(Request $request)
    {
        // Standard payment methods in the system
        $methods = [
            ['method' => 'cash', 'label' => 'Cash'],
            ['method' => 'card', 'label' => 'Card'],
            ['method' => 'bank_transfer', 'label' => 'Bank Transfer'],
            ['method' => 'cheque', 'label' => 'Cheque'],
            ['method' => 'other', 'label' => 'Other'],
            ['method' => 'custom_pay_1', 'label' => System::getProperty('custom_pay_1_name') ?? 'Custom Pay 1'],
            ['method' => 'custom_pay_2', 'label' => System::getProperty('custom_pay_2_name') ?? 'Custom Pay 2'],
            ['method' => 'custom_pay_3', 'label' => System::getProperty('custom_pay_3_name') ?? 'Custom Pay 3'],
        ];

        return $this->successResponse($methods);
    }

    /**
     * Get types of service (dine-in, takeaway, delivery)
     */
    public function typesOfService(Request $request)
    {
        $business_id = $this->getBusinessId($request);

        $types = TypesOfService::where('business_id', $business_id)
            ->get(['id', 'name', 'description']);

        return $this->successResponse($types);
    }

    /**
     * Get restaurant tables
     */
    public function tables(Request $request)
    {
        $business_id = $this->getBusinessId($request);

        $tables = ResTable::where('business_id', $business_id)
            ->get(['id', 'name', 'description']);

        return $this->successResponse($tables);
    }

    /**
     * Get invoice layouts
     */
    public function invoiceLayouts(Request $request)
    {
        $business_id = $this->getBusinessId($request);

        $layouts = \App\InvoiceLayout::where('business_id', $business_id)
            ->get(['id', 'name']);

        return $this->successResponse($layouts);
    }

    /**
     * Get barcode settings
     */
    public function barcodeSettings(Request $request)
    {
        $business_id = $this->getBusinessId($request);

        $settings = \App\Barcode::where('business_id', $business_id)
            ->get();

        return $this->successResponse($settings);
    }

    /**
     * Get VendifyPOS settings for the Flutter app
     */
    public function posSettings(Request $request)
    {
        $business_id = $this->getBusinessId($request);

        $business = \App\Business::with(['currency'])->find($business_id);

        if (!$business) {
            return $this->errorResponse('Business not found.', 404);
        }

        $defaultSettings = app(\App\Utils\BusinessUtil::class)->defaultPosSettings();
        $posSettings = !empty($business->pos_settings) ? json_decode($business->pos_settings, true) : [];
        $posSettings = array_merge($defaultSettings, $posSettings);

        // Get enabled payment methods from business location settings
        $enabledPaymentMethods = [];
        $defaultLocation = \App\BusinessLocation::where('business_id', $business_id)->where('is_active', 1)->first();
        if ($defaultLocation && !empty($defaultLocation->default_payment_accounts)) {
            $paymentAccounts = json_decode($defaultLocation->default_payment_accounts, true);
            if (is_array($paymentAccounts)) {
                foreach ($paymentAccounts as $method => $details) {
                    if (!empty($details['is_enabled'])) {
                        $enabledPaymentMethods[] = [
                            'method' => $method,
                            'account' => $details['account'] ?? null,
                        ];
                    }
                }
            }
        }

        // If no enabled methods found, provide defaults
        if (empty($enabledPaymentMethods)) {
            $enabledPaymentMethods = [
                ['method' => 'cash', 'account' => null],
                ['method' => 'card', 'account' => null],
            ];
        }

        // Get custom payment labels from business settings
        $customLabels = !empty($business->custom_labels) ? json_decode($business->custom_labels, true) : [];
        $customPaymentLabels = $customLabels['payments'] ?? [];

        // Build explicit feature flags for the Flutter app
        $features = [
            // VendifyPOS features
            'hold_recall_cart' => !empty($posSettings['pos_enable_hold_recall']),
            'split_payment' => !empty($posSettings['pos_enable_split_payment']),
            'auth_code_required' => !empty($posSettings['pos_enable_auth_code']),
            'customer_display' => !empty($posSettings['pos_enable_customer_display']),

            // Existing POS settings (shared with web POS)
            'discount_enabled' => empty($posSettings['disable_discount']),
            'order_tax_enabled' => empty($posSettings['disable_order_tax']),
            'allow_overselling' => !empty($posSettings['allow_overselling']),
            'credit_sale_enabled' => empty($posSettings['disable_credit_sale_button']),
            'draft_enabled' => empty($posSettings['disable_draft']),
            'express_checkout_enabled' => empty($posSettings['disable_express_checkout']),
            'suspend_sale_enabled' => empty($posSettings['disable_suspend']),
            'subtotal_editable' => !empty($posSettings['is_pos_subtotal_editable']),
            'service_staff_required' => !empty($posSettings['is_service_staff_required']),
            'service_staff_inline' => !empty($posSettings['inline_service_staff']),
            'weighing_scale_enabled' => !empty($posSettings['enable_weighing_scale']),
            'transaction_date_enabled' => !empty($posSettings['enable_transaction_date']),
        ];

        return $this->successResponse([
            'pos_settings' => $posSettings,
            'features' => $features,
            'custom_payment_labels' => $customPaymentLabels,
            'currency' => [
                'id' => $business->currency_id,
                'symbol' => $business->currency->symbol ?? 'KD',
                'code' => $business->currency->code ?? 'KWD',
                'precision' => $business->currency_precision ?? 3,
                'symbol_placement' => $business->currency_symbol_placement ?? 'before',
            ],
            'enabled_payment_methods' => $enabledPaymentMethods,
            // Reward Points settings
            'reward_points' => [
                'enabled' => !empty($business->enable_rp),
                'name' => $business->rp_name ?? 'Reward Points',
                'amount_per_unit' => (float) ($business->amount_for_unit_rp ?? 1),
                'redeem_amount_per_unit' => (float) ($business->redeem_amount_per_unit_rp ?? 1),
                'min_order_total' => (float) ($business->min_order_total_for_rp ?? 0),
                'max_per_order' => $business->max_rp_per_order ? (float) $business->max_rp_per_order : null,
                'min_redeem_point' => $business->min_redeem_point ? (int) $business->min_redeem_point : 0,
                'max_redeem_point' => $business->max_redeem_point ? (int) $business->max_redeem_point : null,
                'expiry_period' => $business->rp_expiry_period ? (int) $business->rp_expiry_period : null,
                'expiry_type' => $business->rp_expiry_type ?? 'year',
            ],
            'tax_settings' => [
                'enable_inline_tax' => $business->enable_inline_tax ?? false,
                'default_sales_tax' => $business->default_sales_tax,
            ],
        ]);
    }
}
