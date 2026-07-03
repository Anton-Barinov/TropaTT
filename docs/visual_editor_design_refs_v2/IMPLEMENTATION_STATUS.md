# IMPLEMENTATION_STATUS.md — Visual Editor (Vanilla JS WYSIWYG)

**Last updated:** 2026-07-03 23:28 MSK  
**Current phase:** Shared file-upload shell verified on demo; awaiting final status sync and cleanup  
**Branch:** main  
**Commit hash:** c624e5a (modal/file polish), ce5e74d (new task comment layout), 07ecff0 (knowledge/project-module integration), e4ac0ca (task edit UX/localization), edaa8cb (knowledge comment HTML), 6aa1809 (task comment VE sync), e2a8349 (stable image toolbar controls), 1ad168a (empty editor click fix), 3f9d12d (legacy rich text replacement), 536c3ae (modal reopen refresh), fc9e52f (upload endpoint hotfix), c443bba (image persistence/rendering)

## Summary

Implementing a block-based visual editor on pure JavaScript without third-party WYSIWYG libraries. The editor wraps existing textareas as progressive enhancement, syncs to textarea on changes, and supports images with resize/align, text formatting, undo/redo, paste, drag & drop, and secure HTML sanitization.

2026-07-03 10:15 MSK update: user reported that the current deployed implementation does not match the v2 visual references and is functionally incomplete. Treat the previous "implementation complete" status as stale. Continue from the existing implementation, but re-audit against `visual_editor_design_references_v2.md`, `design_references_v2.html`, and PNG states before finalizing. Current uncommitted files at restart: `web/assets/js/visual-editor.js` and `web/DEPLOY_HASH`.

## Architecture

- **JS file:** `web/assets/js/visual-editor.js` — single self-contained IIFE
- **CSS file:** `web/assets/css/visual-editor.css` — all styles with `crm-ve-` prefix
- **API endpoint:** `POST /api/v1/visual-editor/upload-image` — handled by `Api\Controller\VisualEditor\UploadController`
- **Initialization:** via `data-crm-visual-editor="1"` attribute on textarea; also `window.CRM.VisualEditor.Editor` constructor
- **Format:** syncs as sanitized HTML to existing textarea (backward compatible with plain text)
- **Sanitizer:** both client-side (paste/DnD) and server-side (output rendering)

## What has been done

- [x] Read TZ document, design references, screenshots
- [x] Audited project structure — assets, routes, controllers, services
- [x] Identified all textarea fields across the project
- [x] Designed architecture (progressive enhancement over textarea, no replacement)
- [x] Created IMPLEMENTATION_STATUS.md
- [x] Created visual-editor.css — full editor styles
- [x] Created visual-editor.js — full editor implementation
- [x] Created upload controller `Api\Controller\VisualEditor\UploadController`
- [x] Added API route for image upload
- [x] Modified header.php to load visual-editor.css
- [x] Modified footer.php to load visual-editor.js (conditionally)
- [x] Modified modals.js to add `data-crm-visual-editor="1"` and `data-richtext-off="1"` to dynamically created textareas
- [x] Modified task_detail.php — added editor to description inline form, comment form, subtask modals
- [x] Modified knowledge_page.php — added editor to comment textarea
- [x] Modified projects.php, dashboard.php — added editor to project description

2026-07-03 10:27 MSK continuation updates:
- [x] Fixed image resize/save/view/edit round-trip: managed image blocks now save as clean `<figure data-align data-width style="width:N%">...`.
- [x] Fixed reopening saved figures: saved `<figure>` is converted back into `.crm-ve-image-block` with preserved align and width percent.
- [x] Added readonly renderer `window.CRM.VisualEditor.renderReadonly(root)` so saved figures render with preserved left/center/right alignment and width outside the editor.
- [x] Exported `window.CRM.VisualEditor.sanitizeHtml` for existing page renderers.
- [x] Updated task detail renderer to safely allow `figure/img/figcaption` and hydrate task descriptions/comments.
- [x] Updated idea detail and knowledge comment renderers so visual-editor HTML is not escaped back to plain text.
- [x] Fixed CSS application mismatch by giving wrapper both `crm-ve-editor` and `crm-ve-wrapper`.
- [x] Fixed image toolbar visibility and button spacing.
- [x] Fixed resize handle class mismatch so handles are positioned correctly.
- [x] Fixed default upload URL to `/api/v1/visual-editor/upload-image` and added textarea data-attribute config reads.
- [x] Added empty-state quick action chips and placeholder state closer to v2 reference.
- [x] Disabled fallback injected CSS when external `visual-editor.css` is loaded.
- [x] Fixed resize badge order to `px · %` and added active resize handle state.

