# VendifyPOS — Cross-Platform Point of Sale Application

## Implementation Plan

**Product:** VendifyPOS (Standalone POS App)
**Platforms:** Windows Desktop, Android, iOS
**Framework:** Flutter (single codebase, 3 platforms)
**Backend:** VendifyERP (Laravel REST API)
**Target Release:** Phase 1 (MVP) → Phase 2 (Full Feature) → Phase 3 (Advanced)

---

## 1. Architecture Overview

```
┌─────────────────────────────────────────────────────┐
│                   VendifyPOS App                     │
│               (Flutter - Single Codebase)            │
│                                                     │
│  ┌───────────┐  ┌───────────┐  ┌───────────────┐   │
│  │  Windows  │  │  Android  │  │      iOS      │   │
│  │  Desktop  │  │  Mobile   │  │    Mobile     │   │
│  └───────────┘  └───────────┘  └───────────────┘   │
│                                                     │
│  ┌─────────────────────────────────────────────┐    │
│  │              Shared Flutter UI               │    │
│  │  POS Screen │ Products │ Reports │ Settings  │    │
│  └─────────────────────────────────────────────┘    │
│                     │  REST API  │                   │
└─────────────────────┼───────────┼───────────────────┘
                      │           │
              ┌───────▼───────────▼───────┐
              │     VendifyERP Backend     │
              │     (Laravel REST API)     │
              │                            │
              │  Auth │ Products │ Sales   │
              │  Contacts │ Stock │ Reports │
              │  Bookings │ Staff  │        │
              └────────────────────────────┘
                      │
              ┌───────▼───────┐
              │    MySQL DB    │
              └───────────────┘
```

---

## 2. Technology Stack

| Layer | Technology | Rationale |
|-------|-----------|-----------|
| **App Framework** | Flutter 3.x | Single codebase for Windows, Android, iOS |
| **State Management** | Riverpod or Bloc | Clean separation, testable |
| **HTTP Client** | Dio | Interceptors, retry, token refresh |
| **Local Storage** | Hive / SQLite (Drift) | Offline cache, draft transactions |
| **POS Hardware** | Flutter POS plugins | Thermal printer, barcode scanner, cash drawer |
| **Payment Integration** | Stripe SDK (Flutter) | Card payments on mobile |
| **Backend** | Laravel REST API | Existing VendifyERP backend |
| **Authentication** | Laravel Passport (OAuth2) | Token-based auth with refresh |
| **Real-time** | WebSocket / Laravel Echo | Order updates, kitchen display |

---

## 3. Phase 1 — MVP (Weeks 1–6)

### Goal: A working POS app that can process sales on all 3 platforms

### 3.1 Backend: Laravel REST API

#### Authentication API
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/login` | Email/password login, returns access token |
| POST | `/api/v1/auth/logout` | Invalidate token |
| POST | `/api/v1/auth/refresh` | Refresh access token |
| GET | `/api/v1/auth/user` | Get authenticated user profile |

#### Product API
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/products` | List products (paginated, searchable) |
| GET | `/api/v1/products/{id}` | Product detail with variations |
| GET | `/api/v1/products/{id}/variations` | Product variations with stock |
| GET | `/api/v1/categories` | Category tree |
| GET | `/api/v1/brands` | Brand list |
| GET | `/api/v1/units` | Unit list |
| GET | `/api/v1/tax-rates` | Tax rate list |

#### Contact API
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/contacts` | List contacts (paginated, searchable) |
| GET | `/api/v1/contacts/{id}` | Contact detail |
| POST | `/api/v1/contacts` | Create quick customer |
| GET | `/api/v1/customer-groups` | Customer group list |

#### Sale / POS API
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/sells` | Create a sale (POS transaction) |
| GET | `/api/v1/sells` | List sales (paginated) |
| GET | `/api/v1/sells/{id}` | Sale detail with line items |
| PUT | `/api/v1/sells/{id}` | Update sale |
| DELETE | `/api/v1/sells/{id}` | Delete sale |
| POST | `/api/v1/sells/{id}/payment` | Add payment to sale |
| GET | `/api/v1/sells/drafts` | List draft sales |
| GET | `/api/v1/sells/quotations` | List quotations |
| POST | `/api/v1/sells/{id}/convert-to-final` | Convert draft/quotation to final |
| POST | `/api/v1/sells/{id}/convert-to-invoice` | Convert booking to invoice |

