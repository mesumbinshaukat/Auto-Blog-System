# Deployment Guide

Follow these 10 simple steps to deploy the Auto Blog System to production.

## 1. Clone the Repository
```bash
git clone https://github.com/yourusername/auto-blog-system.git
cd auto-blog-system
```

## 2. Install Dependencies
```bash
composer install --optimize-autoloader --no-dev
npm install
npm run build
```

## 3. Configure Environment
```bash
cp .env.example .env
nano .env
```
*Set your database credentials, `APP_URL`, `HUGGINGFACE_API_KEY`, `GEMINI_API_KEY`, and `MAIL_` settings.*

## 4. Generate Application Key
```bash
php artisan key:generate
```

## 5. Migrate and Seed Database
```bash
php artisan migrate --seed --force
```

## 6. Link Storage
```bash
php artisan storage:link
```

## 7. Set Up Scheduler (Cron)
Add the following entry to your server's crontab (`crontab -e`):
```bash
* * * * * cd /path/to/auto-blog-system && php artisan schedule:run >> /dev/null 2>&1
```

## 8. Configure Web Server
Point your Nginx or Apache document root to the `public/` directory.
Ensure permissions are correct:
```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

## 9. Optimize Application
```bash
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 10. Verify Deployment
Run the email test and scheduler check locally to ensure everything is wired up:
```bash
php artisan test:email --to=admin@worldoftech.company
php artisan schedule:test
```

---
## Troubleshooting
- **Thumbnails not showing?** Run `php artisan storage:link`.
- **Jobs failing?** Check `storage/logs/laravel.log` and ensure `php artisan queue:work` is running (supervisor recommended).