2026-07-03 15:47 MSK continuation updates:
- [x] Replaced the knowledge page content editor UI with the shared vanilla visual editor; the old `#knowledgeVisualEditor` is no longer present on demo.
- [x] Updated `richtext.js` so old richtext enhancement hands eligible textareas to the new visual editor instead of creating `.crm-rte`.
- [x] Fixed task comment edit flow: edit textarea now opens as a visual editor with preserved image blocks, not plain image filename/caption text.
- [x] Fixed task comment save flow to read the current value from `CRM.VisualEditor.getInstances()` before create/update/draft requests.
- [x] Added stable `data-align`, `data-width`, and `data-action` attributes to image toolbar buttons.
- [x] Fixed empty editor quick chips so they no longer intercept clicks into the editable content area.
- [x] Fixed knowledge comments backend storage: comments now use server-side HTML sanitizer instead of `strip_tags`, preserving safe `<figure><img>` output from the visual editor.
- [x] Fixed knowledge page save/draft/comment submit paths to read content from the visual editor instance, not only the raw textarea.

2026-07-03 15:58 MSK continuation updates:
- [x] Fixed task description inline edit UX: edit form now replaces the readonly description in the same visual position instead of appearing below the existing content.
- [x] Task description inline save now reads the current value from the visual editor instance before PATCH.
- [x] Widened global create/edit task modals from `modal-lg modal-dialog-centered` to `modal-xl modal-dialog-scrollable` so the visual editor toolbar has enough width and the modal scrolls correctly.
- [x] Restyled task comment edit form as a full-width edit shell with buttons below the editor; actions no longer reduce editor width.
- [x] Added visual editor localization dictionaries for `ru-ru`, `en-gb`, `de-de`, `es-es`, `fr-fr`, `pt-br`, and `zh-cn`.

2026-07-03 16:18 MSK continuation updates:
- [x] Found two remaining useful multiline fields without the new editor during second-pass audit: knowledge list create-page content (`#kbPageContent`) and project modules description (`#projectModuleDescription`).
- [x] Added the visual editor to knowledge create-page modal, widened the modal, refreshed VE on open, and read the VE instance value before page creation.
- [x] Added the visual editor to project modules description, refreshed VE on create/edit modal open, read the VE instance value before save, and rendered table previews as plain text instead of raw HTML snippets.

2026-07-03 23:02 MSK continuation updates:
- [x] Softened the ordered-list toolbar button so it uses a clearer Font Awesome list icon and smaller toolbar icon sizing.
- [x] Reworked file upload visuals by introducing shared `crm-file-input` and `crm-file-upload-row` styles for visible upload fields.
- [x] Wired knowledge-page bottom tabs to an explicit vanilla JS switcher so comments/files panels toggle reliably.
- [x] Hid the knowledge-page Publish button more robustly for already published/archived pages using normalized status checks.
- [x] Widened additional VE modals: dashboard create project, intake create/edit, cycle create/edit, admin tag create/edit, admin webhook create, and admin template create modals.
- [x] Changed task-detail comment create/edit shells so the visual editor occupies the full width and actions sit below instead of squeezing the editor.
- [x] Added file upload row styling to task detail and knowledge page attachments so the file input no longer shares width with the action button.
- [ ] Browser verification after deploy still pending for this pass; local PHP/JS syntax checks are already clean.

2026-07-03 23:15 MSK browser verification on demo after deploy:
- [x] Knowledge page `kbp_f59ea1bd2382838f4828`: status is `Опубликовано`, `knowledgePublishBtn` is hidden, and switching to the Files tab shows the attachment row instead of keeping both panels stacked.
- [x] Knowledge page Files tab: the attachment control is now rendered through the shared file-input styling instead of the old cramped native row.
- [x] App/dashboard create-project modal: browser measured modal width at `1140px`, and the visual editor occupies the full modal width without squeezing into a narrow column.
- [x] Task detail comments shell was rechecked in the browser; the new-comment editor stays on a full-width line with the save button below the editor area.

2026-07-03 23:16 MSK continuation updates:
- [x] Moved task detail inline description binding earlier so the edit button is bound before the slower post-render data loads finish.
- [ ] Browser verification for the first-click description fix still pending.

2026-07-03 23:33 MSK continuation updates:
- [x] Reworked the shared `crm-file-input` controls into a custom shell so selected files are shown in a consistent width-safe row instead of the native browser control.
- [x] Kept the original file input in the DOM for compatibility while hiding it visually and preserving the existing upload JS flows.
- [ ] Browser verification for the new file-input shell is pending.

