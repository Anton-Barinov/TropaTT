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
