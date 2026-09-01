<?php
/**
 * VendifyPOS Complete API Test Script
 * Tests all backend endpoints aligned with Flutter frontend
 */

$baseUrl = 'http://ultimatepos7.0.test';

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║         VendifyPOS Complete API Test Suite                   ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Helper functions
function makeRequest($method, $url, $data = null, $token = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
    ]);
    
    if ($token) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(
            curl_getinfo($ch, CURLINFO_HEADER_OUT) ? [] : [],
            ['Authorization: Bearer ' . $token]
        ));
        // Reset headers with auth
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
        ]);
    }
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['status' => $httpCode, 'response' => json_decode($response, true)];
}

function testEndpoint($name, $method, $url, $data = null, $token = null) {
    echo "┌─────────────────────────────────────────────────────────────┐\n";
    echo "│ TEST: {$name}\n";
    echo "│ {$method} {$url}\n";
    
    $result = makeRequest($method, $url, $data, $token);
    $status = $result['status'];
    $response = $result['response'];
    
    $icon = ($status >= 200 && $status < 300) ? '✅' : '❌';
    echo "│ Status: {$status} {$icon}\n";
    
    if ($response) {
        if (isset($response['success'])) {
            echo "│ Success: " . ($response['success'] ? 'true' : 'false') . "\n";
        }
        if (isset($response['message'])) {
            echo "│ Message: {$response['message']}\n";
        }
        if (isset($response['data']) && is_array($response['data'])) {
            $count = count($response['data']);
            echo "│ Data: {$count} items returned\n";
        }
    }
    
    echo "└─────────────────────────────────────────────────────────────┘\n\n";
    
    return $result;
}

// ═══════════════════════════════════════════════════════════════
// TEST 1: AUTHENTICATION
// ═══════════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════════\n";
echo " 1. AUTHENTICATION\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$loginResult = testEndpoint(
    'Login (pos@test.com - Coiffured Mind)',
    'POST',
    "$baseUrl/api/v1/auth/login",
    ['email' => 'pos@test.com', 'password' => 'pos123', 'business_id' => 2, 'device_name' => 'pos-app']
);

$token = $loginResult['response']['data']['access_token'] ?? null;
$userId = $loginResult['response']['data']['user']['id'] ?? null;

if (!$token) {
    echo "❌ Login failed! Cannot continue tests.\n";
    exit(1);
}

// ═══════════════════════════════════════════════════════════════
// TEST 2: USER PROFILE
// ═══════════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════════\n";
echo " 2. USER PROFILE\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

testEndpoint('Get User Profile', 'GET', "$baseUrl/api/v1/auth/user", null, $token);

// ═══════════════════════════════════════════════════════════════
// TEST 3: POS SETTINGS
// ═══════════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════════\n";
echo " 3. POS SETTINGS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

testEndpoint('Get POS Settings', 'GET', "$baseUrl/api/v1/pos-settings", null, $token);

// ═══════════════════════════════════════════════════════════════
// TEST 4: BUSINESS TYPES
// ═══════════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════════\n";
echo " 4. BUSINESS TYPES\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

testEndpoint('Get Business Types', 'GET', "$baseUrl/api/v1/business-types", null, $token);

// ═══════════════════════════════════════════════════════════════
// TEST 5: PRODUCTS & CATEGORIES
// ═══════════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════════\n";
echo " 5. PRODUCTS & CATEGORIES\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

testEndpoint('Get Categories', 'GET', "$baseUrl/api/v1/categories", null, $token);
testEndpoint('Get Products (Page 1)', 'GET', "$baseUrl/api/v1/products?page=1&per_page=10&location_id=2", null, $token);
testEndpoint('Search Products (Saloon)', 'GET', "$baseUrl/api/v1/products?search=haircut&location_id=2", null, $token);

// ═══════════════════════════════════════════════════════════════
// TEST 6: CUSTOMERS
// ═══════════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════════\n";
echo " 6. CUSTOMERS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

testEndpoint('Get Customers (via contacts)', 'GET', "$baseUrl/api/v1/contacts", null, $token);
testEndpoint('Create Customer (via contacts)', 'POST', "$baseUrl/api/v1/contacts", [
    'name' => 'Test Customer',
    'type' => 'customer',
    'contact_sub_type' => 'individual',
    'mobile' => '+96599887766',
    'email' => 'test@customer.com',
], $token);