2026-07-03 23:28 MSK browser verification on demo after deploy:
- [x] Task detail attachment row now renders as a consistent shell with a fixed-height filename area and the upload button aligned to the right.
- [x] The native file input is visually hidden but remains functional for selection and existing upload handlers.
- [x] The shared shell shape is suitable for the other CRM file-upload fields that use `.crm-file-input`.

2026-07-03 16:12 MSK continuation updates:
- [x] User reported from screenshot that the new task comment form still puts the save button to the right of the editor, shrinking useful editor width.
- [x] Locally changed the new task comment form to full-width editor with mention controls and save button in a separate action row below.
- [x] Added scoped task-detail CSS for the new comment action row and mobile stacking.
- [x] Committed as `ce5e74d` and deployed with hash `ce5e74d-1783084367`.

## Files changed

| File | Change |
|------|--------|
| `web/assets/css/visual-editor.css` | NEW — all editor styles |
| `web/assets/js/visual-editor.js` | NEW — editor implementation (~2000 lines) |
| `api/controller/VisualEditor/UploadController.php` | NEW — image upload endpoint |
| `api/config/routes.php` | ADD route for upload |
| `web/view/template/common/header.php` | ADD visual-editor.css |
| `web/view/template/common/footer.php` | ADD visual-editor.js |
| `web/assets/js/modals.js` | ADD data-crm-visual-editor to task/event textareas |
| `web/view/template/page/task_detail.php` | ADD data-crm-visual-editor to description, comment, subtask |
| `web/view/template/page/knowledge_page.php` | ADD data-crm-visual-editor to comment |
| `web/view/template/page/projects.php` | ADD data-crm-visual-editor to description |
| `web/view/template/page/dashboard.php` | ADD data-crm-visual-editor to project description |
| `web/view/template/page/ideas.php` | ADD data-crm-visual-editor to idea descriptions |
| `web/view/template/intake/index.php` | ADD data-crm-visual-editor to intake description |
| `web/view/template/page/admin_templates.php` | ADD data-crm-visual-editor to template descriptions |
| `web/view/template/page/admin_webhooks.php` | ADD data-crm-visual-editor to webhook description |
| `web/view/template/page/admin_tags.php` | ADD data-crm-visual-editor to tag description |
| `web/view/template/page/cycles.php` | ADD data-crm-visual-editor to cycle description |
| `web/view/template/page/project_detail.php` | ADD data-crm-visual-editor to quick task description |
| `web/view/template/page/tasks.php` | ADD data-crm-visual-editor to saved view description |

2026-07-03 23:02 MSK changed files in the latest pass:

| File | Change |
|------|--------|
| `web/assets/css/pages.css` | Added shared file-input styling, knowledge tab panel width fixes, and task comment layout improvements |
| `web/assets/css/visual-editor.css` | Tweaked toolbar icon sizing for cleaner list button appearance |
| `web/assets/js/visual-editor.js` | Swapped the ordered-list button icon to a clearer Font Awesome icon |
| `web/view/template/page/knowledge_page.php` | Added tab switching, publish-button hide logic, and attachment-row styling |
| `web/view/template/page/task_detail.php` | Added file upload row styling and file-input class for attachments |
| `web/view/template/page/dashboard.php` | Widened the project create modal |
| `web/view/template/page/app.php` | Widened the shared project create modal |
| `web/view/template/intake/index.php` | Widened the intake create modal |
| `web/view/template/page/intake/index.php` | Confirmed/kept the VE modal wide for intake create/edit |
| `web/view/template/page/admin_templates.php` | Widened VE modals |
| `web/view/template/page/admin_webhooks.php` | Widened VE modal |
| `web/view/template/page/admin_tags.php` | Widened VE modals |
| `web/view/template/page/cycles.php` | Widened VE modal |

2026-07-03 15:47 MSK changed files in the latest pass:

| File | Change |
|------|--------|
| `web/assets/css/visual-editor.css` | Empty quick chips no longer intercept clicks into editor content |
| `web/assets/js/visual-editor.js` | Added stable image-toolbar data attributes for align/width/actions |
| `web/assets/js/br1.js` | Task comments now sync create/edit/draft values from visual editor instances |
| `web/assets/js/richtext.js` | Old richtext enhancement now hands off eligible textarea fields to the new editor |
| `web/view/template/page/knowledge_page.php` | Knowledge page content editor uses new VE; saves/drafts/comments read VE value |
| `api/model/knowledge/KnowledgeRepository.php` | Knowledge comments preserve sanitized visual-editor HTML |
| `web/assets/css/pages.css` | Knowledge editor textarea visibility rules adjusted for VE wrapper |
| `web/view/template/page/ideas.php` | Idea edit/comment flows refresh VE after programmatic value changes |
| `web/language/*.php` | Added `visual_editor` localization dictionaries |

