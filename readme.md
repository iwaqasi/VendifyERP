# VendifyERP

**Version:** 7.0 | **License:** MIT

A comprehensive Enterprise Resource Planning system built for multi-industry businesses — salons, spas, retail stores, repair shops, restaurants, clinics, and wholesale operations. Combines a Laravel backend with a Flutter-based POS application that adapts its interface per business type.

---

## Architecture

```
vendify-erp/
├── app/                    # Laravel application (models, controllers, utils)
├── Modules/                # Modular packages (Accounting, CRM, Essentials, Restaurant, Saloon, etc.)
├── routes/                 # Web & API routes
├── database/               # Migrations, seeders, factories
├── resources/              # Blade views, assets, lang files
├── config/                 # Laravel configuration
├── public/                 # Public assets (uploaded images, invoices, etc.)
├── storage/                # App storage (logs, cache, compiled views)
├── vendify_pos/            # VendifyPOS — Flutter POS application (Dart)
├── vendify_cms/            # VendifyCMS — Flutter web CMS frontend (Dart)
└── VendifyPOS_App/         # POS documentation & scripts
```

### Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 11 (PHP 8.2+) |
| Database | MySQL |
| API Auth | Laravel Passport (OAuth2 Bearer tokens) |
| POS Frontend | Flutter (Dart) — desktop, mobile, web |
| CMS Frontend | Flutter (Dart) — web |
| Web Admin | Blade templates + jQuery + DataTables |
| Permissions | Spatie Laravel Permission (100+ granular permissions) |
| PDF Generation | DomPDF / mPDF |
| Excel/CSV | Maatwebsite Excel |
| Barcode | Milon Barcode |
| Push Notifications | Pusher |
| Backups | Spatie Laravel Backup |

---

## Features

### Core Modules

- **Dashboard** — Real-time sales, revenue, stock alerts, due amounts, and booking summaries
- **User Management** — RBAC with predefined roles (Super Admin, Admin, Cashier, Service Staff, etc.)
- **Contacts** — Customers, suppliers, customer groups, credit management, reward points
- **Products & Inventory** — Single/variable/service products, brands, units, tax rates, modifiers, product racks
- **Purchases** — Purchase orders, purchase returns, opening stock imports
- **Sales & POS** — Inline POS, register POS, quotations, sales orders, sales returns
- **Stock Management** — Location-wise stock, stock adjustments, stock transfers, low-stock alerts
- **Expenses & Payments** — Expense categories, payment accounts, payment tracking
- **Accounting** — Chart of accounts, journal entries, budgeting, trial balance, balance sheet
- **Reports** — 30+ reports including sales, purchases, stock, tax, expense, profit/loss, and aging reports
- **CRM** — Leads, campaigns, proposals, call logs, follow-up scheduling
- **Essentials (HRM)** — Employees, departments, designations, attendance, leave management
- **Restaurant Module** — Table management, KOT, kitchen display, order management
- **Saloon/Spa Module** — Appointments, staff scheduling, service timers, booking management
- **Repair Module** — Repair tickets, status tracking, cost management
- **CMS** — Pages, blog posts, products, categories, contact forms
- **Invoice Layouts** — Customizable invoice templates with multiple layout options
- **Multi-Location** — Multiple business locations with location-level access control
- **Backup & Restore** — Automated backups to S3, Dropbox, or local storage

### VendifyPOS Flutter Application

The POS app adapts its interface based on business type:

- **Retail POS** — Standard product grid with barcode scanning
- **Saloon/Spa POS** — Appointment-based with staff selection and service timers
- **Restaurant POS** — Table layout, KOT printing, kitchen workflow
- **Repair POS** — Ticket-based with status tracking
- **Clinic POS** — Appointment scheduling with patient management
- **Wholesale POS** — Bulk pricing with quantity-based discounts

