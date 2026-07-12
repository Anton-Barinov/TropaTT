# Intake Items API

## GET /api/v1/intake-items

List intake items with filtering and pagination.

### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | int | Page number (default: 1) |
| `limit` | int | Items per page (max: 100, default: 20) |
| `status` | string | Filter by status: pending, accepted, rejected, snoozed, duplicate |
| `project_public_id` | string | Filter by project |
| `client_public_id` | string | Filter by client |
| `contact_public_id` | string | Filter by contact |
| `assignee_user_id` | int | Filter by assignee |
| `creator_user_id` | int | Filter by creator |
| `source_type` | string | Filter by source: manual, client, api, webhook, email, ai, import, system |
| `priority_code` | string | Filter by priority |
| `q` | string | Search in title, description, source_ref, source_email, external_id |
| `created_from` | datetime | Filter by created date (>=) |
| `created_to` | datetime | Filter by created date (<=) |
| `due_from` | datetime | Filter by due date (>=) |
| `due_to` | datetime | Filter by due date (<=) |
| `snoozed_mode` | string | all, active (pending + expired snoozed), future (future snoozed) |
| `sort` | string | Sort column: created_at, updated_at, due_at, snoozed_until, priority_code, status, title |
| `order` | string | Sort direction: asc, desc |

### Response

```json
{
  "success": true,
  "code": "OK",
  "data": {
    "items": [
      {
        "public_id": "iin_...",
        "title": "Client request",
        "status": "pending",
        "priority_code": "normal",
        "source_type": "manual",
        "project_public_id": "prj_...",
        "project_title": "Website",
        "assignee_user_id": 12,
        "assignee_name": "Manager",
        "created_at": "2026-06-16 10:00:00"
      }
    ]
  },
  "meta": {
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 1,
      "pages": 1
    }
  }
}
```

## POST /api/v1/intake-items

Create a new intake item.

### Required Permissions
`intake.create`

### Request Body

```json
{
  "title": "Client request (required)",
  "description": "Details",
  "project_public_id": "prj_...",
  "client_public_id": "cli_...",
  "contact_public_id": "cnt_...",
  "priority_code": "normal",
  "source_type": "manual",
  "source_ref": "call 2026-06-16",
  "source_email": null,
  "external_source": null,
  "external_id": null,
  "extra": {"channel": "phone"},
  "due_at": "2026-06-30 18:00:00",
  "assignee_user_id": 12
}
```

## GET /api/v1/intake-items/{public_id}

Get a single intake item with all related data.

### Required Permissions
`intake.view`

## PATCH /api/v1/intake-items/{public_id}

Update an intake item. Fields not included will not be changed.

### Required Permissions
`intake.manage`

### Editable fields
title, description, project_public_id, client_public_id, contact_public_id, priority_code, source_type, source_ref, source_email, external_source, external_id, extra, due_at, assignee_user_id, row_version

## DELETE /api/v1/intake-items/{public_id}

Soft delete an intake item.

### Required Permissions
`intake.delete`

## POST /api/v1/intake-items/{public_id}/accept

Convert intake item to a task.

### Required Permissions
`intake.accept`

## POST /api/v1/intake-items/{public_id}/reject

Reject an intake item.

### Required Permissions
`intake.manage`

### Request Body
```json
{
  "reason": "Client cancelled (required)",
  "row_version": 1
}
```

## POST /api/v1/intake-items/{public_id}/snooze

Snooze an intake item until a specific date.

### Required Permissions
`intake.manage`

### Request Body
```json
{
  "snoozed_until": "2026-06-25 09:00:00",
  "reason": "Waiting for info",
  "row_version": 1
}
```

## POST /api/v1/intake-items/{public_id}/duplicate

Mark an intake item as duplicate of another item or task.

### Required Permissions
`intake.manage`

### Request Body (duplicate of intake item)
```json
{
  "duplicate_intake_item_public_id": "iin_...",
  "reason": "Same request",
  "row_version": 1
}
```

### Request Body (duplicate of task)
```json
{
  "duplicate_task_public_id": "tsk_...",
  "reason": "Already tracked",
  "row_version": 1
}
```

## POST /api/v1/intake-items/{public_id}/reopen

Reopen a rejected, snoozed, or duplicate intake item back to pending.

### Required Permissions
`intake.manage`

## GET /api/v1/intake-items/{public_id}/activities

Get activity feed for an intake item.

### Required Permissions
`intake.view`

## Status Transitions

| From | To | Allowed |
|------|-----|---------|
| pending | accepted, rejected, snoozed, duplicate | Yes |
| snoozed | pending, accepted, rejected, duplicate | Yes |
| rejected | pending | Yes |
| duplicate | pending | Yes |
| accepted | any | No |

## Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| INTAKE_NOT_FOUND | 404 | Item not found |
| INTAKE_FORBIDDEN | 403 | No access |
| INTAKE_INVALID_STATUS_TRANSITION | 422 | Status transition not allowed |
| INTAKE_TITLE_REQUIRED | 422 | Title is required |
| INTAKE_REASON_REQUIRED | 422 | Reason is required for reject |
| INTAKE_SNOOZED_UNTIL_REQUIRED | 422 | Snooze date is required |
| INTAKE_DUPLICATE_TARGET_REQUIRED | 422 | Duplicate target is required |
| INTAKE_DUPLICATE_TARGET_NOT_FOUND | 404 | Duplicate target not found |
| INTAKE_ALREADY_ACCEPTED | 422 | Item is already accepted |
| ROW_VERSION_CONFLICT | 409 | Optimistic lock conflict |
