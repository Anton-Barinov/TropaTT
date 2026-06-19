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
- Complete modal now loads planned / active target cycles and shows localized status labels.
- Added a command-center strip above filters with active, planned, overdue and open-task metrics plus next-step guidance.
- Add-task flow was browser-tested: task attach and detach work. After attach, the UI now switches to the Tasks tab and resets the selected-task counter on each modal open.
- Add-task confirmation button now shows an in-progress label while the API request is running.

Pending verification:
- Browser reload on demo after deployment.
- Open detail modal, Tasks tab, Board tab and Complete modal.
- Create a test cycle, edit it, add a task if suitable demo data exists, and archive/complete safely.
- API and menu access are aligned on `project.manage` for now. A dedicated `cycle.view` / `cycle.manage` permission split can be introduced later with a migration and role seeding plan.

## 2026-06-19 follow-up

Goal: fix cycle focus, Kanban navigation and visibility rules reported from demo.

Implemented:
- Cycle visibility is now restricted at API list/detail level: root/admin roles see all; regular users see cycles they created, own, or where they are assignees of at least one task in the cycle.
- Cycle detail tasks and summary endpoints use the same visibility check, so direct URLs cannot expose hidden cycles.
- The cycle command-center focus no longer clears and recalculates from the currently visible page. It loads a separate stable summary for the selected project.
- Kanban now has a Cycle filter and understands `cycle_public_id` from links such as `index.php?route=kanban&cycle_public_id=...`.
- Kanban persists the cycle filter in `kanbanFilters` cookie and applies it to the API task query when opening a filtered board.
- Cycle pagination was moved from default Bootstrap pagination to CRM-styled compact buttons.

Verification needed:
- Log in as admin and confirm all cycles are visible.
- Log in as a non-admin creator and confirm only owned/created cycles plus cycles with assigned tasks are visible.
- Open a cycle with no tasks, click "Open board", and confirm Kanban shows an empty board with that cycle selected.
- Open a cycle with tasks and confirm Kanban shows only tasks from that cycle.