Key POS features:
- PIN-based quick login for cashiers
- Barcode scanning support
- Hold/Recall cart functionality
- Split payments (cash, card, other)
- Offline mode with automatic sync
- Receipt preview and printing
- Daily register (open/close shift)
- Customer display mode
- Reward points redemption

### VendifyCMS Flutter Web Application

Public-facing CMS frontend for:
- Business homepage with hero sections and slides
- Product catalog browsing
- Blog posts and pages
- Contact form

---

## Requirements

- **PHP** >= 8.2
- **MySQL** >= 5.7 or MariaDB >= 10.3
- **Composer** >= 2.0
- **Node.js** >= 18 (for asset compilation)
- **Flutter SDK** >= 3.0 (for POS/CMS apps)
- **Apache/Nginx** with mod_rewrite

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/iwaqasi/VendifyERP.git
cd VendifyERP
```

### 2. PHP Backend Setup

```bash
# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 3. Configure `.env`

Edit `.env` with your database and application settings:

```env
APP_NAME=VendifyERP
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vendify_erp
DB_USERNAME=root
DB_PASSWORD=

# Passport (API auth for Flutter POS)
PASSPORT_PERSONAL_ACCESS_CLIENT_ID=
PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET=

# Filesystem (for uploads)
FILESYSTEM_DISK=local
```

### 4. Database Setup

```bash
# Run all migrations
php artisan migrate

# Seed default data (optional — the installer handles this too)
php artisan db:seed
```

Or use the web installer:
```
http://your-domain.com/install
```

### 5. Storage & Cache

```bash
# Create storage symlink
php artisan storage:link

# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

### 6. Start the Development Server

```bash
php artisan serve
```

Visit `http://localhost:8000` and follow the installer wizard.

---

## VendifyPOS (Flutter App) Setup

### Prerequisites

- Flutter SDK >= 3.0
- Dart SDK >= 3.0
- Android Studio / VS Code with Flutter plugin

### Setup

```bash
cd vendify_pos

# Install dependencies
flutter pub get

# Configure API endpoint
# Edit lib/config/api_config.dart with your backend URL

# Run on web
flutter run -d chrome

# Build for web (production)
flutter build web

# Build for desktop
flutter build windows   # Windows
flutter build macos     # macOS
flutter build linux     # Linux
```

### API Configuration

Edit `vendify_pos/lib/config/api_config.dart`:

```dart
class ApiConfig {
  static const String baseUrl = 'http://your-domain.com/api';
  // ...
}
```

The POS app communicates with the backend via REST API endpoints under `/api/v1/`.

---

## VendifyCMS (Flutter Web App) Setup

```bash
cd vendify_cms

# Install dependencies
flutter pub get

# Configure API endpoint
# Edit lib/config/api_config.dart with your backend URL

# Run on web
flutter run -d chrome

# Build for production
flutter build web
```

---

## API Documentation

The backend exposes a RESTful API under `/api/v1/` with OAuth2 Bearer token authentication via Laravel Passport.

### Public Endpoints

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/v1/auth/login` | Login with email/password |
| POST | `/api/v1/auth/login-by-pin` | Login with 4-digit PIN |
| GET | `/api/v1/health` | Health check |

### Authenticated Endpoints (requires Bearer token)

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/v1/auth/logout` | Logout |
| POST | `/api/v1/auth/refresh` | Refresh token |
| GET | `/api/v1/auth/user` | Get current user |
| POST | `/api/v1/auth/switch-business` | Switch business context |

### Product Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/products` | List products (search, filter, paginate) |
| GET | `/api/v1/products/{id}` | Product detail with variations |
| GET | `/api/v1/products/{id}/variations` | Product variations with stock |
| GET | `/api/v1/categories` | List categories with product counts |
| GET | `/api/v1/brands` | List brands |
| GET | `/api/v1/units` | List units |
| GET | `/api/v1/tax-rates` | List tax rates |