#### Stock API
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/stock` | Stock levels (per location, per product) |
| GET | `/api/v1/stock/{locationId}` | Stock for specific location |

#### Settings API
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/settings` | Business settings (currency, tax, etc.) |
| GET | `/api/v1/locations` | Business locations |
| GET | `/api/v1/payment-methods` | Available payment methods |
| GET | `/api/v1/printers` | Printer configurations |

#### Reports API (Basic)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/reports/daily-sales` | Today's sales summary |
| GET | `/api/v1/reports/register` | Cash register report |
| GET | `/api/v1/reports/top-products` | Top selling products |

### 3.2 Flutter App: Screens & Features

#### Screen 1: Login
- Email / username + password
- Remember me (store token securely)
- Server URL configuration (for different environments)
- Auto-login if token exists

#### Screen 2: POS Main Screen (Core)
- **Product Grid/List:**
  - Product cards with image, name, price
  - Search bar (search by name, SKU, barcode)
  - Category filter tabs
  - Pagination / infinite scroll
- **Cart Panel:**
  - Added items with quantity, price
  - Edit quantity (tap to adjust)
  - Remove item
  - Line discount
  - Order-level discount
  - Tax display
  - Subtotal, Tax, Total
- **Actions Bar:**
  - Hold (save as draft)
  - Recall (load held transaction)
  - Discount button
  - Customer selection
  - Payment button

#### Screen 3: Payment
- **Payment Methods:**
  - Cash (with quick amount buttons: exact, $5, $10, $20, custom)
  - Card (manual entry or card reader integration)
  - Multiple payment methods (split payment)
- **Amount Display:**
  - Total due
  - Amount tendered
  - Change due
- **Actions:**
  - Process payment
  - Cancel
  - Print receipt

#### Screen 4: Receipt
- Digital receipt display
- Print receipt (thermal printer)
- Email receipt
- Share receipt (WhatsApp, SMS, etc.)

#### Screen 5: Hold / Recall Transactions
- List of held (draft) transactions
- Tap to recall into POS
- Delete held transactions

#### Screen 6: Customer Quick-Add
- Name, phone, email
- Quick create without leaving POS

#### Screen 7: Settings
- Server URL configuration
- Printer configuration
- Cash register open/close
- Business info display
- Logout

### 3.3 Platform-Specific Features

| Feature | Windows | Android | iOS |
|---------|---------|---------|-----|
| Thermal Printer | USB/Network printer | Bluetooth/Network | Bluetooth/AirPrint |
| Barcode Scanner | USB scanner (keyboard mode) | Camera (ML Kit) | Camera (AVFoundation) |
| Cash Drawer | Via printer port (RJ11) | Via printer | Via printer |
| Card Reader | External USB reader | Bluetooth reader | Bluetooth reader |
| Offline Mode | Local SQLite cache | Local SQLite cache | Local SQLite cache |
| Payments | Manual entry | Stripe Terminal SDK | Stripe Terminal SDK |
| Receipt Printing | Direct printer | Bluetooth printer | AirPrint / Bluetooth |

---

## 4. Phase 2 — Full Feature (Weeks 7–12)

### 4.1 Enhanced POS Features
- **Barcode scanning** with camera (ML Kit / AVFoundation)
- **Thermal printer integration** (Bluetooth on mobile, USB/Network on Windows)
- **Cash drawer** open/close via printer port
- **Customer display** support (customer-facing screen)
- **Keyboard shortcuts** (Windows)
- **Product variations** selection (size, color picker)
- **Modifier selection** (extras, add-ons)
- **Service staff assignment** per line item
- **Sales commission tracking**
- **Multi-currency support**
- **Offline mode** with sync queue

### 4.2 Product Management
- View product catalog
- View stock levels per location
- Quick product search with barcode
- Product images display
- Variation/option selection

### 4.3 Contact Management
- View customer list
- View customer details
- View customer purchase history
- Create new customers
- Customer due balance display