## Integration points (textareas with data-crm-visual-editor="1")

| Location | File | Field | Status |
|----------|------|-------|--------|
| Task description inline edit | `task_detail.php` (line 75) | `#taskDescriptionInlineInput` | Done |
| Task comment | `task_detail.php` (line 195) | `textarea[name=comment_text]` | Done |
| Subtask create description | `task_detail.php` (line 477) | `textarea[name=description]` in subtask modal | Done |
| Subtask edit description | `task_detail.php` (line 542) | `textarea[name=description]` in edit subtask | Done |
| Create task modal (JS) | `modals.js` | `textarea[name=description]` in #createTaskForm | Done |
| Edit task modal (JS) | `modals.js` | `textarea[name=description]` in #editTaskForm | Done |
| Calendar event modal (JS) | `modals.js` | `textarea[name=description]` in #calendarEventForm | Done |
| Project create modal | `projects.php`, `dashboard.php` | `textarea[name=description]` | Done |
| Quick task from project | `project_detail.php` | `#projectTaskDescriptionInput` | Done |
| Knowledge page comment | `knowledge_page.php` | `#knowledgeCommentInput` | Done |
| Knowledge create page modal | `knowledge.php` | `#kbPageContent` | Done locally; demo verification pending |
| Idea create/edit | `ideas.php` | `#newIdeaDesc`, `#editIdeaDesc`, `#commentInput` | Done |
| Saved view description | `tasks.php` | `#savedViewDescInput` | Done |
| Intake description | `intake/index.php` | `#intakeDescription` | Done |
| Project module create/edit | `project_modules.php`, `project-modules.js` | `#projectModuleDescription` | Done locally; demo verification pending |
| Admin template descriptions | `admin_templates.php` | Task/project template description | Done |
| Webhook description | `admin_webhooks.php` | Webhook description | Done |
| Tag description | `admin_tags.php` | Tag description | Done |
| Cycle goal/description | `cycles.php` | Cycle description | Done |

## Conscious skips (not integrated)

| Location | Reason |
|----------|--------|
| Client/counterparty addresses | Short fields, not multi-line user content |
| Client/counterparty notes | Short comment fields, low value for rich formatting |
| Admin AI template text/schema JSON | Technical fields, JSON/code input |
| Admin jobs payload | JSON technical field |
| Approvals comments | Short decision comments |
| Workflow comments | Short admin comments |
| Sticky notes (sticky-notes.js) | Simple note-taking, visual editor overhead |
| Chat message input | Single-line input with 4000 char limit, real-time chat |
| `extra_attributes_text` fields | JSON technical field |
| `address_legal` / `address_postal` | Short address fields |
| Status change reason (`taskStatusReasonInput`) | Short admin field |
| Comment editing inline (br1.js) | Dynamic JS-created textareas, low usage |

2026-07-03 15:47 MSK update: `Comment editing inline (br1.js)` is no longer skipped. It is integrated with the visual editor and demo-verified for images, resize/align, save, and reopen.

## Tests performed locally

- [x] PHP syntax check: `php -l` on all 17 modified PHP files — no errors
- [x] JS syntax check: `node -c` on visual-editor.js — no errors
- [x] JS component verification: all 18 key components present (sanitizer, resize, paste, drop, upload, toolbar, history, etc.)
- [x] Git diff review: all changes are scoped to the task, no unintended modifications
- [x] Existing richtext.js compatibility: `data-richtext-off="1"` prevents double-enhancement
- [x] Forms degradation: textarea remains in DOM, form submission works without JS
- [x] 2026-07-03 10:27 MSK: `node -c web/assets/js/visual-editor.js` — no syntax errors
- [x] 2026-07-03 10:27 MSK: `node -c web/assets/js/br1.js` — no syntax errors
- [x] 2026-07-03 10:27 MSK: `php -l web/view/template/page/ideas.php` — no syntax errors
- [x] 2026-07-03 10:27 MSK: `php -l web/view/template/page/knowledge_page.php` — no syntax errors
- [x] 2026-07-03 10:27 MSK: Playwright isolated smoke in system Chrome confirmed saved `62.5% right` reopens as managed image block with `62.5% right`, saves changed `45% left`, and readonly renderer displays `45% left`.
- [x] 2026-07-03 10:27 MSK: Playwright visual smoke confirmed main CSS applies, empty chips display, selected image frame/handles display, image toolbar is visible and spaced.
- [x] 2026-07-03 10:30 MSK: Local `http://crm.ru/` responds and redirects to `https://crm.ru/web/index.php?route=login`; no authenticated local form flow tested yet.
- [x] 2026-07-03 10:30 MSK: Unauthenticated POST to `https://crm.ru/api/v1/visual-editor/upload-image` redirects to auth, confirming the route is not openly accessible.
- [x] 2026-07-03 16:18 MSK: `php -l web/view/template/page/knowledge.php` — no syntax errors.
- [x] 2026-07-03 16:18 MSK: `php -l web/view/template/page/project_modules.php` — no syntax errors.
- [x] 2026-07-03 16:18 MSK: `node -c web/assets/js/project-modules.js` — no syntax errors.
- [x] 2026-07-03 16:12 MSK: `php -l web/view/template/page/task_detail.php` — no syntax errors.
- [x] 2026-07-03 23:02 MSK: `php -l` on all newly touched PHP templates — no syntax errors.
- [x] 2026-07-03 23:02 MSK: `node --check web/assets/js/visual-editor.js` — no syntax errors.
- [x] 2026-07-03 23:02 MSK: `git diff --stat` reviewed; changes are limited to VE polish, modal widths, and file-upload layout.