### Sales Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/sells` | List sales with filters |
| POST | `/api/v1/sells` | Create a new sale |
| GET | `/api/v1/sells/{id}` | Sale detail with line items |
| DELETE | `/api/v1/sells/drafts/{id}` | Delete a draft |
| POST | `/api/v1/sells/{id}/payment` | Add payment to sale |
| POST | `/api/v1/sells/{id}/return` | Return items from sale |
| GET | `/api/v1/reports/daily-sales` | Daily sales summary |

### Contact Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/contacts` | List contacts |
| POST | `/api/v1/contacts` | Create contact |
| PUT | `/api/v1/contacts/{id}` | Update contact |
| POST | `/api/v1/contacts/{id}/pay-credit` | Pay customer credit |

### Settings & Stock

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/settings` | Business settings |
| GET | `/api/v1/locations` | Business locations |
| GET | `/api/v1/payment-methods` | Payment methods |
| GET | `/api/v1/pos-settings` | POS-specific settings |
| GET | `/api/v1/stock` | Stock levels |
| GET | `/api/v1/stock/product/{id}` | Product stock by location |

Full API documentation is available at `VendifyPOS_App/API_DOCUMENTATION.md`.

---

## Modules

| Module | Description |
|---|---|
| **Accounting** | Chart of accounts, journal entries, budgets, trial balance, balance sheet |
| **CRM** | Leads, campaigns, proposals, call logs, follow-up scheduling |
| **Essentials** | HRM — employees, departments, attendance, leave management |
| **Restaurant** | Table management, KOT, kitchen display, order workflow |
| **Saloon** | Appointments, staff scheduling, service timers |
| **Repair** | Repair tickets, status tracking, cost management |
| **Cms** | Pages, blog posts, products, categories |
| **Superadmin** | Multi-tenant management, subscription licensing |

Enable/disable modules via `modules_statuses.json` or the admin panel.

---

## Multi-Business Type System

Each business declares its type, which controls the POS interface and available features:

| Business Type | POS Layout | Key Features |
|---|---|---|
| Retail | Product grid | Barcode scanning, categories, stock management |
| Saloon & Spa | Appointment list | Staff selection, service timers, booking calendar |
| Restaurant | Table layout | KOT, kitchen display, table status management |
| Repair | Ticket list | Status tracking, cost estimation, customer notifications |
| Clinic | Appointment list | Patient management, scheduling |
| Wholesale | Product grid | Bulk pricing, quantity discounts, credit management |

---

## Environment Variables

Key variables in `.env`:

```env
# Application
APP_NAME=VendifyERP
APP_URL=http://localhost:8000
APP_ENV=local
APP_DEBUG=true

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vendify_erp
DB_USERNAME=root
DB_PASSWORD=

# Filesystem (local, s3, dropbox)
FILESYSTEM_DISK=local

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525

# Push Notifications (optional)
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=

# Payment Gateways (configure as needed)
STRIPE_KEY=
STRIPE_SECRET=
PAYPAL_MODE=sandbox
RAZORPAY_KEY=
RAZORPAY_SECRET=
```

---

## Deployment

### Production Checklist

```bash
# 1. Set APP_ENV=production and APP_DEBUG=false in .env
# 2. Optimize Laravel
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 3. Set permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 4. Create storage symlink
php artisan storage:link

# 5. Build Flutter POS for production
cd vendify_pos
flutter build web --release
# Deploy build/web/ to your web server

# 6. Build Flutter CMS for production
cd vendify_cms
flutter build web --release
# Deploy build/web/ to your web server
```

### Web Server Configuration

**Apache** — The included `.htaccess` handles URL rewriting.

**Nginx** — Add this to your server block:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    include fastcgi_params;
}
```

---

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## Support

For issues and questions:
- **GitHub Issues:** [https://github.com/iwaqasi/VendifyERP/issues](https://github.com/iwaqasi/VendifyERP/issues)
- **Documentation:** See `VendifyERP_Feature_Documentation.md` for complete feature docs

---

## License

This project is licensed under the MIT License — see the `composer.json` for details.
