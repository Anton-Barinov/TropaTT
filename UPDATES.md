# TropaTT Updates

TropaTT updates itself from the admin panel — no SSH, no Composer, and no manual
file replacement. The system has two parts:

1. **Update server** (`update.tropatt.com`) — pre-builds ready, signed update
   packages from the GitHub repository on a cron schedule.
2. **Updater inside the CRM** (`updater/`) — downloads the package, verifies the
   signature, runs a safety preflight, creates a backup, applies files and
   database migrations, and can roll back from that backup.

The user-facing page is **Admin → System Updates**
(`web/index.php?route=admin-updates`).

---

## How it works (overview)

```text
GitHub (Anton-Barinov/TropaTT, main branch)
        │  every 10 minutes
        ▼
Update server update.tropatt.com
        │  cron.php run: scan-github → process-builds → publish-channels → cleanup
        │  full and delta archives + signed manifest
        ▼
Your CRM (updater/)
        │  "System Updates" page
        ▼
Check → preflight → download → install → backup → rollback on error
```

---

## Part 1. Update server: building packages

The update server is a separate PHP application deployed at
`update.tropatt.com`. It does not store builds by hand: it mirrors the GitHub
repository and rebuilds packages on cron when new commits appear.

### Cron script

Runs every 10 minutes:

```cron
*/10 * * * * /usr/bin/php /home/tropatt/web/update.tropatt.com/public_html/bin/cron.php run >> .../storage/logs/update-center-cron.log 2>&1
```

`bin/cron.php run` runs four commands in sequence:

| Command | What it does |
|---|---|
| `scan-github` | Updates the git mirror (`storage/repos/TropaTT.git`) and finds new commits on `main` that are not in the database yet. |
| `process-builds` | For each new build: plans the changes, builds full and delta archives, and signs the package and manifest. |
| `publish-channels` | Publishes finished builds to the `nightly` and `stable` channels according to channel policy. |
| `cleanup` | Cleans up expired lock files. |

Individual commands can be run manually:

```bash
php bin/cron.php scan-github
php bin/cron.php process-builds
php bin/cron.php publish-channels
php bin/cron.php build-status                 # show all builds as JSON
php bin/cron.php reset-locks                  # reset expired locks
php bin/cron.php rebuild --sha=<sha>          # rebuild a specific build
php bin/cron.php bootstrap-build --sha=<sha> [--from=<sha>]   # first build from a base commit
```

### What is stored

- `storage/repos/TropaTT.git` — mirror of the GitHub repository
- `storage/worktrees` — working copies for building a specific commit
- `storage/packages` + `packages/` — built archives
- `storage/manifests` — signed manifests
- `storage/logs` — cron script logs
- MySQL database — tables: commits, builds, packages, channels

### Product configuration

`app/Config/products.php` describes the `tropatt-core` product:

- `repository_url` — the GitHub repository
- `branch` — the branch (`main`)
- `base_version` — the base version number
- `version_file` — the version file inside the repository (`upload/VERSION`)
- `channels` — `nightly`, `stable`, `hotfix`
- `core_paths` — which paths go into the update package (including `modules/**`:
  modules ship together with the core update)
- `excluded_paths` / `forbidden_paths` — what is never included (`.env`,
  `storage/**`, local configs, etc.)

### Channels and publishing policy

| Channel | Build policy | Max risk for auto-publish | Auto-publish |
|---|---|---|---|
| `nightly` | `each_commit` — a build for every commit | `high` | yes |
| `stable` | `head_batch` — a build for the current branch head | `medium` | yes |
| `hotfix` | `manual` — manual only | `high` | no |

The CRM uses the **`stable`** channel by default (configurable via the
`TROPATT_UPDATE_CHANNEL` variable in `api/.env`).

### How a package is built

1. `BuildFactory` creates a build record (`queued`) and estimates the risk from
   the list of changed files (`BuildRiskAnalyzer`).
2. `BuildProcessor` processes each `queued` build:
   - verifies or creates the RSA signing key pair;
   - creates a worktree for the target commit;
   - builds a **full archive** (`tropatt-core-<build>.zip`, the complete file
     set) and a **delta archive** (`tropatt-core-<from>--<build>.zip`, only the
     files changed between builds);
   - computes the SHA-256 of each file and of the whole archive;
   - signs the archive SHA-256 and the manifest contents with the private key;
   - saves the manifest (add/modify/delete/rename + signature).
3. `BuildPublisher` publishes the build to a channel according to channel
   policy.

Important: **the manifest and the package are signed** with the update server's
private key. The client CRM verifies the signature with the public key
(`updater/keys/update_public.pem`) before changing any files.

### Update server API

`/api/v1/...`:

- `products/{product}` — product information
- `products/{product}/channels/{channel}` — a channel and its builds
- `products/{product}/update-plan?current_build=...` — the update plan (is
  there a newer build, which package is recommended)
- `products/{product}/changes` — the list of changes between versions
- `manifests/{product}/{from}/{to}` — delta manifest
- `manifests/{product}/full/{to}` — full manifest
- `products/{product}/public-key` — the public key for signature verification