## Tests performed on demo.tropatt.com

- [x] **Assets served:** visual-editor.css and visual-editor.js are loaded on tasks, dashboard, projects pages
- [x] **Editor attributes present:** `data-crm-visual-editor="1" data-richtext-off="1"` found in HTML on tasks, projects, dashboard pages
- [x] **Upload endpoint (authorized):** ✅ Returns `IMAGE_UPLOADED` with URL, dimensions (100x100), MIME (image/png), size (334 bytes)
- [x] **Upload endpoint (unauthorized):** ✅ Returns `UNAUTHORIZED` (401)
- [x] **Upload endpoint (CSRF invalid):** ✅ Returns `CSRF_TOKEN_INVALID` (403)
- [x] **Upload validation (wrong MIME):** ✅ Returns `INVALID_FILE_TYPE` for text/plain
- [x] **All PHP/JS files deployed:** confirmed via deploy output listing

Tests that require browser interaction (pending for manual verification):
- [ ] Create task with visual editor
- [ ] Edit task description
- [ ] Task comment with formatting
- [ ] Insert image into description via toolbar button
- [ ] Resize image with mouse
- [ ] Align image left/center/right
- [ ] Save and reopen — image persists
- [ ] Paste screenshot from clipboard
- [ ] Drag & drop image file
- [ ] Undo/redo
- [ ] XSS sanitization
- [ ] Mobile viewport
- [ ] Old plain text displays correctly

2026-07-03 10:50 MSK pre-fix demo browser reproduction:
- [x] Logged in to `https://demo.tropatt.com/` as admin for browser testing only.
- [x] Opened create task modal and uploaded `/Users/bps/Downloads/avatar-github.png` through the visual editor.
- [x] Confirmed current deployed demo has `veEditor: 0`, `veWrapper: 4`, `veContent: 4`, so main `.crm-ve-editor` CSS does not apply.
- [x] Confirmed uploaded image syncs textarea as internal editor DOM (`<figure class="crm-ve-image-figure" style="--crm-ve-image-width: 75%;">...resize buttons...</figure>`) instead of clean output HTML with `data-align`/`data-width`.
- [x] Confirmed image block remains `data-align="center"`, `data-width` is missing, and toolbar buttons cannot be clicked normally because toolbar opacity is 0 / hidden instances are selected first.
- [x] Confirmed `.crm-ve-image-frame` intercepts pointer events in the current deployed demo.
- [x] These observed demo failures match the local fixes already in progress: clean output conversion, wrapper class fix, toolbar visibility, frame pointer-events/handle CSS, readonly renderer.

2026-07-03 11:10 MSK post-deploy browser verification after commits `c443bba`, `fc9e52f` and deploy marker `d040491`:
- [x] Confirmed deployed asset version: `web/assets/js/visual-editor.js?v=d040491-1783065513`.
- [x] Created a task on demo: `Codex VE verified 1783066170451`.
- [x] Uploaded `/Users/bps/Downloads/avatar-github.png` through the visual editor; upload endpoint returned HTTP 201 at `/api/index.php?route=api/v1/visual-editor/upload-image`.
- [x] Added formatted text (`strong`, `em`) and a bullet list in the editor.
- [x] Used image toolbar to set image width to `50%` and align `right`.
- [x] Before save, textarea contained clean persisted HTML: `<figure data-align="right" data-width="50" style="width:50%">...`.
- [x] Saved task successfully; CRM showed `Задача создана`.
- [x] Opened task detail page: `https://demo.tropatt.com/web/index.php?route=task-detail&task_public_id=tsk_MR4NKAJ1B2A7AC1EEE57D1BB`.
- [x] Readonly renderer preserved image data and layout: `data-align="right"`, `data-width="50"`, `justify-content: flex-end`.
- [x] Measured rendered image width: 350 px in a 699 px block (`ratioOfBlock = 0.5`), so image no longer displays full-width after save.
- [x] Visual screenshot check confirmed formatted text, list, and right-aligned half-width image display correctly on demo.

