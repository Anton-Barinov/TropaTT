# Installation Troubleshooting

This page helps you resolve the most common installation failures when setting up TropaTT on your own server.

> **Before you start:** Make sure your environment meets the [requirements](README.md#getting-started): PHP 8.1+, MySQL-compatible database, writable `api/` configuration and `storage/` directories.

---

## Database connection errors

### "Could not connect to database" or "Access denied for user"

**Symptoms:** The installer stops at the database step with a connection error. Your hosting panel shows the database exists.

**Likely causes and fixes:**

1. **Wrong credentials** — Double-check the MySQL host, port, database name, username, and password. Some hosting panels create databases with a prefix (e.g., `u12345_mydb`).

2. **Remote host blocked** — If MySQL is on a separate server, ensure your web server's IP is allowed. For shared hosting, the database is almost always on `localhost` or `127.0.0.1`.

3. **Port not 3306** — Some hosts use a non-standard MySQL port. Check your hosting panel for the correct port.

4. **User not granted access to the database** — After creating the database, the MySQL user must be granted privileges:
   ```sql
   GRANT ALL PRIVILEGES ON your_database.* TO 'your_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

5. **Database already has tables** — TropaTT needs an empty database for a fresh install. Point it to a new or cleaned database.

### "Table already exists" during installation

**Symptoms:** The installer fails mid-way through schema creation with duplicate table errors.

**Likely cause:** The database is not empty. It may contain tables from a previous installation attempt.

**Fix:** Use a fresh empty database, or clean the existing one:
```sql
DROP DATABASE your_database;
CREATE DATABASE your_database;
```

---

## PHP extension errors

### "PDO extension not found" or "PDO MySQL driver missing"

**Symptoms:** The installer or the login page shows errors about missing PDO or MySQL drivers.

**Required extensions:** TropaTT needs these PHP extensions:
- `pdo` — PDO support
- `pdo_mysql` — MySQL driver for PDO
- `json` — JSON encode/decode
- `mbstring` — Multibyte string functions
- `openssl` — Encryption and secure connections
- `fileinfo` — MIME type detection for file uploads
- `ctype` — Character type checking

**How to enable them:**

- **Shared hosting:** Log into your hosting panel and look for "PHP Extensions", "PHP Settings", or "Select PHP Version". Enable the required extensions.
- **Linux (apt):** `sudo apt install php-mysql php-mbstring php-curl php-xml php-gd`
- **macOS (Homebrew):** `brew install php` (most extensions are bundled)
- **Windows (XAMPP/WAMP):** Uncomment the extensions in `php.ini`:
  ```
  extension=pdo_mysql
  extension=mbstring
  extension=openssl
  extension=fileinfo
  extension=ctype
  ```

### "OpenSSL extension required" error

**Symptoms:** The installer or login shows an error about OpenSSL being missing.

**Fix:** Enable the `openssl` extension in `php.ini` or install it through your hosting panel.

---

## File permission problems

### "Storage directory is not writable" or "Config directory is not writable"

**Symptoms:** The installer reports that `storage/` or `api/` directories are not writable.

**Required permissions:** The web server user (e.g., `www-data`, `nobody`, `apache`) needs write access to:
- `storage/` — runtime data, sessions, cache
- `api/` — configuration files

**Fixes:**

1. **Set correct ownership** — Find your web server user and set ownership:
   ```bash
   # Find web server user
   ps aux | grep -E 'apache|httpd|nginx|php-fpm'
   # Set ownership (replace www-data with your server user)
   chown -R www-data:www-data storage/ api/
   ```

2. **Set correct permissions** — If you cannot change ownership:
   ```bash
   chmod -R 755 storage/ api/
   chmod -R 775 storage/sessions/ 2>/dev/null
   ```

3. **Shared hosting** — Use your hosting panel's file manager or FTP client. Right-click the `storage/` and `api/` directories and set permissions to 755 or 775. Some hosts have a "Permissions" or "CHMOD" option.

### "Cannot write .env file"

**Symptoms:** The installer completes but logs show "cannot write api/.env".

**Fix:** Check that the `api/` directory is writable and that no security script (like `disable_functions`) blocks `file_put_contents` or similar file operations.

---

## "Internal server error" after login

### 500 error right after successful login

**Symptoms:** You log in successfully, but the dashboard shows a blank page or 500 error.

**Likely causes:**

1. **Not enough PHP memory** — Increase PHP memory limit:
   - Create or edit `php.ini`: `memory_limit = 256M`
   - Or add to `.htaccess` (Apache): `php_value memory_limit 256M`
   - Or create `user.ini` (PHP-FPM): `memory_limit = 256M`

2. **Missing JSON extension** — The dashboard loads data via API calls. Without the `json` extension, API responses fail. Enable `php-json` through your hosting panel.

3. **ModSecurity blocking API requests** — Some shared hosting providers enable ModSecurity rules that block API requests. Ask your host to disable ModSecurity for your domain, or check the `.htaccess` file.

### "Session directory is not writable"

**Symptoms:** Login succeeds but pages reload as if you are not logged in.

**Fix:** Ensure the `storage/sessions/` directory exists and is writable:
```bash
mkdir -p storage/sessions/
chmod 755 storage/sessions/
```

---

## URL rewriting / document root issues

### Only the homepage works; all other URLs show 404

**Symptoms:** `https://yoursite.com/` loads the installer or login page, but all other links give 404 errors.

**Likely cause:** The web server or TropaTT is not configured for clean URLs. For Apache, `mod_rewrite` may be disabled.

**Fixes:**

- **Apache:** Ensure `mod_rewrite` is enabled and `.htaccess` files are allowed:
  ```apache
  # In httpd.conf or virtual host config
  AllowOverride All
  ```

- **Nginx:** Add this to your server block:
  ```nginx
  location / {
      try_files $uri $uri/ /index.php?$query_string;
  }
  ```

- **Built-in PHP server:** TropaTT handles routing automatically:
  ```bash
  php -S localhost:8000 -t .
  ```

### HTTPS redirect loop

**Symptoms:** The browser keeps redirecting between `http://` and `https://`.

**Fix:** Set the correct site URL during installation. If you already installed with the wrong URL, edit `api/.env` and set:
```
SITE_URL=https://yoursite.com
```
Then clear any cache in `storage/cache/`.

---

## Browser installer not starting

### White page or no installer when opening the domain

**Symptoms:** Opening your domain shows a blank page or just lists files instead of the installer.

**Likely causes and fixes:**

1. **PHP not processing `.php` files** — Check with your hosting provider that `.php` files are configured to execute.

2. **Installer is locked** — If you previously ran the installer, the lock file `storage/install.lock` blocks re-installation. Delete this file to re-run the installer.

3. **Wrong document root** — Ensure the web server's document root points to the TropaTT root (where `index.php` lives), not to a subdirectory.

---

## Common hosting-panel examples

### cPanel

1. Create a MySQL database and user in "MySQL Databases".
2. Upload files to `public_html/` using File Manager or FTP.
3. Open your domain and follow the installer.

### Plesk

1. Create a database in "Databases".
2. Upload files to `httpdocs/`.
3. Ensure PHP version is set to 8.1+ in "PHP Settings".

### ISPmanager

1. Create a MySQL database in "Databases" → "Create".
2. Upload files to `www/` or `httpdocs/`.
3. Check PHP version in "Webserver" → "PHP Settings".

### Timeweb / Beget / Similar Russian hosts

1. Create a MySQL database in the hosting panel.
2. Open the "File Manager" and upload the archive, then extract it.
3. Set permissions 755 on `storage/` and `api/` directories through the panel.

---

## Still stuck?

If you have tried the steps above and the problem persists, open a GitHub issue with:

- Hosting type (shared hosting name, VPS, local, etc.)
- PHP version (`php -v` or check hosting panel)
- MySQL version
- The exact error message (sanitize any passwords or secrets)
- What you have tried so far
- Screenshots of the installer or error page

Open an issue at: [github.com/Anton-Barinov/TropaTT/issues](https://github.com/Anton-Barinov/TropaTT/issues)

**Do not** include passwords, API keys, session tokens, or other secrets in your issue.
