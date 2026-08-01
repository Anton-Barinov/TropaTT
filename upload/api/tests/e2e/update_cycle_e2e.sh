#!/bin/bash
# =============================================================================
# TropaTT E2E: full update-cycle test, one command.
#
# Verifies the real pipeline on live servers:
#
#   GitHub commit -> update-center scan/build/auto-publish -> CRM check/
#   preflight/download/session/apply -> verification -> rollback -> revert ->
#   next build -> apply -> verification
#
# How it works:
#   1. Adds a temporary `e2e_update_marker` string to all 7 locale files and
#      commits it. The commit is pushed with `--no-verify` so the repo's
#      auto-deploy pre-push hook (deploy.sh) does NOT touch the demo: the ONLY
#      path for the marker to reach the demo is the update center.
#   2. Triggers the update center scan and polls core_builds.json until the
#      new build for our SHA is published on the "stable" channel.
#   3. Applies the update on the demo through the public API exactly like the
#      admin-updates page does (login -> check -> preflight -> download ->
#      session -> apply) using the embedded PHP harness (runs on the demo).
#   4. Verifies the marker landed on the demo, health is OK, maintenance is OFF.
#   5. Optionally (--test-rollback) runs updater rollback and verifies the demo
#      is restored (marker gone, maintenance off), then re-applies the marker
#      build to keep the state consistent for step 6.
#   6. Reverts the marker commit, waits for the next build, applies it and
#      verifies the marker is gone from the demo.
#
# Usage:
#   bash upload/api/tests/e2e/update_cycle_e2e.sh [options]
#
# Options:
#   --yes              actually run the cycle (pushes to GitHub, mutates demo)
#   --test-rollback    after apply, exercise updater rollback and verify restore
#   --keep-marker      do not revert the marker at the end (debugging)
#   --wait SECONDS     max time to wait for the update-center build (default 900)
#   --lint             local-only validation of this script and its embedded
#                      helpers (no SSH, no push) - useful for CI
#   --help             this help
#
# Env overrides (all optional):
#   DEMO_SSH DEMO_ROOT DEMO_BASE DEMO_LOGIN DEMO_PASSWORD
#   UPDATE_SSH UPDATE_ROOT UPDATE_USER
#   GIT_REMOTE GIT_BRANCH
#
# Exit codes: 0 = success, 1 = live test failure, 2 = usage/precondition error
# =============================================================================
set -euo pipefail

# ---------------------------------------------------------------- config ----
REPO_ROOT="$(cd "$(dirname "$0")/../../../.." && pwd)"
DEMO_SSH="${DEMO_SSH:-root@demo.tropatt.com}"
UPDATE_SSH="${UPDATE_SSH:-root@update.tropatt.com}"
DEMO_ROOT="${DEMO_ROOT:-/home/tropatt/web/demo.tropatt.com/public_html}"
UPDATE_ROOT="${UPDATE_ROOT:-/home/tropatt/web/update.tropatt.com/public_html}"
UPDATE_USER="${UPDATE_USER:-tropatt}"
# Absolute PHP path on the update server (its own crontab uses /usr/bin/php;
# the tropatt user's PATH under `su` may not contain `php`).
UPDATE_PHP="${UPDATE_PHP:-/usr/bin/php}"
DEMO_PHP="${DEMO_PHP:-php}"
DEMO_BASE="${DEMO_BASE:-https://demo.tropatt.com}"
# SSL verification for the harness's HTTPS calls to the demo. Default on;
# set DEMO_SSL_VERIFY=0 only to debug a missing CA bundle on the demo host.
DEMO_SSL_VERIFY="${DEMO_SSL_VERIFY:-1}"
DEMO_LOGIN="${DEMO_LOGIN:-admin}"
DEMO_PASSWORD="${DEMO_PASSWORD:-adminadmin}"
GIT_REMOTE="${GIT_REMOTE:-origin}"
GIT_BRANCH="${GIT_BRANCH:-main}"
BUILD_WAIT=900
SSH_OPTS="-o StrictHostKeyChecking=no -o ConnectTimeout=20"

YES=0
TEST_ROLLBACK=0
KEEP_MARKER=0
LINT=0

