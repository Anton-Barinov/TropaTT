#!/usr/bin/env python3
"""MCP test cases — read-only strategy over the live tools/list result.

For every tool returned by tools/list:
  - read/list/lookup tools -> tools/call and expect a result.
  - write/admin/destructive tools -> skipped with a reason (do NOT call them).
Calls run in a small thread pool; a failure never aborts the run.

Prints JSON: { "cases": [...] }
"""
import json
import os
import re
import sys
from concurrent.futures import ThreadPoolExecutor

sys.path.insert(0, os.path.join(os.path.dirname(__file__), "..", ".."))
sys.path.insert(0, os.path.join(os.path.dirname(__file__), "..", "..", "lib"))
import http_client  # noqa: E402

MCP_URL = os.environ.get("MCP_URL", "")
TOKEN = os.environ.get("TOKEN", "")
DOMAIN_IS_DEMO = os.environ.get("TEST_DOMAIN_IS_DEMO", "0") == "1"
MAX_WORKERS = int(os.environ.get("TEST_WORKERS", "8"))

READ_HINTS = {"list", "get", "search", "show", "read", "lookup", "view", "fetch", "me",
              "current", "count", "status", "info", "describe", "history", "summarize",
              "recent", "available", "tree", "categories"}
WRITE_HINTS = {"create", "add", "update", "set", "delete", "remove", "send", "post",
               "write", "edit", "save", "assign", "move", "archive", "restore",
               "impersonate", "change", "rotate", "revoke", "publish", "share", "mark",
               "invite", "activate", "deactivate", "install", "run", "start", "stop",
               "import", "export", "migrate", "merge", "refresh", "regenerate", "execute",
               "submit", "approve", "reject", "pin", "unpin", "complete", "cancel"}

_TOK_RE = re.compile(r"[^a-z0-9]")


def _tokens(name):
    return {t for t in _TOK_RE.split(name.lower()) if t}


def classify(tool):
    name = tool.get("name", "")
    toks = _tokens(name)
    is_read = bool(toks & READ_HINTS)
    is_write = bool(toks & WRITE_HINTS)
    input_schema = tool.get("inputSchema") or tool.get("input_schema") or {}
    props = (input_schema.get("properties") or {}) if isinstance(input_schema, dict) else {}
    required = set(input_schema.get("required") or []) if isinstance(input_schema, dict) else set()

    if is_read and not is_write:
        return "read", None
    if is_write:
        if DOMAIN_IS_DEMO:
            return "write", "write tool over demo test data only — skipped for automated run (no safe isolation without fixtures)"
        return "write", "write tool — not run (non-demo target)"
    if not required:
        return "read", None
    return "write", "unknown tool shape — skipped"


def _call_tool(tool):
    name = tool.get("name", "")
    action, reason = classify(tool)
    if action == "write":
        return {"kind": "mcp", "method": "tools/call", "path": name,
                "status": "skipped", "skipped_reason": reason}
    payload = {"jsonrpc": "2.0", "id": 1, "method": "tools/call",
               "params": {"name": name, "arguments": {}}}
    headers = {"Authorization": f"Bearer {TOKEN}"}
    status, body, err = http_client.request("POST", MCP_URL, headers=headers, json_body=payload)
    ok = (err is None and status is not None and 200 <= status < 300 and
          (body.get("result") is not None if isinstance(body, dict) else False))
    if status == 429:
        # Target protection under load, not a product defect. Report as capacity-skip.
        return {"kind": "mcp", "method": "tools/call", "path": name,
                "status": "skipped", "skipped_reason": "rate-limited by target (429)"}
    return {"kind": "mcp", "method": "tools/call", "path": name,
            "expected": "result", "actual": status,
            "status": "passed" if ok else "failed",
            "error": (None if ok else (err or f"no result (HTTP {status})"))}


def run(tools):
    with ThreadPoolExecutor(max_workers=MAX_WORKERS) as ex:
        return list(ex.map(_call_tool, tools))


def main():
    raw = sys.stdin.read()
    data = json.loads(raw) if raw else {"tools": []}
    res = run(data.get("tools", []))
    print(json.dumps({"cases": res}))


if __name__ == "__main__":
    main()