### 4.4 Reports (On-Device)
- Daily sales summary
- Sales by product
- Sales by category
- Payment method breakdown
- Top selling products
- Cash register report
- Export to PDF / share

### 4.5 Bookings Integration
- View today's bookings
- View staff schedule
- Create quick booking from POS
- Convert booking to sale

### 4.6 Receipt Customization
- Custom receipt header/footer
- Business logo on receipt
- Item-level details
- Tax breakdown
- Thank you message
- QR code / barcode on receipt

---

## 5. Phase 3 — Advanced (Weeks 13–18)

### 5.1 Advanced Features
- **Multi-location support** — Switch between locations
- **Inventory management** — Stock adjustments from app
- **Purchase orders** — Create POs on the go
- **Expense tracking** — Quick expense entry
- **Staff clock-in/out** — Attendance from POS
- **Kitchen Display System** — Order routing to kitchen
- **Real-time sync** — WebSocket for live updates
- **Offline-first architecture** — Full offline with background sync
- **Push notifications** — Low stock alerts, new orders
- **Analytics dashboard** — Charts and trends
- **Multi-language support** — English, Arabic, etc.
- **Dark mode** — Theme support

### 5.2 Hardware Integration
- **Card readers** — Stripe Terminal, SumUp, Square
- **Barcode scanners** — Camera-based with ML Kit
- **Receipt printers** — ESC/POS commands via `esc_pos_printer` package
- **Cash drawers** — Trigger via printer port
- **Customer displays** — External display support
- **Weighing scales** — Integration for weight-based products

### 5.3 Enterprise Features
- **Role-based access** — Cashier, Manager, Admin views
- **Shift management** — Open/close shift with cash count
- **Void/refund** — Process voids and refunds
- **Split bills** — Split a bill across multiple payment methods
- **Tip management** — Add tips to transactions
- **Quote-to-invoice** — Convert quotes to final invoices
- **Loyalty program** — Points and rewards
- **Gift cards** — Create and redeem gift cards

---

## 6. Project Structure

```
vendifypos/
├── lib/
│   ├── main.dart
│   ├── app.dart
│   │
│   ├── config/
│   │   ├── api_config.dart
│   │   ├── theme.dart
│   │   └── constants.dart
│   │
│   ├── models/
│   │   ├── user.dart
│   │   ├── product.dart
│   │   ├── variation.dart
│   │   ├── cart_item.dart
│   │   ├── contact.dart
│   │   ├── transaction.dart
│   │   ├── transaction_line.dart
│   │   ├── payment.dart
│   │   ├── category.dart
│   │   ├── brand.dart
│   │   ├── tax_rate.dart
│   │   ├── business_settings.dart
│   │   ├── location.dart
│   │   └── stock_level.dart
│   │
│   ├── services/
│   │   ├── api_service.dart
│   │   ├── auth_service.dart
│   │   ├── product_service.dart
│   │   ├── contact_service.dart
│   │   ├── pos_service.dart
│   │   ├── stock_service.dart
│   │   ├── report_service.dart
│   │   ├── printer_service.dart
│   │   ├── sync_service.dart
│   │   └── storage_service.dart
│   │
│   ├── providers/
│   │   ├── auth_provider.dart
│   │   ├── product_provider.dart
│   │   ├── cart_provider.dart
│   │   ├── contact_provider.dart
│   │   ├── settings_provider.dart
│   │   ├── printer_provider.dart
│   │   └── sync_provider.dart
│   │
│   ├── screens/
│   │   ├── login/
│   │   │   └── login_screen.dart
│   │   ├── pos/
│   │   │   ├── pos_screen.dart
│   │   │   ├── product_grid.dart
│   │   │   ├── cart_panel.dart
│   │   │   ├── payment_screen.dart
│   │   │   ├── receipt_screen.dart
│   │   │   └── hold_screen.dart
│   │   ├── products/
│   │   │   ├── product_list_screen.dart
│   │   │   └── product_detail_screen.dart
│   │   ├── contacts/
│   │   │   ├── customer_list_screen.dart
│   │   │   └── quick_add_customer.dart
│   │   ├── reports/
│   │   │   ├── daily_sales_screen.dart
│   │   │   ├── top_products_screen.dart
│   │   │   └── register_report_screen.dart
│   │   ├── bookings/
│   │   │   ├── bookings_screen.dart
│   │   │   └── staff_schedule_screen.dart
│   │   └── settings/
│   │       ├── settings_screen.dart
│   │       └── printer_settings_screen.dart
│   │
│   ├── widgets/
│   │   ├── product_card.dart
│   │   ├── cart_tile.dart
│   │   ├── payment_button.dart
│   │   ├── search_bar.dart
│   │   ├── category_tabs.dart
│   │   ├── quantity_selector.dart
│   │   ├── price_display.dart
│   │   └── loading_indicator.dart
│   │
│   ├── utils/
│   │   ├── formatters.dart
│   │   ├── validators.dart
│   │   └── platform_utils.dart
│   │
│   └── offline/
│       ├── database.dart
│       ├── sync_queue.dart
│       └── conflict_resolver.dart
│
├── android/
├── ios/
├── windows/
│
├── pubspec.yaml
└── README.md
```

