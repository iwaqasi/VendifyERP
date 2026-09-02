# VendifyERP Deployment Guide — SiteGround

## Quick Deploy (SSH)

```bash
# 1. SSH into your SiteGround server
ssh youruser@server

# 2. Navigate to the project
cd /home/youruser/VendifyERP

# 3. Pull latest code
git pull origin main

# 4. Run the deployment script
bash scripts/siteground-deploy.sh
```

## Setup SiteGround Git Integration

### Step 1: Enable Git in Site Tools
1. Log into **SiteGround** → **Site Tools** → **Devs** → **Git**
2. Click **Create Repository**
3. Enter your GitHub repo URL: `https://github.com/iwaqasi/VendifyERP.git`
4. Choose the branch: `main`
5. Click **Create**

### Step 2: Set Up Auto-Deploy
SiteGround's Git integration can auto-pull on a schedule:

1. **Site Tools** → **Devs** → **Cron Jobs**
2. Add a new cron job:
   ```
   * * * * * cd /home/youruser/VendifyERP && git pull origin main && bash scripts/siteground-deploy.sh >> /home/youruser/logs/deploy.log 2>&1
   ```
   (Runs every minute — adjust frequency as needed)

### Step 3: Environment Variables
Make sure `.env` is configured on the server:
```bash
# Check your .env exists and is correct
cat .env | head -5
```

## Post-Deployment Checklist

After deploying, verify:

- [ ] Visit https://erp.arksoftsolutions.com — site loads
- [ ] Login works — test with a cashier account
- [ ] POS opens at the correct location
- [ ] Stock quantities display correctly
- [ ] Product images load
- [ ] Printing works from Windows/browser

## Troubleshooting

### "Permission denied" errors
```bash
chmod -R 775 storage bootstrap/cache
chmod 640 .env
```

### "Class not found" errors
```bash
composer dump-autoload
php artisan package:discover
```

### Blank page after deploy
```bash
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

### Database connection errors
Check `.env` database credentials match your SiteGround MySQL settings in **Site Tools** → **MySQL**.

## File Structure on Server

```
/home/youruser/VendifyERP/
├── app/                    # Laravel application
├── config/                 # Configuration
├── database/               # Migrations
├── modules/                # Module providers
├── public/                 # Web root
│   ├── index.php          # Laravel entry point
│   ├── uploads/           # Product images
│   ├── vendify-pos/       # Flutter POS (built)
│   └── vendify-cms/       # Flutter CMS (built)
├── scripts/
│   └── siteground-deploy.sh
├── vendor/                 # Composer packages
├── .env                    # Environment config (not in git)
└── deploy.sh               # Full deployment script
```

## Building Flutter Web Apps

Flutter builds must be done locally (SiteGround doesn't have Flutter):

```bash
# On your local machine
cd vendify_pos
flutter build web --release --dart-define=API_BASE_URL=https://erp.arksoftsolutions.com/api

# Copy to public directory
rm -rf ../public/vendify-pos
cp -r build/web ../public/vendify-pos

# Upload via SiteGround File Manager or SCP
scp -r public/vendify-pos youruser@server:/home/youruser/VendifyERP/public/
```

Repeat for `vendify_cms`.
