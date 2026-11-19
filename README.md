# GSSF Family Match Tracker

A PHP 8+ web application designed for **Apache** to track, edit, and compare **Glock Sport Shooting Foundation (GSSF)** match results for your family.

## Features
- **User Logins & Roles**: Session-based authentication with `password_hash()` / `password_verify()`, CSRF protection, and `admin` / `member` roles.
- **Event Times (Add & Edit)**: Log and edit GSSF stage times (**Five to Glock**, **Glock the Plates**, **Glock 'M**, and **Penalties**) with live Total Time calculation, division tagging, location, and notes.
- **Family Shooters (Add & Edit Users)**: Manage family member accounts, usernames, passwords, default GSSF divisions, active status, and graph colors.
- **Progress Graphs**: Interactive SVG charts comparing selected family members over time (Total Time or individual stages) plus side-by-side stage-by-stage Average and Personal Best bar comparisons.

## Apache Deployment

1. Copy this repository into your Apache web root (for example `/var/www/html/gssf`).
2. Ensure PHP 8.0+ (`libapache2-mod-php` or `php-fpm`) is enabled and `mod_rewrite` + `mod_headers` are active:
   ```bash
   sudo a2enmod rewrite headers
   sudo systemctl restart apache2
   ```
3. Ensure Apache has write permission to `data/` if using the default zero-config JSON storage:
   ```bash
   sudo chown -R www-data:www-data /var/www/html/gssf/data
   ```
4. Optionally configure a VirtualHost using `apache-vhost.conf` (`AllowOverride All` enables the included `.htaccess` security rules that block direct HTTP access to `data/`, `src/`, `config.php`, and `schema.sql`).

### Optional: Using MySQL / MariaDB instead of JSON storage
By default, `config.php` uses `'storage_driver' => 'json'` so the app works immediately without database setup. To switch to MySQL/MariaDB:
1. Import `schema.sql`:
   ```bash
   mysql -u root -p < schema.sql
   ```
2. Create `config.local.php` (ignored by git):
   ```php
   <?php
   return [
       'storage_driver' => 'mysql',
       'mysql' => [
           'host' => '127.0.0.1',
           'port' => 3306,
           'database' => 'gssf_tracker',
           'username' => 'your_db_user',
           'password' => 'your_db_password',
       ],
   ];
   ```

## Default Starter Accounts
On first run, the app seeds 3 starter accounts and sample matches so you can test graphing immediately:
- **Admin**: `admin` / `gssf2026`
- **Shooter**: `alex` / `family2026`
- **Shooter**: `jordan` / `family2026`

Change these passwords in **Family Shooters** after signing in.
