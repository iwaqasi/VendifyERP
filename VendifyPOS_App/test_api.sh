#!/bin/bash
# ============================================================
# VendifyPOS API Test Script
# Run: bash VendifyPOS_App/test_api.sh
# ============================================================

BASE_URL="http://127.0.0.1:8000/api/v1"
EMAIL="pos@test.com"
PASSWORD="pos123"
BUSINESS_ID=2

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

passed=0
failed=0

# Helper function
test_endpoint() {
    local name="$1"
    local method="$2"
    local endpoint="$3"
    local data="$4"
    local expected_code="${5:-200}"

    if [ "$method" = "POST" ] && [ -n "$data" ]; then
        response=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL$endpoint" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -H "Authorization: Bearer $TOKEN" \
            -d "$data")
    elif [ "$method" = "PUT" ] && [ -n "$data" ]; then
        response=$(curl -s -w "\n%{http_code}" -X PUT "$BASE_URL$endpoint" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -H "Authorization: Bearer $TOKEN" \
            -d "$data")
    elif [ "$method" = "DELETE" ]; then
        response=$(curl -s -w "\n%{http_code}" -X DELETE "$BASE_URL$endpoint" \
            -H "Accept: application/json" \
            -H "Authorization: Bearer $TOKEN")
    else
        response=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL$endpoint" \
            -H "Accept: application/json" \
            -H "Authorization: Bearer $TOKEN")
    fi

    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')

    if [ "$http_code" = "$expected_code" ]; then
        echo -e "${GREEN}✓ PASS${NC} [$http_code] $name"
        ((passed++))
    else
        echo -e "${RED}✗ FAIL${NC} [$http_code] $name (expected $expected_code)"
        echo "  Response: $(echo "$body" | head -c 200)"
        ((failed++))
    fi
}

echo "============================================"
echo "  VendifyPOS API Test Suite"
echo "============================================"
echo ""

# ---- PUBLIC ENDPOINTS ----
echo -e "${YELLOW}--- Public Endpoints ---${NC}"
test_endpoint "Health Check" "GET" "/health" "" "200"

# ---- AUTH ----
echo ""
echo -e "${YELLOW}--- Authentication ---${NC}"
login_response=$(curl -s -X POST "$BASE_URL/auth/login" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\",\"business_id\":$BUSINESS_ID}")

TOKEN=$(echo "$login_response" | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)

if [ -n "$TOKEN" ]; then
    echo -e "${GREEN}✓ PASS${NC} [200] Login — Token obtained"
    ((passed++))
else
    echo -e "${RED}✗ FAIL${NC} Login failed!"
    echo "  Response: $login_response"
    ((failed++))
    echo ""
    echo "Tests stopped. Fix login first."
    exit 1
fi

test_endpoint "Get User Profile" "GET" "/auth/user" "" "200"
# ---- LICENSE CHECK ----
echo ""
echo -e "${YELLOW}--- License Check ---${NC}"
test_endpoint "License Status" "GET" "/license/check" "" "200"

# ---- PRODUCTS ----
echo ""
echo -e "${YELLOW}--- Products ---${NC}"
test_endpoint "List Products" "GET" "/products?per_page=5" "" "200"
test_endpoint "Search Products" "GET" "/products?search=hair" "" "200"
test_endpoint "Get Product #1" "GET" "/products/1" "" "200"
test_endpoint "Get Product Variations" "GET" "/products/1/variations" "" "200"
test_endpoint "List Categories" "GET" "/categories" "" "200"
test_endpoint "List Brands" "GET" "/brands" "" "200"
test_endpoint "List Units" "GET" "/units" "" "200"
test_endpoint "List Tax Rates" "GET" "/tax-rates" "" "200"

# ---- CONTACTS ----
echo ""
echo -e "${YELLOW}--- Contacts ---${NC}"
test_endpoint "List Customers" "GET" "/contacts?type=customer&per_page=5" "" "200"
test_endpoint "Get Contact #3" "GET" "/contacts/3" "" "200"

# Create a test customer
create_response=$(curl -s -X POST "$BASE_URL/contacts" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer $TOKEN" \
    -d '{"name":"API Test Customer","type":"customer","mobile":"99999999"}')
NEW_CONTACT_ID=$(echo "$create_response" | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -n "$NEW_CONTACT_ID" ]; then
    echo -e "${GREEN}✓ PASS${NC} [201] Create Contact — ID: $NEW_CONTACT_ID"
    ((passed++))
else
    echo -e "${RED}✗ FAIL${NC} [201] Create Contact"
    echo "  Response: $(echo "$create_response" | head -c 200)"
    ((failed++))
fi

test_endpoint "List Customer Groups" "GET" "/customer-groups" "" "200"

# ---- SALES ----
echo ""
echo -e "${YELLOW}--- Sales / POS ---${NC}"

# Create a sale
SELL_DATA="{\"location_id\":2,\"contact_id\":3,\"products\":[{\"product_id\":1,\"variation_id\":1,\"quantity\":1,\"unit_price\":35,\"discount\":0}],\"payments\":[{\"amount\":35,\"method\":\"cash\",\"reference\":\"\"}]}"

sell_response=$(curl -s -X POST "$BASE_URL/sells" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer $TOKEN" \
    -d "$SELL_DATA")
SELL_ID=$(echo "$sell_response" | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)
SELL_HTTP=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE_URL/sells" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer $TOKEN" \
    -d "$SELL_DATA")

if [ "$SELL_HTTP" = "201" ]; then
    echo -e "${GREEN}✓ PASS${NC} [201] Create Sale — ID: $SELL_ID"
    ((passed++))
else
    echo -e "${RED}✗ FAIL${NC} [$SELL_HTTP] Create Sale"
    echo "  Response: $(echo "$sell_response" | head -c 300)"
    ((failed++))
fi

test_endpoint "List Sales" "GET" "/sells?per_page=5" "" "200"
if [ -n "$SELL_ID" ]; then
    test_endpoint "Get Sale Detail" "GET" "/sells/$SELL_ID" "" "200"
fi
test_endpoint "List Drafts" "GET" "/sells/drafts" "" "200"
test_endpoint "Daily Sales Summary" "GET" "/reports/daily-sales" "" "200"

# ---- STOCK ----
echo ""
echo -e "${YELLOW}--- Stock ---${NC}"
test_endpoint "Stock Levels" "GET" "/stock?location_id=2" "" "200"
test_endpoint "Product Stock" "GET" "/stock/product/1?location_id=2" "" "200"

# ---- SETTINGS ----
echo ""
echo -e "${YELLOW}--- Settings ---${NC}"
test_endpoint "Business Settings" "GET" "/settings" "" "200"
test_endpoint "Locations" "GET" "/locations" "" "200"
test_endpoint "Payment Methods" "GET" "/payment-methods" "" "200"
test_endpoint "Types of Service" "GET" "/types-of-service" "" "200"
test_endpoint "Tables" "GET" "/tables" "" "200"
test_endpoint "Invoice Layouts" "GET" "/invoice-layouts" "" "200"

# ---- CLEANUP ----
echo ""
echo -e "${YELLOW}--- Cleanup ---${NC}"
test_endpoint "Logout" "POST" "/auth/logout" "{}" "200"

# ---- SUMMARY ----
echo ""
echo "============================================"
echo -e "  Results: ${GREEN}$passed passed${NC}, ${RED}$failed failed${NC}"
echo "============================================"

if [ $failed -eq 0 ]; then
    echo -e "${GREEN}All tests passed! ✓${NC}"
else
    echo -e "${RED}$failed test(s) failed. Review above.${NC}"
fi
