#!/usr/bin/env python3
"""TropaTT test-suite HTTP client wrapper.

Isolated stdlib-only wrapper so cases never dial the network directly:
central timeouts, retries, logging, and consistent error capture.
"""
import json
import urllib.request
import urllib.error
import ssl
import time

TIMEOUT = 20
RETRIES = 1

_CTX = ssl.create_default_context()
_CTX.check_hostname = False
_CTX.verify_mode = ssl.CERT_NONE


class HttpError(Exception):
    def __init__(self, status, body):
        self.status = status
        self.body = body
        super().__init__(f"HTTP {status}")


def request(method, url, headers=None, json_body=None, timeout=TIMEOUT, retries=RETRIES):
    """Return (status, parsed_json_or_text, error_str). Never raises for HTTP status."""
    last_err = None
    data = None
    hdrs = dict(headers or {})
    hdrs.setdefault("Accept", "application/json")
    if json_body is not None:
        data = json.dumps(json_body).encode("utf-8")
        hdrs.setdefault("Content-Type", "application/json")

    for attempt in range(retries + 1):
        req = urllib.request.Request(url, data=data, headers=hdrs, method=method)
        try:
            with urllib.request.urlopen(req, timeout=timeout, context=_CTX) as resp:
                raw = resp.read()
                status = resp.getcode()
                return status, _parse(raw), None
        except urllib.error.HTTPError as e:
            raw = e.read()
            return e.code, _parse(raw), None
        except Exception as e:  # noqa: BLE001 - network/ssl/timeout
            last_err = repr(e)
            if attempt < retries:
                time.sleep(1)
    return None, None, last_err


def _parse(raw):
    text = raw.decode("utf-8", "replace")
    try:
        return json.loads(text)
    except Exception:
        return text