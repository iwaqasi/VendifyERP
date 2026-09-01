<?php

namespace App\Config;

class BusinessTypes
{
    /**
     * All supported business types with their configuration.
     * Each type defines:
     * - label: Display name
     * - description: Short description for selection screen
     * - icon: FontAwesome icon class
     * - color: Theme color for the type
     * - enabled_modules: Which Laravel modules are active
     * - pos_layout: Which Flutter POS screen layout to use
     * - features: Granular feature flags for the POS
     * - custom_fields: Extra fields shown on product/customer forms
     */
    public const TYPES = [
        'saloon' => [
            'label' => 'Saloon & Spa',
            'description' => 'Hair salon, beauty spa, wellness center with appointment booking',
            'icon' => 'fa-spa',
            'color' => '#E91E63',
            'enabled_modules' => [
                'bookings' => true,
                'repair' => false,
                'kitchen_display' => false,
                'wholesale' => false,
            ],
            'pos_layout' => 'saloon',
            'features' => [
                'appointment_sidebar' => true,
                'service_timer' => true,
                'staff_assignment' => true,
                'table_management' => false,
                'barcode_scan' => false,
                'customer_display' => true,
                'reminders' => true,
                'booking_calendar' => true,
            ],
            'custom_fields' => [
                'product' => ['service_duration', 'service_staff'],
                'customer' => ['preferred_staff', 'allergies'],
            ],
        ],
        'repair' => [
            'label' => 'Repair Shop',
            'description' => 'Electronics, mobile, appliance repair with job tracking',
            'icon' => 'fa-tools',
            'color' => '#FF9800',
            'enabled_modules' => [
                'bookings' => false,
                'repair' => true,
                'kitchen_display' => false,
                'wholesale' => false,
            ],
            'pos_layout' => 'repair',
            'features' => [
                'repair_tickets' => true,
                'device_details' => true,
                'status_tracking' => true,
                'estimated_completion' => true,
                'customer_notifications' => true,
                'parts_tracking' => true,
                'warranty_tracking' => true,
            ],
            'custom_fields' => [
                'repair' => ['device_type', 'device_brand', 'device_model', 'device_serial', 'reported_issue', 'estimated_cost'],
                'customer' => ['alt_phone'],
            ],
        ],
        'restaurant' => [
            'label' => 'Restaurant & Kitchen',
            'description' => 'Restaurant, café, cloud kitchen with table management and KDS',
            'icon' => 'fa-utensils',
            'color' => '#4CAF50',
            'enabled_modules' => [
                'bookings' => false,
                'repair' => false,
                'kitchen_display' => true,
                'wholesale' => false,
            ],
            'pos_layout' => 'restaurant',
            'features' => [
                'table_management' => true,
                'kitchen_display' => true,
                'order_queue' => true,
                'dine_in_takeaway' => true,
                'course_serving' => true,
                'kot_printing' => true,
                'delivery_tracking' => true,
                'tip_support' => true,
            ],
            'custom_fields' => [
                'product' => ['prep_time', 'kitchen_station', 'course_number'],
                'order' => ['table_number', 'guest_count', 'order_type'],
            ],
        ],
        'retail' => [
            'label' => 'Retail Store',
            'description' => 'General retail, fashion, accessories, grocery with barcode scanning',
            'icon' => 'fa-store',
            'color' => '#2196F3',
            'enabled_modules' => [
                'bookings' => false,
                'repair' => false,
                'kitchen_display' => false,
                'wholesale' => false,
            ],
            'pos_layout' => 'retail',
            'features' => [
                'barcode_scan' => true,
                'quick_checkout' => true,
                'price_check' => true,
                'label_printing' => true,
                'stock_alerts' => true,
                'loyalty_program' => true,
                'return_refund' => true,
            ],
            'custom_fields' => [],
        ],
        'wholesale' => [
            'label' => 'Wholesale & Distribution',
            'description' => 'Bulk selling, multi-location, tiered pricing, warehouse management',
            'icon' => 'fa-warehouse',
            'color' => '#795548',
            'enabled_modules' => [
                'bookings' => false,
                'repair' => false,
                'kitchen_display' => false,
                'wholesale' => true,
            ],
            'pos_layout' => 'wholesale',
            'features' => [
                'barcode_scan' => true,
                'tiered_pricing' => true,
                'bulk_orders' => true,
                'multi_location' => true,
                'credit_management' => true,
                'delivery_scheduling' => true,
                'invoice_templates' => true,
            ],
            'custom_fields' => [
                'product' => ['min_order_qty', 'case_size', 'lead_time'],
                'customer' => ['credit_limit', 'payment_terms', 'tax_id'],
            ],
        ],
        'clinic' => [
            'label' => 'Clinic & Professional Services',
            'description' => 'Medical clinic, dental, consulting with appointment management',
            'icon' => 'fa-user-md',
            'color' => '#00BCD4',
            'enabled_modules' => [
                'bookings' => true,
                'repair' => false,
                'kitchen_display' => false,
                'wholesale' => false,
            ],
            'pos_layout' => 'clinic',
            'features' => [
                'appointment_sidebar' => true,
                'patient_checkin' => true,
                'service_timer' => false,
                'prescription_management' => true,
                'insurance_billing' => true,
                'package_deals' => true,
                'follow_up_reminders' => true,
            ],
            'custom_fields' => [
                'customer' => ['date_of_birth', 'blood_group', 'emergency_contact', 'insurance_id'],
                'service' => ['consultation_duration', 'follow_up_days'],
            ],
        ],
        'other' => [
            'label' => 'Other Business',
            'description' => 'Custom setup - configure modules manually',
            'icon' => 'fa-cog',
            'color' => '#607D8B',
            'enabled_modules' => [
                'bookings' => false,
                'repair' => false,
                'kitchen_display' => false,
                'wholesale' => false,
            ],
            'pos_layout' => 'retail',
            'features' => [
                'barcode_scan' => true,
                'quick_checkout' => true,
            ],
            'custom_fields' => [],
        ],
    ];

    /**
     * Get all business types as a simple list for dropdowns.
     */
    public static function getTypesList(): array
    {
        return array_map(fn($config) => $config['label'], self::TYPES);
    }

    /**
     * Get full config for a business type.
     */
    public static function getConfig(string $type): ?array
    {
        return self::TYPES[$type] ?? null;
    }

    /**
     * Get enabled modules for a business type.
     */
    public static function getModules(string $type): array
    {
        return self::TYPES[$type]['enabled_modules'] ?? [];
    }

    /**
     * Get POS features for a business type.
     */
    public static function getFeatures(string $type): array
    {
        return self::TYPES[$type]['features'] ?? [];
    }

    /**
     * Get the POS layout identifier for a business type.
     */
    public static function getPosLayout(string $type): string
    {
        return self::TYPES[$type]['pos_layout'] ?? 'retail';
    }
}
