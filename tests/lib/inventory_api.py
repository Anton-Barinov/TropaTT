#!/usr/bin/env python3
"""Build the API-route registry automatically, never by hand.

Sources:
  1. Parse endpoint tables (| Method | Endpoint | Description | Auth | Permissions | Notes |)
     from docs_api/api_en.md (in-repo, available to the runner at clone time).
  2. If the project source is available (routes.php present), cross-check with
     upload/api/config/routes.php to catch documentation drift.

Prints a JSON registry of routes to stdout:
  {
    "routes": [ {method, path, auth, permissions, notes, source} ... ],
    "drift":   [ {type, route, method} ... ]   # missing_in_docs | missing_in_code
    "skip_rules": [...]
  }
"""
import json
import re
import os

REPO_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
DOC_API = os.path.join(REPO_ROOT, "docs_api", "api_en.md")
ROUTES_PHP = os.path.join(REPO_ROOT, "upload", "api", "config", "routes.php")

# Rows look like: | GET, POST | `/api/v1/...` 🔄 | Description | Yes | `perm` | notes |
# The optional alias marker (🔄) sits between the path backtick and the next pipe.
ROW_RE = re.compile(
    r"^\|\s*(?P<methods>[A-Z][A-Z,\s]*)\s*\|\s*`(?P<path>[^`]+)`(?:\s*🔄)?\s*"
    r"\|\s*(?P<desc>[^|]*)\s*\|\s*(?P<auth>No|Yes|[— ]+)\s*\|\s*(?P<perms>.*?)\s*\|\s*(?P<notes>[^|]*?)\s*\|\s*$"
)


def parse_doc(path=DOC_API):
    routes = []
    if not os.path.exists(path):
        return routes
    with open(path, "r", encoding="utf-8") as f:
        for line in f:
            m = ROW_RE.match(line)
            if not m:
                continue
            methods = [x.strip() for x in m.group("methods").split(",") if x.strip()]
            perms = [x.strip().strip('`') for x in m.group("perms").split(",") if x.strip()]
            auth = m.group("auth").strip() == "Yes"
            routes.append({
                "method": methods,
                "path": m.group("path").strip(),
                "description": m.group("desc").strip(),
                "auth": auth,
                "permissions": perms,
                "notes": m.group("notes").strip(),
                "source": "docs",
            })
    return routes


def parse_routes_php(path=ROUTES_PHP):
    """Extract (method, pattern) from routes.php loose enough to be robust."""
    routes = []
    if not os.path.exists(path):
        return routes
    with open(path, "r", encoding="utf-8") as f:
        content = f.read()
    # match: ['methods' => ['GET', 'POST'], 'pattern' => '/path', 'controller' => ...
    pattern = re.compile(
        r"\[\s*'methods'\s*=>\s*\[(?P<methods>.*?)\]\s*,\s*'pattern'\s*=>\s*'(?P<path>[^']+)'",
        re.S,
    )
    for m in pattern.finditer(content):
        methods = re.findall(r"'([A-Z]+)'", m.group("methods"))
        path = m.group("path").strip()
        if path.startswith("/api/v1/"):
            routes.append((methods, path))
    return routes


def normalize(path):
    return re.sub(r"\{[^}]+\}", "{}", path.replace(",", ""))


LEGACY_ALIAS_RE = re.compile(r"/(create|list|get|update|delete)/(?:\)|\{[^}]*\})$")


def _is_legacy_alias(path):
    return bool(LEGACY_ALIAS_RE.search(path))


def _is_core(path):
    return path.startswith("/api/v1/") and "/_module/" not in path


def build_drift(doc_routes, code_routes):
    """Drift is scoped to core /api/v1/ routes only.

    Module routes (_module/...) are defined in per-module route files, not in the core
    routes.php, so comparing them here would only produce noise. They are exercised via
    the live MCP tools/list instead.
    """
    doc_norm = set()
    for r in doc_routes:
        if not _is_core(r["path"]):
            continue
        for method in r["method"]:
            doc_norm.add((method, normalize(r["path"])))
    code_norm = set()
    for methods, path in code_routes:
        if not _is_core(path):
            continue
        for method in methods:
            code_norm.add((method, normalize(path)))

    drift = []
    for (m, p) in sorted(doc_norm):
        if (m, p) not in code_norm:
            drift.append({"type": "missing_in_code", "route": p, "method": m})
    for (m, p) in sorted(code_norm):
        if (m, p) not in doc_norm:
            # legacy CRUD aliases are intentionally excluded from api_en.md
            item = {"type": "missing_in_docs", "route": p, "method": m}
            if _is_legacy_alias(p):
                item["legacy_alias"] = True
            drift.append(item)
    return drift


def main():
    doc_routes = parse_doc()
    code_routes = parse_routes_php()
    drift = build_drift(doc_routes, code_routes) if code_routes else []
    result = {
        "routes": doc_routes,
        "code_routes_count": len(code_routes),
        "doc_routes_count": len(doc_routes),
        "drift": drift,
        "skip_rules": [
            "path starts with /install/*",
            "legacy CRUD aliases (/create /list /get/{id} /update/{id} /delete/{id})",
            "module routes for modules not installed on the stand",
        ],
    }
    print(json.dumps(result, ensure_ascii=False))


if __name__ == "__main__":
    main()