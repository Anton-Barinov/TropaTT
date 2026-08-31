#!/bin/bash
# TropaTT test suite — main entrypoint (CI / cron runs this).
#
# Builds the API registry from docs (+ drift vs routes.php), builds the MCP registry
# live via tools/list, runs the cases, and writes:
#   tests/reports/latest.json  (machine-readable, drives the auto-merge gate)
#   tests/reports/latest.md    (human-readable)
#   tests/reports/history/     (dated archive)
#
# CRITICAL (task spec 5.4): this script ALWAYS exits 0, regardless of failures found.
# "Did tests fail?" is communicated via latest.json `failed_count`, never the exit code.
set -u

HERE="$(cd "$(dirname "$0")" && pwd)"
REPORT_DIR="${HERE}/reports"
HISTORY_DIR="${REPORT_DIR}/history"
ENV_FILE="${REPORT_DIR}/../config/test.env"
ENV_EXAMPLE="${REPORT_DIR}/../config/test.env.example"
mkdir -p "${HISTORY_DIR}"

# ---- load config -------------------------------------------------------
if [ -f "${ENV_FILE}" ]; then
  # shellcheck disable=SC1090
  set -a; . "${ENV_FILE}"; set +a
else
  # allow env-var based config (CI style) without a file
  : "${BASE_URL:=}"
fi

if [ -z "${BASE_URL:-}" ]; then
  echo "No config. Copy tests/config/test.env.example -> tests/config/test.env and set BASE_URL (or export BASE_URL)." >&2
  python3 - "$REPORT_DIR" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" <<'PY' >/dev/null
import json, sys
json.dump({"run_at": sys.argv[2], "failed_count": -1, "errors": ["missing BASE_URL config"]}, open(sys.argv[1]+"/latest.json","w"), ensure_ascii=False, indent=2)
PY
  exit 0
fi

MCP_URL="${MCP_URL:-${BASE_URL%/}/mcp}"
TOKEN="${TOKEN:-}"
LOGIN="${LOGIN:-}"
PASSWORD="${PASSWORD:-}"

# ---- obtain a token ----------------------------------------------------
if [ -z "${TOKEN}" ] && [ -n "${LOGIN}" ]; then
  TOKEN=$(python3 - "$BASE_URL" "$LOGIN" "$PASSWORD" <<'PY'
import sys, json, urllib.request
base, login, pwd = sys.argv[1], sys.argv[2], sys.argv[3]
req = urllib.request.Request(base + "/auth/login", data=json.dumps({"login":login,"password":pwd}).encode(), headers={"Content-Type":"application/json"}, method="POST")
try:
    with urllib.request.urlopen(req, timeout=25) as r:
        b = json.loads(r.read())
    print(b.get("data", {}).get("access_token", ""))
except Exception:
    print("")
PY
)
fi
if [ -z "${TOKEN:-}" ]; then
  echo "No TOKEN and login failed. Provide TOKEN or LOGIN/PASSWORD in tests/config/test.env." >&2
fi
export TOKEN
export BASE_URL
export MCP_URL

# ---- run ---------------------------------------------------------------
RUN_AT=$(date -u +%Y-%m-%dT%H:%M:%SZ)
COMMIT=$(cd "${HERE}/.." && git rev-parse --short HEAD 2>/dev/null || echo "unknown")
BRANCH=$(cd "${HERE}/.." && git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "unknown")

# API registry (docs + drift)
API_JSON=$(python3 "${HERE}/lib/inventory_api.py")
API_CASES=$(echo "$API_JSON" | python3 "${HERE}/cases/api/run.py")
# MCP registry (live tools/list) + cases
MCP_JSON=$(python3 "${HERE}/lib/inventory_mcp.py")
MCP_CASES=$(echo "$MCP_JSON" | python3 "${HERE}/cases/mcp/run.py")

# ---- aggregate into latest.json / latest.md -----------------------------
python3 - "$RUN_AT" "$BASE_URL" "$BRANCH" "$COMMIT" "$API_JSON" "$API_CASES" "$MCP_JSON" "$MCP_CASES" "$REPORT_DIR" <<'PY'
import sys, json, datetime
run_at, base_url, branch, commit = sys.argv[1], sys.argv[2], sys.argv[3], sys.argv[4]
api_inv = json.loads(sys.argv[5]); api_cases = json.loads(sys.argv[6])["cases"]
mcp_inv = json.loads(sys.argv[7]); mcp_cases = json.loads(sys.argv[8])["cases"]

tot = {"api_total":0,"api_passed":0,"api_failed":0,"api_skipped":0,
       "mcp_total":0,"mcp_passed":0,"mcp_failed":0,"mcp_skipped":0}
for c in api_cases:
    tot["api_total"] += 1
    k = f"api_{c['status']}"
    if c["status"]=="skipped": tot["api_skipped"] += 1
    elif c["status"]=="passed": tot["api_passed"] += 1
    else: tot["api_failed"] += 1
for c in mcp_cases:
    tot["mcp_total"] += 1
    if c["status"]=="skipped": tot["mcp_skipped"] += 1
    elif c["status"]=="passed": tot["mcp_passed"] += 1
    else: tot["mcp_failed"] += 1

failed_count = tot["api_failed"] + tot["mcp_failed"]
failures = [c for c in api_cases+mcp_cases if c["status"]=="failed"]
drift = api_inv.get("drift", [])

report = {
  "run_at": run_at, "target_base_url": base_url,
  "branch_tested": branch, "commit": commit,
  "totals": tot, "failed_count": failed_count,
  "documentation_drift": drift, "failures": failures,
}
md_lines = [
 f"# TropaTT Test Report — {run_at}",
 f"- target: `{base_url}`",
 f"- branch: `{branch}` @ `{commit}`",
 "",
 f"## Totals",
 f"- API: {tot['api_total']} total, {tot['api_passed']} passed, {tot['api_failed']} failed, {tot['api_skipped']} skipped",
 f"- MCP: {tot['mcp_total']} total, {tot['mcp_passed']} passed, {tot['mcp_failed']} failed, {tot['mcp_skipped']} skipped",
 f"- **failed_count = {failed_count}**" + ("  ✅ safe to merge develop->main" if failed_count==0 else "  ❌ keep changes in develop"),
 "",
]
if drift:
    md_lines.append(f"## Documentation drift ({len(drift)})")
    for d in drift[:50]:
        md_lines.append(f"- `{d.get('type')}` {d.get('method','')} {d.get('route','')}")
    md_lines.append("")
if failures:
    md_lines.append(f"## Failures ({len(failures)})")
    for f in failures[:100]:
        md_lines.append(f"- {f.get('kind','')} {f.get('method','')} {f.get('path','')} expected={f.get('expected')} actual={f.get('actual')} msg={f.get('error')}")
    md_lines.append("")
with open(sys.argv[9]+"/latest.json","w") as f: json.dump(report,f,ensure_ascii=False,indent=2)
with open(sys.argv[9]+"/latest.md","w") as f: f.write("\n".join(md_lines))
with open(sys.argv[9]+f"/history/{run_at.replace(':','-').replace('T','_')}.json","w") as f: json.dump(report,f,ensure_ascii=False,indent=2)
print(json.dumps(report))
PY

# copy default fixture cache
cp "${HERE}/fixtures/fixtures.json" "${HERE}/fixtures/fixtures.cache.json" 2>/dev/null || true

# Always exit 0 (task spec 5.4)
exit 0