LOCALES=(
  upload/web/language/de-de.php
  upload/web/language/en-gb.php
  upload/web/language/es-es.php
  upload/web/language/fr-fr.php
  upload/web/language/pt-br.php
  upload/web/language/ru-ru.php
  upload/web/language/zh-cn.php
)

# ---------------------------------------------------------------- helpers ----
step()  { echo; echo "===== [$(date +%H:%M:%S)] $* ====="; }
fatal() { echo "FATAL: $*" >&2; exit 1; }
die_usage() { echo "Use --help for usage." >&2; exit 2; }

recover_hint() {
  echo
  echo "=============================================================================="
  echo "E2E failed. Recovery notes:"
  echo "  * The demo stays on the last build that applied successfully."
  echo "  * If a marker commit was pushed, remove it when convenient:"
  echo "      git reset --hard HEAD~1   # local only"
  echo "      git push --no-verify $GIT_REMOTE $GIT_BRANCH   # only if you want to"
  echo "  * Run 'bash $0 --lint' to re-validate the script."
  echo "=============================================================================="
}

# ------------------------------------------------------------- embedded ------
# Marker patch: adds/removes the e2e marker line to/from the locale files.
write_patch_script() {
  cat > /tmp/e2e_patch_locales.py <<'PYEOF'
#!/usr/bin/env python3
"""Add or remove the e2e_update_marker line in TropaTT locale files."""
import io
import sys

mode = sys.argv[1]          # add | remove
stamp = sys.argv[2]
files = sys.argv[3:]
changed = []

for path in files:
    with io.open(path, encoding="utf-8") as fh:
        text = fh.read()
    if mode == "add":
        if "e2e_update_marker" in text:
            continue
        anchor = "return array (\n"
        if anchor not in text:
            print("FAIL anchor-not-found: %s" % path)
            sys.exit(1)
        line = "  'e2e_update_marker' => '%s',\n" % stamp
        text = text.replace(anchor, anchor + line, 1)
    else:
        kept = [ln for ln in text.split("\n") if "'e2e_update_marker'" not in ln]
        text = "\n".join(kept)
    with io.open(path, "w", encoding="utf-8") as fh:
        fh.write(text)
    changed.append(path)

print("PATCH_OK %s changed=%d" % (mode, len(changed)))
PYEOF
}

# Poll: reports whether the update center has built+published our commit.
write_poll_script() {
  cat > /tmp/e2e_poll_build.py <<'PYEOF'
#!/usr/bin/env python3
"""Poll the update-center state for a build that covers the given commit SHA."""
import json
import sys

sha = sys.argv[1]
root = sys.argv[2]
try:
    builds = json.load(open(root + "/storage/cache/core_builds.json"))
    channels = json.load(open(root + "/storage/cache/update_channels.json"))
except Exception as exc:  # noqa: BLE001 - file may be mid-write
    print("WAITING cannot-read: %s" % exc)
    sys.exit(0)

stable = next((c for c in channels if c.get("channel") == "stable"), {})
hit = None
for b in builds:
    stored = str(b.get("source_to_sha") or "")
    if stored and sha.startswith(stored):
        hit = b
        break

if hit is None:
    print("WAITING no-build-for-sha")
elif str(hit.get("status")) != "published":
    print("WAITING status=%s" % hit.get("status"))
elif int(stable.get("latest_build_id") or 0) != int(hit.get("id") or 0):
    print("WAITING stable-latest=%s" % stable.get("latest_build_id"))
else:
    print("READY %s %s" % (hit.get("id"), hit.get("core_build")))
PYEOF
}