---

## Part 2. The user update flow

### Where the page is

**Admin → System Updates** (`web/index.php?route=admin-updates`).

Access: root/admin with the settings-management permission only (otherwise 403).

### Step 1 — "Check for updates"

The CRM requests an update plan from the update server: `current_build` →
`target_build`, the recommended package, the risk level, and the change list.
The page shows KPIs:

- **Current version** — the installed build (for example `20260731.002`)
- **Available version** — the target build (for example `20260731.005`) or
  `latest`
- **What will be downloaded** — the package type (FULL / DELTA) and its size
- **Risk level** — low / medium / high / critical

### Step 2 — "Check safety" (preflight)

A read-only safety check: **CRM files are not modified**. By default the local
API runs the updater directly inside the same PHP process, without an HTTP
request to its own public domain. This matters on shared hosting: a reverse
proxy, WAF, DNS hairpin, NAT loopback, or a single PHP worker can block an
internal request to `https://your-domain/updater/` even when the site opens
normally from outside.

A public HTTP(S) call is kept only as a fallback for old or non-standard
installations where `updater/src/` is unavailable. Never disable TLS
verification or hard-code `127.0.0.1`.

The check covers:

- the update server is reachable;
- the manifest signature and the package signature (cryptographically);
- the package URL is reachable and the size matches;
- the PHP `zip` and `openssl` extensions are present;
- write permissions for `api/`, `web/`, `storage`;
- no forbidden paths in the package (zip-slip, `.env`, etc.);
- enough free disk space;
- no active update lock.

Only after **all** checks pass (14/14) can you proceed.

### Step 3 — "Prepare archive" (download)

The CRM downloads the recommended package into a temporary folder
(`storage_api/updates/packages/`) and unpacks it into staging
(`storage_api/updates/staging/`). **Working CRM files are still untouched** —
this is only preparation.

### Step 4 — "Install update" (apply)

Before installing, the CRM asks for confirmation (type `APPLY`). The process:

1. **Maintenance mode** is enabled — the site is temporarily unavailable.
2. A **backup** is created of all files that will be changed or deleted
   (`storage_api/updates/backups/`).
3. The package files are applied (add / modify / delete).
4. Health check.
5. **Database migrations** are applied (if the package includes new migrations).
6. The local `installed-core.json` is updated — the new build becomes current.
7. Maintenance mode is disabled. The CRM is live again.

The page shows the install result: job ID, number of updated files, backup ID,
migration status, and the installed build.

### How interruptions are handled (resume + auto-continue)

An update runs as a **step machine**: each phase (file backup, file apply,
health check, DB backup, migrations, finalization) is split into many short
HTTP requests — by default **~20 seconds of work per request**
(`steps.max_seconds_per_request` in `api/config/update.php`). This fits the
limits of even the most modest shared hosting (nginx `proxy_read_timeout`,
Apache `Timeout`, PHP-FPM `request_terminate_timeout`).

- Progress is persisted to disk between steps (`storage_api/updates/jobs/`).
- If the browser is closed or the request is interrupted, the next run of the
  same update **continues from the saved point** instead of starting over.
- If the package is already downloaded and unpacked (job state
  `staging_ready`), the "Install update" button **jumps straight to apply**
  without re-running preflight/download from scratch. This is critical for
  hosting behind tricky proxies/WAFs: a re-run preflight could hang and leave
  the update stuck in `staging_ready` forever.
- Every updater request has an explicit client timeout (90 s per step, 300 s
  for archive download). If a proxy/WAF drops the connection or the request
  hangs, the page **automatically retries the same step** (up to 3 attempts
  with a pause) — the job is resumable, so a retry continues the saved progress
  instead of breaking the update.
- A one-time updater token (10-minute TTL) is **automatically renewed on every
  step** (sliding window); if it expires, the page obtains a new one via the
  `session` endpoint and continues the same job — up to 3 attempts. The
  "Updater token is invalid or expired" error after an interruption no longer
  needs manual action: just press "Install update" again and it continues.

### Step 5 — "Restore from backup" (rollback)

If something goes wrong after install (errors, instability), the **"Restore
from backup"** button is available (confirmation: type `ROLLBACK`). Process:

1. Maintenance mode is enabled.
2. Files are restored from the latest backup.
3. Health check.
4. `installed-core.json` is returned to the previous build.
5. Maintenance mode is disabled.

Rollback works only if a backup exists (`can_rollback: true`). After a rollback
you can try the update again.

### Process security

- The package and manifest are **signed** — the CRM verifies the signature
  before applying.
- **Protected paths** (`storage/**`, `storage_api/**`, `uploads/**`,
  `backups/**`, `logs/**`, `cache/**`, `.env`, `api/config/*.local.php`, ...)
  are never modified by the package.