2026-07-03 11:16 MSK edit/reopen verification after commit `536c3ae` and deploy hash `536c3ae-1783066472`:
- [x] Fixed editor refresh when CRM fills textarea programmatically after a modal opens.
- [x] Added `Editor.refreshFromTextarea(force)` and public `CRM.VisualEditor.refreshEditors(scope, force)`.
- [x] Added scheduled refresh after modal/open/edit clicks and Bootstrap `shown.bs.modal` events.
- [x] Internal image block now keeps `data-width` as well as CSS `--crm-ve-image-width`.
- [x] Deployed to demo with asset `web/assets/js/visual-editor.js?v=536c3ae-1783066472`.
- [x] Reopened task `Codex VE verified 1783066170451` in edit modal.
- [x] Visible edit form contains managed image block with `data-align="right"`, `data-width="50"`, and CSS `--crm-ve-image-width: 50%`.
- [x] Visual screenshot check confirmed the edit modal shows formatted text, list, and right-aligned half-width image, not an empty/full-width editor.
- [x] Changed the same image inside edit modal to `75%` and `left`, saved the task, reloaded the detail page.
- [x] Detail page after edit-save shows `data-align="left"`, `data-width="75"`, `justify-content: flex-start`, rendered width 525 px in a 699 px block (`ratio = 0.75`).
- [x] Visual screenshot check confirmed the image displays left-aligned at 75% width after saving from the edit modal.

2026-07-03 15:47 MSK browser verification after commits through `edaa8cb` and deploy hash `edaa8cb-1783082780`:
- [x] Demo assets loaded with `visual-editor.css?v=edaa8cb-1783082780` and `visual-editor.js?v=edaa8cb-1783082780`.
- [x] Task comment create flow: uploaded image, set `right` and `50%`, textarea contained clean `<figure data-align="right" data-width="50">`, saved comment rendered with `data-align="right"`, `data-width="50"`, and figure width about half of the comment card.
- [x] Task comment edit flow: clicking edit opened a visual editor with one managed `.crm-ve-image-block`, `data-align="right"`, `data-width="50"`, and textarea value containing `<figure>`; it did not degrade to image filename/caption text.
- [x] Task comment edit-save flow: changed the same image to `left` and `75%`, saved, and readonly comment re-rendered with `data-align="left"`, `data-width="75"`, figure width about `0.72` of the card.
- [x] Knowledge page edit smoke on `kbp_f59ea1bd2382838f4828`: edit form shows the new `.crm-ve-editor`, `#knowledgeVisualEditor` is absent, `.crm-rte` count is `0`, old toolbar is hidden, textarea has `data-crm-ve-ready="1"`, visual toolbar has 14 buttons.
- [x] Knowledge comment create flow: uploaded image, saved comment now contains/rendered `<figure><img>`, `data-align="right"`, width persisted around `63.83%` because the comment column is narrow and min image width clamps the requested 50%.
- [x] Cross-page smoke on demo: `/tasks`, `/task-detail`, `/projects`, `/ideas`, `/knowledge-page` all had `oldRte=0`; marked visual-editor textareas were initialized with `data-crm-ve-ready="1"`.
- [x] Client sanitizer XSS smoke on demo: removed/neutralized `onerror`, `javascript:`, `svg`, `iframe`, and inline `style` payloads.
- [x] Screenshots captured for visual checks: `/tmp/crm-ve-task-comment-created.png`, `/tmp/crm-ve-task-comment-saved-left75.png`, `/tmp/crm-ve-kb-edit-smoke.png`, `/tmp/crm-ve-kb-comment-after-patch.png`.

