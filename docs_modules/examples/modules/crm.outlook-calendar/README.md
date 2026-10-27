# Microsoft Outlook Calendar Integration for TropaTT CRM

This module imports the current CRM user's **primary Microsoft 365 calendar** into the active CRM workspace. It is deliberately one-way (Outlook → CRM); it does not create or modify Outlook events. Event updates from Outlook replace the mapped CRM copy, while Outlook deletions remove only the corresponding imported CRM event. Disconnecting retains already imported CRM events.

## Requirements

- TropaTT CRM with the module migration applied.
- PHP 8.1+, PDO, OpenSSL, and a valid CRM `APP_KEY` containing 32 random bytes encoded as 64 hexadecimal characters.
- Normal outbound HTTPS from the shared host to `login.microsoftonline.com` and `graph.microsoft.com` on port 443. No daemon, shell access, server configuration, or inbound webhook is required.
- An administrator in the customer tenant who can register/consent to an Entra application.

The module fails closed if `APP_KEY` or OpenSSL is unavailable. App secrets, OAuth state verifiers, access tokens and refresh tokens are encrypted with AES-256-GCM. A key rotation makes saved credentials unreadable; reconnect the Microsoft account and replace the app secret after rotating the key.

## Microsoft Entra application setup

1. In the CRM, open **Outlook Календарь** in the target workspace. Copy its displayed Redirect URI.
2. Create an Entra app registration configured for accounts appropriate to the customer. Add that exact URI as a **Web** redirect URI.
3. Create a client secret and grant delegated Microsoft Graph permissions `User.Read` and `Calendars.Read`. The module requests `openid profile offline_access User.Read Calendars.Read`; tenant policy may require administrator consent.
4. In the CRM module settings, enter the tenant selector (`organizations`, `common`, `consumers`, or a tenant UUID), Application (client) ID, client secret, and the exact HTTPS Redirect URI. The client secret is write-only in CRM and is never returned by the status API.
5. Choose **Connect Microsoft 365**, sign in, and approve consent. The authorization-code flow binds a short-lived, single-use state to the current user and workspace and uses PKCE S256. The callback requires the same authenticated CRM user and active workspace.
6. Run **Sync now**. The current user's primary calendar is imported for a fixed window beginning 90 days before the initial sync and ending 365 days after it. The window remains fixed for the lifetime of its Graph delta cursor; reconnecting starts a fresh window.

## Sync and conflict behavior

- Uses Microsoft Graph `calendarView/delta` with a fixed start/end range and resumes opaque `@odata.nextLink` / `@odata.deltaLink` URLs only after validating the HTTPS Graph host and allowed calendar endpoint.
- Processes at most three pages per web/MCP request. Each successful page and its cursor are committed together. Continue sync until the UI reports completion; no background worker is required.
- CRM stores a unique mapping by workspace, connection, and Graph event ID. Repeated delta delivery updates the same CRM event instead of duplicating it.
- Outlook is authoritative for imported event title, plain-text description, start/end time, and cancellation. Event ownership is stored as the connecting CRM user. A Graph tombstone deletes only a CRM event whose workspace, owner, source type, and external ID match the mapping.
- If Graph expires a cursor (HTTP 410), the module clears the cursor and fixed range. Run sync again to rebuild the snapshot. Existing mapped events are updated idempotently.
- A short workspace/user connection lock prevents overlapping sync requests. The module has bounded HTTP timeouts, response size limits, TLS verification, and no redirects.

## MCP tools

- `outlook_calendar_status`: returns safe connection status for the current actor and active workspace.
- `outlook_calendar_sync`: imports the current actor's calendar into the active workspace. Only `direction: pull` is accepted.

The MCP registry applies the tool's `calendar.view` / `calendar.edit` permission and workspace context. The handlers refuse to run without an authenticated actor and explicit workspace context.

## Validation

Run the private mock-HTTP unit test from the repository root:

```sh
php upload/api/tests/unit/outlook_calendar_module_unit.php
```

It covers encrypted persistence, PKCE/state binding and replay rejection, workspace/user scoping, successful import, HTML-to-text mapping, Graph tombstones, expired continuation cursor recovery, Graph host validation, and missing-key fail-closed behavior. It does not use live Microsoft credentials. A real tenant authorization/consent round trip must be verified in a configured customer test workspace before marketplace release.

## References

- [Microsoft identity platform authorization-code flow](https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow)
- [Microsoft Graph event delta query](https://learn.microsoft.com/en-us/graph/delta-query-events)
- [Microsoft Graph event delta permissions](https://learn.microsoft.com/en-us/graph/api/event-delta?view=graph-rest-1.0)