---

## 7. Key Flutter Packages

| Package | Purpose |
|---------|---------|
| `dio` | HTTP client with interceptors |
| `flutter_riverpod` | State management |
| `hive` / `drift` | Local database for offline |
| `esc_pos_printer` | Thermal printer (ESC/POS) |
| `flutter_bluetooth_serial` | Bluetooth on Android |
| `mobile_scanner` | Barcode scanning (camera) |
| `usb_serial` | USB printer/scanner on Windows |
| `flutter_usb_printer` | USB printer on Android |
| `stripe_terminal` | Card reader integration |
| `shared_preferences` | Token storage |
| `flutter_secure_storage` | Secure credential storage |
| `path_provider` | File paths |
| `pdf` / `printing` | PDF receipt generation |
| `connectivity_plus` | Network status detection |
| `flutter_local_notifications` | Local notifications |
| `permission_handler` | Camera, bluetooth, storage permissions |
| `cached_network_image` | Product image caching |
| `hive_flutter` | Hive initialization |

---

## 8. API Authentication Flow

```
┌─────────┐         ┌─────────┐         ┌──────────┐
│ Flutter │         │  Laravel │         │  MySQL   │
│   POS   │         │  API    │         │   DB     │
└────┬────┘         └────┬────┘         └────┬─────┘
     │                   │                   │
     │  POST /auth/login │                   │
     │  {email, pass}    │                   │
     │──────────────────>│                   │
     │                   │  Query user       │
     │                   │──────────────────>│
     │                   │  Return user      │
     │                   │<──────────────────│
     │  Return token     │                   │
     │<──────────────────│                   │
     │                   │                   │
     │  GET /products    │                   │
     │  Authorization:   │                   │
     │  Bearer <token>   │                   │
     │──────────────────>│                   │
     │  Validate token   │                   │
     │  Query products   │                   │
     │                   │──────────────────>│
     │  Return products  │                   │
     │<──────────────────│                   │
     │                   │                   │
     │  POST /sells      │                   │
     │  {cart items,     │                   │
     │   customer, etc}  │                   │
     │──────────────────>│                   │
     │  Create sale      │                   │
     │  Update stock     │                   │
     │                   │──────────────────>│
     │  Return sale ID   │                   │
     │<──────────────────│                   │
```

---

## 9. Offline-First Strategy

```
┌─────────────────────────────────────────┐
│              Offline Queue               │
│                                         │
│  ┌─────────┐  ┌─────────┐  ┌────────┐  │
│  │ Products │  │ Stock   │  │ Sales  │  │
│  │  Cache   │  │ Cache   │  │ Queue  │  │
│  └─────────┘  └─────────┘  └────────┘  │
│                                         │
│  ┌─────────┐  ┌─────────┐  ┌────────┐  │
│  │Contacts  │  │ Settings│  │Drafts  │  │
│  │  Cache   │  │  Cache  │  │ Local  │  │
│  └─────────┘  └─────────┘  └────────┘  │
└─────────────────────────────────────────┘
```

### Sync Rules:
1. **Products, Categories, Settings** → Synced on login, refreshed every 30 minutes
2. **Stock levels** → Synced on login, updated after each sale
3. **Sales (transactions)** → Created offline, synced when online
4. **Contacts** → Created offline, synced when online
5. **Conflict resolution** → Server wins for stock, client wins for drafts

