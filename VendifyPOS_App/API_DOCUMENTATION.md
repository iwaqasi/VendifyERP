# VendifyPOS API v1 — Complete Documentation

**Base URL:** `http://your-server.com/api/v1`
**Authentication:** Laravel Passport (Bearer Token)
**Content-Type:** `application/json`

---

## Authentication

### Login
```
POST /api/v1/auth/login
```

**Request:**
```json
{
    "email": "pos@test.com",
    "password": "pos123",
    "business_id": 2,
    "device_name": "pos-app"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOi...",
        "token_type": "Bearer",
        "expires_at": null,
        "user": {
            "id": 6,
            "name": "POS Test",
            "email": "pos@test.com",
            "business_id": 2,
            "business_name": "Coiffured Mind and Body Resort",
            "default_location_id": 2,
            "default_location_name": "Coiffured Mind and Body Resort",
            "roles": ["POS Operator"]
        }
    }
}
```

### Logout
```
POST /api/v1/auth/logout
Authorization: Bearer {token}
```

### Refresh Token
```
POST /api/v1/auth/refresh
Authorization: Bearer {token}
```

### Get User Profile
```
GET /api/v1/auth/user
Authorization: Bearer {token}
```

---

## Products

### List Products
```
GET /api/v1/products?search=hair&category_id=1&type=service&location_id=2&per_page=20
Authorization: Bearer {token}
```

**Query Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| search | string | Search by name, SKU, barcode |
| category_id | int | Filter by category |
| brand_id | int | Filter by brand |
| type | string | single, variable, service |
| location_id | int | Filter by location stock |
| per_page | int | Items per page (default: 20) |

**Response (200):**
```json
{
    "success": true,
    "message": "Success",
    "data": [
        {
            "id": 1,
            "name": "HAIRCUT",
            "sku": "0001",
            "barcode": null,
            "type": "single",
            "category_id": 1,
            "category_name": "HAIRCUT",
            "brand_name": null,
            "unit_name": "Pc(s)",
            "sell_price_inc_tax": 35,
            "product_cost_price": 0,
            "enable_stock": 0,
            "qty_available": 0,
            "is_flexible_price": false,
            "image": "http://127.0.0.1:8000/img/default.png",
            "tax_rate": 0,
            "is_service_product": false,
            "has_variations": false,
            "service_time": 60
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 20,
        "total": 2
    }
}
```

### Get Product Detail
```
GET /api/v1/products/{id}?location_id=2
Authorization: Bearer {token}
```

### Get Product Variations
```
GET /api/v1/products/{id}/variations?location_id=2
Authorization: Bearer {token}
```

### List Categories
```
GET /api/v1/categories
Authorization: Bearer {token}
```

### List Brands
```
GET /api/v1/brands
Authorization: Bearer {token}
```

### List Units
```
GET /api/v1/units
Authorization: Bearer {token}
```

### List Tax Rates
```
GET /api/v1/tax-rates
Authorization: Bearer {token}
```

---

## Contacts (Customers/Suppliers)

### List Contacts
```
GET /api/v1/contacts?search=john&type=customer&per_page=20
Authorization: Bearer {token}
```

### Get Contact Detail
```
GET /api/v1/contacts/{id}
Authorization: Bearer {token}
```

### Create Contact
```
POST /api/v1/contacts
Authorization: Bearer {token}

{
    "name": "John Smith",
    "type": "customer",
    "mobile": "+1234567890",
    "email": "john@example.com",
    "tax_number": "123456"
}
```

### Update Contact
```
PUT /api/v1/contacts/{id}
Authorization: Bearer {token}

{
    "name": "John Smith Jr",
    "mobile": "+1234567891"
}
```

### List Customer Groups
```
GET /api/v1/customer-groups
Authorization: Bearer {token}
```

---

## Sales / POS

### List Sales
```
GET /api/v1/sells?status=final&location_id=2&start_date=2026-01-01&end_date=2026-08-22&per_page=20
Authorization: Bearer {token}
```

### Get Sale Detail
```
GET /api/v1/sells/{id}
Authorization: Bearer {token}
```

### Create Sale (POS Transaction)
```
POST /api/v1/sells
Authorization: Bearer {token}

{
    "location_id": 2,
    "contact_id": 3,
    "products": [
        {
            "product_id": 1,
            "variation_id": 1,
            "quantity": 1,
            "unit_price": 35,
            "discount": 0,
            "tax_id": null,
            "service_staff_id": 5
        }
    ],
    "payments": [
        {
            "amount": 35,
            "method": "cash",
            "reference": ""
        }
    ],
    "discount_type": "fixed",
    "discount_amount": 0,
    "shipping_charges": 0,
    "additional_notes": "Walk-in customer",
    "sale_note": "Express service"
}
```

**Response (201):**
```json
{
    "success": true,
    "message": "Sale created successfully",
    "data": {
        "id": 42,
        "invoice_no": "INV-0042",
        "contact_id": 3,
        "final_total": 35,
        "payment_status": "paid",
        "status": "final",
        "sell_lines": [...],
        "payment_lines": [...]
    }
}
```

### List Drafts
```
GET /api/v1/sells/drafts?location_id=2
Authorization: Bearer {token}
```

### Delete Draft
```
DELETE /api/v1/sells/drafts/{id}
Authorization: Bearer {token}
```

### Add Payment to Sale
```
POST /api/v1/sells/{id}/payment
Authorization: Bearer {token}

{
    "amount": 50,
    "method": "cash",
    "reference": ""
}
```

### Daily Sales Summary
```
GET /api/v1/reports/daily-sales?location_id=2&date=2026-08-22
Authorization: Bearer {token}
```

---

## Stock

### List Stock Levels
```
GET /api/v1/stock?location_id=2&search=hair&low_stock=true&per_page=50
Authorization: Bearer {token}
```

### Get Product Stock
```
GET /api/v1/stock/product/{product_id}?location_id=2
Authorization: Bearer {token}
```

---

## Settings

### Business Settings
```
GET /api/v1/settings
Authorization: Bearer {token}
```

### Locations
```
GET /api/v1/locations
Authorization: Bearer {token}
```

### Payment Methods
```
GET /api/v1/payment-methods
Authorization: Bearer {token}
```

### Types of Service
```
GET /api/v1/types-of-service
Authorization: Bearer {token}
```

### Tables
```
GET /api/v1/tables
Authorization: Bearer {token}
```

### Invoice Layouts
```
GET /api/v1/invoice-layouts
Authorization: Bearer {token}
```

---

## Health Check (No Auth Required)
```
GET /api/v1/health
```

**Response:**
```json
{
    "status": "ok",
    "version": "7.0",
    "app_name": "VendifyERP",
    "timestamp": "2026-08-22 21:32:32"
}
```

---

## Error Responses

### Validation Error (422)
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email field is required."]
    }
}
```

### Unauthorized (401)
```json
{
    "message": "Unauthenticated."
}
```

### Not Found (404)
```json
{
    "success": false,
    "message": "Product not found."
}
```

### Server Error (500)
```json
{
    "success": false,
    "message": "Failed to create sale: Insufficient stock"
}
```

---

## Flutter App Integration Notes

1. **Store the access_token** securely using `flutter_secure_storage`
2. **Send Authorization header** with every request: `Authorization: Bearer {token}`
3. **Handle 401 errors** by redirecting to login screen
4. **Pagination**: Use `meta.current_page` and `meta.last_page` for infinite scroll
5. **Business context**: The `business_id` from login determines which data is returned
6. **Location context**: Most endpoints accept `location_id` for location-specific stock
