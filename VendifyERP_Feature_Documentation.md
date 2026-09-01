# VendifyERP — Complete Feature Documentation

**Version:** 7.0  
**Product:** VendifyERP  
**Industry Focus:** Multi-Industry — Salons, Spas, Retail, Repair Shops, Restaurants, Clinics, Wholesale  

---

## Table of Contents

1. [Overview](#1-overview)
2. [Dashboard](#2-dashboard)
3. [User Management](#3-user-management)
4. [Contacts Management](#4-contacts-management)
5. [Products & Inventory](#5-products--inventory)
6. [Purchases](#6-purchases)
7. [Sales & POS](#7-sales--pos)
8. [VendifyPOS Flutter Application](#8-vendifypos-flutter-application)
9. [Multi-Business Type System](#9-multi-business-type-system)
10. [Bookings & Staff Scheduling](#10-bookings--staff-scheduling)
11. [Restaurant / Kitchen Module](#11-restaurant--kitchen-module)
12. [Stock Management](#12-stock-management)
13. [Expenses & Payments](#13-expenses--payments)
14. [Accounting](#14-accounting)
15. [Reports & Analytics](#15-reports--analytics)
16. [CRM Module](#16-crm-module)
17. [Essentials (HRM)](#17-essentials-hrm)
18. [Repair Module](#18-repair-module)
19. [Customer Loyalty & Credit Management](#19-customer-loyalty--credit-management)
20. [Invoice History, Returns & Exchanges](#20-invoice-history-returns--exchanges)
21. [CMS (Content Management System)](#21-cms-content-management-system)
22. [Settings & Configuration](#22-settings--configuration)
23. [Integrations & Payment Gateways](#23-integrations--payment-gateways)
24. [Backup & System](#24-backup--system)
25. [Recently Developed Features](#25-recently-developed-features)

---

## 1. Overview

VendifyERP is a comprehensive Enterprise Resource Planning system built on Laravel, designed as a **multi-industry platform**. It serves salons, spas, retail stores, repair shops, restaurants, clinics, and wholesale businesses from a single codebase. The system combines a Laravel backend (admin panel, API, accounting) with a **Flutter-based POS application (VendifyPOS)** that adapts its interface based on the selected business type.

### Core Architecture
- **Backend:** Laravel (PHP) with RESTful API
- **POS Frontend:** Flutter (Dart) — VendifyPOS desktop/mobile app
- **Web Admin:** Blade templates with jQuery, DataTables, Select2
- **Database:** MySQL
- **Authentication:** Laravel Sanctum (token-based API auth) + PIN-based POS login
- **Role-Based Access:** Spatie Permission package with 100+ granular permissions
- **CMS Frontend:** Flutter (Dart) — VendifyCMS web application (planned)

### Multi-Business Support
- Multiple business locations per account
- **Business Type Selection** — Each business declares its type (Retail, Saloon & Spa, Repair, Restaurant, Clinic, Wholesale)
- Type-specific POS screen adaptation
- Business-specific settings, users, and products
- Location-level permissions and access control

---

## 2. Dashboard

The dashboard provides a real-time overview of business operations.

### Features:
- **Sales Overview** — Today's sales, pending payments, recent transactions
- **Purchase Overview** — Today's purchases, pending supplier payments
- **Revenue & Expense Summary** — Profit/loss snapshot
- **Stock Alerts** — Low stock and out-of-stock product notifications
- **Due Amounts** — Customer and supplier due amounts with quick-pay actions
- **Top Selling Products** — Best-performing products by quantity and revenue
- **Recent Transactions** — Latest sales and purchase activity
- **Booking Summary** — Today's bookings and upcoming appointments
- **Service Staff Availability** — Which staff are working today
- **POS Dashboard** — Daily register summary with sales, payments, and shift management
- **Customizable Widget Layout** — Configure which widgets appear via Dashboard Configurator

---

## 3. User Management

Complete user administration with role-based access control (RBAC).

### Features:
- **User CRUD** — Create, edit, view, delete users with full profile information
- **Role Management** — Create custom roles with granular permissions
- **Predefined Roles:**
  - Super Admin — Full system access
  - Admin — Full business access
  - Business Owner — Business-level access
  - Cashier / POS Operator — Sales and POS access
  - Service Staff — Booking and service access
  - Delivery Personnel — Shipment access
- **PIN-Based Login** — Service staff and cashiers can log into POS using a 4-digit PIN
- **Granular Permissions** — Over 100+ individual permissions covering every module
- **Location Access Control** — Assign specific business locations per user
- **User Types:** Service Staff, Admin, Users (regular)
- **Sales Commission Agents** — Designate users as commission agents
- **Bulk Import** — Import users via CSV

### User Profile Fields:
- First name, Last name, Surname
- Email, Contact number, Alt number, Family number
- Date of birth, Gender, Blood group, Marital status
- Permanent address, Current address
- Bank details, ID proof (name + number)
- Social media links (Facebook, Twitter, 2 custom)
- Custom fields (1–4)
- Guardian name
- **PIN** — 4-digit PIN for POS login

---

## 4. Contacts Management

Manage all business relationships — customers, suppliers, and their groups.

### Features:
- **Contact Types:** Customer, Supplier, Both (customer + supplier)
- **Contact Details:**
  - Business name, contact person, email, phone
  - Tax number, GST/VAT number
  - Shipping and billing addresses
  - Custom fields (1–6)
  - Opening balance
  - **Credit limit** — Maximum credit amount allowed per customer
  - **Payment terms** — Net days or months for credit customers
- **Contact Groups** — Categorize contacts for segmentation and reporting
- **Contact Access Control** — Restrict which contacts each user can view/edit
- **Contact Map View** — Visual map of contacts by location
- **Import/Export** — CSV import with field mapping, export to CSV/Excel
- **Due Tracking** — Outstanding balances per contact with payment history
- **Pay Due Actions** — Quick-pay customer/supplier due amounts
- **Purchase & Sales History** — View all transactions per contact
- **Document Attachments** — Attach files and notes to contacts
- **Loyalty Points Balance** — View and manage reward points per customer

---

## 5. Products & Inventory

Full product catalog management with variation support, pricing, and stock tracking.

### Product Types:
1. **Single Product** — One SKU, one barcode
2. **Variable Product** — Multiple variations (size, color, etc.)
3. **Service Product** — Non-stock items (services, appointments)

### Features:
- **Product Information:**
  - Name (English), Name (Arabic) — Multi-language support
  - SKU (auto-generated or manual), Barcode, Barcode Type
  - Category, Sub-category, Brand, Unit, Tax
  - Description (plain text + rich text)
  - Product image (upload from computer, support for product images)
  - Product type (Single, Variable, Service)
  - **On Hand Quantity** — Visible in product list
  - **Not For Selling** flag — Hide products from POS
- **Pricing:**
  - Selling Price (per variation or per product)
  - Cost Price (visible when Manage Stock is enabled)
  - **Flexible Price** — Toggle to allow/disallow price changes at POS
  - Selling Price Groups — Different prices for different customer segments
  - Multi-currency support
  - Tax-inclusive and tax-exclusive pricing
  - **Profit Margin** — Auto-calculated (shows N/A when no cost price)
- **Inventory Management:**
  - Manage Stock toggle — Enable/disable stock tracking per product
  - Opening Stock — Set initial stock quantity and value per location
  - Current Stock — Real-time stock levels per location
  - Stock Alerts — Set minimum and maximum stock levels
  - Batch/Lot tracking — Track stock by batch or lot number
  - Expiry date tracking — FIFO expiry management
- **Variations:**
  - Variation Templates — Create reusable variation sets
  - Per-variation SKU (sub_sku), barcode, selling price, images
  - Per-location stock tracking per variation
- **Product Actions:**
  - Clone product — Duplicate an existing product
  - Bulk price update — Update prices for multiple products at once
  - **Enhanced CSV Import/Export** — Full import with all fields:
    - NAME, BRAND, UNIT, CATEGORY, SUB-CATEGORY, SKU, BARCODE TYPE
    - MANAGE STOCK, ALERT QUANTITY, EXPIRES IN, EXPIRY PERIOD UNIT
    - APPLICABLE TAX, Selling Price Tax Type, PRODUCT TYPE
    - VARIATION NAME, VARIATION VALUES, VARIATION SKUs
    - PURCHASE PRICE (Including/Excluding tax), PROFIT MARGIN
    - SELLING PRICE, OPENING STOCK, LOCATION, EXPIRY DATE
    - ENABLE IMEI OR SERIAL NUMBER, WEIGHT, RACK, ROW, POSITION
    - IMAGE, PRODUCT DESCRIPTION, CUSTOM FIELDS 1–4
    - NOT FOR SELLING, PRODUCT LOCATIONS
  - **Auto-create Brands, Categories, Sub-categories** — Import script creates missing taxonomies automatically
  - **Variation Import** — Import variable products with all variation data
  - **Product Image Download** — Download and save product images during import
  - Print barcode labels — Generate and print barcode labels
  - Product taxonomy (categories, sub-categories) — Tree-based categorization
  - Units of measure — Define and manage measurement units
  - Brands — Brand management
  - Warranties — Define warranty periods and terms
- **Product Search & Filters:**
  - Search by name, SKU, barcode
  - Filter by category, brand, supplier, stock status
  - Quick add from POS screen
  - **Product Selector Widget** — Multi-select product picker with checkboxes, search, pagination, and category filter (used across Purchases, Stock Adjustments, Stock Transfers)

---

## 6. Purchases

End-to-end purchase management from requisition to payment.

### Features:
- **Product Selector** — Multi-select product picker with search, pagination, and category filter
- **Purchase Requisition** — Request items before formal purchase order
- **Purchase Orders** — Create formal POs to suppliers
- **Purchase Entry:**
  - Select supplier
  - Add multiple products with quantities and prices (via product selector or search)
  - Tax calculation
  - Shipping charges
  - Discount (flat or percentage)
  - Additional notes
  - Reference number
  - Purchase status (Received, Pending, Partial)
- **Purchase Receiving** — Mark items as received with quantities
- **Purchase Returns** — Return items to suppliers with full return workflow
- **Payment Tracking:**
  - Add payments against purchases
  - View payment history
  - Due amount tracking
- **Purchase Reports:**
  - Purchase list with filters (date range, supplier, status)
  - Purchase by product, Purchase by supplier, Purchase return report
- **Bulk Import** — Import purchase history via CSV

---

## 7. Sales & POS

Multi-channel sales management with a dedicated POS (Point of Sale) interface.

### 7.1 Point of Sale (POS) — Laravel Web
- **Touch-Optimized Interface** — Designed for fast-paced counter operations
- **Product Selection:**
  - Search by name, SKU, barcode
  - Category-based filtering
  - Quick-add buttons for frequent items
- **Cart Management:**
  - Add/remove products
  - Adjust quantities
  - Apply line-item discounts
  - Apply order-level discounts
  - Tax calculation (inclusive/exclusive)
  - Service Staff assignment per line item
- **Customer Management:**
  - Quick-add customer from POS
  - Select existing customer
  - Walk-in customer support
- **Payment Processing:**
  - Cash payment
  - Card payment
  - Multiple payment methods per transaction
  - Split payment support
  - Custom payment methods
- **POS Features:**
  - Hold/Recall transactions
  - Cash register open/close
  - Discount codes
  - Sales commission agent assignment
  - Types of service (Dine-in, Takeaway, Delivery)
  - Table assignment
  - Kitchen order printing

### 7.2 Sales (Invoice/Bill)
- **Invoice Creation:**
  - Full invoice form (similar to POS but form-based)
  - Customer selection
  - Multiple line items with products/services
  - Tax and discount calculations
  - Shipping and additional charges
- **Invoice Layouts** — Customizable invoice templates
- **Invoice Schemes** — Numbering schemes with prefixes
- **Invoice Statuses:** Final, Draft, Quotation, Cancelled
- **Draft Management** — Save incomplete invoices as drafts
- **Quotation Management** — Save as quotations, convert to invoice
- **Sales Orders** — Pre-sales order management
- **Sell Returns** — Process returns against invoices with refund workflow
- **Shipment Tracking** — Track delivery status for sold items

### 7.3 Discount Management
- **Discount Types:** Flat amount, Percentage
- **Discount Application:** Per-line or per-order
- **Discount Codes** — Create and manage promotional discount codes

### 7.4 Subscriptions
- Recurring billing management for subscription-based services

---

## 8. VendifyPOS Flutter Application ★ (Major New Module)

A complete standalone **Flutter POS application** that connects to the Laravel backend via API. It provides a modern, touch-optimized point-of-sale experience that adapts its interface based on the business type.

### 8.1 Authentication & Onboarding
- **Email + Password Login** — Login with business email and password
- **PIN-Based Quick Login** — 4-digit PIN for cashiers/service staff
- **Business Selection** — Multi-business dropdown (if user belongs to multiple businesses)
- **Business Type Selection** — On first login, select the business type which configures the POS layout
- **Token-Based Auth** — Laravel Sanctum tokens with automatic refresh
- **Location Auto-Selection** — Automatically selects the user's default location

### 8.2 Adaptive POS Layouts by Business Type

The POS screen dynamically adapts based on the selected business type:

| Business Type | Layout | Special Features |
|---|---|---|
| **Retail Store** | Product grid with categories | Barcode scanning, barcode search |
| **Saloon & Spa** | Product grid + Appointment sidebar | Toggle appointments, booking calendar, service staff |
| **Repair Shop** | Product grid + Repair tracking | Device models, repair status |
| **Restaurant** | Menu grid + Table management | Dine-in/Takeaway, kitchen display |
| **Clinic** | Product grid + Appointment sidebar | Patient management, session tracking |
| **Wholesale** | Product grid + Bulk pricing | Quantity discounts, credit management |

### 8.3 Product Display & Cart
- **Product Grid** — Visual product cards with images, prices, and stock status
- **Category Sidebar** — Filter by category with item counts
- **Search** — Search by product name, SKU, or barcode
- **Barcode Scanner Input** — Scan or type barcode/SKU to add items directly
- **Product Cards** — Compact cards showing name, price, image, and stock status (Non-stock badge for service items)
- **Cart Panel** — Right-side panel showing current sale with:
  - Customer info with avatar
  - Reward Points badge (when customer selected)
  - Barcode scan input
  - Item list with quantities and prices
  - Tender Details (items count, quantity, actual amount, discount, totals)
  - Hold Cart / Recall buttons
  - Checkout button

### 8.4 Item-Level Features
- **Quantity Adjustment** — +/- buttons and direct input
- **Item Discount** — Discount amount or percentage per line item
- **Remove Item** — Swipe or button to remove from cart
- **Price Display** — Shows unit price and line total

### 8.5 Receipt-Level Features
- **Receipt Discount** — Apply discount (% or flat) to entire order
- **Amount After Discount** — Shows discounted total
- **Tax Calculation** — Based on product tax rates and POS settings

### 8.6 Payment Screen
- **Multiple Payment Methods** — Cash, Debit Card, Visa, Mastercard, Cheque, Bank Transfer, etc.
- **Split Payment** — Pay with multiple methods (e.g., 10 KD cash + 50 KD card)
- **Payment Method Visibility** — Only shows enabled payment methods from Laravel settings; hidden if blank
- **Authentication Code** — For card/Visa/Mastercard payments, cashier can enter approval/auth code
- **Reference Number** — For cheque and bank transfer payments
- **Cash Tendered** — Enter amount given by customer, auto-calculates change
- **Credit Payment** — Option to charge to customer credit (with limit enforcement)
- **Receipt Preview** — Generated after payment with invoice number, items, totals, and barcode

### 8.7 Hold Cart & Recall
- **Hold Cart** — Save current cart to local storage to serve another customer
- **Recall Cart** — View all held carts and restore any one
- **Multiple Held Carts** — Support for holding multiple carts simultaneously
- **Cart Restore** — Full restore of items, customer, and discounts
- **Shared Service** — `HoldRecallService` used across all 6 POS layouts

### 8.8 Invoice History & Past Transactions
- **Invoice List** — View all past invoices with search and filters
- **Search Filters:**
  - Customer name or phone number
  - Invoice number
  - Date range (start date / end date)
  - Payment status (paid, unpaid, partial)
- **Invoice Cards** — Show invoice number, date, customer, total, paid amount, status badge
- **Pagination** — Paginated list with page controls
- **Invoice Detail View** — Full invoice breakdown:
  - Customer info (name, phone, email)
  - Line items with quantities, prices, and return tracking
  - Payment history with method and date
  - Totals breakdown (subtotal, discount, tax, grand total)
  - Amount Paid and Balance Due

### 8.9 Returns & Exchange
- **Return from Invoice Detail** — Select any past invoice and initiate a return
- **Item Selection** — Choose which items to return with quantity controls
- **Return Reason** — Text field for documenting the reason
- **Refund Method** — Cash, Card, Credit (to customer balance)
- **Return Total** — Auto-calculated return amount
- **Partial Returns** — Support returning some items while keeping others
- **Exchange** — Return old items and add new items in a single transaction
- **Status Tracking** — Return status on original invoice (returned quantities shown)

### 8.10 Reprint Invoice
- **Reprint Button** — On any invoice detail, reprint the receipt
- **REPRINT Watermark** — Large diagonal red "REPRINT" text overlaid on receipt (semi-transparent)
- **"REPRINTED" Label** — Clearly marked at bottom of receipt
- **Business Name** — Fetches actual business name from saved settings (not hardcoded)
- **Full Receipt Content** — Invoice number, date, customer, items, totals, payments
- **Barcode** — Code 128 barcode of invoice number at bottom for scanning

### 8.11 Credit Customer Management
- **Customer Credit Info** — When a customer is selected, shows:
  - Outstanding balance (sell due)
  - Credit limit
  - Payment terms (Net 30 days, etc.)
- **Credit Limit Enforcement** — Before checkout, checks if `sellDue + grandTotal > creditLimit`
  - Shows warning dialog with amounts
  - Options: "Cancel" or "Proceed Anyway"
- **Credit Payment Collection** — Collect payments against outstanding credit balances
  - Quick amount buttons (Full Amount, Half, custom amounts)
  - Payment method selection (Cash, Card, Bank Transfer)
  - Reference number for non-cash payments
  - Auto-applies to oldest invoices first

### 8.12 Customer Loyalty Points (Reward Points)
- **Points Display** — Shows customer's reward points in cart panel (e.g., "114 Reward Points")
- **Equivalent Value** — Shows points value in currency (e.g., "Worth: KD 114.000")
- **Redeem Button** — Opens redemption dialog
- **Redemption Dialog:**
  - Shows available points and equivalent amount
  - Slider to select redemption amount
  - Quick buttons (25%, 50%, 75%, Max)
  - Real-time conversion display
  - Validates maximum redemption limit
- **Points Deduction** — Deducts from points balance after successful redemption
- **Points Earning** — Backend calculates points earned per transaction based on settings
- **Admin Configuration** — Enable/disable in Business Settings, configure points per currency unit

### 8.13 Daily Register / Cash Register
- **Register Screen** — Shows daily sales summary
- **Shift Management** — Open/close shift with counted cash
- **Summary Cards:**
  - Total Sales, Tax, Discounts, Refunds, Net Sales
  - Payment breakdown by method (Cash, Card, etc.)
  - Product revenue totals
- **Close Shift** — Enter counted cash, system calculates expected cash and difference
- **Register Reports** — Historical shift data

### 8.14 POS Top Bar Features
- **Invoices** — Quick access to Invoice History
- **Register** — Quick access to Daily Register
- **Inventory** — View stock levels
- **Refresh** — Reload products and settings
- **Settings** — Access POS settings
- **Logout** — Return to login screen
- **Online Status** — Green indicator showing API connection
- **User Info** — Current cashier name at bottom
- **Date/Time** — Real-time clock display

### 8.15 Appointments from POS (Saloon & Spa / Clinic)
- **Toggle Appointments Sidebar** — Show/hide appointment panel
- **Appointment Cards** — Visual cards showing customer, time, service, status
- **Status Colors:**
  - Confirmed (green), In Progress (orange), Completed (blue), Cancelled (red)
- **New Appointment** — Create appointment directly from POS:
  - Customer search/add
  - Service selection (multiple services supported)
  - Staff assignment
  - Date/time picker
- **Appointment Notifications** — Pop-up alerts when an appointment time arrives
- **Start Service** — Begins the appointment, adds services to cart
- **Complete & Pay** — Finishes service, processes payment
- **Appointment → Invoice** — Automatically creates invoice from appointment services

### 8.16 Notification System
- **Appointment Reminders** — Alert when an appointment is due
- **Visual Notification Badge** — Unread notification count
- **Quick Actions** — Start Service, View Details directly from notification

### 8.17 Staff Integration
- **Staff Fetched from Users** — Service staff populated from actual user accounts (not hardcoded)
- **Staff Selection** — Dropdown showing available staff for the current day
- **Staff Assignment** — Assign staff to appointments and service lines

---

## 9. Multi-Business Type System ★ (Major New Feature)

A flexible business type framework that allows the same application to serve different industries with appropriate features and POS layouts.

### 9.1 Business Types

| Type | Key Features | POS Layout |
|---|---|---|
| **retail** | Product grid, barcode scanning, split payment, inventory | Retail POS |
| **saloon** | Appointments sidebar, booking calendar, service staff, walk-in queue | Saloon POS |
| **repair** | Device tracking, repair status workflow, job sheets | Repair POS |
| **restaurant** | Table management, kitchen display, dine-in/takeaway/delivery | Restaurant POS |
| **clinic** | Appointments, patient management, session tracking, credit | Clinic POS |
| **wholesale** | Bulk pricing, credit management, multi-location stock | Wholesale POS |
| **other** | Generic POS with all standard features | Standard POS |

### 9.2 Business Type Selection
- **Admin Panel** — Business Settings page has a "Business Type" tab
  - Grid of 7 business types with icons, colors, and descriptions
  - One-click selection with active badge
  - "Save Business Type" button
- **Flutter POS** — First login shows Business Type selector screen
  - Visual card grid with business type icons
  - Tap to select and save
  - Redirects to appropriate POS layout
- **API Endpoint** — `POST /api/v1/business-type` and `GET /api/v1/business-type`
- **Database** — `businesses.business_type` column (enum: retail, saloon, repair, restaurant, clinic, wholesale, other)

### 9.3 Feature Flags Per Business Type

Each business type automatically enables/disables relevant features:

| Feature | Retail | Saloon | Repair | Restaurant | Clinic | Wholesale |
|---|---|---|---|---|---|---|
| Appointment Sidebar | ✗ | ✓ | ✗ | ✗ | ✓ | ✗ |
| Appointment Calendar | ✗ | ✓ | ✗ | ✗ | ✓ | ✗ |
| Service Staff | ✗ | ✓ | ✗ | ✗ | ✓ | ✗ |
| Table Management | ✗ | ✗ | ✗ | ✓ | ✗ | ✗ |
| Kitchen Display | ✗ | ✗ | ✗ | ✓ | ✗ | ✗ |
| Repair Tracking | ✗ | ✗ | ✓ | ✗ | ✗ | ✗ |
| Barcode Search | ✓ | ✓ | ✓ | ✗ | ✓ | ✓ |
| Hold/Recall Cart | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Split Payment | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Credit Management | ✓ | ✓ | ✓ | ✗ | ✓ | ✓ |
| Loyalty Points | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Customer Display | ✓ | ✓ | ✓ | ✗ | ✓ | ✓ |

### 9.4 Enabled Modules
- Based on business type, relevant Laravel modules are enabled/disabled
- **Modules:** Bookings, Repair, Restaurant/Kitchen, CRM, Accounting, Essentials (HRM)
- Configurable from admin panel per business

### 9.5 API Business Context
- **Middleware:** `ApiBusinessContext` — Automatically injects business context from auth token
- **License Verification:** `VerifyPosLicense` — Validates business license on every API call
- **Business Slug** — Each business gets a unique URL-friendly slug

---

## 10. Bookings & Staff Scheduling

A complete appointment booking system with staff schedule management and service-to-invoice conversion.

### 10.1 Booking Calendar
- **Visual Calendar Interface** — FullCalendar-based view with Day/Week/Month views
- **Create Booking:**
  - Double-click on a time slot to open the booking modal
  - Select Customer (from contacts)
  - Select Business Location
  - Set Start Time and End Time
  - **Add Service Lines** — Multiple services per booking:
    - Select service product (searchable dropdown with 150+ services)
    - Assign Service Staff
    - Quantity
    - Unit Price (editable if product has Flexible Price enabled)
    - Line Total (auto-calculated)
  - **Customer Notes** — Free-text notes for the booking
  - **Total Amount** — Auto-calculated from all service lines
- **Booking Statuses:** Pending, Confirmed, In Progress, Completed, Cancelled
- **Booking View:**
  - View booking details with service breakdown
  - Service items table with quantities and prices
  - Customer information
  - Assigned staff details
  - **Convert to Invoice** button — Converts booking to a POS invoice
- **Booking Filter:**
  - Filter by date range
  - Filter by service staff
  - Filter by status

### 10.2 Staff Scheduling
- **Schedule Management Screen** — Accessible from the Bookings page
- **Weekly Schedule per Staff Member:**
  - Define working hours for each day of the week (Sunday–Saturday)
  - Set start time and end time per day
  - Mark days as OFF (e.g., Thursday OFF)
  - Support for overnight shifts (e.g., 4 PM to 12 AM)
- **Schedule Features:**
  - Edit schedule inline with time picker inputs
  - Bulk edit — Edit all staff schedules at once
  - Visual indicators for OFF days
  - Save/Update schedule with validation
- **Staff Availability Integration:**
  - When creating a booking, staff who are OFF for that day are automatically hidden
  - Staff who are already booked for overlapping time slots are filtered out
  - Real-time availability validation prevents double-booking

### 10.3 Booking → Invoice Conversion
- **One-Click Conversion** — Convert any booking to a POS invoice
- **Pre-populated POS** — All services auto-populated in the POS screen
- **Invoice Generation** — Automatic invoice number assignment
- **Payment Processing** — Full POS payment flow (cash, card, split)
- **Booking Completion** — Automatic status update to "Completed"
- **Staff Schedule Release** — Service staff freed after completion

### 10.4 Service Product Integration
- **Flexible Price Toggle** — Products marked as "Flexible Price" allow price editing at booking time
- **Fixed Price** — Products without flexible price have locked prices
- **Cost Price** — Visible only for managed stock products
- **Service Duration** — Products can have a preparation/service time that auto-populates booking duration

---

## 11. Restaurant / Kitchen Module

For businesses with dine-in service, food preparation, and kitchen operations.

### Features:
- **Table Management:**
  - Create and manage dining tables
  - Assign tables to orders
  - Table status tracking (Available, Occupied, Reserved)
- **Order Management:**
  - Create orders linked to tables
  - Add multiple items with modifiers
  - Order status tracking
  - Order printing
- **Kitchen Display:**
  - Kitchen order screen for back-of-house
  - Real-time order updates
  - Order status management (New → In Progress → Ready)
- **Modifier Sets:**
  - Create modifier sets (e.g., "Extras", "Spice Level")
  - Assign modifiers to products
  - Modifiers with additional pricing
- **Service Staff Report** — Track staff performance and service metrics
- **Table Report** — Table utilization and revenue analysis

---

## 12. Stock Management

Comprehensive inventory control and stock movement tracking.

### Features:
- **Stock Transfers:**
  - Transfer stock between business locations
  - **Product Selector** — Multi-select products with search, pagination, category filter
  - Track transfer status (Pending, In Transit, Received)
  - Stock transfer documents
- **Stock Adjustments:**
  - **Three Types of Adjustments:**
    - Stock Adjustment — Add/remove stock quantities
    - Cost Adjustment — Modify product cost prices
    - Price Adjustment — Modify product selling prices
  - **Product Selector** — Multi-select products for adjustment
  - Reason tracking for adjustments
  - Adjustment approval workflow
- **Opening Stock:**
  - Set initial stock quantities per location
  - Import opening stock via CSV
- **Stock Reports:**
  - Current stock levels per location
  - Stock movement history
  - Stock expiry report — Track products approaching expiry
  - Lot/Batch report — Track specific batches
  - Stock adjustment report

---

## 13. Expenses & Payments

Track and manage all business expenses and payments.

### Features:
- **Expense Management:**
  - Create expenses with date, amount, category, reference
  - Attach receipts/documents
  - Expense approval workflow
- **Expense Categories:**
  - Create and manage expense categories
  - Category-level expense reporting
- **Payment Methods:**
  - Cash, Bank Transfer, Card, Cheque, Online
  - Custom payment methods (custom_pay_1 through custom_pay_7)
  - **Configurable from POS Settings** — Enable/disable payment methods per business
  - Payment account tracking
- **Payment Tracking:**
  - Add/view payments against purchases and sales
  - Due amount management
  - Payment history per contact
  - Bulk payment actions
- **Import Expenses** — Bulk import expenses via CSV

---

## 14. Accounting

Double-entry accounting with comprehensive financial reporting.

### Features:
- **Chart of Accounts:**
  - Predefined account types (Assets, Liabilities, Equity, Revenue, Expenses)
  - Create and manage custom accounts
  - Account hierarchy
- **Journal Entries:**
  - Automatic mapping of transactions to accounts
  - Manual journal entries
- **Financial Reports:**
  - **Balance Sheet** — Assets vs. Liabilities snapshot
  - **Trial Balance** — All account balances verification
  - **Cash Flow Statement** — Cash inflows and outflows
  - **Payment Account Report** — Per-account transaction history
- **Budget Management:**
  - Create budgets per account
  - Budget vs. actual comparison
- **Transaction Mapping:**
  - Map sales, purchases, expenses to specific accounts
  - Automatic account mapping based on transaction type

---

## 15. Reports & Analytics

Comprehensive reporting suite for data-driven decisions.

### Report Categories:

#### Sales Reports:
- **Profit & Loss Report** — Revenue, COGS, gross profit, net profit
- **Sale Report** — Detailed sales by date, customer, product
- **Sell Payment Report** — Payment collection analysis
- **GST Sales Report** — Tax collected on sales
- **Sales Representative Report** — Performance by sales agent
- **Service Staff Report** — Revenue by service staff member

#### Purchase Reports:
- **Purchase Report** — Purchases by date, supplier, product
- **Purchase Payment Report** — Payments to suppliers
- **GST Purchase Report** — Tax paid on purchases

#### Inventory Reports:
- **Stock Report** — Current stock levels across locations
- **Stock Expiry Report** — Products approaching or past expiry
- **Lot/Batch Report** — Stock by batch/lot number
- **Stock Adjustment Report** — Adjustment history and reasons
- **Items Report** — Product performance metrics
- **Trending Products** — Top-selling products analysis

#### Financial Reports:
- **Purchase vs. Sell Report** — Margin analysis
- **Tax Report** — Tax collected vs. paid
- **Expense Report** — Expense breakdown by category
- **Register Report** — Cash register open/close history
- **Customer Group Report** — Revenue by customer segment

#### Other Reports:
- **Customer/Supplier Report** — Contact-level financial summary
- **Activity Log** — System audit trail of all user actions
- **Table Report** — Table utilization (restaurant)
- **Service Staff Report** — Staff performance metrics

### Report Features:
- Date range filters
- Location filters
- User/agent filters
- Export to CSV, Excel, PDF
- Print-friendly layouts
- Drill-down capability

---

## 16. CRM Module

Customer Relationship Management for lead tracking and follow-ups.

### Features:
- **Contact Management:** Extended contact profiles with CRM-specific fields
- **Schedules & Follow-ups:** Create follow-up schedules per contact
- **Call Logs:** Log phone calls with contacts
- **Campaigns:** Create and manage CRM campaigns
- **Proposals:** Create proposals for contacts, convert to invoices
- **Lead Management:** Lead users assignment, lead status tracking
- **Marketplaces:** Track lead sources/marketplaces

---

## 17. Essentials (HRM)

Human Resource Management module for employee operations.

### Features:
- **Attendance Management:** Clock in/out, location-based tracking, shift-based attendance
- **Shift Management:** Create/manage shifts, assign to users, auto clock-out
- **Leave Management:** Leave types, application/approval workflow, balance tracking
- **Holiday Management:** Mark company-wide holidays
- **Payroll:** Create payroll, groups, allowances/deductions, linked to accounting
- **Sales Targets:** Set monthly/weekly targets per user
- **Documents & Notes:** Upload/share documents
- **Reminders:** Set reminders with recurrence
- **To-Do Lists:** Create/manage to-do items with comments
- **Knowledge Base:** Internal articles
- **Messaging:** Internal messaging between users

---

## 18. Repair Module

Device repair tracking and job sheet management.

### Features:
- **Repair Statuses:** Custom statuses with colors, email notification on change
- **Device Management:** Device models linked to brands
- **Job Sheets:** Create job sheets, track progress, custom fields, parts used
- **Repair Permissions:** `repair.create`, `repair.view`, `repair.update`, `repair.delete`
- **Integration with Sales:** Link repairs to transactions, create invoices

---

## 19. Customer Loyalty & Credit Management ★ (Newly Developed)

A comprehensive customer loyalty and credit management system integrated into the POS.

### 19.1 Loyalty Points System
- **Points Earning** — Customers earn points on each transaction
- **Points Display** — Customer's current points shown in POS cart panel
- **Points Redemption** — Customers can redeem points for discounts
  - Slider-based redemption with quick buttons (25%, 50%, 75%, Max)
  - Real-time conversion to currency
  - Validated against available points
- **Backend Configuration:**
  - Enable/disable loyalty per business
  - Points per currency unit (e.g., 1 point per 1 KD)
  - Points name customization
  - Minimum points for redemption
  - Points expiry policy

### 19.2 Credit Management
- **Credit Customers** — Customers can purchase on credit
- **Credit Limit** — Set maximum credit amount per customer
- **Payment Terms** — Define payment terms (Net 30 days, Net 60 days, etc.)
- **Balance Display** — Shows current balance, due amount, and credit limit in POS
- **Credit Limit Enforcement** — Warning when checkout exceeds credit limit
- **Credit Payment Collection** — Collect payments against outstanding balances
  - Quick amount buttons (Full, Half, custom)
  - Payment method selection
  - Reference number for non-cash payments
  - Auto-applies to oldest invoices first

### 19.3 Customer Information at POS
- **Customer Tile** — Shows credit limit badge, due amount, payment terms
- **Cart Panel** — Credit info box when customer is selected
- **Add Customer from POS** — Full customer creation form with:
  - First Name, Last Name, Mobile, Email
  - Tax Number, Address
  - Credit Limit
  - Payment Terms (number + type: days/months)
- **Customer Search** — Search by name, phone, or email

---

## 20. Invoice History, Returns & Exchanges ★ (Newly Developed)

Complete invoice management with returns, exchanges, and credit collection.

### 20.1 Invoice History
- **Invoice List** — Paginated list of all past invoices
- **Search & Filters:**
  - Customer name or phone number
  - Invoice number
  - Date range (start date / end date)
  - Payment status (paid, unpaid, partial)
- **Invoice Cards** — Show key info: invoice number, date, customer, total, paid, status
- **Status Badges** — Color-coded: PAID (green), UNPAID (red), PARTIAL (orange)

### 20.2 Invoice Detail View
- **Customer Info** — Name, phone, email
- **Line Items** — All products/services with quantities, prices, discounts
- **Return Tracking** — Shows returned quantities per item
- **Payment History** — All payments with method and date
- **Totals Breakdown** — Subtotal, discount, tax, grand total, amount paid, balance due
- **Action Buttons:**
  - **Reprint Invoice** — Receipt with REPRINT watermark and barcode
  - **Return/Exchange** — Initiate return flow
  - **Collect Payment** — Credit payment collection (only shown if balance > 0)

### 20.3 Returns & Exchange
- **Return Flow:**
  1. Select invoice from history
  2. Click "Return/Exchange"
  3. Select items to return with quantity controls
  4. Enter return reason
  5. Choose refund method (Cash, Card, Credit)
  6. Process return
- **Partial Returns** — Return some items while keeping others
- **Exchange** — Return old items and add new items
- **Return Status** — Original invoice shows returned quantities

### 20.4 Invoice Reprint
- **Reprint Receipt** — Regenerate receipt for any past invoice
- **REPRINT Watermark** — Diagonal red "REPRINT" text (semi-transparent) across receipt
- **"REPRINTED" Label** — Clearly marked at bottom
- **Barcode** — Code 128 barcode of invoice number for quick scanning
- **Business Name** — Dynamically fetched from saved settings

---

## 21. CMS (Content Management System) ★ (Planned/In Development)

A Flutter-based web application for managing the business website.

### 21.1 Architecture
- **Backend API** — Laravel API endpoints for CMS content
- **Frontend** — Flutter web application (VendifyCMS)
- **Preview** — Live preview of the website from admin panel

### 21.2 CMS Features (In Development)
- **Navigation Menu** — Manage website navigation links
- **Page Builder** — Drag-and-drop page creation
- **Content Sections** — Hero banners, product showcases, testimonials
- **Blog/Posts** — Create and manage blog posts
- **Product Catalog** — Display products on the website
- **Media Library** — Upload and manage images/videos
- **SEO Settings** — Meta titles, descriptions, URLs
- **Multi-Language** — English/Arabic support with language switching
- **Business Type Templates** — Different website templates per business type

### 21.3 CMS API Endpoints
- `GET /api/v1/cms/home` — Homepage data
- `GET /api/v1/cms/products` — Product listings
- `GET /api/v1/cms/categories` — Category listings
- `GET /api/v1/cms/posts` — Blog posts
- `GET /api/v1/cms/pages` — Page content
- `GET /api/v1/cms/navigation` — Navigation menu

---

## 22. Settings & Configuration

Comprehensive system configuration for business operations.

### Business Settings:
- **Business Profile:**
  - Business name, logo, address
  - GST/VAT number
  - Default currency, timezone
  - **Business Type** — Select industry type (Retail, Saloon, Repair, Restaurant, Clinic, Wholesale)
- **Business Locations:**
  - Create multiple locations
  - Location-specific settings
  - Location-level permissions
  - Printer configuration per location
- **Invoice Settings:**
  - Invoice schemes (numbering format, prefixes)
  - Invoice layouts (customizable templates)
  - T&C (Terms & Conditions) per layout
  - Footer text
  - Show/hide fields on invoice

### Product Settings:
- **Barcode Settings:** Barcode format configuration, label printing setup
- **Tax Rates:** Create and manage tax rates, group taxes
- **Units:** Create measurement units with relationships
- **Selling Price Groups:** Create price tiers (Wholesale, Retail, VIP)

### POS Settings ★ (Enhanced):
- **VendifyPOS Configuration:**
  - Receipt prefix (e.g., "INV-POS-")
  - Default payment method
  - Tax behavior (inclusive/exclusive)
  - Currency symbol
  - Receipt footer text
  - Customer display enable/disable
  - Hold & Recall Cart enable/disable
  - Split Payment enable/disable
  - Auth Code required for card payments
- **Feature Flags (Synced to Flutter POS):**
  - Disable Pay Checkout
  - Disable Draft
  - Disable Express Checkout
  - Hide Product Suggestion
  - Hide Recent Transactions
  - Disable Discount
  - Disable Order Tax
  - POS Subtotal Editable
- **Payment Methods Configuration:**
  - Enable/disable: Cash, Card, Cheque, Bank Transfer, Other
  - Custom payment methods 1-7 (show/hide based on configuration)
- **Legacy POS Settings:**
  - Amount rounding method
  - Commission calculation type
  - Cash denominations
  - Razor Pay / Stripe integration keys

### Notification Templates:
- Email and SMS notification templates with template variables

### Roles & Permissions:
- Custom role creation with granular permission assignment
- Location-level access control

---

## 23. Integrations & Payment Gateways

### Payment Gateway Integrations:
- **MyFatoorah** — Kuwait/Saudi Arabia/UAE/Qatar/Bahrain/Oman/Jordan/Egypt
- **PayPal** — Global online payments
- **Paystack** — African online payments
- **PesaPal** — East African payments
- **Cash on Delivery** — Built-in COD support

### Other Integrations:
- **OpenAI** — AI-powered features
- **Printer Integration:** Thermal (80mm, 58mm), A4, Network printers, Kitchen order printing
- **Email Integration:** SMTP configuration, transactional emails
- **Barcode Scanner** — USB/Bluetooth barcode scanner support in POS
- **Flutter Barcode Package** — Code 128 barcode generation for receipts

---

## 24. Backup & System

### Features:
- **Database Backup:** Manual and scheduled backups, download files
- **System Restore:** Restore from backup, migration management
- **Activity Log:** Track all user actions with filters
- **Modules:** Install/uninstall modules (Accounting, CRM, Essentials, Repair)

---

## 25. Recently Developed Features

Comprehensive list of all features developed during the VendifyERP customization:

### 25.1 VendifyPOS Flutter Application
- Complete Flutter POS application with API integration
- Email/password and PIN-based authentication
- Business type selection with adaptive POS layouts
- 6 industry-specific POS layouts (Retail, Saloon, Repair, Restaurant, Clinic, Wholesale)
- Product grid with categories, search, and barcode scanning
- Cart management with item-level and receipt-level discounts
- Hold Cart & Recall across all layouts
- Split payment with multiple payment methods
- Authentication code capture for card payments
- Daily register/cash register management
- Invoice history with search and filters
- Invoice detail with full breakdown
- Reprint invoice with REPRINT watermark and barcode
- Returns & Exchange flow
- Credit payment collection
- Customer loyalty points display and redemption
- Credit limit enforcement at checkout
- POS settings synchronization from Laravel admin
- Online status indicator and real-time clock

### 25.2 Multi-Business Type System
- Business type selection in admin panel
- 7 business types with unique POS layouts
- Feature flags per business type
- Enabled modules per business type
- API business context middleware
- License verification middleware

### 25.3 Customer Management Enhancement
- Add customer from POS with all fields (email, tax, address, credit limit, payment terms)
- Customer credit info display in POS
- Credit limit enforcement
- Payment terms (Net days/months)
- Credit payment collection

### 25.4 Product Management Enhancement
- Multi-language support (English/Arabic product names)
- Enhanced CSV import with all fields
- Auto-create brands, categories, sub-categories during import
- Variation import support
- Product image download during import
- Product Selector widget with multi-select, search, pagination

### 25.5 Stock Management Enhancement
- Three types of adjustments (Stock, Cost, Price)
- Product Selector for adjustments and transfers
- Pagination support

### 25.6 Booking System Enhancement
- Multi-service bookings
- Appointment creation from POS
- Appointment notifications
- Staff fetched from user accounts
- Service staff filtering by availability
- Booking → Invoice conversion
- Flexible price products

### 25.7 Invoice & Sales Enhancement
- Invoice reprint with REPRINT watermark
- Code 128 barcode on receipts for scanning
- Invoice history with comprehensive filters
- Returns and exchange flow
- Credit payment collection
- Amount paid tracking on transactions

### 25.8 Bug Fixes
- String-to-Double conversion fixes (API returns amounts as Strings)
- Payment amount_paid column fix (added to transactions table)
- Render overflow fixes on cart panel
- Hardcoded business name fixes in receipts
- Hold/Recall working across all 6 POS layouts
- Product selector integration with purchase, adjustment, and transfer screens
- Appointments toggle button fixes
- Staff fetching from user accounts
- Customer selection from POS contacts
- Multiple services in appointments
- Service quantity duplication fix
- Daily register String parsing fixes
- Business type save/update fixes
- Enabled modules persistence fix

---

## Appendix A: Database Schema Highlights

### Core Tables:
- `users` — User accounts with roles and PIN
- `contacts` — Customers and suppliers with credit limits and payment terms
- `products` — Product catalog with multi-language names
- `product_variations` — Product variations
- `variations` — Variation options with sub_sku and barcode
- `transactions` — Sales and purchases with amount_paid and amount_remaining
- `transaction_sell_lines` — Sales line items
- `transaction_purchase_lines` — Purchase line items
- `transaction_payments` — Payment records
- `businesses` — Business accounts with business_type field

### Tables Added/Modified:
- `transactions.amount_paid` — Amount already paid (DECIMAL 25,4)
- `transactions.amount_remaining` — Amount remaining (DECIMAL 25,4)
- `transactions.discount_type` — Receipt-level discount type
- `transactions.discount_amount` — Receipt-level discount amount
- `staff_schedules` — Weekly staff availability schedules
- `booking_services` — Service lines within bookings
- `bookings` — Booking records with status and notes
- `businesses.business_type` — Industry type (retail, saloon, repair, etc.)
- `contacts.credit_limit` — Maximum credit amount
- `contacts.pay_term_number` — Payment term number
- `contacts.pay_term_type` — Payment term type (days/months)
- `variation_location_details` — Stock per variation per location

---

## Appendix B: API Route Summary

### Auth Routes:
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/login` | Email/password login with business_id |
| POST | `/api/v1/auth/login-by-pin` | PIN-based quick login |
| GET | `/api/v1/auth/me` | Get current user info |

### POS Routes:
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/pos-settings` | Get POS settings and features |
| GET | `/api/v1/products` | List products with search and filters |
| GET | `/api/v1/categories` | List categories |
| GET | `/api/v1/contacts` | List customers/suppliers |
| POST | `/api/v1/contacts` | Create new customer from POS |
| GET | `/api/v1/contacts/{id}` | Get customer details (credit info) |
| POST | `/api/v1/sells` | Create a new sale |
| GET | `/api/v1/sells` | List invoices with filters |
| GET | `/api/v1/sells/{id}` | Get invoice details |
| POST | `/api/v1/sells/{id}/return` | Process a return |
| POST | `/api/v1/contacts/{id}/pay-credit` | Collect credit payment |
| GET | `/api/v1/reward-points/{contactId}` | Get customer loyalty points |
| POST | `/api/v1/reward-points/{contactId}/redeem` | Redeem loyalty points |

### Business Type Routes:
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/business-type` | Get current business type |
| POST | `/api/v1/business-type` | Update business type |

### Shift/Daily Register Routes:
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/shifts/current` | Get current shift |
| POST | `/api/v1/shifts/open` | Open shift |
| POST | `/api/v1/shifts/close` | Close shift |

### Booking Routes:
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/bookings` | List bookings |
| POST | `/api/v1/bookings` | Create booking |
| PUT | `/api/v1/bookings/{id}` | Update booking |
| PUT | `/api/v1/bookings/{id}/status` | Update booking status |

### CMS Routes:
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/cms/home` | Homepage data |
| GET | `/api/v1/cms/products` | Product listings |
| GET | `/api/v1/cms/categories` | Categories |
| GET | `/api/v1/cms/posts` | Blog posts |

### Standard Laravel Routes:
| Module | URL Pattern | Description |
|--------|-------------|-------------|
| Dashboard | `/home` | Main dashboard |
| Users | `/users` | User management |
| Roles | `/roles` | Role management |
| Contacts | `/contacts` | Contact management |
| Products | `/products` | Product catalog |
| Purchases | `/purchases` | Purchase management |
| Sales | `/sells` | Sales/invoice management |
| POS | `/pos` | Web POS |
| Bookings | `/bookings` | Booking calendar |
| Staff Schedules | `/staff-schedules` | Staff schedule management |
| Kitchen | `/modules/kitchen` | Kitchen display |
| Orders | `/modules/orders` | Restaurant orders |
| Tables | `/modules/tables` | Table management |
| Stock Transfer | `/stock-transfers` | Stock transfers |
| Stock Adjustment | `/stock-adjustments` | Stock adjustments |
| Expenses | `/expenses` | Expense management |
| Reports | `/report` | All reports |
| Accounting | `/account` | Accounting module |
| Settings | `/business` | Business settings |
| CMS | `/cms` | CMS management |
| Backup | `/backup` | System backup |

---

## Appendix C: Permission Matrix

| Permission | Admin | Cashier | Service Staff |
|-----------|-------|---------|---------------|
| user.create | ✓ | ✗ | ✗ |
| product.create | ✓ | ✗ | ✗ |
| sell.create | ✓ | ✓ | ✗ |
| sell.view | ✓ | ✓ | ✗ |
| purchase.create | ✓ | ✗ | ✗ |
| expense.create | ✓ | ✓ | ✗ |
| contacts.create | ✓ | ✓ | ✗ |
| booking.view | ✓ | ✓ | ✓ |
| booking.create | ✓ | ✓ | ✗ |
| direct_sell.access | ✓ | ✓ | ✗ |
| stock_transfer.view | ✓ | ✗ | ✗ |
| repair.create | ✓ | ✗ | ✗ |
| repair.view | ✓ | ✓ | ✓ |

---

## Appendix D: Flutter Application Architecture

### VendifyPOS Directory Structure:
```
vendify_pos/
├── lib/
│   ├── main.dart                    # App entry point
│   ├── config/
│   │   └── api_config.dart          # API base URL and settings
│   ├── models/
│   │   ├── product.dart             # Product model
│   │   ├── category.dart            # Category model
│   │   ├── contact.dart             # Contact model with credit fields
│   │   └── cart_item.dart           # Cart item model
│   ├── services/
│   │   ├── pos_service.dart         # API service for all POS endpoints
│   │   └── auth_service.dart        # Authentication service
│   ├── screens/
│   │   ├── login_screen.dart        # Email/password login
│   │   ├── pin_login_screen.dart    # PIN-based quick login
│   │   ├── business_type_screen.dart # Business type selection
│   │   ├── pos/
│   │   │   ├── layouts/
│   │   │   │   ├── retail_pos_screen.dart
│   │   │   │   ├── saloon_pos_screen.dart
│   │   │   │   ├── repair_pos_screen.dart
│   │   │   │   ├── restaurant_pos_screen.dart
│   │   │   │   ├── clinic_pos_screen.dart
│   │   │   │   └── wholesale_pos_screen.dart
│   │   │   ├── widgets/
│   │   │   │   ├── cart_panel.dart
│   │   │   │   ├── receipt_preview.dart
│   │   │   │   ├── hold_recall_service.dart
│   │   │   │   ├── booking_dialog.dart
│   │   │   │   └── appointment_detail_dialog.dart
│   │   │   ├── customer_selection_sheet.dart
│   │   │   ├── invoice_history_screen.dart
│   │   │   ├── invoice_detail_screen.dart
│   │   │   ├── sell_return_screen.dart
│   │   │   ├── credit_payment_screen.dart
│   │   │   └── daily_register_screen.dart
│   └── utils/
│       └── helpers.dart             # Utility functions
├── pubspec.yaml
└── android/ ios/ windows/           # Platform-specific configs
```

### VendifyCMS Directory Structure:
```
vendify_cms/
├── lib/
│   ├── main.dart
│   ├── screens/
│   └── services/
├── pubspec.yaml
└── web/
```

---

*Document generated for VendifyERP V7.0*  
*Last Updated: August 30, 2026*
