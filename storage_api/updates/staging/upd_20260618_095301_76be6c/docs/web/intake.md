# Intake / Входящие заявки — Web Interface

## URL
`/web/index.php?route=intake`

## Permissions
- `intake.view` — view the intake page and list items
- `intake.create` — create new intake items
- `intake.manage` — edit, reject, snooze, mark duplicate, reopen
- `intake.accept` — convert intake item to a task
- `intake.delete` — soft delete intake items

## Features

### List Page
- Table with columns: title, status, project, source, priority, assignee, due date, created date, actions
- Filter by status, source, project, priority
- Search by title/description/ref
- Pagination
- Empty state when no items exist

### Create Modal
- Title (required)
- Description
- Project, client, priority, source, assignee selects
- Due date

### Detail Modal
- Full item information
- Editable fields (title, description, project, client, priority, source, assignee, due, contact)
- Action buttons (accept, reject, snooze, duplicate, reopen, delete)
- Activity feed

### Status Colors
| Status | Label | Badge Class |
|--------|-------|-------------|
| pending | Новая | info |
| accepted | Принята | success |
| rejected | Отклонена | secondary |
| snoozed | Отложена | warning |
| duplicate | Дубликат | secondary |

### Actions by Status
- **Pending/Snoozed**: Accept, Snooze, Reject, Duplicate
- **Rejected/Snoozed/Duplicate**: Reopen
- **All statuses**: Open, Delete

## JavaScript Module
`web/assets/js/intake.js` — auto-initialized on pages with `data-page="intake"`

### Available Functions (via `window.CRM.intake`)
- `loadItems()` — reload list
- `openCreateModal()` — open create modal
- `openEditModal(publicId)` — open detail/edit modal
- `saveItem()` — save new item
- `acceptItem(publicId)` — accept to task
- `rejectItem(publicId)` — reject with reason
- `snoozeItem(publicId)` — snooze with date
- `markDuplicate(publicId)` — mark as duplicate
- `reopenItem(publicId)` — reopen
- `deleteItem(publicId)` — soft delete
