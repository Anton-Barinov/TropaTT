# Webhook Security

This document explains how TropaTT webhooks work and how to secure, verify, and operate them on self-hosted installations.

---

## What are webhooks?

Webhooks allow TropaTT to send real-time HTTP POST requests to external services when events happen in your workspace. For example, you can send a notification to Slack when a task status changes, or update an external CRM when a client record is created.

---

## Authentication and signing model

Every webhook payload includes a signature that allows the receiving endpoint to verify that the request comes from your TropaTT instance and has not been tampered with.

### HMAC-SHA256 signing

Each webhook request includes a signature header:

```
X-TropaTT-Signature: t=1234567890,s=abc123def456...
```

The signature is computed as:

```
HMAC-SHA256(webhook_secret, payload_json + '.' + timestamp)
```

Where:
- `webhook_secret` — a secret key you generate and configure in the webhook settings
- `payload_json` — the raw JSON body of the webhook request
- `timestamp` — the Unix timestamp when the signature was created

### How to verify the signature on the receiving side

```php
<?php
// Example: PHP webhook receiver
$secret = 'your-webhook-secret';

$body = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_X_TROPA_SIGNATURE'] ?? '';

// Parse the signature header
$parts = [];
foreach (explode(',', $signatureHeader) as $part) {
    $kv = explode('=', $part, 2);
    if (count($kv) === 2) {
        $parts[$kv[0]] = $kv[1];
    }
}

$timestamp = $parts['t'] ?? 0;
$signature = $parts['s'] ?? '';

// Verify timestamp is within 5 minutes (300 seconds)
if (abs(time() - (int)$timestamp) > 300) {
    http_response_code(403);
    echo 'Invalid timestamp';
    exit;
}

// Compute expected signature
$expected = hash_hmac('sha256', $body . '.' . $timestamp, $secret);

// Constant-time comparison
if (!hash_equals($expected, $signature)) {
    http_response_code(403);
    echo 'Invalid signature';
    exit;
}

// Signature is valid — process the webhook
echo 'OK';
```

**Important:** Always use `hash_equals()` (or equivalent constant-time comparison) to compare signatures. Do not use `===` as it is vulnerable to timing attacks.

---

## Replay protection

The timestamp in the signature header serves as replay protection:

1. **5-minute window:** Signatures older than 5 minutes are rejected by default.
2. **Unique payload per event:** Each webhook call carries a unique event ID (`event_id` in the payload body).
3. **Idempotency:** For critical operations, store processed `event_id` values and reject duplicates.

---

## Secret rotation

1. **Generate a strong secret:** Use a random 32+ character string:
   ```bash
   openssl rand -hex 32
   ```
2. **Configure in the admin panel:** Go to Admin → Webhooks → Edit → Secret.
3. **Rotate periodically:** Change the secret every 90 days or immediately if you suspect a compromise.
4. **Update receivers:** Any service consuming your webhooks needs the new secret.
5. **Zero-downtime rotation:** Keep both old and new secrets valid for 24 hours by accepting either signature during rotation.

---

## Webhook payload format

Every webhook request has a consistent JSON envelope:

```json
{
    "event_id": "evt_abc123",
    "event_type": "task_status_changed",
    "timestamp": "2026-01-15T10:30:00Z",
    "workspace_id": "ws_xyz789",
    "data": {
        "task_public_id": "PRJ-042",
        "task_title": "Implement payment integration",
        "old_status": "in_progress",
        "new_status": "done",
        "changed_by_user_id": "usr_123",
        "changed_by_name": "Alice"
    }
}
```

The exact fields in `data` depend on the event type.

---

## Webhook event types

| Event type | Trigger | Common use |
|---|---|---|
| `task_created` | New task created | Sync to external project tracker |
| `task_updated` | Task fields changed | Update external dashboards |
| `task_status_changed` | Status transition | Notify Slack when task is done |
| `task_deleted` | Task removed | Clean up external references |
| `project_created` | New project | Create project in accounting system |
| `project_updated` | Project settings changed | Sync project metadata |
| `client_created` | New client record | Update external CRM |
| `client_updated` | Client info changed | Sync contact details |
| `chat_message_sent` | Message posted | Archive to external system |
| `workflow_triggered` | Automation rule fired | Log automation activity |
| `sla_breached` | SLA deadline missed | Alert support team |
| `file_uploaded` | File attached to entity | Trigger virus scan or backup |

