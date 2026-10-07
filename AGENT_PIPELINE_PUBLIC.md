# TropaTT Agent Workflow

This document is the portable workflow for human and AI contributors, regardless of model vendor or development environment. It contains no credentials. Local owner context and CRM Knowledge Base may add environment-specific details, but must not weaken the safety rules here. Engineering rules in `AGENTS.md` and narrower directory instructions still apply to source changes.

## Sources of truth and authority

- CRM is authoritative for task assignment, status, acceptance criteria, dependencies, knowledge links, journal events and worklogs.
- Git is authoritative for source history and the exact candidate SHA.
- A deployment marker plus verified file manifest and database migration state are authoritative for what is actually running.
- A test report is evidence only for the exact SHA, deployment ID and run it names. A moving branch, “latest” pointer, comment, or green summary alone is not release approval.
- Follow the direct owner request, applicable private environment context, CRM regulations, this workflow, then engineering instructions. Resolve conflicts by updating the conflicting instructions and recording the outcome in CRM; do not silently follow a stale command.

## Lifecycle

`inspect → claim → prepare → implement → local_verified → published_candidate → deployed → live_verified → promoted → complete`.

A stage that fails or has an unknown result enters `reconcile`; it does not retry blindly. A checkpoint means paused/incomplete, never done. Never claim a stage for which there is no evidence.

## 1. Inspect and claim work

1. Read repository instructions and the relevant CRM regulations, infrastructure notes and task-linked knowledge pages. Verify the current CRM actor and tool schemas before mutations.
2. Work only on a task assigned to the current agent/run or explicitly assigned by the owner. Read all task pages, comments, attachments, checklists, subtasks, relations and recent worklogs before editing.
3. Run a read-only capability check before changing CRM state. Verify the live tool catalog and effective capabilities against the assigned task: authenticated actor, task assignment, `crm_agent_lease` status probe, and `crm_agent_journal` read probe. Use `python3 scripts/crm_agent_cli.py doctor --task tsk_ID` when the private helper is available; otherwise perform equivalent read-only MCP calls. If any tool/schema/probe is absent, mismatched or ambiguous, stop before task writes. Claim an exclusive CRM task lease using the supported lease operation. A status such as `in_progress`, a recent comment, a shared agent account, memory entry, or lock comment is not a lease. Record a human-readable start comment and journal intent after ownership is confirmed. If the live CRM does not expose and successfully enforce the lease, do not run parallel task writers; report the missing capability for rollout. A source commit or API schema in `develop` does not prove the connected CRM has it.
4. Use a unique stable agent identity and run ID. Keep lease capability state in a private file outside the repository with owner-only permissions. Renew before expiry. On lost ownership, stop task mutations and publication; reconcile first.
5. Use CRM-native checklists, subtasks, relations and knowledge links. A helper that creates several entities sequentially is not atomic: inspect its receipts and partial state before retry. Do not assume a QA task is assigned or linked unless returned and verified. QA relation direction must mean implementation is blocked by QA.

## 2. Isolate parallel work

- Give every concurrent contributor a separate worktree/checkout and task branch. Do not share the Git index or mutable checkout. Do not use `stash`, destructive reset/clean, broad `git add -A`, or copy/rsync an active worktree over another one.
- Agree on file ownership for overlapping work. Serialize edits to shared files or integrate them through a reviewed Git merge. If a conflict occurs, preserve both histories and ask the designated integrator to resolve it.
- Before editing, record the task/run, branch, base SHA and owned paths. Before committing, inspect the exact staged paths and diff. Reject credentials, runtime state, test artefacts or unrelated files.
- Every commit and push must follow the repository's required attribution policy. Push only normal fast-forward updates; never force-push. A rejected push means fetch and reconcile the newer history.
- Task leases prevent two agents from owning the same CRM task only after the live capability has been verified. If unavailable, serialize all task mutations under a single owner. Task leases do not serialize shared Git integration, a demo server, update jobs or production releases; those resources require their own locks.

## 3. Journal, comments and recovery

Before every substantial external mutation, append a uniquely identified `intent` journal event. Afterward append `result` with outcome and evidence. If interrupted or uncertain, append `checkpoint` or `blocker`. Use idempotency keys where supported. If a response is lost, query the operation receipt and target state before deciding whether to retry.

Task comments are a concise readable activity timeline, not raw tool transcripts. Record start, material implementation decisions, completed checks, push/deploy/test milestones, blockers and stop/resume checkpoints. Include task/run IDs, full commit SHA, owned paths, report digest and next specific action when relevant. Never include credentials, lease tokens, private config values, session data or unnecessary personal data.

A durable checkpoint must preserve: task and QA IDs; run/lease generation; stage; worktree/branch; base, local and remote SHAs; changed paths; commit and deployment IDs; pending operations; report path/digest; fixtures requiring cleanup; known blockers; and the next command plus conditions for safely running it. Resume by reading the CRM journal and checkpoint, validating the lease, inspecting Git and operation receipts, and reconciling actual state before repeating a mutation.

## 4. Implement and verify locally

