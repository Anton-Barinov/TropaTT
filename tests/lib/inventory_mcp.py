#!/usr/bin/env python3
"""Build the MCP tool registry live, not from docs.

Sends tools/list to the running MCP endpoint and prints the tool list to stdout
as JSON: { "tools": [ {name, description, input_schema} ... ] }.
"""
import json
import sys
import os

sys.path.insert(0, os.path.dirname(__file__))
import http_client  # noqa: E402


def list_tools(mcp_url, token):
    payload = {"jsonrpc": "2.0", "id": 1, "method": "tools/list"}
    headers = {"Authorization": f"Bearer {token}"}
    status, body, err = http_client.request("POST", mcp_url, headers=headers, json_body=payload)
    if err or status is None:
        return None, f"network error: {err}"
    if status != 200:
        return None, f"HTTP {status}: {body if isinstance(body, str) else json.dumps(body)[:500]}"
    # JSON-RPC response: {"jsonrpc","id","result": {"tools": [...]}}
    result = body.get("result") if isinstance(body, dict) else None
    tools = (result or {}).get("tools") if result else None
    if tools is None:
        return None, f"unexpected MCP envelope: {json.dumps(body)[:500]}"
    return tools, None


def main():
    mcp_url = os.environ.get("MCP_URL", "")
    token = os.environ.get("TOKEN", "")
    tools, err = list_tools(mcp_url, token)
    if err:
        print(json.dumps({"error": err, "tools": []}))
        sys.exit(0)  # never fail the whole run
    print(json.dumps({"tools": tools, "count": len(tools)}))


if __name__ == "__main__":
    main()