# PHP harness: drives the public API + updater like the admin-updates page.
write_harness() {
  cat > /tmp/e2e_update_harness.php <<'PHPEOF'
<?php
declare(strict_types=1);

/**
 * TropaTT E2E update harness.
 * Drives the public update API + updater exactly like the admin-updates page.
 *
 * Usage: php e2e_update_harness.php <apply|rollback|status> [expected_build]
 *
 * Env: DEMO_BASE, DEMO_LOGIN, DEMO_PASSWORD, MAINT_FILE
 */

$mode = (string)($argv[1] ?? 'status');
$expectedBuild = (string)($argv[2] ?? '');
$base = getenv('DEMO_BASE') ?: 'https://demo.tropatt.com';
$login = getenv('DEMO_LOGIN') ?: 'admin';
$password = getenv('DEMO_PASSWORD') ?: 'adminadmin';
$maintFile = getenv('MAINT_FILE') ?: '/home/tropatt/web/demo.tropatt.com/public_html/storage_api/maintenance.flag';

function req(string $method, string $url, array $body, ?string $token, ?string $csrf): array
{
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token !== null && $token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    if ($csrf !== null && $csrf !== '') {
        $headers[] = 'X-CSRF-Token: ' . $csrf;
    }
    $opts = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers) . "\r\n",
            'ignore_errors' => true,
            'timeout' => 600,
            'follow_location' => 1,
        ],
        'ssl' => [
            'verify_peer' => (getenv('DEMO_SSL_VERIFY') ?: '1') !== '0',
            'verify_peer_name' => (getenv('DEMO_SSL_VERIFY') ?: '1') !== '0',
        ],
    ];
    if ($method === 'POST') {
        $opts['http']['content'] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
    $raw = @file_get_contents($url, false, stream_context_create($opts));
    $decoded = json_decode((string)$raw, true);
    return is_array($decoded) ? $decoded : ['success' => false, 'error' => 'invalid_response', 'raw' => substr((string)$raw, 0, 300)];
}

function fail(string $msg): never
{
    fwrite(STDERR, "HARNESS_FAIL: $msg\n");
    exit(1);
}

