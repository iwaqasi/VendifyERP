<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| VendifyPOS API v1 — Endpoints for the Flutter POS application.
| Authentication: Laravel Passport Bearer tokens
| License: POS module requires active subscription with flutter_pos=1
|
*/

// ============================================================
// PUBLIC ROUTES (No authentication required)
// ============================================================

Route::post('/v1/auth/login', [\App\Http\Controllers\Api\V1\AuthController::class, 'login']);
Route::post('/v1/auth/login-by-pin', [\App\Http\Controllers\Api\V1\AuthController::class, 'loginByPin']);

// ============================================================
// HEALTH CHECK (No authentication required)
// ============================================================
Route::get('/v1/health', function () {
    return response()->json([
        'status' => 'ok',
        'version' => config('app.version', '7.0'),
        'app_name' => config('app.name', 'VendifyERP'),
        'timestamp' => now()->toDateTimeString(),
    ]);
});

// ============================================================
// AUTHENTICATED ROUTES (Passport Bearer token required)
// ============================================================
Route::middleware('auth:api')->group(function () {

    // --- Auth Management (No license check needed) ---
    Route::post('/v1/auth/logout', [\App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
    Route::post('/v1/auth/refresh', [\App\Http\Controllers\Api\V1\AuthController::class, 'refresh']);
    Route::get('/v1/auth/user', [\App\Http\Controllers\Api\V1\AuthController::class, 'user']);
    Route::get('/v1/auth/me', [\App\Http\Controllers\Api\V1\AuthController::class, 'user']);
    Route::post('/v1/auth/switch-business', [\App\Http\Controllers\Api\V1\AuthController::class, 'switchBusiness']);

    // --- Device session management (token lifecycle) ---
    Route::get('/v1/auth/devices', [\App\Http\Controllers\Api\V1\AuthController::class, 'devices']);
    Route::delete('/v1/auth/devices/{id}', [\App\Http\Controllers\Api\V1\AuthController::class, 'revokeDevice']);

    // --- License Check Endpoint (fail-closed: never grants on error) ---
    Route::get('/v1/license/check', [\App\Http\Controllers\Api\V1\LicenseController::class, 'check']);

    // ============================================================
    // POS MODULE ROUTES (License required)
    // All routes below require: auth:api + pos.license middleware
    // ============================================================
    Route::middleware('pos.license')->group(function () {

        // --- Products ---
        Route::get('/v1/products', [\App\Http\Controllers\Api\V1\ProductController::class, 'index']);
        Route::get('/v1/products/{id}', [\App\Http\Controllers\Api\V1\ProductController::class, 'show']);
        Route::get('/v1/products/{id}/variations', [\App\Http\Controllers\Api\V1\ProductController::class, 'variations']);
        Route::get('/v1/categories', [\App\Http\Controllers\Api\V1\ProductController::class, 'categories']);
        Route::post('/v1/categories/image', [\App\Http\Controllers\Api\V1\ProductController::class, 'uploadCategoryImage']);
        Route::delete('/v1/categories/image', [\App\Http\Controllers\Api\V1\ProductController::class, 'deleteCategoryImage']);
        Route::get('/v1/brands', [\App\Http\Controllers\Api\V1\ProductController::class, 'brands']);
        Route::get('/v1/units', [\App\Http\Controllers\Api\V1\ProductController::class, 'units']);
        Route::get('/v1/tax-rates', [\App\Http\Controllers\Api\V1\ProductController::class, 'taxRates']);

        // --- Contacts (Customers/Suppliers) ---
        Route::get('/v1/contacts', [\App\Http\Controllers\Api\V1\ContactController::class, 'index']);
        Route::get('/v1/contacts/{id}', [\App\Http\Controllers\Api\V1\ContactController::class, 'show']);
        Route::post('/v1/contacts', [\App\Http\Controllers\Api\V1\ContactController::class, 'store']);
        Route::put('/v1/contacts/{id}', [\App\Http\Controllers\Api\V1\ContactController::class, 'update']);
        Route::get('/v1/customer-groups', [\App\Http\Controllers\Api\V1\ContactController::class, 'customerGroups']);
        Route::get('/v1/contacts/{id}/reward-points', [\App\Http\Controllers\Api\V1\ContactController::class, 'rewardPoints']);
        
        // Alias routes for backwards compatibility
        Route::get('/v1/customers', [\App\Http\Controllers\Api\V1\ContactController::class, 'index']);
        Route::post('/v1/customers', [\App\Http\Controllers\Api\V1\ContactController::class, 'store']);

        // --- Sales / POS ---
        Route::get('/v1/sells', [\App\Http\Controllers\Api\V1\SellController::class, 'index']);
        Route::post('/v1/sells', [\App\Http\Controllers\Api\V1\SellController::class, 'store']);
        Route::get('/v1/sells/drafts', [\App\Http\Controllers\Api\V1\SellController::class, 'drafts']);
        Route::delete('/v1/sells/drafts/{id}', [\App\Http\Controllers\Api\V1\SellController::class, 'destroyDraft']);
        Route::get('/v1/sells/{id}', [\App\Http\Controllers\Api\V1\SellController::class, 'show']);
        Route::post('/v1/sells/{id}/payment', [\App\Http\Controllers\Api\V1\SellController::class, 'addPayment']);
        Route::post('/v1/sells/{id}/return', [\App\Http\Controllers\Api\V1\SellController::class, 'returnItems']);
        Route::post('/v1/contacts/{id}/pay-credit', [\App\Http\Controllers\Api\V1\SellController::class, 'payCredit']);
        Route::get('/v1/reports/daily-sales', [\App\Http\Controllers\Api\V1\SellController::class, 'dailySummary']);

        // --- Stock ---
        Route::get('/v1/stock', [\App\Http\Controllers\Api\V1\StockController::class, 'index']);
        Route::get('/v1/stock/product/{product_id}', [\App\Http\Controllers\Api\V1\StockController::class, 'productStock']);

        // --- Settings ---
        Route::get('/v1/settings', [\App\Http\Controllers\Api\V1\SettingsController::class, 'business']);
        Route::get('/v1/locations', [\App\Http\Controllers\Api\V1\SettingsController::class, 'locations']);
        Route::get('/v1/payment-methods', [\App\Http\Controllers\Api\V1\SettingsController::class, 'paymentMethods']);
        Route::get('/v1/types-of-service', [\App\Http\Controllers\Api\V1\SettingsController::class, 'typesOfService']);
        Route::get('/v1/tables', [\App\Http\Controllers\Api\V1\SettingsController::class, 'tables']);
        Route::get('/v1/invoice-layouts', [\App\Http\Controllers\Api\V1\SettingsController::class, 'invoiceLayouts']);
        Route::get('/v1/pos-settings', [\App\Http\Controllers\Api\V1\SettingsController::class, 'posSettings']);

        // --- Business Type ---
        Route::get('/v1/business-types', [\App\Http\Controllers\Api\V1\BusinessTypeController::class, 'index']);
        Route::get('/v1/business-type', [\App\Http\Controllers\Api\V1\BusinessTypeController::class, 'show']);
        Route::post('/v1/business-type', [\App\Http\Controllers\Api\V1\BusinessTypeController::class, 'update']);
        Route::get('/v1/pos-layout', [\App\Http\Controllers\Api\V1\BusinessTypeController::class, 'posLayout']);

        // --- Saloon / Spa ---
        Route::get('/v1/saloon/appointments', [\App\Http\Controllers\Api\V1\SaloonController::class, 'appointments']);
        Route::post('/v1/saloon/appointments', [\App\Http\Controllers\Api\V1\SaloonController::class, 'storeAppointment']);
        Route::put('/v1/saloon/appointments/{id}/status', [\App\Http\Controllers\Api\V1\SaloonController::class, 'updateAppointmentStatus']);
        Route::get('/v1/saloon/staff', [\App\Http\Controllers\Api\V1\SaloonController::class, 'staff']);
        Route::post('/v1/saloon/staff', [\App\Http\Controllers\Api\V1\SaloonController::class, 'storeStaff']);
        Route::post('/v1/saloon/service/start', [\App\Http\Controllers\Api\V1\SaloonController::class, 'startService']);
        Route::post('/v1/saloon/service/complete', [\App\Http\Controllers\Api\V1\SaloonController::class, 'completeService']);

        // --- Repair ---
        Route::get('/v1/repairs', [\App\Http\Controllers\Api\V1\RepairController::class, 'index']);
        Route::post('/v1/repairs', [\App\Http\Controllers\Api\V1\RepairController::class, 'store']);
        Route::put('/v1/repairs/{id}/status', [\App\Http\Controllers\Api\V1\RepairController::class, 'updateStatus']);
        Route::put('/v1/repairs/{id}/costs', [\App\Http\Controllers\Api\V1\RepairController::class, 'updateCosts']);

        // --- Restaurant ---
        Route::get('/v1/restaurant/tables', [\App\Http\Controllers\Api\V1\RestaurantController::class, 'tables']);
        Route::put('/v1/restaurant/tables/{id}/status', [\App\Http\Controllers\Api\V1\RestaurantController::class, 'updateTableStatus']);
        Route::get('/v1/restaurant/orders', [\App\Http\Controllers\Api\V1\RestaurantController::class, 'orders']);
        Route::post('/v1/restaurant/orders', [\App\Http\Controllers\Api\V1\RestaurantController::class, 'storeOrder']);
        Route::put('/v1/restaurant/orders/{id}/status', [\App\Http\Controllers\Api\V1\RestaurantController::class, 'updateOrderStatus']);
        Route::post('/v1/restaurant/kot/send', [\App\Http\Controllers\Api\V1\RestaurantController::class, 'sendToKot']);

        // --- Shifts / Daily Register ---
        Route::get('/v1/shifts/current', [\App\Http\Controllers\Api\V1\ShiftController::class, 'current']);
        Route::post('/v1/shifts/open', [\App\Http\Controllers\Api\V1\ShiftController::class, 'open']);
        Route::post('/v1/shifts/close', [\App\Http\Controllers\Api\V1\ShiftController::class, 'close']);
        Route::get('/v1/shifts/daily-summary', [\App\Http\Controllers\Api\V1\ShiftController::class, 'dailySummary']);
        Route::get('/v1/shifts/history', [\App\Http\Controllers\Api\V1\ShiftController::class, 'history']);
    });

    // --- Multi-Location ---
    Route::get('/v1/locations', [\App\Http\Controllers\Api\V1\LocationController::class, 'index']);
    Route::get('/v1/locations/stock-summary', [\App\Http\Controllers\Api\V1\LocationController::class, 'stockSummary']);
    Route::get('/v1/locations/stock/{product_id}', [\App\Http\Controllers\Api\V1\LocationController::class, 'productStock']);
    Route::get('/v1/locations/cross-stock/{variation_id}', [\App\Http\Controllers\Api\V1\LocationController::class, 'crossLocationStock']);
    Route::post('/v1/locations/transfer', [\App\Http\Controllers\Api\V1\LocationController::class, 'createTransfer']);
    Route::get('/v1/locations/transfers', [\App\Http\Controllers\Api\V1\LocationController::class, 'transfers']);
    Route::get('/v1/locations/sales-report', [\App\Http\Controllers\Api\V1\LocationController::class, 'salesReport']);
});

// ============================================================
// CMS ROUTES (Public — no auth required, used by Flutter CMS web)
// NOTE: the `api` middleware group (incl. throttle) still applies
// because this file is loaded with ->middleware('api').
// ============================================================
Route::group(['middleware' => 'api'], function () {
    Route::get('/v1/cms/config', [\App\Http\Controllers\Api\V1\CmsController::class, 'config']);
    Route::get('/v1/cms/home', [\App\Http\Controllers\Api\V1\CmsController::class, 'home']);
    Route::get('/v1/cms/pages/{slug}', [\App\Http\Controllers\Api\V1\CmsController::class, 'page']);
    Route::get('/v1/cms/posts', [\App\Http\Controllers\Api\V1\CmsController::class, 'posts']);
    Route::get('/v1/cms/posts/{slug}', [\App\Http\Controllers\Api\V1\CmsController::class, 'post']);
    Route::get('/v1/cms/products', [\App\Http\Controllers\Api\V1\CmsController::class, 'products']);
    Route::get('/v1/cms/products/{slug}', [\App\Http\Controllers\Api\V1\CmsController::class, 'product']);
    Route::get('/v1/cms/categories', [\App\Http\Controllers\Api\V1\CmsController::class, 'categories']);
    Route::post('/v1/cms/contact', [\App\Http\Controllers\Api\V1\CmsController::class, 'contact'])
        ->middleware('throttle:5,1'); // 5 submissions per minute per IP — prevents spam
});
