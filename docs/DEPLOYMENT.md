# Deployment Guide for cPanel / Apache Server

This guide covers deploying the BlaBla application to a production server using cPanel or standard Apache hosting.

## Pre-Deployment Checklist

- [ ] PHP 8.2 or higher installed
- [ ] MySQL/MariaDB database created
- [ ] Composer installed on server
- [ ] Node.js and NPM installed (for asset compilation)
- [ ] SSL certificate configured (recommended)
- [ ] Server meets minimum requirements

## Step 1: Upload Files

1. **Upload via FTP/SFTP or cPanel File Manager:**
   - Upload all files from the `backend/` directory to your server
   - Recommended location: `/public_html/` or `/home/username/public_html/`

2. **File Structure on Server (Root as Document Root):**
   ```
   public_html/                    (Document Root)
   ├── .htaccess                  (Main routing & security)
   ├── index.php                   (Bootstrap file)
   ├── app/
   ├── bootstrap/
   ├── config/
   ├── database/
   ├── public/                     (Public assets - accessible via /public/)
   │   ├── css/
   │   ├── js/
   │   ├── .htaccess
   │   └── ...
   ├── resources/
   ├── routes/
   ├── storage/
   ├── vendor/
   ├── .env
   └── ...
   ```

**Note:** With this setup, the root directory is the document root. The `.htaccess` file in the root handles all routing and security. Public assets are still accessible via the `/public/` path.

## Step 2: Configure Document Root

### Option A: Root Directory as Document Root (Current Setup)

**This setup uses the root directory as the document root, not the public folder.**

1. Go to **cPanel > Domains > Addon Domains** or **Subdomains**
2. Set document root to: `/public_html/` (or your application root path)
3. The root `.htaccess` and `index.php` will handle all routing
4. Public assets are accessible via `/public/` path

### Option B: Public Directory as Document Root (Alternative)

If you prefer to use the public directory as document root:

1. Go to **cPanel > Domains > Addon Domains** or **Subdomains**
2. Set document root to: `/public_html/public` (or your path + `/public`)
3. This ensures only the `public/` directory is web-accessible

### Option C: Apache Virtual Host (Root Directory)

Edit your Apache virtual host configuration:

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /path/to/your/app
    
    <Directory /path/to/your/app>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Option D: Apache Virtual Host (Public Directory)

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /path/to/your/app/public
    
    <Directory /path/to/your/app/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

## Step 3: Set Permissions

Run these commands via SSH or cPanel Terminal:

```bash
# Navigate to your application directory
cd /path/to/your/app

# Set storage permissions
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Set ownership (replace 'username' with your server username)
chown -R username:username storage
chown -R username:username bootstrap/cache
```

## Step 4: Environment Configuration

1. **Copy `.env.example` to `.env`:**
   ```bash
   cp .env.example .env
   ```

2. **Edit `.env` file with your production settings:**
   ```env
   APP_NAME="BlaBla"
   APP_ENV=production
   APP_KEY=base64:YOUR_GENERATED_KEY
   APP_DEBUG=false
   APP_URL=https://yourdomain.com
   
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=your_database
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

3. **Generate application key:**
   ```bash
   php artisan key:generate
   ```

## Step 5: Install Dependencies

```bash
# Install PHP dependencies
composer install --optimize-autoloader --no-dev

# Install NPM dependencies (if needed)
npm install --production

# Build assets (if using Vite/Mix)
npm run build
```

## Step 6: Run Migrations

```bash
# Run database migrations
php artisan migrate --force

# Seed initial data (optional)
php artisan db:seed --class=SystemSettingsSeeder
```

## Step 7: Create Storage Link

```bash
php artisan storage:link
```

**Note:** If you're using root as document root, the storage link will create a symlink at `public/storage`. Assets will be accessible via `/public/storage/` path. Make sure your `.htaccess` allows access to the `public/` directory.

## Step 8: Optimize Application

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize
```

## Step 9: Configure Cron Job

1. **Via cPanel Cron Jobs:**
   - Go to **cPanel > Advanced > Cron Jobs**
   - Add new cron job:
   - **Minute:** `*`
   - **Hour:** `*`
   - **Day:** `*`
   - **Month:** `*`
   - **Weekday:** `*`
   - **Command:** 
     ```bash
     cd /path/to/your/app && php artisan schedule:run >> /dev/null 2>&1
     ```

2. **Via SSH (crontab -e):**
   ```bash
   * * * * * cd /path/to/your/app && php artisan schedule:run >> /dev/null 2>&1
   ```

## Step 10: Configure Queue Worker (If Using)

If you're using database or Redis queues:

```bash
# Via Supervisor (recommended for production)
# Create /etc/supervisor/conf.d/blabla-worker.conf
```

Or use cPanel's process manager if available.

## Step 11: SSL/HTTPS Configuration

1. **Install SSL certificate** via cPanel SSL/TLS
2. **Uncomment HTTPS redirect** in `public/.htaccess`:
   ```apache
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

## Step 12: Security Hardening

1. **Set proper file permissions:**
   ```bash
   # Make .env readable only by owner
   chmod 600 .env
   
   # Protect storage
   chmod -R 775 storage
   chmod -R 775 bootstrap/cache
   ```

2. **Verify `.htaccess` is working:**
   - Try accessing `/.env` - should be blocked
   - Try accessing `/composer.json` - should be blocked

3. **Disable PHP execution in storage:**
   Create `storage/.htaccess`:
   ```apache
   <Files *.php>
       Order Deny,Allow
       Deny from all
   </Files>
   ```

## Step 13: Test Installation

1. Visit `https://yourdomain.com/install` (if not installed)
2. Complete the installation wizard
3. Test admin panel: `https://yourdomain.com/admin`
4. Test API: `https://yourdomain.com/api/v1/health`

## Troubleshooting

### 500 Internal Server Error

1. Check error logs: `storage/logs/laravel.log`
2. Verify file permissions
3. Check `.htaccess` syntax
4. Ensure `mod_rewrite` is enabled

### Permission Denied

```bash
chmod -R 775 storage bootstrap/cache
chown -R username:username storage bootstrap/cache
```

### Database Connection Failed

1. Verify database credentials in `.env`
2. Check database user has proper permissions
3. Verify database host (use `127.0.0.1` instead of `localhost` if needed)

### Assets Not Loading

1. Run `php artisan storage:link`
2. Check `public/storage` symlink exists
3. Verify file permissions on `storage/app/public`

### Cron Not Running

1. Verify cron job syntax
2. Check cron logs
3. Test manually: `php artisan schedule:run`
4. Verify PHP path in cron command

## Performance Optimization

1. **Enable OPcache** (contact hosting provider)
2. **Use Redis** for cache and sessions
3. **Enable Gzip compression** (already in `.htaccess`)
4. **Use CDN** for static assets
5. **Enable HTTP/2** (if supported)

## Backup Strategy

1. **Database backups:**
   - Use cPanel backup tool
   - Or set up automated MySQL dumps

2. **File backups:**
   - Regular backups of `storage/` directory
   - Backup `.env` file securely

## Maintenance Mode

```bash
# Enable maintenance mode
php artisan down

# Disable maintenance mode
php artisan up
```

## Post-Deployment

1. Clear all caches:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```

2. Monitor logs:
   - `storage/logs/laravel.log`
   - Server error logs

3. Set up monitoring:
   - Uptime monitoring
   - Error tracking (Sentry, etc.)
   - Performance monitoring

## Support

For issues, check:
- Laravel logs: `storage/logs/laravel.log`
- Server error logs
- System Health page: `/admin/system-health`
- Cron Status page: `/admin/cron-status`

