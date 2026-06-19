# Work Cycles Feature Progress

## 2026-06-19

Goal: make `/web/index.php?route=cycles` useful as a real sprint / iteration planning workspace, not just a CRUD page.

Checked:
- API documentation in `docs/api/work-cycles.md`.
- Web documentation in `docs/web/work-cycles.md`.
- Page template `web/view/template/page/cycles.php`.
- Frontend logic `web/assets/js/work-cycles.js`.
- API controller, service and repositories for work cycles.
- Demo page visually in browser at `https://demo.tropatt.com/web/index.php?route=cycles`.

Findings:
- The page showed many active test cycles with `0/0` tasks and no practical next step.
- The detail modal showed mostly raw metadata and did not guide the user toward planning, work, transfer or completion.
- Editing a cycle sent `status` in `PATCH`, while the API service rejects status changes through update.
- Project and owner selects were loaded asynchronously, but edit values were applied before options were guaranteed to exist.
- Completing with "move unfinished tasks" requested cycles with `status=planned,active`, while the API expects one status value.
- Task search for adding tasks was not constrained to the cycle project, causing avoidable invalid selections.

Implemented in first pass:
- Hide status field on edit and do not send `status` in `PATCH`.
- Wait for project / owner select loaders before applying edit values.
- Add a Board tab in cycle detail with a direct Kanban link filtered by `cycle_public_id`.
- Add action-oriented hints on cycle cards and in the detail overview.
- Search tasks for a cycle inside that cycle's project.
- Fix target-cycle loading for unfinished task transfer by loading cycles and filtering planned / active client-side.
- Add missing Russian and English translation keys for the new UI states.
- Add compact responsive CSS for cycle cards, progress and detail callouts.

Browser verification after deploy:
- New cache-busted `work-cycles.js` is now loaded with the shared assets version.
- Cycle cards render the new actionable empty-plan state.
- Cycle detail modal opens and shows Overview, Tasks, Board and Statistics tabs.
- Edit modal waits for project options and hides the status field.
- Edit save exposed an API contract issue: empty date fields were sent as `null`, while the service expects an empty string for clearing dates. Fixed in frontend.

Pending verification:
- Browser reload on demo after deployment.
- Open detail modal, Tasks tab, Board tab and Complete modal.
- Create a test cycle, edit it, add a task if suitable demo data exists, and archive/complete safely.
- Decide whether API route permissions should move from `project.manage` to dedicated `cycle.view` / `cycle.manage` after checking role seeding and existing installs.
