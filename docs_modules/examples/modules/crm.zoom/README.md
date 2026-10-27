# Zoom Meetings for TropaTT CRM

This module connects one Zoom account to each CRM workspace using the customer's own Zoom **Server-to-Server OAuth** app. It supports verifying the configured host, creating a scheduled meeting, and creating the matching workspace-owned event in the CRM calendar.

## Setup

1. In Zoom App Marketplace create an internal **Server-to-Server OAuth** app for the account that will host meetings.
2. Add only these granular scopes:
   - `meeting:write:meeting:admin` — create meetings for the configured account user.
   - `user:read:user:admin` — verify the configured host user.
3. Activate the Zoom app in the Zoom account.
4. In CRM, open **Administration → Modules → Zoom Meetings** and enter Account ID, Client ID, Client Secret, and the Zoom user ID or email that will host meetings. Keep a 32+ character `APP_KEY` configured in the CRM environment. The module encrypts the client secret with AES-256-GCM and uses the workspace ID as authenticated encryption context. An old plaintext client secret must be re-entered; the previous release's meeting host URLs and passcodes are automatically encrypted and cleared from plaintext columns when the module is opened.
5. Save, then choose **Check connection**. A connection is marked verified only after Zoom issues a token and the Users API confirms the host identity. Tokens are requested when needed and are not persisted.

Zoom's official S2S flow uses `grant_type=account_credentials` and the account ID at `https://zoom.us/oauth/token`. Meeting creation uses `POST /users/{userId}/meetings`; the module always uses the saved host identity and fixed Zoom endpoints. See [Zoom S2S OAuth docs](https://developers.zoom.us/docs/internal-apps/s2s-oauth/), [OAuth scopes](https://developers.zoom.us/docs/integrations/oauth-scopes-overview/), [Users API](https://developers.zoom.us/docs/api/users/), and [Meetings API](https://developers.zoom.us/docs/api/meetings/).

## Security and retry behavior

- Settings and meetings are scoped to the active workspace. Module API routes enforce session authentication, workspace context and `settings.*` / `calendar.*` permissions; browser writes use the CRM API client and its CSRF/session protections.
- Secrets and host-only `start_url`/passcode values are encrypted with AES-256-GCM. Legacy host URLs/passcodes are moved into encrypted fields and removed from plaintext columns. They are not returned in API lists, page markup, or logs. The attendee URL is checked against Zoom's HTTPS join URL shape and query parameters are removed from the visible/stored link.
- All network destinations are fixed Zoom HTTPS hosts, TLS verification is on, redirects/proxies are disabled, DNS is validated and pinned, and response size/time are bounded. Neither module settings nor meeting inputs can choose a URL or path outside the fixed API routes.
- Meeting input is validated server-side. The module generates waiting-room enabled meetings and does not accept a caller-selected Zoom user, password or arbitrary provider settings.
- Every creation requires an idempotency key. The key and normalized request hash are unique per workspace. Repeating a completed key returns the same CRM meeting. Reusing a key with different input is rejected. If the provider response is uncertain, the reservation stays pending and the module will not blindly create another meeting; check Zoom before manually resolving the result.

## Supported scope and limits

Supported: Zoom account verification, scheduled meetings, workspace meeting list, CRM calendar event creation. The module does **not** currently synchronize recordings, transcripts, attendance, Zoom webhooks, or meetings created outside the CRM. Zoom meeting quotas, account licenses, app approval, and Zoom's own retention and access rules still apply. Server-to-server credentials represent the configured Zoom account; workspace admins should only configure an account they are authorized to connect.

## Tests

From the repository root:

```sh
php docs_modules/examples/modules/crm.zoom/tests/ZoomIntegrationTest.php
php -l docs_modules/examples/modules/crm.zoom/api/Service/ZoomService.php
```

The HTTP transport is mocked in the test; it never calls Zoom. The production transport remains the fixed-host, TLS-verified implementation.
