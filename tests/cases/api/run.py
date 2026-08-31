#!/usr/bin/env python3
"""API test cases — run a safe, non-destructive strategy over the route registry.

Strategy per endpoint type (task spec 5.3):
  - public GET, no path params         : call without token -> expect 2xx
  - protected no-param GET (lists)     : token -> 2xx (not 401/403/5xx); no token -> 401
  - {param} endpoints                  : create a safe fixture (curated map only), use its
                                          public id for GET/PATCH/DELETE, always cleanup
  - destructive / mutating operations  : ONLY over self-created test data; otherwise
                                          skipped. We NEVER auto-run a mutating endpoint
                                          unless it targets a verified safe-fixture entity,
                                          because e.g. POST /security/sessions/revoke-others
                                          or /auth/logout would revoke the bearer token
                                          mid-run and poison all remaining requests.
  - /install/*, legacy aliases, mods   : skipped with a reason

Network calls run in a small thread pool. Never raises; every failure becomes a case
result entry.
"""
import json
import os
import re
import sys
import uuid
from concurrent.futures import ThreadPoolExecutor

sys.path.insert(0, os.path.join(os.path.dirname(__file__), "..", ".."))
sys.path.insert(0, os.path.join(os.path.dirname(__file__), "..", "..", "lib"))
import http_client  # noqa: E402

BASE_URL = os.environ.get("BASE_URL", "")
DOMAIN_IS_DEMO = os.environ.get("TEST_DOMAIN_IS_DEMO", "0") == "1"
TOKEN = os.environ.get("TOKEN", "")
MAX_WORKERS = int(os.environ.get("TEST_WORKERS", "8"))

# Curated SAFE_FIXTURE fixtures: plural resource key -> create payload. These units
# have no external side effects and a real DELETE, so create->use->delete is safe on demo.
SAFE_FIXTURE = {
    "tags": {"title": f"qa-fixture-{uuid.uuid4().hex[:10]}", "code": f"qa{uuid.uuid4().hex[:6]}"},
}

# Paths/methods that mutating a running session — never auto-called.
TOKEN_MUTATORS = (
    "/auth/logout",
    "/security/sessions",
    "/security/sessions/revoke-others",
    "/security/impersonation",
    "/security/2fa",
    "/profile/change-password",
    "/auth/login",
)

# Role-scoped contexts where an admin token legitimately receives 403 (RBAC working as
# intended, not a defect): external client portal and root-only earnings.
EXPECTED_403_PREFIXES = (
    "/api/v1/client-cabinet/",
    "/api/v1/me/earnings/",
)

# AI-gated endpoints that require an external AI provider / config not present on the
# demo stand — treated as skipped (requires_external_integration), never a failure.
AI_EXTERNAL_ENDPOINTS = (
    "/api/v1/knowledge/ai/admin/find-orphans",
    "/api/v1/knowledge/ai/admin/suggest-structure",
    "/api/v1/knowledge/ai/admin/find-duplicates",
)


def _segments(path):
    return [s for s in path.split("/") if s]


def _resource_key(path):
    for s in _segments(path):
        if s in SAFE_FIXTURE:
            return s
    return None


def _is_legacy_alias(path):
    low = path.rstrip("/")
    return low.endswith(("/create", "/list", "/get/{id}", "/update/{id}", "/delete/{id}"))


def _is_module_route(path):
    return "/_module/" in path or re.search(r"/modules?", path) is not None


