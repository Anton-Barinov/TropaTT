# HubSpot Contacts JSON Import for TropaTT CRM

## Implemented scope

This module provides a bounded, manual migration workflow. It does **not** connect to the HubSpot API, request OAuth credentials, or claim a live synchronization. The user pastes a JSON array representing HubSpot contact records. The importer creates TropaTT CRM client records of type `individual` in the currently selected workspace.

Example input:

```json
[
  {
    "id": "123456",
    "properties": {
      "firstname": "Ada",
      "lastname": "Lovelace",
      "email": "ada@example.com",
      "phone": "+1 555 0100",
      "notes": "Imported contact"
    }
  }
]
```

The module supports only contacts and the fields `firstname`, `lastname`, `email`, `phone` (or `mobilephone`) and `notes`. Field mapping allows selecting these property names. A name or valid email is required. The module currently does not parse CSV files, import companies/deals/activities/associations, or infer links between records. Convert the desired contact subset to the documented JSON shape before pasting.

## Limits and safety

- Up to 100 contacts and 512 KiB of record data per staging request; import executes at most 25 contacts per request.
- Each contact object is limited to 16 KiB, with scalar HubSpot properties only.
- Every session, staging row, and idempotency mapping is scoped to the active workspace. CRM records are created through the existing `ClientService`, which applies workspace ownership and business rules.
- HubSpot IDs are unique per workspace. Retrying or importing the same ID in another session does not create a duplicate.
- Staging stores only fields needed for the import. On success, normalized PII payloads are cleared. Failed rows retain their normalized data in the workspace-scoped staging table so they can be retried in bounded batches; the explicit discard action erases failed rows. The import report keeps the HubSpot ID, contact label, state, and an opaque fingerprint needed for safe rollback.
- Rollback deletes only records created by that session whose CRM fields still match the import fingerprint. If a record has since been edited or is inaccessible, it is retained and reported. No pre-existing CRM contact is deleted.
- Module uninstall removes its migration tables and ID mappings, not imported CRM contacts. Reinstalling the module removes the module-side duplicate guard; do not reimport a previously imported HubSpot dataset after reinstall without first reconciling those records.

## Workflow

1. Create a session in the active workspace.
2. Paste the JSON array and review field mappings.
3. Prepare the preview. This validates data and stages the batch but does not write CRM contacts.
4. Review the report and import in batches of up to 25. Repeat until all records are processed.
5. Review counts and per-record states. Use “Повторить ошибки” to retry up to 25 failed records in the same session; repeat for remaining failures. Use rollback if required; edited records are preserved. Use “Очистить неперенесённые данные” to erase staged records and failed rows while keeping already imported CRM contacts.

## API

All routes require an authenticated internal user, valid active workspace context, CSRF protection on writes, and the listed permissions.

- `GET /api/v1/_module/crm.hubspot-migration/status` — workspace-scoped session summary.
- `POST /api/v1/_module/crm.hubspot-migration/sessions` — create a session.
- `POST /api/v1/_module/crm.hubspot-migration/sessions/contacts` — validate and stage contacts; body contains `session_id`, `records`, and optional `mapping`.
- `GET /api/v1/_module/crm.hubspot-migration/sessions/{session_id}` — preview/report.
- `POST /api/v1/_module/crm.hubspot-migration/sessions/{session_id}/import` — import one batch; JSON body may set `limit` 1–25.
- `POST /api/v1/_module/crm.hubspot-migration/sessions/{session_id}/retry-errors` — reprocess up to 25 failed records whose normalized staging data is retained.
- `POST /api/v1/_module/crm.hubspot-migration/sessions/{session_id}/rollback` — safely remove unchanged records created by the session.
- `POST /api/v1/_module/crm.hubspot-migration/sessions/{session_id}/discard-pending` — erase staged, failed, and duplicate-preview rows while preserving imported CRM contacts.

## MCP

- `hubspot_migration_status` returns a session summary in the authorized workspace.
- `hubspot_migration_run` can import one batch or run safe rollback for an existing session. It accepts only `import_batch` or `rollback`, a valid session ID, and a limit up to 25. MCP authorization must grant `client.manage`; the runtime actor and workspace are resolved by the CRM MCP registry.

## Installation/update

Migration `001_hubspot_migration_tables.sql` creates the session and ID-map tables. `002_hubspot_contacts_workflow.sql` creates the workflow record table with workspace isolation, report fields, and a per-session unique key; the migration leaves the legacy staging table and its rows unchanged, and creates a separate workspace-scoped workflow table. The new workflow does not read legacy staging records; they remain available for manual reconciliation or cleanup. Keep the paired `_rollback.sql` files with their migration files. The module remains compatible with PHP 8.1+ and the CRM's MySQL/MariaDB migration runner; it requires no daemon, queue, CLI binary, or server-level service.
