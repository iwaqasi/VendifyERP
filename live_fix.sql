-- ============================================
-- LIVE SERVER FIX: Create missing tables & columns
-- Run this on your live MySQL database
-- Safe: only creates new tables/columns, never touches existing data
-- ============================================

-- 1. Create staff_schedules table (if not exists)
CREATE TABLE IF NOT EXISTS `staff_schedules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `business_id` int unsigned NOT NULL,
  `sat_is_off` tinyint(1) NOT NULL DEFAULT 0,
  `sat_start_time` time DEFAULT NULL,
  `sat_end_time` time DEFAULT NULL,
  `sun_is_off` tinyint(1) NOT NULL DEFAULT 0,
  `sun_start_time` time DEFAULT NULL,
  `sun_end_time` time DEFAULT NULL,
  `mon_is_off` tinyint(1) NOT NULL DEFAULT 0,
  `mon_start_time` time DEFAULT NULL,
  `mon_end_time` time DEFAULT NULL,
  `tue_is_off` tinyint(1) NOT NULL DEFAULT 0,
  `tue_start_time` time DEFAULT NULL,
  `tue_end_time` time DEFAULT NULL,
  `wed_is_off` tinyint(1) NOT NULL DEFAULT 0,
  `wed_start_time` time DEFAULT NULL,
  `wed_end_time` time DEFAULT NULL,
  `thu_is_off` tinyint(1) NOT NULL DEFAULT 0,
  `thu_start_time` time DEFAULT NULL,
  `thu_end_time` time DEFAULT NULL,
  `fri_is_off` tinyint(1) NOT NULL DEFAULT 0,
  `fri_start_time` time DEFAULT NULL,
  `fri_end_time` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_schedules_user_id_business_id_unique` (`user_id`,`business_id`),
  KEY `staff_schedules_business_id_index` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create booking_services table (if not exists)
CREATE TABLE IF NOT EXISTS `booking_services` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int unsigned NOT NULL,
  `product_id` int unsigned NOT NULL,
  `service_staff_id` int unsigned DEFAULT NULL,
  `quantity` decimal(22,4) NOT NULL DEFAULT 1.0000,
  `unit_price` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `line_total` decimal(22,4) NOT NULL DEFAULT 0.0000,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `booking_services_booking_id_index` (`booking_id`),
  KEY `booking_services_product_id_index` (`product_id`),
  KEY `booking_services_service_staff_id_index` (`service_staff_id`),
  CONSTRAINT `booking_services_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_services_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_services_service_staff_id_foreign` FOREIGN KEY (`service_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Add is_flexible_price column to products (if not exists)
SET @column_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND COLUMN_NAME = 'is_flexible_price'
);

SET @sql = IF(@column_exists = 0,
  'ALTER TABLE `products` ADD COLUMN `is_flexible_price` tinyint(1) NOT NULL DEFAULT 0 AFTER `enable_stock`',
  'SELECT "is_flexible_price column already exists" AS status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Mark these migrations as done so artisan migrate won't re-run them
-- (Only if the migrations table exists and these entries are missing)
INSERT IGNORE INTO `migrations` (`migration`, `batch`) VALUES
  ('2026_08_18_000001_create_staff_schedules_table', 1),
  ('2026_08_18_000002_add_flexible_price_to_products_table', 1),
  ('2026_08_18_000003_create_booking_services_table', 1);

-- Done! Tables and column created without touching existing data.