- **Modules** (`modules/**`) ship together with the update: module files are
  added and updated from the package but are not deleted while the module still
  exists in the product (deletion goes only by the file list from the manifest;
  local modules that are not in the product are not touched). After install,
  new modules appear on **Admin → Modules** with the «Обнаружен» (Discovered)
  status — you just need to activate them.
- **Apply / rollback** require a one-time updater token, which the CRM obtains
  via a separate request (the `session` API endpoint).
- **Preflight / download** are **rate-limited** per client IP (default: 20
  requests per 5 minutes, then a 15-minute block with a 429 response and a
  `Retry-After` header). A normal update flow makes 2–3 requests, so the limit
  never gets in the way.
- Limit settings live in `api/config/update.php` (`rate_limits`).

### Disaster recovery (recovery key + rescue.php)

If an update is interrupted and the CRM is left in **maintenance mode**, the
normal login is usually unavailable — but the updates page and a rescue script
remain reachable:

- **`/updater/rescue.php`** — the emergency recovery page. It requires a
  **recovery key** to enter.
- The key is generated **at install time** and shown **once** on the final
  installer screen (save it!). Only its hash is stored
  (`storage_api/updates/recovery_key.hash`).
- You can generate a new key on **Admin → System Updates → Disaster recovery →
  "Show recovery key"** — the key is shown once and copied to the clipboard; on
  repeat, a new key is generated.
- The rescue page allows restoring from backup while maintenance mode is held.
  After recovery, maintenance mode is turned off and the CRM works again.

### Hosting requirements

The update system is designed for the **most basic shared hosting**:

- normal local preflight does not require the server to call its own public
  HTTPS address;
- `TROPATT_LOCAL_UPDATER_URL` remains compatible only for the fallback scenario
  and must point to a trusted address of this same installation;
- behind a reverse proxy the scheme is detected via `HTTPS`, `REQUEST_SCHEME`,
  port 443, and only then `X-Forwarded-Proto`, to work correctly in typical
  Docker/Apache/openresty configurations;
- only PHP (8.1+) + MySQL, no shell utilities for the user;
- the whole update is managed from the browser;
- backup and staging live inside the `storage_api/` directory (which must be
  writable);
- **each request is short** (~20 seconds of work by default) — big operations
  are split into steps, so even hosting with hard timeouts (60–300 seconds per
  request) completes updates without interruption;
- no Composer/npm/SSH — the update runs through the browser.

---

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| "Update server unreachable" | `update.tropatt.com` is down or `TROPATT_UPDATE_CENTER_URL` is misconfigured. Check outbound HTTPS access to the update server. Access to your own public domain is no longer required for normal preflight. |
| "Signature verification failed" | The public key `updater/keys/update_public.pem` does not match the update server's key. |
| 429 RATE_LIMITED | Too many preflight/download requests from one IP. Wait for `Retry-After` (usually up to 15 minutes). |
| Install interrupted mid-way | Do nothing: press "Install update" again — the job and prepared staging are preserved, and the install **continues from the saved state** (auto-resume); the updater token renews automatically if needed. Even if only the archive preparation was interrupted (`staging_ready`), the install button continues apply without re-downloading. |
| "Updater token is invalid or expired" | Heals automatically: the page gets a new token via `session` and continues the same job (up to 3 attempts). If it persists, press the install button again. |
| CRM stuck in maintenance mode | Open `/updater/rescue.php` with the recovery key (see "Disaster recovery") and restore from backup. |
| Recovery key lost | On "System Updates → Disaster recovery" press "Show recovery key" — a new key is generated. |
| Errors after install | Press "Restore from backup" and report the job ID. |
| DB migrations not applied | Migrations sometimes need a manual re-run. The install report shows a "DB migrations" section (applied / not applied + reason). |

---

## Building and shipping updates (for maintainers)

1. Push changes to the GitHub repository. The build number (`20260805.00X`) is
   assigned **automatically** by the update server — you do not need to bump
   `upload/VERSION` on every commit (VERSION stays `1.0.0`; version comparison
   is done by builds).
2. The update server picks up new commits on cron (within ~10 minutes) and
   builds/signs packages.
3. Check the status on the update server: `php bin/cron.php build-status`.
4. Rebuild if needed: `php bin/cron.php rebuild --sha=<sha>`.
5. Ready packages appear in the `stable` channel automatically if the build risk
   is ≤ `medium`.
6. If a build is rated **high-risk** (for example, `web/install.php` or
   sensitive components were touched), it is **not** auto-published to
   `stable` — it goes to `nightly`. To release it to `stable` after review, run
   on the update server:

   ```bash
   php bin/cron.php publish --build=<build> --channel=stable
   ```

   For example: `php bin/cron.php publish --build=20260805.010 --channel=stable`.

> **Note.** The updater rate limiter (new files
> `updater/src/Security/RequestRateLimiter.php`, `RequestRateLimiter` in
> `UpdaterKernel.php`, config `rate_limits` in `api/config/update.php`) is part
> of the CRM code and is delivered by normal updates. If you change `updater/`
> locally, always commit the changes to GitHub so that update-server packages
> include the current updater.
