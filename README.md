# GSSF Family Match Tracker

A PHP 8+ web application designed for **Apache** (`/var/www/gssf`) using **SQLite** to track, edit, and compare **Glock Sport Shooting Foundation (GSSF)** match results for your family.

## Features
- **User Logins & Passkeys (WebAuthn)**: Session-based authentication with `password_hash()` / `password_verify()`, CSRF protection, `admin` / `member` roles, and **Passkey (WebAuthn)** registration & passwordless sign-in (Touch ID, Face ID, Windows Hello, hardware security keys).
- **SQLite Storage**: Zero-configuration SQLite database at `/var/www/gssf/data/gssf.sqlite` (using `pdo_sqlite`).
- **Event Times (Add & Edit)**: Log and edit GSSF stage times (**Five to Glock**, **Glock the Plates**, **Glock 'M**, and **Penalties**) with live Total Time calculation, division tagging, location, and notes.
- **Family Shooters (Add & Edit Users)**: Manage family member accounts, usernames, passwords, default GSSF divisions, active status, graph colors, and passkeys.
- **Progress Graphs**: Interactive SVG charts comparing selected family members over time (Total Time or individual stages) plus side-by-side stage-by-stage Average and Personal Best bar comparisons.

## Apache Deployment (`/var/www/gssf`)

1. Place this repository at `/var/www/gssf`.
2. Ensure PHP 8.0+ with `pdo_sqlite` and `openssl` is installed, and enable Apache `mod_rewrite` and `mod_headers`:
   ```bash
   sudo apt install php-sqlite3
   sudo a2enmod rewrite headers
   sudo systemctl restart apache2
   ```
3. Ensure Apache has write permission to `/var/www/gssf/data` for the SQLite database:
   ```bash
   sudo chown -R www-data:www-data /var/www/gssf/data
   ```
4. Configure your Apache VirtualHost using `apache-vhost.conf` (sets `DocumentRoot /var/www/gssf` and `AllowOverride All`, which enables `.htaccess` rules blocking direct HTTP access to `data/`, `src/`, `*.sqlite`, `config.php`, and `schema.sql`).

### Passkey (WebAuthn) Requirement
Browsers require **HTTPS** (or `http://localhost` for local testing) for WebAuthn passkey registration and sign-in.

### Optional: Using MySQL / MariaDB instead of SQLite
To switch from SQLite to MySQL/MariaDB:
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
On first run, the app seeds 3 starter accounts and sample matches in SQLite:
- **Admin**: `admin` / `gssf2026`
- **Shooter**: `alex` / `family2026`
- **Shooter**: `jordan` / `family2026`

Change these passwords and register your Passkeys in **Family Shooters** after signing in.
