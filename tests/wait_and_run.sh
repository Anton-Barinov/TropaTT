#!/bin/bash
# Wait until demo reflects the just-pushed develop commit, then run the test suite.
# This guarantees tests start only AFTER the demo has actually updated.
#
# Detection: admin-updates demo writes upload/web/DEPLOY_HASH as "<short-hash>-<ts>".
# We poll that marker (configurable via DEPLOY_HASH_URL) until it starts with the target
# short commit, falling back to /version core_build match. Both compare the first token.
#
# Usage: tests/wait_and_run.sh [<target_commit>] [<timeout_seconds>]
#   target_commit : short git hash pushed to develop (default: current HEAD)
#   timeout_seconds: default 1800 (30 min)
set -u

HERE="$(cd "$(dirname "$0")" && pwd)"
ENV_FILE="${HERE}/config/test.env"
[ -f "${ENV_FILE}" ] && { set -a; . "${ENV_FILE}"; set +a; }

TARGET="${1:-$(git -C "${HERE}/.." rev-parse --short HEAD)}"
TIMEOUT="${2:-1800}"
INTERVAL="${POLL_INTERVAL:-30}"

# Default deploy-hash marker URL is derived from BASE_URL. Allow override for CI.
if [ -n "${DEPLOY_HASH_URL:-}" ]; then
  MARKER_URL="$DEPLOY_HASH_URL"
elif [ -n "${BASE_URL:-}" ]; then
  # BASE_URL ends with /api/index.php?route=api/v1 ; web root is ../..
  BASE_REL="${BASE_URL%%/api/index.php*}"
  MARKER_URL="${BASE_REL}/web/DEPLOY_HASH"
else
  MARKER_URL=""
fi

echo "Waiting for demo to reach develop commit '${TARGET}' (timeout ${TIMEOUT}s, poll ${INTERVAL}s)..."
echo "  marker: ${MARKER_URL:-<none>}"
ELAPSED=0
while [ "${ELAPSED}" -lt "${TIMEOUT}" ]; do
  MATCH=0
  if [ -n "${MARKER_URL}" ]; then
    MARKER=$(curl -sk -m 15 "${MARKER_URL}" 2>/dev/null | tr -d '[:space:]')
    # marker format: <short-hash>-<ts> ; compare first token
    MARKER_HASH="${MARKER%%-*}"
    if [ -n "${MARKER}" ] && [ "${MARKER_HASH}" = "${TARGET}" ]; then
      echo "  ✅ DEMO_HASH matches target (${TARGET}). Proceeding to tests."
      MATCH=1
    fi
  fi
  if [ "${MATCH}" = "0" ] && [ -n "${BASE_URL:-}" ]; then
    # fallback: /version core_build/commit match
    VERSION_JSON=$(curl -sk -m 15 "${BASE_URL}/version" 2>/dev/null)
    if echo "$VERSION_JSON" | grep -qi "${TARGET}"; then
      echo "  ✅ /version matches commit (${TARGET}). Proceeding to tests."
      MATCH=1
    fi
  fi
  [ "${MATCH}" = "1" ] && break
  sleep "${INTERVAL}"
  ELAPSED=$((ELAPSED + INTERVAL))
done

if [ "${ELAPSED}" -ge "${TIMEOUT}" ]; then
  echo "⚠ Timed out waiting for demo to reach ${TARGET}. Running tests anyway against current state."
fi

exec bash "${HERE}/run_all.sh"