---

## 10. Hardware Integration Matrix

| Hardware | Windows | Android | iOS | Package |
|----------|---------|---------|-----|---------|
| Thermal Printer (USB) | ✅ | ✅ | ❌ | `usb_serial` |
| Thermal Printer (Bluetooth) | ✅ | ✅ | ✅ | `flutter_bluetooth_serial` |
| Thermal Printer (Network) | ✅ | ✅ | ✅ | `esc_pos_printer` |
| Barcode Scanner (Camera) | ✅ | ✅ | ✅ | `mobile_scanner` |
| Barcode Scanner (USB) | ✅ | ❌ | ❌ | `usb_serial` |
| Cash Drawer | ✅ | ✅ | ✅ | Via printer RJ11 port |
| Card Reader (Stripe) | ❌ | ✅ | ✅ | `stripe_terminal` |
| Card Reader (USB) | ✅ | ❌ | ❌ | Custom integration |
| Customer Display | ✅ | ❌ | ❌ | Serial/USB |
| Weighing Scale | ✅ | ✅ | ❌ | `usb_serial` |

---

## 11. Development Milestones

### Month 1: Foundation (Weeks 1–4)
| Week | Milestone | Deliverable |
|------|-----------|-------------|
| 1 | Project setup | Flutter project scaffold, API service layer, auth flow |
| 2 | Backend API | Auth API + Product API + Contact API |
| 3 | POS Screen v1 | Product grid, cart, basic add/remove |
| 4 | Payment flow | Payment screen, cash payment, receipt |

### Month 2: Core POS (Weeks 5–8)
| Week | Milestone | Deliverable |
|------|-----------|-------------|
| 5 | Product search | Barcode scan, category filter, search |
| 6 | Customer management | Customer select, quick-add, due tracking |
| 7 | Hold/Recall | Draft transactions, recall, delete |
| 8 | Printer integration | Thermal printer (all platforms) |

### Month 3: Features (Weeks 9–12)
| Week | Milestone | Deliverable |
|------|-----------|-------------|
| 9 | Reports | Daily sales, top products, register report |
| 10 | Bookings integration | View bookings, convert to sale |
| 11 | Offline mode | Local storage, sync queue, conflict resolution |
| 12 | Polish & testing | UI polish, performance, bug fixes |

---

## 12. Effort Estimation

| Component | Effort (days) | Priority |
|-----------|--------------|----------|
| Backend REST API (all endpoints) | 15 | P0 |
| Flutter project setup + auth | 5 | P0 |
| POS screen (core) | 10 | P0 |
| Payment flow | 5 | P0 |
| Product grid + search | 5 | P0 |
| Cart management | 5 | P0 |
| Customer management | 3 | P1 |
| Hold/Recall transactions | 3 | P1 |
| Thermal printer integration | 7 | P1 |
| Barcode scanning | 5 | P1 |
| Receipt generation + printing | 3 | P1 |
| Reports screen | 5 | P2 |
| Bookings integration | 5 | P2 |
| Offline mode + sync | 10 | P2 |
| Settings + configuration | 3 | P2 |
| UI polish + testing | 10 | P0 |
| **Total** | **~94 days** | |

---

## 13. Getting Started — First Steps

### Step 1: Backend API (Laravel)
```
1. Create API route group with versioning (v1)
2. Set up Laravel Passport for OAuth2 tokens
3. Build Auth endpoints (login, logout, refresh)
4. Build Product endpoints (list, detail, variations)
5. Build Contact endpoints (list, create)
6. Build POS/Sell endpoints (create, list, payment)
7. Add API response formatting (JSON:API or custom)
8. Add rate limiting and CORS headers
```

### Step 2: Flutter Project
```
1. Create Flutter project: flutter create vendifypos
2. Set up project structure (models, services, screens)
3. Configure Dio HTTP client with interceptors
4. Build login screen + auth flow
5. Build POS main screen (product grid + cart)
6. Build payment flow
7. Test on Windows first (fastest iteration)
8. Test on Android
9. Test on iOS
```

---

*Document prepared for VendifyERP POS Application*
*Version: 1.0*
*Date: August 2026*
