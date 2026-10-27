# Public Forms for TropaTT CRM

This module lets workspace staff create and edit public intake forms, publish or unpublish them, copy the public URL, and review submitted answers. New forms are drafts. Intake forms can optionally route submissions to an active project and assignee in the same workspace. Each successful public submission creates one workspace-scoped core Intake item; the response stores the linked `intake_public_id`, consent text/version snapshot, and validated answers. Intake processing uses a durable claim and stable external reference: retries recover an interrupted attempt and do not create a second Intake item. If Intake is temporarily unavailable, the response remains saved and the same idempotency key can retry safely. The module also supports appointment booking with workspace-member validation and calendar conflict checks.

## Setup and permissions

1. Install the module and apply its migrations.
2. Open **Публичные формы** in a workspace. `settings.view` allows viewing forms; `settings.edit` allows creating, editing, and publishing; `client.manage` is required to view answers because they may contain customer personal data.
3. Create a form with at least one field, review the consent text, then publish it. The public link is available only while the form is published.
4. Use the public link to submit a test request. The form must be opened in a browser as a visitor; the public API does not expose workspace IDs or unpublished forms.

Project and assignee routing is optional and applies to intake forms. The editor lists only unarchived projects and active workspace members. The API revalidates both choices on save and again when processing a submission, so a guessed foreign-workspace ID or an option removed after form publication fails closed. Booking forms use a workspace member as the calendar owner and cannot also set intake routing.

## Security notes

- Forms, configuration, and submissions are scoped to the active workspace on every staff endpoint.
- The public slug is unique across the CRM installation so a public URL cannot ambiguously resolve to another workspace's form.
- Public submissions are limited before JSON decoding on the shared-hosting request path, then validated for consent, payload size, schema fields, and per-form/IP submission rate.
- Dynamic labels, data, statuses, and links are inserted using DOM text properties; external values are not interpolated into HTML.
- Unpublishing immediately makes the public read endpoint return not found.

## Focused tests

From the repository root, run:

```sh
php -d zend.assertions=1 upload/api/tests/unit/public_forms_admin_service_unit.php
```

The service test uses an in-memory SQLite database and does not send public submissions over the network.
