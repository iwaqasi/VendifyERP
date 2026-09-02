# Deploy Without Git — SiteGround

## Method 1: File Manager (Easiest)

### Step 1: Build Locally
```bash
# On your Windows machine, in the project root
cd D:\Projects\Laravel\UltimatePOS7.0

# Install dependencies for production
composer install --prefer-dist --no-dev --no-interaction

# Create the deploy package
bash scripts/package-for-upload.sh
```

This creates `vendify-deploy.zip` in your project root.

### Step 2: Upload to SiteGround
1. Log into **SiteGround** → **Site Tools** → **Site** → **File Manager**
2. Navigate to your home directory (e.g., `/home/youruser/`)
3. Click **Upload** → select `vendify-deploy.zip`
4. Right-click the zip → **Extract**
5. Move extracted files to your domain's root if needed

### Step 3: Run Deploy Script
If you have **Terminal** access (Site Tools → Devs → Terminal):
```bash
cd ~/VendifyERP
bash scripts/siteground-deploy.sh
```

If you don't have Terminal, the script will need to be run manually step-by-step via the SiteGround **PHP Terminal** (Site Tools → Devs → Terminal) or ask SiteGround support to run it.

---

## Method 2: FTP Upload (If File Manager is slow)

### Step 1: Get FTP Credentials
1. SiteGround → **Site Tools** → **Site** → **FTP Accounts**
2. Note: FTP Host, Username, Password

### Step 2: Connect with FileZilla
1. Download [FileZilla](https://filezilla-project.org/)
2. Enter your FTP credentials
3. Connect

### Step 3: Upload
Upload these files/folders to your domain's root:

| Upload | Destination |
|--------|-------------|
| `app/` | `/public_html/app/` |
| `bootstrap/` | `/public_html/bootstrap/` |
| `config/` | `/public_html/config/` |
| `database/` | `/public_html/database/` |
| `modules/` | `/public_html/modules/` |
| `public/` | `/public_html/public/` |
| `resources/` | `/public_html/resources/` |
| `routes/` | `/public_html/routes/` |
| `storage/` | `/public_html/storage/` |
| `vendor/` | `/public_html/vendor/` |
| `artisan` | `/public_html/artisan` |
| `composer.json` | `/public_html/composer.json` |
| `composer.lock` | `/public_html/composer.lock` |
| `.env` | `/public_html/.env` (create from .env.example) |

**Do NOT upload:** `.git/`, `vendify_pos/`, `vendify_cms/`, `tests/`, `node_modules/`

### Step 4: Configure .env
In File Manager, create/edit `.env` with your production settings:
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erp.arksoftsolutions.com
DB_HOST=localhost
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

---

## Method 3: SSH (If available on your plan)

```bash
# Connect
ssh youruser@server

# Navigate to project
cd ~/VendifyERP

# Pull latest (if you set up Git manually)
git pull origin main

# Run deployment
bash scripts/siteground-deploy.sh
```

---

## After Any Upload — Manual Steps

If you can't run the deploy script, do these manually:

### Via SiteGround → Devs → Terminal:
```bash
cd ~/VendifyERP

# 1. Install dependencies
composer install --prefer-dist --no-dev --no-interaction

# 2. Set environment
cp .env.example .env
# Then edit .env with your DB credentials

# 3. Generate app key
php artisan key:generate

# 4. Generate Passport keys
php artisan passport:keys

# 5. Run migrations
php artisan migrate --force

# 6. Clear and rebuild caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Fix permissions
chmod -R 775 storage bootstrap/cache
chmod 640 .env
```

### Via SiteGround → PHP Variables Manager:
If no terminal is available, ask SiteGround support to run the artisan commands.

---

## Flutter Web Apps (Manual Upload)

Since SiteGround doesn't have Flutter, build locally and upload:

```bash
# On your Windows machine
cd vendify_pos
flutter build web --release --dart-define=API_BASE_URL=https://erp.arksoftsolutions.com/api

# Upload the build/web/ folder to SiteGround
# Destination: public_html/public/vendify-pos/
```

Repeat for `vendify_cms` → `public_html/public/vendify-cms/`.
