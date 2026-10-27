# WhatsApp Business Platform module

This module provides a workspace-scoped WhatsApp Cloud API setup, signed Meta webhook ingress, an inbox with text conversation history, Intake linkage, and outbound text replies.

## Requirements

- TropaTT CRM with the module system enabled and PHP 8.1+.
- OpenSSL and an application `APP_KEY` consisting of 64 hexadecimal characters (32 bytes). Credentials fail closed if encryption is unavailable.
- Outbound HTTPS from the shared host to the fixed Meta Graph API host `graph.facebook.com`; cURL with TLS verification enabled is required. Redirects are disabled.
- A Meta WhatsApp Business Platform app, WABA, phone number, access token, App Secret, and a random Verify Token configured by the workspace administrator.

## Setup

1. Install and activate the module from CRM administration.
2. Open **WhatsApp Business** while the intended workspace is active.
3. Enter the Meta Phone Number ID, WABA ID, access token, Verify Token and App Secret. The three secrets are encrypted with an APP_KEY-derived AES-256-GCM key and are never returned to the browser. Save them; the UI clears secret inputs afterward. When upgrading from earlier preview builds that stored Verify Token in plaintext, re-enter all three credentials once so they are migrated to encrypted storage.
4. Copy the workspace-specific Webhook URL shown in the page. In Meta's app dashboard configure this URL, enter exactly the same Verify Token, and subscribe to `messages`.
5. Use the Meta webhook test to confirm the GET verification. The endpoint returns the challenge as exact `text/plain` only when `hub.mode=subscribe` and the token matches. Invalid requests receive a generic 403.
6. The POST endpoint requires `X-Hub-Signature-256` to match an HMAC-SHA256 over the exact raw request body using the configured App Secret. Each Meta WABA and phone number ID is checked against that workspace configuration. Requests are limited to 256 KiB before JSON decoding; malformed/oversized bodies fail closed.

## Inbox and replies

- Inbound text messages (and safe placeholders for supported non-text message types) are grouped by sender and deduplicated by Meta message ID within the workspace.
- An Intake item is created once per conversation through the core `IntakeItemService`; it records the inbound history and uses `external_source=whatsapp`. If Intake linking fails after message persistence, the endpoint returns a retryable 503; a Meta retry deduplicates messages and repairs the link.
- Inbox, conversation and message reads are scoped to the active workspace. HTML rendering uses DOM `textContent`.
- Replies are text-only, capped at 4096 characters, allowed only within 24 hours of the latest inbound message, and sent to the fixed HTTPS Graph API host with TLS peer and host verification. The CRM user must have `settings.edit`; reads require `settings.view`; browser mutations use the CRM CSRF/session policy.
- Reply endpoints require `Idempotency-Key`. Keys and payload hashes are stored, not the raw key. A repeated confirmed send returns the prior result. A timeout or ambiguous provider response is not automatically resent; check the conversation before creating a new user-initiated reply.

## Privacy and operational limits

- Tokens, Verify Token and App Secret are never logged, included in status responses, or sent to the browser after save.
- The module stores message text in the CRM database and has no automated retention or media download. Media content is intentionally not fetched. Apply the customer's CRM retention policy to stored conversations.
- Delivery status updates, templates, attachments, assignment workflow, read receipts, and bulk sends are outside this vertical slice.
- Real provider round-trip tests require workspace-owned Meta credentials and a reachable public webhook URL; automated tests use a mock transport and must never send a real message.

## Verification

Run the module test:

```sh
php docs_modules/examples/modules/crm.whatsapp-business/tests/WhatsAppBusinessServiceTest.php
```

Also lint the modified module/controller/core files and run the repository module fast tests before release.
