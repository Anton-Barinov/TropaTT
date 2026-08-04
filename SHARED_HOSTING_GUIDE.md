# Shared Hosting Installation Guide

This guide walks you through installing TropaTT on shared hosting — the most common and affordable way to get started. You do not need terminal access, command-line skills, or DevOps experience.

> **If you hit any issues**, see [INSTALL_TROUBLESHOOTING.md](INSTALL_TROUBLESHOOTING.md) for common problems and fixes.

---

## What you need

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| **Hosting plan** | PHP 8.1, MySQL, 500 MB disk | Any shared hosting plan ~$3–5/month |
| **PHP extensions** | `pdo`, `pdo_mysql`, `json`, `mbstring`, `openssl`, `fileinfo`, `ctype` | Same (most hosts include these) |
| **MySQL** | Any MySQL-compatible database | MySQL 5.7+ or MariaDB 10.3+ |
| **Disk space** | 200 MB for source files | 1 GB+ for files, data, and growth |
| **Domain** | Any domain or subdomain | A dedicated subdomain (e.g., `crm.yourcompany.com`) |

---

## Step 1: Download TropaTT

Download the latest source code from the [releases page](https://github.com/Anton-Barinov/TropaTT/releases).

Choose the source code archive (ZIP or tar.gz). You do not need Composer, npm, or any build tools.

---

## Step 2: Upload files to your hosting

### Using cPanel File Manager (easiest)

1. Log into your hosting control panel (cPanel, Plesk, ISPmanager, etc.).
2. Open **File Manager**.
3. Navigate to the directory where your website should live:
   - `public_html/` — your main domain root
   - `subdomain/` — for a subdomain
4. Upload the downloaded ZIP archive using the **Upload** button.
5. Extract the archive. Most panels have an **Extract** option when you right-click the uploaded ZIP.

### Using FTP

1. Install an FTP client (FileZilla, WinSCP, Cyberduck, etc.).
2. Connect using the FTP credentials from your hosting panel.
3. Navigate to your website's root directory (`public_html/`, `httpdocs/`, `www/`, etc.).
4. Upload all files from the TropaTT source archive into this directory.
5. This may take a few minutes — TropaTT is about 10–15 MB uncompressed.

### What files to upload

Upload the entire contents of the release archive to your document root:

```
TropaTT/
├── api/           # Required
├── web/           # Required
├── modules/       # Required
├── index.php      # Required — the entry point
├── favicon.ico    # Optional but nice
├── README.md      # Optional
└── LICENSE        # Optional
```

---

## Step 3: Create a MySQL database

### cPanel

1. Open **MySQL Databases**.
2. Under "Create New Database", enter a name and click **Create Database**.
3. Scroll down to **MySQL Users**. Create a new user with a strong password. Save these credentials.
4. Scroll to **Add User to Database**. Select your user and database, and grant **All Privileges**.
5. Note the full database name and username — your host may prefix them (e.g., `u12345_mydb`, `u12345_admin`).

### Plesk

1. Open **Databases**.
2. Click **Add Database**.
3. Enter a database name and create a user with a password.
4. Click **OK**.

### ISPmanager

1. Open **Databases** → **MySQL**.
2. Click **Create**.
3. Enter a database name, username, and password.
4. Click **OK**.

---

## Step 4: Set directory permissions

TropaT needs write access to two directories:

```bash
# Storage for sessions, cache, and uploaded files
chmod 755 storage/          # If the directory exists
mkdir -p storage/sessions   # If not, create it
chmod -R 755 storage/

# API configuration directory
chmod 755 api/
```

**On most shared hosting:** These directories are automatically writable after upload. If you get "not writable" errors during installation, use your hosting File Manager:

1. Right-click `storage/` and `api/` directories.
2. Select **Change Permissions** (or **CHMOD**).
3. Set permissions to **755** (owner: read/write/execute, group/others: read/execute).

---

## Step 5: Run the browser installer

1. Open your domain in a web browser (e.g., `https://yourdomain.com` or `https://crm.yourcompany.com`).
2. TropaTT detects it is not configured and launches the browser installer automatically.
3. The installer checks your environment (PHP version, extensions, permissions).
4. Enter your MySQL credentials from Step 3:
   - **Host:** Usually `localhost` or `127.0.0.1`
   - **Port:** Usually `3306`
   - **Database name:** The database you created
   - **Username:** The MySQL user you created
   - **Password:** The password you set
5. Enter your site URL (your domain) and timezone.
6. Create the first administrator account — choose a strong password.
7. Click **Install**. The installer creates the database schema, seeds reference data, and writes the configuration file.
8. After completion, you are redirected to the login page.

**Note:** The installer may take 30–60 seconds on shared hosting. Do not refresh or close the browser during installation.

---

## Step 6: Log in and start working

1. Log in with the administrator account you created.
2. The dashboard shows your workspace. You can:
   - Create projects and tasks
   - Add team members (in the Admin panel)
   - Set up AI providers (optional)
   - Configure webhooks, workflow rules, and SLA policies

---

## What to do after installation

### Security checklist

- [ ] Delete the installer archive from your server.
- [ ] Make sure `storage/install.lock` exists (the installer creates it — do not delete it).
- [ ] Set up HTTPS if not already configured by your host.
- [ ] Create regular database backups through your hosting panel.
- [ ] Set a strong password for your admin account.
- [ ] Configure daily backups: your hosting panel likely has an "Automatic Backup" feature.

### Storage protection

TropaTT stores uploaded files in `storage_api/uploads/`. The system protects these files using:

1. **File extension `.bin` on disk** — uploaded files are stored without their original extension (e.g., `<publicId>.bin`), so even if the directory is web-accessible, the files cannot be executed as scripts.
2. **Dangerous file types are rejected** — `.php`, `.phtml`, `.phar`, `.asp`, `.jsp`, and similar files are blocked with a `FILE_TYPE_FORBIDDEN` error before they reach the disk.
3. **Same-origin download** — files are served through PHP with proper authentication, not directly by the web server.

**On Apache:** `.htaccess` files in `storage/` and `storage_api/` directories deny all direct HTTP access.

**On nginx:** `.htaccess` is not supported. See the [nginx configuration guide](#nginx) below for securing storage.

**Check protection status:** Visit `https://yourdomain.com/api/v1/health/deep` as root admin and look for the `environment.storage_protection_verified` field.

### Trusted proxies

If your site is behind a reverse proxy or CDN (e.g., Cloudflare, hosting load balancer), TropaTT needs to know which IPs are trusted to extract the real client IP for rate limiting and audit logging.

#### How to check if you need this

Compare the client IP in application logs (`/api/v1/logs/request`) with your own public IP. If all entries show the same IP (the proxy's), you need trusted proxy configuration.

#### Configuration

Add to your `api/.env` file:

```bash
# Comma-separated list of trusted proxy CIDR ranges
CRM_TRUSTED_PROXIES=173.245.48.0/20,103.21.244.0/22,2a06:98c0::/29

# Optional: custom header name (default: X-Forwarded-For)
# CRM_TRUSTED_PROXY_HEADER=X-Forwarded-For
```

**Cloudflare IP ranges:** Cloudflare publishes their current ranges at https://www.cloudflare.com/ips-v4 and https://www.cloudflare.com/ips-v6. Check them periodically as they change.

> **Warning:** Only add addresses you fully control. Adding an untrusted address allows anyone to spoof their IP by sending `X-Forwarded-For` headers.

### HSTS (HTTP Strict Transport Security)

TropaTT sends the `Strict-Transport-Security` header when accessed over HTTPS. This tells browsers to always use HTTPS for your domain.

#### Default behavior

- **Over HTTPS:** `Strict-Transport-Security: max-age=31536000` (valid for 1 year, no `includeSubDomains`)
- **Over HTTP:** No header is sent

#### Why `includeSubDomains` is off by default

On shared hosting, your domain may share subdomains with other services (e.g., `blog.example.com`, `mail.example.com`). Enabling `includeSubDomains` would require ALL subdomains to support HTTPS, potentially making others inaccessible for up to a year.

#### Configuration (optional)

Add to your `api/.env` file to customize HSTS:

```bash
# Enable/disable HSTS (default: 1/enabled)
CRM_HSTS_ENABLED=1

# Set max age in seconds (default: 31536000 = 1 year)
CRM_HSTS_MAX_AGE=31536000

# Include subdomains (default: 0/disabled)
# Only enable if you own ALL subdomains and ALL have HTTPS
CRM_HSTS_INCLUDE_SUBDOMAINS=0
```

**Note:** HSTS is cached by browsers for the duration of `max-age`. If you enable `includeSubDomains` and later need to revert, you cannot "withdraw" the header — you must wait for the cache to expire or issue `max-age=0` and wait for each user to visit again.

### nginx configuration

If your hosting uses nginx (not Apache), add these rules to your nginx configuration to protect storage and API directories:

```nginx
# Block direct access to storage directories
location ~ ^/(storage|storage_api)/ {
    deny all;
    return 404;
}

# Block access to sensitive files
location ~ \.(env|yml|json|lock|md)$ {
    deny all;
    return 404;
}

# Block access to API configuration
location ~ ^/api/config/ {
    deny all;
    return 404;
}
```

If you cannot modify nginx configuration directly (common on shared hosting), the application-level protections (`.bin` extension, dangerous file rejection, PHP-based download) still protect uploaded files.

### Optional configuration

- **PHP memory limit:** If the site feels slow, increase the memory limit:
  - Add to `public_html/.htaccess`: `php_value memory_limit 256M`
  - Or ask your host to increase it in the PHP settings.

- **File upload size:** To allow larger file uploads, ask your host to increase `upload_max_filesize` and `post_max_size`.

- **Cron jobs / Background tasks:** TropaTT uses a `web/cron.php` endpoint for periodic tasks. Set up a cron job (in your hosting panel's "Cron Jobs" section) to call it every minute:
  ```bash
  curl -s https://yourdomain.com/web/cron.php?key=YOUR_CRON_KEY >/dev/null 2>&1
  ```
  You can find the cron key in `api/.env` under `CRON_KEY`.

### Performance tips for shared hosting

- Use a caching plugin or page optimizer if your host provides one.
- Limit the number of active users if the site feels slow.
- Set up indexing on the MySQL tables (the installer does this automatically).
- Move to a VPS when your team grows beyond 15–20 users.

---

## Updates on shared hosting

TropaTT's update center (Admin → Updates) works on plain shared hosting without shell access, cron, or background processes.

**How it survives hosting limits:** a single request on shared hosting is usually cut by the web server long before PHP's own `max_execution_time` matters (nginx `proxy_read_timeout` defaults to 60s, Apache `Timeout` to 300s, and some PHP-FPM setups enforce `request_terminate_timeout`). A big update or a large database dump can easily exceed those limits, so TropaTT runs every update as a **step machine**: backup, file apply, database dump, migrations, database restore and file rollback are each split into many short HTTP requests (about 20 seconds of work per request by default). The page automatically keeps issuing the next step until the job finishes, so updates run correctly even on the smallest virtual hosting.

- No step ever depends on `set_time_limit()` or background processes.
- Long jobs survive browser refreshes: progress is persisted per job, the lock is heartbeat-refreshed on every step, and a crashed job can be rolled back from the same page.
- Step budgets are configurable in `api/config/update.php` under `steps` (`max_seconds_per_request`, `max_files_per_request`, `max_rows_per_request`, `max_migrations_per_request`, `max_statements_per_request`, `lock_ttl_seconds`).
- The package is downloaded to disk in a single streaming pass (memory-flat even for 100MB packages); only extraction is chunked.

If your host terminates requests even faster than 20 seconds, lower `steps.max_seconds_per_request` in `api/config/update.php` (for example to 10).

---

## Minimum files needed

If your host has strict file limits, these are the minimum directories and files required:

```
api/
web/
modules/
index.php
```

All other root files (`README.md`, `LICENSE`, etc.) are optional.

---

## Troubleshooting

If something goes wrong, check [INSTALL_TROUBLESHOOTING.md](INSTALL_TROUBLESHOOTING.md) for:

- Database connection errors
- Missing PHP extensions
- File permission problems
- "Internal server error" after login
- White page / installer not starting

If you still need help, open a GitHub issue with your hosting provider, PHP version, and the exact error message (without passwords or secrets).