$auth = req('POST', "$base/api/index.php?route=api/v1/auth/login", ['login' => $login, 'password' => $password], null, null);
$token = (string)($auth['data']['access_token'] ?? '');
$csrf = (string)($auth['data']['csrf_token'] ?? '');
if ($token === '') {
    fail('login failed: ' . json_encode($auth, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
echo "LOGIN_OK\n";

if ($mode === 'status') {
    $st = req('GET', "$base/api/index.php?route=api/v1/core/updates/status", [], $token, $csrf);
    if (($st['success'] ?? false) !== true) {
        fail('status failed: ' . json_encode($st, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    echo "STATUS_OK " . json_encode($st['data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

if ($mode === 'rollback') {
    $up = req('POST', "$base/updater/index.php?action=status", [], null, null);
    $latest = $up['data']['latest_job'] ?? [];
    $jobId = (string)($latest['job_id'] ?? '');
    if ($jobId === '') {
        fail('rollback: no job found: ' . json_encode($up, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    if (($latest['can_rollback'] ?? false) !== true) {
        fail('rollback: latest job is not rollback-able (state=' . (string)($latest['state'] ?? '?') . '): ' . json_encode($latest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    $sess = req('POST', "$base/api/index.php?route=api/v1/core/updates/session", [], $token, $csrf);
    $updaterToken = (string)($sess['data']['updater_token'] ?? '');
    if ($updaterToken === '') {
        fail('rollback: session failed: ' . json_encode($sess, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    $rb = req('POST', "$base/updater/index.php?action=rollback", ['job_id' => $jobId, 'token' => $updaterToken], null, null);
    if (($rb['success'] ?? false) !== true) {
        fail('rollback failed: ' . json_encode($rb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    $data = $rb['data'] ?? [];
    $healthOk = (bool)(($data['health'] ?? [])['ok'] ?? false);
    $maint = file_exists($maintFile) ? 'ON' : 'OFF';
    if (!$healthOk) {
        fail('rollback: health not ok: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    if ($maint !== 'OFF') {
        fail('rollback: maintenance still ON');
    }
    $installedBuild = (string)(($data['installed_core'] ?? [])['core_build'] ?? '');
    echo "ROLLBACK_OK job=$jobId health=OK maintenance=OFF installed=$installedBuild\n";
    exit(0);
}
/* ---- apply mode ---- */
$check = req('POST', "$base/api/index.php?route=api/v1/core/updates/check", [], $token, $csrf);
if (($check['success'] ?? false) !== true) {
    fail('check failed: ' . json_encode($check, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
$plan = is_array($check['data']['plan'] ?? null) ? $check['data']['plan'] : (is_array($check['data'] ?? null) ? $check['data'] : []);
$updateAvailable = ($plan['update_available'] ?? false) === true;
$targetBuild = (string)($plan['target_build'] ?? '');
if (!$updateAvailable) {
    fail('check: no update available. plan=' . json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
if ($expectedBuild !== '' && $targetBuild !== $expectedBuild) {
    fail("check: target build mismatch expected=$expectedBuild got=$targetBuild");
}
echo "CHECK_OK target=$targetBuild\n";

$pre = req('POST', "$base/api/index.php?route=api/v1/core/updates/preflight", ['dry_run' => true], $token, $csrf);
if (($pre['success'] ?? false) !== true) {
    fail('preflight failed: ' . json_encode($pre, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
$jobId = (string)($pre['data']['job_id'] ?? '');
$preReport = is_array($pre['data']['preflight'] ?? null) ? $pre['data']['preflight'] : [];
if (($preReport['ok'] ?? false) !== true) {
    fail('preflight: not ok: ' . json_encode($preReport, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
echo "PREFLIGHT_OK job=$jobId\n";

$dl = req('POST', "$base/updater/index.php?action=download", ['dry_run' => true, 'job_id' => $jobId], null, null);
if (($dl['success'] ?? false) !== true) {
    fail('download failed: ' . json_encode($dl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
$fileCount = (int)(($dl['data']['staging'] ?? [])['file_count'] ?? 0);
echo "DOWNLOAD_OK files=$fileCount\n";

$sess = req('POST', "$base/api/index.php?route=api/v1/core/updates/session", [], $token, $csrf);
$updaterToken = (string)($sess['data']['updater_token'] ?? '');
if ($updaterToken === '') {
    fail('session failed: ' . json_encode($sess, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
echo "SESSION_OK\n";

$app = req('POST', "$base/updater/index.php?action=apply", ['job_id' => $jobId, 'confirm_apply' => true, 'token' => $updaterToken], null, null);
if (($app['success'] ?? false) !== true) {
    fail('apply failed: ' . json_encode($app, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
$data = $app['data'] ?? [];
$healthOk = (bool)(($data['health'] ?? [])['ok'] ?? false);
$installedBuild = (string)(($data['installed_core'] ?? [])['core_build'] ?? '');
$maint = file_exists($maintFile) ? 'ON' : 'OFF';
if (!$healthOk) {
    fail('apply: health not ok: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
if ($maint !== 'OFF') {
    fail('apply: maintenance still ON');
}
if ($expectedBuild !== '' && $installedBuild !== $expectedBuild) {
    fail("apply: installed build mismatch expected=$expectedBuild got=$installedBuild");
}
$appliedCount = (int)(($data['applied'] ?? [])['count'] ?? 0);
$backupId = (string)(($data['backup'] ?? [])['backup_id'] ?? '');
$migrations = (array)(($data['migrations'] ?? [])['executed'] ?? []);
echo "APPLY_OK job=$jobId files=$appliedCount backup=$backupId installed=$installedBuild migrations=" . count($migrations) . " maintenance=OFF\n";
exit(0);
PHPEOF
}

# ------------------------------------------------------------ functions ------
wait_for_build() {
  local sha="$1"
  local deadline=$((SECONDS + BUILD_WAIT))
  local out=""
  local last_scan=-999
  local scan_cmd="su -s /bin/sh - '$UPDATE_USER' -c '$UPDATE_PHP $UPDATE_ROOT/bin/cron.php run >> $UPDATE_ROOT/storage/logs/update-center-cron.log 2>&1'"
  echo "  [update-center] triggering scan"
  ssh $SSH_OPTS "$UPDATE_SSH" "$scan_cmd" || true
  last_scan=$SECONDS
  while (( SECONDS < deadline )); do
    out=$(ssh $SSH_OPTS "$UPDATE_SSH" "python3 /tmp/e2e_poll_build.py '$sha' '$UPDATE_ROOT'" 2>/dev/null || true)
    echo "  [update-center] $out"
    if [[ "$out" == READY* ]]; then
      # READY <build_id> <core_build>
      echo "$out" | awk '{print $3}'
      return 0
    fi
    # Re-scan at most every 2 minutes in case the first scan raced with the push.
    if (( SECONDS - last_scan >= 120 )); then
      ssh $SSH_OPTS "$UPDATE_SSH" "$scan_cmd" || true
      last_scan=$SECONDS
    fi
    sleep 10
  done
  echo "  [update-center] TIMEOUT: no published build for $sha after ${BUILD_WAIT}s" >&2
  return 1
}

run_harness() {
  # $1 = mode (apply|rollback|status), $2 = optional expected build
  ssh $SSH_OPTS "$DEMO_SSH" \
    "DEMO_BASE='$DEMO_BASE' DEMO_LOGIN='$DEMO_LOGIN' DEMO_PASSWORD='$DEMO_PASSWORD' MAINT_FILE='$DEMO_ROOT/storage_api/maintenance.flag' DEMO_SSL_VERIFY='$DEMO_SSL_VERIFY' $DEMO_PHP /tmp/e2e_update_harness.php '$1' '$2'"
}

marker_present() {
  # $1 = stamp; prints the number of locale files on the demo containing it
  ssh $SSH_OPTS "$DEMO_SSH" "grep -l '$1' $DEMO_ROOT/web/language/*.php 2>/dev/null | wc -l" | tr -d ' '
}

push_no_deploy() {
  # Bypass the repo's auto-deploy pre-push hook so the demo is only touched
  # through the update center.
  git push --no-verify "$GIT_REMOTE" "$GIT_BRANCH"
}

lint() {
  bash -n "$0" || return 1
  write_patch_script
  write_poll_script
  write_harness
  php -l /tmp/e2e_update_harness.php >/dev/null
  python3 -m py_compile /tmp/e2e_patch_locales.py /tmp/e2e_poll_build.py
  rm -f /tmp/e2e_patch_locales.py /tmp/e2e_poll_build.py /tmp/e2e_update_harness.php \
        /tmp/__pycache__/e2e_patch_locales*.pyc /tmp/__pycache__/e2e_poll_build*.pyc
  echo "LINT_OK: script + embedded helpers are syntactically valid"
  return 0
}

# ------------------------------------------------------------------ flags ----
while [[ $# -gt 0 ]]; do
  case "$1" in
    --yes) YES=1; shift ;;
    --test-rollback) TEST_ROLLBACK=1; shift ;;
    --keep-marker) KEEP_MARKER=1; shift ;;
    --lint) LINT=1; shift ;;
    --wait)
      [[ $# -ge 2 && "$2" =~ ^[0-9]+$ ]] || die_usage
      BUILD_WAIT="$2"; shift 2 ;;
    --wait=*)
      BUILD_WAIT="${1#*=}"; shift ;;
    --help|-h)
      sed -n '2,60p' "$0"
      exit 0
      ;;
    *)
      echo "ERROR: unknown argument: $1" >&2
      die_usage
      ;;
  esac
done

if [[ "$LINT" == "1" ]]; then
  lint
  exit $?
fi

if [[ "$YES" != "1" ]]; then
  echo "ERROR: this script pushes commits to GitHub and applies real updates on the demo."
  echo "Rerun with --yes to confirm. Use --lint for a local syntax check only."
  exit 2
fi

# ---------------------------------------------------------- preconditions ----
trap recover_hint EXIT

cd "$REPO_ROOT"
[[ -d .git ]] || fatal "not a git repository: $REPO_ROOT"
command -v python3 >/dev/null || fatal "python3 is required locally"
command -v php >/dev/null || fatal "php is required locally"

if [[ -n "$(git status --porcelain)" ]]; then
  echo "ERROR: working tree is not clean:" >&2
  git status --short >&2
  exit 2
fi

step "Preconditions"
ssh $SSH_OPTS "$DEMO_SSH" 'true' || fatal "cannot SSH to $DEMO_SSH"
ssh $SSH_OPTS "$UPDATE_SSH" 'true' || fatal "cannot SSH to $UPDATE_SSH"
echo "  demo:     $DEMO_SSH  ($DEMO_ROOT)"
echo "  update:   $UPDATE_SSH  ($UPDATE_ROOT)"
echo "  remote:   $GIT_REMOTE/$GIT_BRANCH -> $(git remote get-url "$GIT_REMOTE")"
echo "  head:     $(git rev-parse --short=12 HEAD)"

step "Write helpers to /tmp"
write_patch_script
write_poll_script
write_harness
scp -q $SSH_OPTS /tmp/e2e_poll_build.py "$UPDATE_SSH:/tmp/e2e_poll_build.py"
scp -q $SSH_OPTS /tmp/e2e_update_harness.php "$DEMO_SSH:/tmp/e2e_update_harness.php"
echo "  helpers uploaded"

# ============================================================== Phase A =====
STAMP="E2E_UPDATE_MARKER_$(date +%Y%m%d_%H%M%S)"
echo
echo "############################################################"
echo "# Phase A: push marker and apply the update on the demo"
echo "# marker:  $STAMP"
echo "############################################################"

python3 /tmp/e2e_patch_locales.py add "$STAMP" "${LOCALES[@]}"
git add "${LOCALES[@]}"
if git diff --cached --quiet; then
  fatal "marker patch produced no changes (marker already present?)"
fi
git commit -q -m "test(e2e): add E2E update marker $STAMP"
echo "  committed: $(git rev-parse --short=12 HEAD)"
push_no_deploy
SHA_A="$(git rev-parse HEAD)"
echo "  pushed:   $SHA_A"

step "Wait for update-center build of $SHA_A"
BUILD_A="$(wait_for_build "$SHA_A")"
echo "  target build: $BUILD_A"

step "Apply $BUILD_A on the demo via public API"
run_harness apply "$BUILD_A"

step "Verify marker on the demo"
COUNT_A="$(marker_present "$STAMP")"
echo "  locale files containing the marker on demo: $COUNT_A"
[[ "$COUNT_A" -ge 1 ]] || fatal "marker not found on the demo after apply"
echo "  OK: marker deployed through the update pipeline"

# =========================================================== Rollback =======
if [[ "$TEST_ROLLBACK" == "1" ]]; then
  step "Optional: exercise updater rollback"
  run_harness rollback
  COUNT_R="$(marker_present "$STAMP")"
  echo "  locale files still containing the marker after rollback: $COUNT_R"
  [[ "$COUNT_R" -eq 0 ]] || fatal "marker still present after rollback"
  echo "  OK: rollback restored the pre-update files and disabled maintenance"

  step "Re-apply $BUILD_A to keep state consistent"
  run_harness apply "$BUILD_A"
  echo "  OK: demo back on $BUILD_A with the marker"
fi

# ============================================================== Phase B =====
if [[ "$KEEP_MARKER" == "1" ]]; then
  step "Skipping Phase B (--keep-marker)"
  echo "  demo is on $BUILD_A with marker; local HEAD is the marker commit."
else
  echo
  echo "############################################################"
  echo "# Phase B: revert the marker and apply the next build"
  echo "############################################################"

  python3 /tmp/e2e_patch_locales.py remove "$STAMP" "${LOCALES[@]}"
  git add "${LOCALES[@]}"
  if git diff --cached --quiet; then
    fatal "marker removal produced no changes (marker not present?)"
  fi
  git commit -q -m "revert(test): remove E2E update marker $STAMP"
  echo "  committed: $(git rev-parse --short=12 HEAD)"
  push_no_deploy
  SHA_B="$(git rev-parse HEAD)"

  step "Wait for update-center build of $SHA_B"
  BUILD_B="$(wait_for_build "$SHA_B")"
  echo "  target build: $BUILD_B"

  step "Apply $BUILD_B on the demo via public API"
  run_harness apply "$BUILD_B"

  step "Verify marker is gone from the demo"
  COUNT_B="$(marker_present "$STAMP")"
  echo "  locale files containing the marker on demo: $COUNT_B"
  [[ "$COUNT_B" -eq 0 ]] || fatal "marker still present after Phase B"
  echo "  OK: marker removed through the update pipeline"
fi

# ================================================================ final =====
step "Final status"
run_harness status || true
if [[ -n "$(git status --porcelain)" ]]; then
  echo "NOTE: working tree has local changes (should be none after Phase B):"
  git status --short
fi
echo
echo "======================================================================"
echo "E2E UPDATE-CYCLE PASSED"
[[ "$KEEP_MARKER" == "1" ]] && echo "(marker intentionally kept: $STAMP)"
echo "  demo final build:  ${BUILD_B:-$BUILD_A}"
echo "  local head:        $(git rev-parse --short=12 HEAD)"
echo "======================================================================"
trap - EXIT
exit 0