---

## Logging and monitoring

1. **Delivery logs** — The webhook delivery log is available in the admin panel (Admin → Logs). It records:
   - Event type and target URL
   - HTTP status code returned
   - Response body (truncated)
   - Timestamp and duration
   - Error details if delivery failed

2. **Retry policy** — TropaTT retries failed deliveries up to 3 times with exponential backoff (1 min, 5 min, 15 min).

3. **Alerting** — Monitor webhook delivery failures in the admin panel. Repeated failures may indicate a misconfigured receiver.

---

## Shared hosting considerations

| Concern | Recommendation |
|---|---|
| **Outbound HTTP** | Most shared hosting allows outbound HTTP calls. If webhooks fail, check if your host blocks external connections. |
| **Execution time** | Webhook delivery is queued and runs in background jobs (`web/cron.php`). Ensure cron is configured (see [SHARED_HOSTING_GUIDE.md](SHARED_HOSTING_GUIDE.md)). |
| **IP whitelisting** | If your receiver requires an IP allowlist, note that shared hosting IPs may vary. Use signature verification instead. |
| **Payload size** | Webhook payloads are typically under 10 KB. If you expect larger payloads, ensure your server's `post_max_size` is adequate. |
| **Rate limits** | TropaTT respects the receiving server's response. If the receiver returns 429 (Too Many Requests), TropaTT will retry after the `Retry-After` header value. |

---

## Best practices

1. **Always verify signatures** — Never process an unauthenticated webhook request. TropaTT signs every payload.

2. **Use HTTPS endpoints** — Send webhooks to HTTPS URLs to protect payloads in transit. TropaTT will refuse non-HTTPS URLs in production mode.

3. **Respond quickly** — The webhook sender expects a 2xx response within 10 seconds. Process the event asynchronously if it takes longer.

4. **Make receivers idempotent** — A webhook may be delivered more than once. Use the `event_id` to detect and ignore duplicates.

5. **Limit retries** — If a receiver is down repeatedly, disable the webhook to avoid queue buildup.

6. **Audit your webhooks** — Regularly review the webhook list in the admin panel. Remove unused or test webhooks.

7. **Signature timestamp check** — Always validate the timestamp to prevent replay attacks. Use a 5-minute window by default.

---

## Example: Verify webhook in Python

```python
import hashlib
import hmac
import time

def verify_webhook(payload: bytes, signature_header: str, secret: str, max_age: int = 300) -> bool:
    parts = {}
    for item in signature_header.split(','):
        key, value = item.split('=', 1)
        parts[key] = value

    timestamp = parts.get('t')
    signature = parts.get('s')

    if not timestamp or not signature:
        return False

    # Check timestamp freshness
    if abs(time.time() - int(timestamp)) > max_age:
        return False

    # Compute expected signature
    expected = hmac.new(
        secret.encode(),
        payload + b'.' + timestamp.encode(),
        hashlib.sha256
    ).hexdigest()

    return hmac.compare_digest(expected, signature)
```

## Example: Verify webhook in JavaScript (Node.js)

```javascript
const crypto = require('crypto');

function verifyWebhook(payload, signatureHeader, secret, maxAge = 300) {
    const parts = {};
    signatureHeader.split(',').forEach(pair => {
        const [key, value] = pair.split('=', 2);
        parts[key] = value;
    });

    const { t: timestamp, s: signature } = parts;
    if (!timestamp || !signature) return false;

    // Check timestamp freshness
    if (Math.abs(Date.now() / 1000 - parseInt(timestamp)) > maxAge) return false;

    // Compute expected signature
    const expected = crypto
        .createHmac('sha256', secret)
        .update(payload + '.' + timestamp)
        .digest('hex');

    return crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(signature));
}
```

---

## Troubleshooting webhooks

| Symptom | Likely cause | Fix |
|---|---|---|
| Delivery status shows 0 | Cron not running | [Set up cron](SHARED_HOSTING_GUIDE.md#optional-configuration) |
| Receiver returns 403 | Wrong secret | Regenerate secret and update both sides |
| Receiver returns 404 | Wrong URL | Check the webhook endpoint URL |
| All deliveries show error | Outbound blocked | Ask host to allow outbound HTTPS on port 443 |
| Signature verification fails | Clock skew | Ensure both servers have correct time (NTP) |
| Duplicate processing | Receiver not idempotent | Use `event_id` to deduplicate |