- Make the smallest coherent change that satisfies the task. Preserve existing behavior outside scope. Consider security, RBAC, organization/workspace boundaries, migrations, API/MCP contracts, localization, shared hosting and installer parity where applicable.
- Run syntax and format checks for touched files, focused tests, then the appropriate broader suite. Contract changes also require schema/permission/OpenAPI/documentation parity. UI work requires browser checks at relevant viewport sizes, scrolling, loading/empty/error/permission states and interaction flows.
- Every required test stage must produce a valid non-empty result, successful process exit and expected positive coverage. Missing, timed out, malformed, empty, all-skipped or contradictory results fail closed. Explain allowed skips. Never edit expected outcomes just to get green.
- Database changes require idempotent new migrations, upgrade/rollback analysis, matching fresh-install schema/seed, and tests against the supported MySQL behavior. Do not mutate a shipped migration body without a new migration key.
- Store evidence under a unique run ID. Do not treat shared `latest` files as immutable evidence.

## 5. Publish source candidates safely

Publish only reviewed committed changes from an isolated, clean worktree. Pin the full 40-character SHA and record the parent/base SHA. Integration against a moving development branch is serialized: fetch the latest target, integrate in an owned checkout, rerun affected tests, inspect the integrated diff and push a normal fast-forward update. Preserve all concurrent commits. A candidate push is not a deployment or promotion.

## 6. Demo deployment and live QA

Only the accepted release coordinator may deploy. If no coordinator has passed acceptance for the target environment, do not use an ad-hoc script, bidirectional sync, raw upload, or manual server edit as a substitute. Continue local work and report the exact missing acceptance gate.

A safe coordinator must hold an exclusive cross-machine release lease and host release lock from preflight through the final QA/promotion decision. On shared hosting, the host lock is a persistent PHP CLI process running `upload/updater/bin/release_host_guard.php`; it uses `flock()` on `storage_api/release-coordinator/host-release.lock`, is only reachable over the supervisor's SSH/pipe transport, and accepts only bounded `ping` and `release` JSON lines. The coordinator must verify the initial protocol handshake, monitor process liveness, use a bounded timeout, and treat EOF or a lost pipe as lease loss. Never expose this helper as an HTTP API or send shell commands, paths, or secrets through its protocol. This host lock serializes competing release supervisors but does not block normal application traffic by itself. For the snapshot and mutation window, the coordinator also acquires the installation's exclusive PHP deployment mutex. Configured API/web front controllers and cooperating background workers hold a shared lock on that same inode for their complete pass; this drains in-flight PHP requests and workers before files or the database change. New front-controller requests receive a retryable 503 while the exclusive installation lock is held. If this request fence is absent, unverified, or cannot be acquired, the release stops before snapshot. Set the owned maintenance reason to `deployment_pipeline` before bounded snapshot/migration steps; this mode allows only read-only status/recovery routes between lock intervals, so no application writer can change the database mid-snapshot. Release the installation mutex after the mutation is verified, then restore service for exact-SHA live QA while retaining the cross-machine release lease and host lock; if QA fails after users can write again, use forward repair rather than restoring an old database snapshot over new work. The coordinator must:

1. Pin exact source SHA, immutable upload manifest, deployment ID, target and release lease.
2. Prove all writers that could mutate relevant files/database are coordinated; drain in-flight requests/jobs through the shared/exclusive installation mutex. A new lock cannot fence work already running unless those writers hold the shared side. Stop if the exact target is not running the request fence or quiescence cannot be proven.
3. Take and verify a consistent MySQL snapshot while writes are quiesced. Record the migration/recovery plan before mutation.
4. Apply only the immutable manifest with a durable per-file journal, preserving unrelated runtime data, ownership and permissions. Reconcile actual hashes after interruptions; never overwrite foreign bytes.
5. Apply migrations in bounded, recorded steps. Preserve maintenance after partial failure. Do not restore an old database snapshot over new user writes; prefer controlled forward repair if the system has reopened.
6. Verify full SHA, marker, manifest digest and actual target hashes. Run browser, API, lifecycle, negative/RBAC and relevant shared-hosting checks against that exact deployment. If the target changes during QA, discard that report and test the actual target again.
7. Write a complete immutable report in a unique run directory. Missing/malformed evidence, a marker/SHA mismatch, required failure, unauthorized skip, fixture-cleanup failure, documentation drift, or unknown migration state blocks promotion.
8. Promote only the exact tested SHA through the authorized protected path. Do not merge a moving branch after testing an older SHA. Keep the task/release open on failure or uncertain outcome; reconcile before retry.

Never claim “deployed”, “tested”, “published” or “complete” based only on a successful command return. Verify remote state and record evidence.

## 7. QA and closeout

Use an assigned QA owner/run, native test checklist, exact build identity, and a formal blocking relation. Test only isolated, run-prefixed fixtures; clean only records created by that run and record cleanup outcome. Include desktop/mobile and browser interaction for UI changes, plus permission-negative tests for protected changes.

Close QA only after its checklist and exact-SHA report pass. Close implementation only after QA, required subtasks/DoD, docs, cleanup, worklog, final comment and no pending mutation are verified. Reconcile an uncertain CRM write before retrying it. The final report states commit SHA, deployment/run/report IDs, tests and limitations.

## 8. Portability and shared hosting

The customer installation remains PHP/MySQL on ordinary shared hosting. Do not add mandatory containers, root privileges, Redis, daemons, Node, or persistent shell access to customer runtime. Agent tooling may use optional Git/Python/SSH only when available in the development environment; it must detect missing capabilities and fail with actionable instructions. Keep provider-specific adapters behind documented interfaces.