2026-07-03 15:58 MSK browser verification after commit `e4ac0ca` and deploy hash `e4ac0ca-1783083407`:
- [x] Task description edit visual control: before edit, readonly content was at `y=677.4`; after clicking edit, editor appears at the same `y=677.4`, readonly content is hidden, and focus is inside `.crm-ve-content`.
- [x] Create task modal visual control: dialog is now `modal-dialog modal-xl modal-dialog-scrollable`, width `1140px`, editor width `1106px`; toolbar no longer squeezes.
- [x] Task comment edit visual control: edit shell class `crm-comment-edit-shell`; editor width `599px` inside `625px` shell, buttons are below the editor, `editorNotSqueezed=true`, `actionsBelow=true`.
- [x] RU localization check: task description editor toolbar titles are `Жирный`, `Курсив`, `Зачеркнутый`, `Код`, `Ссылка`; create task modal quick chips are `+ Изображение`, `# Заголовок`, `• Список`, `Ctrl+V скриншот`.
- [x] EN localization check in fresh login session with `en-gb`: `window.CRM.locale=en-gb`, `html lang=en`, toolbar titles are `Bold`, `Italic`, `Strikethrough`, `Inline code`, `Link`, quick chips are `+ Image`, `# Heading`, `• List`, `Ctrl+V screenshot`.
- [x] Screenshots captured for visual checks: `/tmp/crm-ux-desc-edit-after-fix.png`, `/tmp/crm-ux-create-task-modal-after-fix.png`, `/tmp/crm-ux-comment-edit-after-fix.png`.

2026-07-03 16:05 MSK browser verification after commit `e4ac0ca` and deploy hash `e4ac0ca-1783083407`:
- [x] Paste image/data URL flow in create task modal: pasted image uploaded to `/storage_api/uploads/visual-editor/2026/07/bb623ae90fb7af91da27c514abd0948b.png`, textarea contains clean `<figure>`, no `data:image` remains, image persisted in editor as `align=center`, `width=75`.
- [x] Drag & drop image file flow in create task modal: dropped image uploaded to `/storage_api/uploads/visual-editor/2026/07/70b7dc786e3235f0b17b82f9a9748cf8.png`, page did not navigate away from `route=tasks`, textarea contains clean `<figure>`, no `data:image` remains, drag-over state is cleared after drop.
- [x] Visual screenshot captured for paste/drop check: `/tmp/crm-ve-paste-drop-demo.png`.

2026-07-03 16:24 MSK browser verification after commits `07ecff0`, `ce5e74d` and deploy hash `ce5e74d-1783084367`:
- [x] New task comment form visual check: editor width `699px` in `699px` form (`ratio=1.0`), save button is below the editor, not to the right; screenshot `/tmp/crm-ve-new-comment-layout-ce5e74d.png`.
- [x] Task comment edit visual check on task `tsk_MQ1CLSY537BD9E9A06EC6E42`: edit shell width `673px`, editor width `647px` (`ratio=0.961` because of shell padding), save/cancel row is below the editor, not to the right; screenshot `/tmp/crm-ve-comment-create-edit-layout-ce5e74d.png`.
- [x] Mobile task comment form check at `390px`: editor width `328px` in `328px` form (`ratio=1.0`), save button stacks below mention controls and spans full width; screenshot `/tmp/crm-ve-new-comment-mobile-ce5e74d.png`.
- [x] Knowledge create-page modal check: `modal-xl modal-dialog-scrollable`, dialog `1140px`, editor `1106px`, textarea `data-crm-ve-ready=1`, old `.crm-rte=0`.
- [x] Knowledge create-page save with uploaded image: response `201 KNOWLEDGE_PAGE_CREATED`, saved `content_html` contains `<strong>` and `<figure data-align="right" data-width="50" style="width:50%">`; readonly page `kbp_b6362dc726bef3deec8a` renders image with `align=right`, `width=50`, unsafe HTML probe false.
- [x] Project module create/edit check: modal editor initialized (`data-crm-ve-ready=1`, old `.crm-rte=0`), saved description contains `<strong>` and `<figure>`, table preview does not show raw tags, reopening edit modal restores managed image block with `align=right`, `width=50`.
- [x] Screenshots captured: `/tmp/crm-ve-kb-create-modal-image-ce5e74d.png`, `/tmp/crm-ve-kb-created-image-ce5e74d.png`, `/tmp/crm-ve-project-module-modal-ce5e74d.png`, `/tmp/crm-ve-project-module-reopen-ce5e74d.png`.

2026-07-03 16:39 MSK browser verification after commits `c69c7d7`, `4410e11`, `e506b7d` and deploy hash `e506b7d-1783085607`:
- [x] Idea list create flow: fixed missing list-page visual-editor helper; create modal no longer throws `ReferenceError: getVisualEditorValue is not defined`.
- [x] Idea create/view flow: created idea `idea_db416698275dd32b9a890731`; detail page renders saved rich description with formatted text and image `align=right`, `width=50`; unsafe HTML probe false.
- [x] Idea comment API contract: fixed frontend to send `{body: html}` instead of `{text: html}` and to render `c.body || c.text`.
- [x] Idea comment visual create flow on demo: pasted image uploaded to `/storage_api/uploads/visual-editor/2026/07/ea87a5797edaae7a35d6f6ca24fd1188.png`; before submit textarea contained `<figure data-align="right" data-width="50" style="width:50%">`.
- [x] Idea comment readonly flow: saved comment renders `.crm-ve-readonly-image-block` with `data-align="right"`, inner `figure data-width="50"`, real uploaded image URL, text `Codex idea comment rich e506b7d`, and unsafe HTML probe false.
- [x] Idea comment layout visual check: new comment save button is below the editor, not to the right; screenshot `/tmp/crm-ve-idea-comment-e506b7d.png`.
- [x] PHP syntax check passed for `web/view/template/page/ideas.php`.