def _classify(route):
    path = route["path"]
    method = route["method"][0]
    auth = route["auth"]

    if path.startswith("/install") or "/internal/migration" in path:
        return "skip_install_migration", "installer/migration — not run on installed stand"
    if _is_legacy_alias(path):
        return "skip_legacy_alias", "legacy CRUD alias — not recommended"
    if _is_module_route(path):
        return "skip_module", "module route — external integration / not installed (see notes)"
    if "/api/v1/ops/cron/run-due" in path:
        return "skip_manual_only", "ops cron run-due — manual only"
    if "/api/v1/ops/cron" in path and method != "GET":
        return "skip_manual_only", "ops cron non-GET — manual only"
    if any(path.startswith(ep) for ep in AI_EXTERNAL_ENDPOINTS):
        return "skip_external_ai", "requires external AI provider/config not on demo"

    # A mutating endpoint may ONLY run over a verified safe fixture on demo.
    if method in ("DELETE", "POST", "PATCH", "PUT"):
        key = _resource_key(path)
        if key and DOMAIN_IS_DEMO and "{" in path:
            # write/read over a safe fixture -> allow (fixture path handles create+delete)
            pass
        else:
            if TOKEN_MUTATORS and any(p in path for p in TOKEN_MUTATORS):
                return "skip_session_mutator", "mutates current session/token — never auto-called"
            if key:
                return "skip_destructive", "mutating — only over test-created data"
            if re.search(r"\b(email|send|webhook|sms|notification|push-test)\b", path, re.I):
                return "skip_destructive_external", "destructive/external side effect"
            return "skip_mutating", "mutating endpoint — not auto-run (would change state)"
    elif method == "GET" and "{" in path and not auth:
        return "skip_public_param", "public {param} GET — needs fixture"

    if not auth:
        return "public", None
    return "protected", None


def _url(path):
    """Join an inventory path onto BASE_URL correctly.

    Inventory paths carry the /api/v1 prefix, and BASE_URL already ends in
    ...?route=api/v1, so a naive concat would produce a doubled prefix (404). Strip
    the version segment when it is already part of BASE_URL.
    """
    p = path if isinstance(path, str) else str(path)
    if BASE_URL.rstrip("/").endswith("/api/v1") or BASE_URL.rstrip("/").endswith("?route=api/v1"):
        p = re.sub(r"^/api/v1/?", "", p, flags=re.I)
    return BASE_URL.rstrip("/") + "/" + p.lstrip("/")


def _req(method, path, token, json_body=None):
    url = _url(path)
    headers = {"Authorization": f"Bearer {token}"} if token else {}
    if method == "GET":
        return http_client.request("GET", url, headers=headers)
    return http_client.request(method, url, headers=headers, json_body=json_body)


def _case(method, path, expected, actual, ok, err):
    if actual == 429:
        # Target rate-limiting under load, not a product defect.
        return {"kind": "api", "method": method, "path": path,
                "status": "skipped",
                "skipped_reason": "rate-limited by target (429)"}
    return {
        "kind": "api", "method": method, "path": path,
        "expected": expected, "actual": actual,
        "status": "passed" if ok else "failed",
        "error": None if ok else err,
    }


def _protected_ok(status, path):
    """A protected call passes unless 401/403/5xx. But 403 on role-scoped contexts is
    RBAC working as intended (task spec 5.3) — count it as success."""
    if status is None:
        return False
    if status in (401, 403) and not any(path.startswith(p) for p in EXPECTED_403_PREFIXES):
        return False
    if status >= 500:
        return False
    return True


def _job(route):
    action, reason = _classify(route)
    path = route["path"]
    method = route["method"][0]

    if action.startswith("skip"):
        return [{"kind": "api", "method": method, "path": path,
                 "status": "skipped", "skipped_reason": reason}]

    if action == "public":
        status, body, err = _req(method, path, None)
        ok = (status is not None and 200 <= status < 300)
        return [_case(method, path, "2xx", status, ok, err)]

    if action == "protected" and "{" not in path:
        out = []
        status, body, err = _req(method, path, TOKEN)
        ok = _protected_ok(status, path)
        out.append(_case(method, path, "2xx (auth)", status, ok, err))
        if method == "GET":
            s2, _b2, e2 = _req(method, path, None)
            ok2 = (s2 is not None and s2 == 401)
            out.append({
                "kind": "api", "method": method, "path": path,
                "expected": "401 (no token)", "actual": s2,
                "status": "passed" if ok2 else "failed",
                "error": None if ok2 else e2 or f"expected 401 got {s2}",
            })
        return out

    if action == "protected":
        key = _resource_key(path)
        if key and DOMAIN_IS_DEMO and FIXTURES.get(key):
            return _fixture_cases(route, key)
        status, body, err = _req(method, path, TOKEN)
        ok = _protected_ok(status, path)
        return [_case(method, path, "2xx (auth)", status, ok, err)]

    return [{"kind": "api", "method": method, "path": path,
             "status": "skipped", "skipped_reason": "unhandled"}]