// ═══════════════════════════════════════════════════════════════
// TEST 7: LOCATIONS
// ═══════════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════════\n";
echo " 7. LOCATIONS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

testEndpoint('Get Locations', 'GET', "$baseUrl/api/v1/locations", null, $token);
testEndpoint('Stock Summary', 'GET', "$baseUrl/api/v1/locations/stock-summary", null, $token);

// ═══════════════════════════════════════════════════════════════
// TEST 8: SHIFTS (DAILY REGISTER)
// ═══════════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════════\n";
echo " 8. SHIFTS (DAILY REGISTER)\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

testEndpoint('Get Current Shift', 'GET', "$baseUrl/api/v1/shifts/current?location_id=2", null, $token);
testEndpoint('Get Daily Summary', 'GET', "$baseUrl/api/v1/shifts/daily-summary?location_id=2", null, $token);

// ═══════════════════════════════════════════════════════════════
// TEST 9: POS MODULE (Business Type Specific)
// ═══════════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════════\n";
echo " 9. POS MODULE - BUSINESS TYPE SPECIFIC\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

testEndpoint('Get Appointments (Saloon)', 'GET', "$baseUrl/api/v1/saloon/appointments?date=" . date('Y-m-d'), null, $token);
testEndpoint('Get Staff (Saloon)', 'GET', "$baseUrl/api/v1/saloon/staff", null, $token);
testEndpoint('Get Repairs', 'GET', "$baseUrl/api/v1/repairs", null, $token);
testEndpoint('Get Restaurant Tables', 'GET', "$baseUrl/api/v1/restaurant/tables", null, $token);
testEndpoint('Get Restaurant Orders', 'GET', "$baseUrl/api/v1/restaurant/orders", null, $token);

// ═══════════════════════════════════════════════════════════════
// TEST 10: CMS (WEBSITE)
// ═══════════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════════\n";
echo " 10. CMS (WEBSITE)\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

testEndpoint('CMS Home Data', 'GET', "$baseUrl/api/v1/cms/home?business_id=3", null, $token);
testEndpoint('CMS Products', 'GET', "$baseUrl/api/v1/cms/products?page=1&business_id=3", null, $token);
testEndpoint('CMS Categories', 'GET', "$baseUrl/api/v1/cms/categories?business_id=3", null, $token);
testEndpoint('CMS Posts', 'GET', "$baseUrl/api/v1/cms/posts?business_id=3", null, $token);

// ═══════════════════════════════════════════════════════════════
// TEST 11: CREATE A SALE (End-to-End)
// ═══════════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════════\n";
echo " 11. CREATE A SALE (END-TO-END)\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// First, get a product to sell
$productsResult = makeRequest('GET', "$baseUrl/api/v1/products?page=1&per_page=1&location_id=2", null, $token);
$products = $productsResult['response']['data']['data'] ?? [];

if (!empty($products)) {
    $product = $products[0];
    $productId = $product['product_id'];
    $variationId = $product['variation_id'];
    $unitPrice = $product['default_sell_price_inc_tax'];
    
    echo "Product to sell: {$product['name']} (ID: {$productId})\n";
    echo "Price: {$unitPrice}\n\n";
    
    $saleResult = testEndpoint(
        'Create Sale',
        'POST',
        "$baseUrl/api/v1/sells",
        [
            'location_id' => 2,
            'contact_id' => null,
            'products' => [
                [
                    'product_id' => $productId,
                    'variation_id' => $variationId,
                    'quantity' => 1,
                    'unit_price' => $unitPrice,
                    'discount' => 0,
                    'tax_id' => null,
                ]
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => round($unitPrice + 0.100, 3), 'reference' => null, 'auth_code' => null]
            ],
            'additional_notes' => 'Walk-in Customer',
            'sale_note' => null,
            'discount_type' => 'fixed',
            'discount_amount' => 0,
        ],
        $token
    );
} else {
    echo "⚠️ No products found to test sale creation\n\n";
}

// ═══════════════════════════════════════════════════════════════
// TEST 12: LOGOUT
// ═══════════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════════════\n";
echo " 12. LOGOUT\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

testEndpoint('Logout', 'POST', "$baseUrl/api/v1/auth/logout", null, $token);

// ═══════════════════════════════════════════════════════════════
// SUMMARY
// ═══════════════════════════════════════════════════════════════
echo "\n═══════════════════════════════════════════════════════════════\n";
echo " TEST SUITE COMPLETE\n";
echo "═══════════════════════════════════════════════════════════════\n";

?>