2026-07-03 16:49 MSK browser verification after commits `421939a`, `835c24d`, `ab20544` and deploy hash `ab20544-1783086426`:
- [x] Knowledge comment compose layout fixed and deployed: save action is below the visual editor, not to the right; editor uses the full available comment width; old `.crm-rte` count is `0`.
- [x] Project detail description integration fixed and deployed: project identity edit form initializes the new visual editor with `data-crm-ve-ready="1"` and no old editor.
- [x] Project description save/reopen verified on demo project `prj_MQUU96XMD17FC47C9C4AECC1`: before submit textarea contained clean `<figure data-align="right" data-width="50" style="width:50%">`.
- [x] Project description readonly after save renders `.crm-ve-readonly-image-block` with `data-align="right"`, `data-width="50"`, `unsafe=false`, and real rendered image ratio `0.5`, so the image no longer expands to full block width.
- [x] Project description repeated edit verified: reopened editor restores the saved image as a managed `.crm-ve-image-block` with `data-align="right"` and `data-width="50"`, not as plain caption/filename text.
- [x] Forced visual editor to use the light theme everywhere. Removed dark-context CSS overrides and set `.crm-ve-editor { color-scheme: light; }`; floating image toolbar/popovers/toasts now use light backgrounds and dark text.
- [x] Demo theme verification: page body still has `crm-sidebar-collapsed`, but editor computed colors are light (`background rgb(255,255,255)`, border `rgb(217,225,236)`, text `rgb(30,41,59)`), toolbar is light (`rgb(246,248,251)`), and floating image toolbar is white.
- [x] Visual screenshots captured: `/tmp/crm-ve-light-theme-ab20544.png`, `/tmp/crm-ve-project-reopen-light-ab20544.png`.

## Known issues

- Fixed and demo-verified the reported critical bug where resized images saved/reopened/rendered at full width.
- Demo task verification confirms image width/align now survive save, readonly rendering, and reopening in the edit modal (`50%`, right aligned).
- Fixed and demo-verified the follow-up task comment bug: editing a comment with an image now opens a visual editor with a managed image block, not only the image filename/caption text.
- Fixed and demo-verified the knowledge page bug: the page edit form now uses the new visual editor, not the old custom editor.
- Fixed and demo-verified knowledge comments preserving visual-editor image HTML after backend sanitization.
- Fixed and demo-verified task description edit placement, create/edit task modal width, task comment edit layout, and editor RU/EN localization.
- Fixed and demo-verified project detail description save/reopen: image align/width persist in readonly and repeated edit.
- Fixed and demo-verified the theme complaint: the editor is now intentionally light in all CRM contexts, including collapsed-sidebar pages where it previously inherited dark styling.
- Still to finish against v2 references: inline text selection bubble, resize ghost/guides/drag vector, polished image settings popover (currently prompt-style actions), upload drop-card visual, and deeper browser checks inside actual CRM forms.
- Need second-pass authenticated coverage for intake/admin-template rich text beyond smoke and remaining save/reopen XSS probes.
- Current worktree has `web/DEPLOY_HASH` modified by deploy; docs reference folder is git-ignored, so this status file is local-only unless git ignore rules are changed.

## Continuation point

Continue with second-pass hardening, not with the already-fixed task-comment/knowledge bugs:
1. Check intake/admin-template surfaces with real create/edit/view flows and decide whether visual editor is useful there.
2. Check remaining comment forms (knowledge page, project-adjacent pages, client cabinet if editable) for button placement and old editor leftovers.
3. Run mobile viewport checks.
4. Continue visual polish against v2 references: inline text bubble, resize ghost/guides, settings popover, upload drop-card/error placement, mobile final pass.

## How to add to a new textarea

Simply add these attributes to the textarea:
```html
<textarea data-crm-visual-editor="1" data-richtext-off="1" ...></textarea>
```

Optional data attributes:
- `data-crm-visual-editor-upload-url` — custom upload endpoint
- `data-crm-visual-editor-placeholder` — custom placeholder text
- `data-crm-visual-editor-min-image-width` — min image width in px

The editor auto-initializes on DOMContentLoaded via MutationObserver.