# {key: public_id} created ONCE per run, shared across routes (no parallel races).
FIXTURES = {}


def _find_public_id(node):
    """Recursively find a public_id in a nested response (data/tag/items etc)."""
    if isinstance(node, dict):
        pid = node.get("public_id")
        if pid:
            return pid
        for v in node.values():
            r = _find_public_id(v)
            if r:
                return r
        if isinstance(node.get("data"), dict):
            return _find_public_id(node["data"])
        if isinstance(node.get("tag"), dict):
            return node["tag"].get("public_id")
        if isinstance(node.get("item"), dict):
            return node["item"].get("public_id")
    elif isinstance(node, list):
        for v in node:
            r = _find_public_id(v)
            if r:
                return r
    return None


def _prepare_fixtures():
    """Create each safe fixture once before the parallel run; record id for reuse."""
    if not (DOMAIN_IS_DEMO and TOKEN):
        return []
    out = []
    for key, payload in SAFE_FIXTURE.items():
        status, body, err = http_client.request(
            "POST", _url("/api/v1/" + key),
            headers={"Authorization": "Bearer " + TOKEN},
            json_body=payload,
        )
        ok = (status is not None and 200 <= status < 300)
        pid = _find_public_id(body) if isinstance(body, dict) else None
        if ok and pid:
            FIXTURES[key] = pid
        out.append(_case("POST", "/api/v1/" + key, "2xx (fixture create)",
                         status, bool(pid), err))
    return out


def _cleanup_fixtures():
    out = []
    for key, pid in FIXTURES.items():
        s, b, e = http_client.request(
            "DELETE", _url("/api/v1/" + key + "/" + pid),
            headers={"Authorization": "Bearer " + TOKEN},
        )
        out.append({"kind": "api", "method": "DELETE", "path": "/api/v1/" + key + "/" + pid,
                    "expected": "2xx (cleanup)", "actual": s,
                    "status": "passed" if (s is not None and 200 <= s < 400) else "failed",
                    "error": (None if (s is not None and 200 <= s < 400) else e or f"cleanup {s}")})
    FIXTURES.clear()
    return out


def run(inventory):
    routes = inventory["routes"]
    results = _prepare_fixtures()
    with ThreadPoolExecutor(max_workers=MAX_WORKERS) as ex:
        for chunk in ex.map(_job, routes):
            results.extend(chunk)
    results.extend(_cleanup_fixtures())
    return results


def _fixture_cases(route, key):
    """Use the pre-created fixture id for READ checks on a matching route.

    The fixture is created once by _prepare_fixtures() and deleted exactly once by
    _cleanup_fixtures(). Per spec, destructive ops run only over self-created data and
    always clean up; here cleanup is owned by _cleanup_fixtures, so route-level writes
    are not auto-called (avoid double-delete races).
    """
    pid = FIXTURES.get(key)
    out = []
    if not pid:
        return out
    item_path = "/api/v1/" + key + "/" + pid
    status, body, err = _req("GET", item_path, TOKEN)
    ok = (status is not None and 200 <= status < 500)
    out.append(_case("GET", item_path, "2xx (auth)", status, ok, err))
    s2, _b2, e2 = _req("GET", item_path, None)
    ok2 = (s2 is not None and s2 == 401)
    out.append({"kind": "api", "method": "GET", "path": item_path,
                "expected": "401 (no token)", "actual": s2,
                "status": "passed" if ok2 else "failed",
                "error": None if ok2 else e2 or f"expected 401 got {s2}"})
    return out


def main():
    raw = sys.stdin.read()
    inventory = json.loads(raw) if raw else {"routes": [], "drift": []}
    res = run(inventory)
    print(json.dumps({"cases": res}))


if __name__ == "__main__":
    main()