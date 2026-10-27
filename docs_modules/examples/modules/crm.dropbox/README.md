# Dropbox storage for TropaTT CRM

This module connects one Dropbox account to the active CRM workspace. It supports secure OAuth refresh-token configuration, connection verification, and manual upload of an authorized CRM file into a private Dropbox folder. It does not create public/shared links or delete remote files.

## Requirements

- PHP 8.1+, cURL and OpenSSL enabled.
- A persistent `APP_KEY` of at least 32 characters. Credentials are encrypted with AES-256-GCM and authenticated module-specific associated data. There is no plaintext/base64 fallback.
- A Dropbox app configured with the OAuth scopes `account_info.read`, `files.content.write`, and `files.metadata.write`, plus its App Key, App Secret, and OAuth refresh token.
- Outbound HTTPS access from the shared host to `api.dropboxapi.com` and `content.dropboxapi.com`.

## Setup

1. Open **Modules → Dropbox Storage** in the desired workspace.
2. Enter App Key, App Secret, refresh token, and a root path (defaults to `/TropaTT_CRM`). Secrets are write-only; they are never read back into the page or status response.
3. Save, then choose **Check connection**. A successful test calls Dropbox `get_current_account`; the connected account email is shown.
4. To transfer a CRM file, enter its `file_public_id` and choose **Transfer file**. The server re-checks the current actor's file access and workspace, rejects quarantined, unreadable, symlinked, changed-size, and over-20-MB files, then uploads it to a private provider path. The uploaded mapping is stored for idempotent replacement of that CRM file.

## Security and limitations

- Every API route is authenticated, requires an active workspace, and checks the corresponding settings/files permissions.
- Fixed Dropbox HTTPS endpoints are used; redirects are disabled and TLS peer/host verification is mandatory.
- Credentials and token responses are not returned to the browser or logged. Database rows are workspace-scoped.
- No remote shared link is generated. Remote deletion, automatic background sync and OAuth browser callback are not supported; the refresh token is entered from the provider's OAuth setup flow.
- Files are read only after core `FileService` object-level authorization. Quarantined content is never copied to Dropbox.
- If the account app, scopes, or provider token are invalid, the page reports a generic connection failure without exposing provider response bodies.
