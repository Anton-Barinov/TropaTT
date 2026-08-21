<?php

declare(strict_types=1);
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

/**
 * external_users_security_check
 *
 * Guards the Client Portal (External Users) security model. Every assertion here
 * encodes a bug that was actually found in review — each one, if it regresses,
 * silently breaks tenant isolation without any visible error.
 *
 * Runs standalone in CI (no database, no network): php upload/api/scripts/external_users_security_check.php
 *
 * The external-guest model is deliberately defence-in-depth, because the codebase's
 * permission codes are coarse: an external guest holds `task.manage` + `project.manage`
 * (the only real codes that gate task/project reads), which on their own would also
 * unlock bulk edits, board views, deletes and project administration. Tenant isolation
 * therefore rests on three independent layers, all asserted below:
 *
 *   1. RLS scoping   — ProjectService/TaskService filter by counterparty in SQL.
 *   2. Route allowlist — `external_ok` in api/config/routes.php, enforced centrally
 *                        in App::run(); everything not on the list is 403.
 *   3. UI/page gating — MenuController + web/index.php hide and block the rest.
 *
 * Layer 2 is the critical one: without it, layers 1 and 3 are bypassable via direct
 * API calls. This test is DB-free by design (static/source analysis only) so it runs
 * in CI without a database, exactly like ai_canonical_api_contract_smoke.php.
 */

$root = dirname(__DIR__, 2);
$apiRoot = $root . '/api';
$webRoot = $root . '/web';

$routesPath = $apiRoot . '/config/routes.php';
$appPath = $apiRoot . '/system/library/app.php';
$routerPath = $apiRoot . '/system/library/router/Router.php';
$migrationPath = $apiRoot . '/system/library/database/migration/ExternalUsersMigration.php';
$permissionServicePath = $apiRoot . '/system/library/service/PermissionService.php';
$authRepositoryPath = $apiRoot . '/model/auth/AuthRepository.php';
$authServicePath = $apiRoot . '/system/library/service/AuthService.php';
$migrationManagerPath = $apiRoot . '/system/library/database/migration/MigrationManager.php';
$taskServicePath = $apiRoot . '/system/library/service/TaskService.php';
$projectServicePath = $apiRoot . '/system/library/service/ProjectService.php';
$menuControllerPath = $apiRoot . '/controller/auth/MenuController.php';
$fileServicePath = $apiRoot . '/system/library/service/FileService.php';
$commentServicePath = $apiRoot . '/system/library/service/CommentService.php';
$commentRepositoryPath = $apiRoot . '/model/comment/CommentRepository.php';
$notificationServicePath = $apiRoot . '/system/library/service/NotificationService.php';
$externalUserServicePath = $apiRoot . '/system/library/service/ExternalUserService.php';
$externalUserControllerPath = $apiRoot . '/controller/external/ExternalUserController.php';
$contactRepositoryPath = $apiRoot . '/model/contact/ContactRepository.php';
$contactServicePath = $apiRoot . '/system/library/service/ContactService.php';
$counterpartyServicePath = $apiRoot . '/system/library/service/CounterpartyService.php';
$portalBindingsPath = $webRoot . '/assets/js/page-api-bindings.js';
$apiClientJsPath = $webRoot . '/assets/js/api.js';
$externalInvitationMigrationPath = $apiRoot . '/system/library/database/migration/ExternalInvitationLifecycleMigration.php';
$userRepositoryPath = $apiRoot . '/model/common/UserRepository.php';
$webIndexPath = $webRoot . '/index.php';
$webControllerPath = $webRoot . '/system/Core/Controller.php';
$webLanguageRoot = $webRoot . '/language';

$failures = [];

function failSmoke(string $message): void
{
    fwrite(STDERR, "[FAIL] external_users_security_check: {$message}\n");
    exit(1);
}

function readFileSafe(string $path): string
{
    if (!is_file($path)) {
        failSmoke("file not found: {$path}");
    }

    $content = file_get_contents($path);
    if ($content === false) {
        failSmoke("unable to read file: {$path}");
    }

    return $content;
}

/** @param list<string> $failures */
function check(array &$failures, bool $condition, string $message): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

// ---------------------------------------------------------------------------
// 1. Route allowlist: exact membership.
// ---------------------------------------------------------------------------
// The allowlist is asserted as an EXACT set, not a subset. Adding `external_ok`
// to a new route must fail this test until someone consciously updates the
// expected list — that review step is the whole point.

/** @var array<int,array<string,mixed>> $routes */
$routes = require $routesPath;
if (!is_array($routes) || $routes === []) {
    failSmoke('api/config/routes.php must return a non-empty array');
}

$expectedExternalRoutes = [
    'POST /api/v1/auth/logout',
    'GET /api/v1/auth/me',
    'GET /api/v1/auth/menu',
    'GET /api/v1/projects',
    'GET /api/v1/projects/{public_id}',
    'GET /api/v1/tasks',
    'POST /api/v1/tasks',
    'GET /api/v1/tasks/{public_id}',
    'GET /api/v1/tasks/{public_id}/comments',
    'POST /api/v1/tasks/{public_id}/comments',
    'GET /api/v1/tasks/{public_id}/files',
    'POST /api/v1/files',
    'GET /api/v1/files/{public_id}',
    'GET /api/v1/files/{public_id}/download',
    'GET /api/v1/notifications',
    'GET /api/v1/notifications/counters',
    'PATCH /api/v1/notifications/{public_id}/read',
    'PUT /api/v1/notifications/{public_id}/read',
    'PATCH /api/v1/notifications/{public_id}/unread',
    'PUT /api/v1/notifications/{public_id}/unread',
    'POST /api/v1/notifications/mark-all-read',
    // Own-profile self-service (point 8: a guest must be able to open and manage their
    // own account). Every one of these is explicitly self-scoped (authz_note says so and
    // the underlying service reads/writes only the authenticated actor's own row) — none
    // of them can reach another user's data. profile/me's update path can never actually
    // change email/login (UserProfileService::updateMe() unconditionally rejects any email
    // change with EMAIL_CHANGE_REQUIRES_VERIFICATION for every actor, not just guests), so
    // opening it cannot let a guest collide their login with another account's.
    'GET /api/v1/profile/me',
    'PATCH /api/v1/profile/me',
    'PUT /api/v1/profile/me',
    'GET /api/v1/profile/preferences',
    'PATCH /api/v1/profile/preferences',
    'PUT /api/v1/profile/preferences',
    'POST /api/v1/profile/change-password',
    'GET /api/v1/security/sessions',
    'POST /api/v1/security/sessions/revoke-others',
    'GET /api/v1/security/2fa/status',
    'POST /api/v1/security/2fa/enable',
    'POST /api/v1/security/2fa/disable',
    // Project overview data for a guest's own accessible project (point 9: the
    // project card must not come up with a broken/empty layout for a guest).
    // All four routes reuse ProjectService::get()/MilestoneService's identical
    // per-project access check already trusted for GET /api/v1/projects/{id} —
    // no new object-level authorization surface. Content is aggregate-only
    // (task/milestone counts) except ProjectSummaryService::summary(), which
    // explicitly strips per-employee workload.items for is_external actors
    // (checked below) so a guest's network tab can never reveal internal staff
    // names or individual task assignments.
    'GET /api/v1/projects/{public_id}/summary',
    'GET /api/v1/projects/{public_id}/milestones-summary',
    'GET /api/v1/projects/{public_id}/risks',
    'GET /api/v1/milestones',
    // External user client chat: list own project_client chats, read/write messages.
    // Defence-in-depth: ChatController::list() filters to project_client type only for
    // external users; ChatService::getChatForExternal() verifies chat.type = 'project_client'
    // AND participant membership on every call.
    'GET /api/v1/chats',
    'GET /api/v1/chats/{public_id}/messages',
    'POST /api/v1/chats/{public_id}/messages',
    'POST /api/v1/chats/{public_id}/read',
    'GET /api/v1/chats/{public_id}',
    // External user knowledge access: client-visible pages linked to a project.
    // Read-only, stripped of internal metadata, access checked via ProjectService::get().
    'GET /api/v1/knowledge/project/{project_public_id}/client-pages',
    'GET /api/v1/knowledge/client-page/{public_id}',
];

$actualExternalRoutes = [];
foreach ($routes as $route) {
    if (($route['external_ok'] ?? false) !== true) {
        continue;
    }
    $pattern = (string)($route['pattern'] ?? '');
    foreach ((array)($route['methods'] ?? []) as $method) {
        $method = strtoupper(trim((string)$method));
        if ($method !== '' && $pattern !== '') {
            $actualExternalRoutes[] = $method . ' ' . $pattern;
        }
    }
}

sort($expectedExternalRoutes);
sort($actualExternalRoutes);

$unexpected = array_diff($actualExternalRoutes, $expectedExternalRoutes);
$missing = array_diff($expectedExternalRoutes, $actualExternalRoutes);

check(
    $failures,
    $unexpected === [],
    'route(s) marked external_ok but NOT in the reviewed allowlist (possible privilege '
    . 'escalation for client-portal guests): ' . implode(', ', $unexpected)
);
check(
    $failures,
    $missing === [],
    'route(s) expected to be external_ok but are not — the client portal will 403 on '
    . 'legitimate guest traffic: ' . implode(', ', $missing)
);

// ---------------------------------------------------------------------------
// 2. Route allowlist: no destructive or admin surface, regardless of the list above.
// ---------------------------------------------------------------------------
// A second, independent guard so that even an incorrect edit to the expected list
// above cannot hand guests a destructive endpoint.

$forbiddenSubstrings = [
    '/admin/', '/users', '/roles', '/permissions', '/settings',
    '/webhooks', '/api-clients', '/import', '/export', '/recycle-bin', '/teams',
    '/departments', '/companies', '/clients', '/counterparties', '/contacts',
    '/external-users', '/analytics', '/worklogs', '/approvals', '/modules',
];
// Specific patterns that ARE allowed for external users (subset of /chat and /knowledge).
$allowedChatKnowledgePatterns = [
    '/chats/{public_id}/messages',
    '/chats/{public_id}/read',
    '/chats/{public_id}',
    '/knowledge/project/{project_public_id}/client-pages',
    '/knowledge/client-page/{public_id}',
];

foreach ($routes as $route) {
    if (($route['external_ok'] ?? false) !== true) {
        continue;
    }
    $pattern = (string)($route['pattern'] ?? '');
    $methods = array_map(
        static fn($m): string => strtoupper(trim((string)$m)),
        (array)($route['methods'] ?? [])
    );

    if (in_array('DELETE', $methods, true)) {
        $failures[] = "external_ok route allows DELETE (guests must never delete): {$pattern}";
    }

    // PATCH/PUT is permitted only for marking one's own notifications read/unread, or for
    // the two own-profile self-service writes (name/locale/timezone, interface prefs — both
    // scoped to the actor's own row, never another user's).
    $isNotificationReadToggle = str_contains($pattern, '/notifications/')
        && (str_ends_with($pattern, '/read') || str_ends_with($pattern, '/unread'));
    $isOwnProfileWrite = $pattern === '/api/v1/profile/me' || $pattern === '/api/v1/profile/preferences';
    foreach (['PATCH', 'PUT'] as $writeMethod) {
        if (in_array($writeMethod, $methods, true) && !$isNotificationReadToggle && !$isOwnProfileWrite) {
            $failures[] = "external_ok route allows {$writeMethod} outside the notification "
                . "read/unread toggle or own-profile self-service (guests must not mutate CRM "
                . "records): {$pattern}";
        }
    }

    // Check if the pattern is one of the explicitly allowed chat/knowledge routes
    $isAllowedSpecial = false;
    foreach ($allowedChatKnowledgePatterns as $allowedPattern) {
        if (str_contains($pattern, $allowedPattern)) {
            $isAllowedSpecial = true;
            break;
        }
    }

    foreach ($forbiddenSubstrings as $needle) {
        if (str_contains($pattern, $needle)) {
            // Allow specific chat and knowledge patterns that are explicitly permitted
            if ($isAllowedSpecial && ($needle === '/chat' || $needle === '/knowledge')) {
                continue;
            }
            $failures[] = "external_ok route touches a restricted area ({$needle}): {$pattern}";
        }
    }
}

// ---------------------------------------------------------------------------
// 1a. Executor route allowlist: exact membership + no destructive surface.
// ---------------------------------------------------------------------------
// external_executor_ok is a second, narrower allowlist reachable only by the
// 'executor' guest role (never by 'observer'/client guests) once the app.php
// gate below passes it through. Same exact-set + no-destructive-surface
// discipline as the external_ok allowlist above, so it gets its own review
// trigger instead of silently inheriting whatever the base allowlist permits.

$expectedExecutorRoutes = [
    'GET /api/v1/worklogs',
    'POST /api/v1/worklogs',
    'GET /api/v1/me/earnings',
    'GET /api/v1/me/earnings/available',
];

$actualExecutorRoutes = [];
foreach ($routes as $route) {
    if (($route['external_executor_ok'] ?? false) !== true) {
        continue;
    }
    $pattern = (string)($route['pattern'] ?? '');
    $methods = array_map(
        static fn($m): string => strtoupper(trim((string)$m)),
        (array)($route['methods'] ?? [])
    );
    foreach ($methods as $method) {
        if ($method !== '' && $pattern !== '') {
            $actualExecutorRoutes[] = $method . ' ' . $pattern;
        }
    }

    if (in_array('DELETE', $methods, true)) {
        $failures[] = "external_executor_ok route allows DELETE (executors must never delete "
            . "worklogs): {$pattern}";
    }
    foreach (['PATCH', 'PUT'] as $writeMethod) {
        if (in_array($writeMethod, $methods, true)) {
            $failures[] = "external_executor_ok route allows {$writeMethod} — executors may only "
                . "create/list their own worklogs, never edit or delete existing ones: {$pattern}";
        }
    }
}

sort($expectedExecutorRoutes);
sort($actualExecutorRoutes);

$unexpectedExecutor = array_diff($actualExecutorRoutes, $expectedExecutorRoutes);
$missingExecutor = array_diff($expectedExecutorRoutes, $actualExecutorRoutes);

check(
    $failures,
    $unexpectedExecutor === [],
    'route(s) marked external_executor_ok but NOT in the reviewed allowlist (possible '
    . 'privilege escalation for executor-role guests): ' . implode(', ', $unexpectedExecutor)
);
check(
    $failures,
    $missingExecutor === [],
    'route(s) expected to be external_executor_ok but are not — executor guests will 403 '
    . 'when trying to log time: ' . implode(', ', $missingExecutor)
);

// A route should never carry both flags: external_ok already lets every external
// guest through (observer and executor alike), so pairing it with
// external_executor_ok is dead/misleading — it looks role-gated but isn't.
foreach ($routes as $route) {
    if (($route['external_ok'] ?? false) === true && ($route['external_executor_ok'] ?? false) === true) {
        $pattern = (string)($route['pattern'] ?? '');
        $failures[] = 'route carries both external_ok and external_executor_ok, which is '
            . "redundant and misleading (external_ok already admits every external guest): {$pattern}";
    }
}

// ---------------------------------------------------------------------------
// 1b. Executor project-access management routes stay internal-only.
// ---------------------------------------------------------------------------
// Granting/revoking/listing an executor's per-project grants is a staff action
// performed ABOUT a guest (project.manage/contact.manage); it must never be
// something the guest performs on themselves via the external gate.

$projectAccessRoutes = array_values(array_filter(
    $routes,
    static fn(array $r): bool => str_contains((string)($r['pattern'] ?? ''), '/project-access')
));

check(
    $failures,
    count($projectAccessRoutes) === 3,
    'expected exactly 3 external-users/*/project-access routes (list/grant/revoke); found '
    . count($projectAccessRoutes) . ' — the route table for executor project grants has drifted'
);

foreach ($projectAccessRoutes as $route) {
    $pattern = (string)($route['pattern'] ?? '');
    check(
        $failures,
        ($route['external_ok'] ?? false) !== true && ($route['external_executor_ok'] ?? false) !== true,
        "project-access route must not be externally reachable — it manages a guest's access, "
        . "it is not an action the guest performs on themselves: {$pattern}"
    );
    check(
        $failures,
        !empty($route['required_permissions']),
        "project-access route has no required_permissions: {$pattern}"
    );
}

// ---------------------------------------------------------------------------
// 3. Central enforcement in App::run().
// ---------------------------------------------------------------------------
// The allowlist is inert unless something actually checks it. Assert the gate
// exists, keys off is_external, and denies by default.

$appSource = readFileSafe($appPath);

check(
    $failures,
    str_contains($appSource, "external_ok"),
    'app.php does not reference external_ok — the route allowlist is not enforced anywhere'
);
check(
    $failures,
    str_contains($appSource, 'EXTERNAL_ACCESS_DENIED'),
    'app.php does not emit EXTERNAL_ACCESS_DENIED — external guests are not being gated'
);
check(
    $failures,
    str_contains($appSource, "is_external"),
    'app.php does not read is_external — the external gate cannot trigger'
);
check(
    $failures,
    (bool)preg_match('/external_ok[\'"]\]\s*\?\?\s*false\)\s*===\s*true/', $appSource),
    'app.php external gate must build its allow flag from a strict comparison '
    . "(`=== true`) against external_ok; a truthy/loose check ('!empty', bare boolean) "
    . 'would let unmarked routes through for external guests'
);
check(
    $failures,
    str_contains($appSource, 'if (!$externalGateAllowed)'),
    'app.php external gate must deny by default via `if (!$externalGateAllowed)` — the '
    . 'allow/deny decision must be a single explicit boolean checked once, not scattered '
    . 'ad-hoc truthy checks that could diverge'
);
check(
    $failures,
    str_contains($appSource, 'external_executor_ok'),
    'app.php does not reference external_executor_ok — executor-role guests (freelancers '
    . 'logging time) would 403 on every worklog route, or — if the flag exists in routes.php '
    . 'without being read here — every external guest including observers would reach it'
);
check(
    $failures,
    (bool)preg_match(
        "/external_executor_ok'\\]\\s*\\?\\?\\s*false\\)\\s*===\\s*true[^}]*?external_role'\\]\\s*\\?\\?\\s*'observer'\\)\\s*===\\s*'executor'/s",
        $appSource
    ),
    'app.php external_executor_ok gate does not additionally require external_role === '
    . "'executor' in the same condition — an observer (client) guest could reach "
    . 'executor-only routes such as worklog creation, defeating the role split'
);

if (preg_match("/factory\\('service\\.external_user',.*?\\)\\);/s", $appSource, $extUserFactoryMatch) === 1) {
    $extUserFactoryBlock = $extUserFactoryMatch[0];
    check(
        $failures,
        str_contains($extUserFactoryBlock, 'repository.project'),
        'app.php service.external_user wiring does not provide ProjectRepository — the '
        . 'executor project-grant/revoke object-level authorization check cannot resolve '
        . 'the target project'
    );
    check(
        $failures,
        !str_contains($extUserFactoryBlock, "'service.project'"),
        'app.php service.external_user wiring depends on service.project — ProjectService\'s '
        . 'own factory already depends on service.external_user, so this reintroduces a '
        . 'circular dependency that deadlocks container resolution on first use'
    );
} else {
    $failures[] = 'could not locate the service.external_user factory block in app.php';
}

// Router must forward the flag, or App::run() always sees null.
$routerSource = readFileSafe($routerPath);
check(
    $failures,
    str_contains($routerSource, 'external_ok'),
    'Router::match() does not propagate external_ok — App::run() would see it as unset and '
    . 'deny every route (or, worse, allow them if the check is loosened)'
);

// ---------------------------------------------------------------------------
// 4. is_external must survive the real authentication pipeline.
// ---------------------------------------------------------------------------
// This was a live bug: the column existed and every consumer read
// $actor['is_external'], but the session lookup never SELECTed it, so the flag was
// always absent and every guard silently evaluated to "internal user".

$authRepositorySource = readFileSafe($authRepositoryPath);
check(
    $failures,
    (bool)preg_match('/[\'"]u\.is_external[\'"]/', $authRepositorySource),
    'AuthRepository session lookup does not SELECT u.is_external — is_external would be '
    . 'absent from the runtime actor and EVERY external-guest guard would silently pass'
);

$authServiceSource = readFileSafe($authServicePath);
check(
    $failures,
    substr_count($authServiceSource, 'is_external') >= 2,
    'AuthService must propagate is_external through both me() and normalizeUser(); fewer '
    . 'than two references means the flag is dropped before reaching callers'
);

// ---------------------------------------------------------------------------
// 5. Row-level security must be SQL-side and must compare the right columns.
// ---------------------------------------------------------------------------

$taskServiceSource = readFileSafe($taskServicePath);
$projectServiceSource = readFileSafe($projectServicePath);

check(
    $failures,
    str_contains($taskServiceSource, 'task_client_public_id'),
    'TaskService must compare the task-level counterparty via task_client_public_id; '
    . 'reading client_public_id for both sides (a copy-paste bug fixed here) makes the '
    . 'task-level ownership check a no-op'
);
check(
    $failures,
    str_contains($taskServiceSource, 'is_external'),
    'TaskService does not branch on is_external — external guests would not be scoped'
);
check(
    $failures,
    str_contains($projectServiceSource, 'is_external'),
    'ProjectService does not branch on is_external — external guests would not be scoped'
);

$projectSummaryServicePath = $apiRoot . '/system/library/service/ProjectSummaryService.php';
$projectSummaryServiceSource = readFileSafe($projectSummaryServicePath);
check(
    $failures,
    str_contains($projectSummaryServiceSource, 'is_external'),
    'ProjectSummaryService does not branch on is_external — GET .../summary is external_ok, '
    . 'so without this branch a guest would receive the full per-employee workload.items '
    . '(internal staff names + individual task assignments) straight in the API response'
);
check(
    $failures,
    (bool)preg_match('/is_external.{0,80}workload\s*=\s*\[.{0,40}items.{0,10}=>\s*\[\]/s', $projectSummaryServiceSource),
    'ProjectSummaryService::summary() must overwrite workload with an empty items array '
    . 'for is_external actors, not merely read the flag without using it'
);

// ---------------------------------------------------------------------------
// 6. Seeded role permissions must be codes that actually exist.
// ---------------------------------------------------------------------------
// The original migration seeded invented codes (task.view, project.view, file.view,
// file.upload, task.comment, chat.use, knowledge.view). None exist in the registry,
// so external_guest would have been created with zero effective permissions and the
// whole portal would 403 — while looking correct in the migration source.

$permissionServiceSource = readFileSafe($permissionServicePath);
preg_match_all("/'([a-z_]+\.[a-z_]+)'\s*=>\s*\\\$this->t\(/", $permissionServiceSource, $permMatches);
$knownPermissionCodes = array_values(array_unique($permMatches[1] ?? []));

if ($knownPermissionCodes === []) {
    failSmoke('could not extract the permission registry from PermissionService — the test '
        . 'cannot validate seeded codes (registry format changed?)');
}

$migrationSource = readFileSafe($migrationPath);
if (preg_match('/\$wantedPermissionCodes\s*=\s*\[(.*?)\];/s', $migrationSource, $seedBlock) !== 1) {
    failSmoke('could not locate $wantedPermissionCodes in ExternalUsersMigration');
}
preg_match_all("/'([^']+)'/", $seedBlock[1], $seedMatches);
$seededCodes = array_values(array_unique($seedMatches[1] ?? []));

check(
    $failures,
    $seededCodes !== [],
    'ExternalUsersMigration seeds no permission codes at all — external_guest would be '
    . 'created without any access'
);

foreach ($seededCodes as $code) {
    check(
        $failures,
        in_array($code, $knownPermissionCodes, true),
        "ExternalUsersMigration seeds permission code '{$code}', which does not exist in the "
        . 'PermissionService registry — the external_guest role would silently receive no '
        . 'such permission'
    );
}

// Seeding a broad admin-ish code to an untrusted guest role is never right.
$neverSeedForGuests = [
    'user.manage', 'role.manage', 'settings.manage', 'logs.view', 'api_client.manage',
    'webhook.manage', 'import.manage', 'export.manage', 'recycle_bin.manage',
    'feature_flag.manage', 'approval.manage',
];
foreach ($seededCodes as $code) {
    check(
        $failures,
        !in_array($code, $neverSeedForGuests, true),
        "ExternalUsersMigration seeds '{$code}' to the external_guest role — this grants "
        . 'administrative capability to untrusted portal users'
    );
}

// ---------------------------------------------------------------------------
// 6a. List scoping must FAIL CLOSED.
// ---------------------------------------------------------------------------
// The scoping filter used to be applied only when the counterparty resolved:
//   if ($cpPublicId !== '') { $filters['client_public_id'] = $cpPublicId; }
// A guest whose contact link was missing or broken therefore ran an UNSCOPED
// query and received every counterparty's rows. Scoping must instead deny.

foreach ([
    'ProjectService' => $projectServicePath,
    'TaskService' => $taskServicePath,
] as $label => $path) {
    $source = readFileSafe($path);

    $failOpenNeedle = 'if ($cpPublicId !== \'\') {';
    check(
        $failures,
        !str_contains($source, $failOpenNeedle),
        "{$label}::list() applies the counterparty filter only when it resolves — an "
        . 'external actor with an unresolvable counterparty would run an UNSCOPED query '
        . 'and receive every tenant rows. It must return an empty result instead.'
    );
    check(
        $failures,
        str_contains($source, 'emptyListResult'),
        "{$label} has no empty-result path for unscopable external actors (fail-closed "
        . 'listing is missing)'
    );
    $serviceGatedNeedle = '(int)($actor[\'is_external\'] ?? 0)) && $this->externalUsers';
    check(
        $failures,
        !str_contains($source, $serviceGatedNeedle),
        "{$label} gates the external branch on the ExternalUserService being present; if it "
        . 'is not wired the guest falls through to the internal ownership checks and is '
        . 'judged as an employee. Deny instead.'
    );
}

// ---------------------------------------------------------------------------
// 6a2. Executor project scoping must ALSO fail closed.
// ---------------------------------------------------------------------------
// An executor with zero project grants must see zero rows. The filter is
// gated on array_key_exists (not !empty/isset-on-value) so the key's mere
// PRESENCE — even with an empty id list — triggers the RLS bypass; otherwise
// a zero-grant executor would fall through to the internal ownership check
// (created_by/manager/team) and be treated like an unscoped employee.

$projectRepositorySource = readFileSafe($apiRoot . '/model/project/ProjectRepository.php');
$taskRepositorySource = readFileSafe($apiRoot . '/model/task/TaskRepository.php');

foreach ([
    'ProjectRepository' => $projectRepositorySource,
    'TaskRepository' => $taskRepositorySource,
] as $label => $source) {
    check(
        $failures,
        (bool)preg_match('/if\s*\(\s*array_key_exists\(\s*[\'"]executor_project_ids[\'"]/', $source),
        "{$label} does not gate the executor filter block itself on "
        . "if (array_key_exists('executor_project_ids', ...)) — !empty()/isset() would let an "
        . 'executor with zero grants fall through to the unscoped/internal-ownership query path '
        . 'instead of seeing zero rows'
    );
    check(
        $failures,
        (bool)preg_match(
            "/executor_project_ids.*?ids\\s*===\\s*\\[\\]\\s*\\)\\s*\\{\\s*\\\$qb->whereRaw\\(\\s*'1\\s*=\\s*0'/s",
            $source
        ),
        "{$label} does not force a zero-row result ('1 = 0') when an executor has no project "
        . 'grants — it must fail closed, never run an unscoped query'
    );
    check(
        $failures,
        (bool)preg_match('/hasRlsClientFilter\s*=.*executor_project_ids/', $source),
        "{$label} does not extend its internal-ownership bypass (hasRlsClientFilter) to also "
        . 'trigger on executor_project_ids — an executor would additionally be judged by the '
        . 'created_by/manager/team ownership check meant for internal employees'
    );
}

// ---------------------------------------------------------------------------
// 6d. Invitation lifecycle and object-level management authorization.
// ---------------------------------------------------------------------------

$externalUserServiceSource = readFileSafe($externalUserServicePath);
$externalInvitationMigrationSource = readFileSafe($externalInvitationMigrationPath);
$userRepositorySource = readFileSafe($userRepositoryPath);
$migrationManagerSource = readFileSafe($migrationManagerPath);
check(
    $failures,
    str_contains($externalUserServiceSource, 'contactService->get'),
    'ExternalUserService must resolve invite/deactivate contacts through ContactService '
    . 'so contact.manage cannot operate on another hierarchy\'s records'
);
check(
    $failures,
    str_contains($externalUserServiceSource, 'external_invitation_expires_at'),
    'ExternalUserService does not persist/clear invitation expiry metadata'
);
check(
    $failures,
    str_contains($externalUserServiceSource, 'password_too_long'),
    'ExternalUserService does not cap invitation password input before hashing'
);
check(
    $failures,
    str_contains($externalInvitationMigrationSource, 'external_invitation_expires_at'),
    'ExternalInvitationLifecycleMigration does not add the invitation expiry column'
);
check(
    $failures,
    str_contains($userRepositorySource, 'external_invitation_expires_at'),
    'UserRepository does not explicitly select invitation expiry metadata'
);
check(
    $failures,
    str_contains($userRepositorySource, 'activateExternalInvitation'),
    'UserRepository has no atomic invitation-consume operation — concurrent accepts could '
    . 'activate the same one-time token more than once'
);
check(
    $failures,
    str_contains($externalUserServiceSource, 'revokeAllByUserId'),
    'ExternalUserService does not revoke all sessions on deactivate/resend — an old session '
    . 'could become valid again after a later reactivation'
);
check(
    $failures,
    str_contains($authRepositorySource, 'revokeAllByUserId'),
    'AuthRepository has no user-wide session revocation operation'
);
check(
    $failures,
    str_contains($migrationManagerSource, 'new ExternalInvitationLifecycleMigration()'),
    'MigrationManager does not register ExternalInvitationLifecycleMigration'
);
check(
    $failures,
    str_contains($migrationManagerSource, 'new ExternalUserRolesMigration()'),
    'MigrationManager does not register ExternalUserRolesMigration — the external_role '
    . 'column and external_user_project_access table would never be created'
);
$externalUserControllerSource = readFileSafe($externalUserControllerPath);
$contactRepositorySource = readFileSafe($contactRepositoryPath);
$contactServiceSource = readFileSafe($contactServicePath);
$counterpartyServiceSource = readFileSafe($counterpartyServicePath);
$portalBindingsSource = readFileSafe($portalBindingsPath);
$apiClientJsSource = readFileSafe($apiClientJsPath);
check(
    $failures,
    str_contains($externalUserControllerSource, 'withIdempotency'),
    'ExternalUserController does not protect invite/revoke retries with the shared '
    . 'idempotency layer'
);
check(
    $failures,
    str_contains($externalUserServiceSource, 'findByIdForUpdate'),
    'ExternalUserService does not lock the contact row before checking/creating the '
    . 'linked account — concurrent invites could create duplicate guest accounts'
);
check(
    $failures,
    str_contains($contactRepositorySource, 'findByIdForUpdate'),
    'ContactRepository has no portable row-locking lookup for external invitation lifecycle'
);
check(
    $failures,
    str_contains($contactServiceSource, 'revokeLinkedExternalUser'),
    'ContactService does not revoke a linked guest when a contact is deleted or moved '
    . 'between counterparties'
);
check(
    $failures,
    str_contains($contactRepositorySource, 'findByCounterpartyId'),
    'ContactRepository cannot enumerate contacts for counterparty lifecycle cleanup'
);
check(
    $failures,
    str_contains($contactServiceSource, 'revokeExternalUsersForCounterparty'),
    'ContactService does not expose counterparty-wide external session cleanup'
);
check(
    $failures,
    str_contains($counterpartyServiceSource, 'revokeExternalUsersForCounterparty'),
    'CounterpartyService does not revoke linked external users before counterparty deletion'
);
check(
    $failures,
    (bool)preg_match("/factory\\('service\\.counterparty'.*?'service\\.contact'/s", $appSource),
    'App service wiring does not provide ContactService to the counterparty lifecycle service'
);
check(
    $failures,
    str_contains($contactRepositorySource, "whereNull('eu.deleted_at')"),
    'ContactRepository portal status query includes soft-deleted guest accounts, leaving '
    . 'the UI stuck on a non-actionable pending state'
);
check(
    $failures,
    str_contains($externalUserServiceSource, 'soft-deleted external account'),
    'ExternalUserService does not explicitly retire a soft-deleted linked guest before '
    . 'creating a fresh invitation'
);
check(
    $failures,
    str_contains($portalBindingsSource, 'external-revoke-'),
    'Portal UI does not send an idempotency key when revoking external access'
);
check(
    $failures,
    str_contains($apiClientJsSource, 'authReferenceCacheScope')
        && str_contains($apiClientJsSource, '|auth_scope='),
    'Web API reference cache is not bound to the current auth credential scope — '
    . 'auth/me could return a previous user after login/token switching'
);
check(
    $failures,
    str_contains($externalUserServiceSource, 'contactService->list'),
    'ExternalUserService::listExternalUsers() does not scope non-root results through '
    . 'the contacts access policy'
);
check(
    $failures,
    str_contains($externalUserServiceSource, 'u.deleted_at IS NULL'),
    'ExternalUserService::listExternalUsers() does not exclude soft-deleted external '
    . 'accounts'
);
check(
    $failures,
    str_contains($externalUserServiceSource, "['deleted_at'] ?? null"),
    'ExternalUserService does not fail closed for soft-deleted linked external accounts'
);

// ---------------------------------------------------------------------------
// 6e. Invite login must be the invited email, checked against the login namespace.
// ---------------------------------------------------------------------------
// A generated ext_xxxx login is unguessable but forces the guest to remember a
// credential different from what they were invited with, and support/reset
// flows key off email. The email must also be checked against the existing
// LOGIN namespace (not only the email column) before being reused as a login,
// or an invite could collide with an unrelated account.

check(
    $failures,
    str_contains($externalUserServiceSource, 'login_email_conflict'),
    'ExternalUserService::invite() does not check the invited email against the existing '
    . "login namespace before reusing it as the login — a collision with an unrelated "
    . "account's login could attach the invite to the wrong account or fail confusingly "
    . 'at authentication time'
);
check(
    $failures,
    !str_contains($externalUserServiceSource, "'ext_' . substr("),
    'ExternalUserService::invite() still generates a synthetic ext_xxxx login instead of '
    . "using the invited email as the login"
);

// ---------------------------------------------------------------------------
// 6f. ExternalUserService must not depend on ProjectService (circular DI).
// ---------------------------------------------------------------------------
// ProjectService's own factory already depends on service.external_user (to scope
// project listings for external actors). The Container caches a factory's result
// only after the factory closure fully returns, so wiring ProjectService back into
// ExternalUserService's constructor would deadlock the very first resolution of
// either service (infinite recursion), not fail loudly at boot. Use
// ProjectRepository for the executor grant/revoke object-level check instead.

check(
    $failures,
    !(bool)preg_match('/ProjectService\s*\$/', $externalUserServiceSource)
        && !str_contains($externalUserServiceSource, 'Service\\ProjectService;'),
    'ExternalUserService depends on ProjectService — this creates a circular dependency '
    . "with ProjectService's own factory (which depends on service.external_user) and "
    . 'will deadlock container resolution on first use'
);
check(
    $failures,
    str_contains($externalUserServiceSource, 'ProjectRepository'),
    'ExternalUserService does not use ProjectRepository for its executor project-grant/'
    . 'revoke object-level authorization'
);

// ---------------------------------------------------------------------------
// 6c. Notifications must not echo internal comments to guests.
// ---------------------------------------------------------------------------
// Notification bodies embed an excerpt of the comment. A guest legitimately
// becomes a recipient (commenting on their own task makes them a thread
// participant; creating a task makes them a stakeholder), so an internal-only
// comment would reach them through the notification feed even though the
// comment list itself filters it out.

$notificationServiceSource = readFileSafe($notificationServicePath);
check(
    $failures,
    str_contains($notificationServiceSource, 'excludeExternalRecipients'),
    'NotificationService does not filter external recipients — an internal-visibility '
    . 'comment excerpt would be delivered to client-portal users via notifications, '
    . 'bypassing the comment visibility filter'
);
check(
    $failures,
    str_contains($notificationServiceSource, "=== 'client'"),
    'NotificationService does not distinguish client-visible comments when choosing '
    . 'recipients'
);

// ---------------------------------------------------------------------------
// 6b. Attachments and comments must be counterparty-scoped too.
// ---------------------------------------------------------------------------
// Opening the file and comment routes to guests is only safe if those services
// understand external actors. FileService resolved access purely through internal
// relationships (creator / assignee / manager / team member) — an external guest is
// none of those, so attachments on their own tasks were unreachable. CommentService
// returned every comment on a task the guest could see, including `internal` ones.

$fileServiceSource = readFileSafe($fileServicePath);
check(
    $failures,
    str_contains($fileServiceSource, 'is_external'),
    'FileService does not branch on is_external — attachment access is decided purely by '
    . 'internal relationships (creator/assignee/manager/team member), which an external '
    . 'guest never has, so files on their own tasks would be unreachable'
);
check(
    $failures,
    str_contains($fileServiceSource, 'getCounterpartyPublicId'),
    'FileService does not resolve the actor counterparty — external file access cannot be '
    . 'scoped without it'
);

$commentServiceSource = readFileSafe($commentServicePath);
$commentRepositorySource = readFileSafe($commentRepositoryPath);
check(
    $failures,
    str_contains($commentServiceSource, 'is_external'),
    'CommentService does not branch on is_external — internal-visibility comments on a task '
    . 'would be served to client-portal guests'
);
check(
    $failures,
    str_contains($commentRepositorySource, 'clientVisibleOnly'),
    'CommentRepository has no client-visible-only mode — comment visibility filtering for '
    . 'guests would have to happen after fetch, which breaks pagination totals and is the '
    . 'PHP-array filtering this project explicitly avoids'
);
check(
    $failures,
    (bool)preg_match("/where\(\s*'c\.visibility'\s*,\s*'='\s*,\s*'client'\s*\)/", $commentRepositorySource),
    'CommentRepository must filter visibility in SQL (c.visibility = client) for guests'
);

// ---------------------------------------------------------------------------
// 7. Interface isolation (nav + page shells).
// ---------------------------------------------------------------------------

$menuControllerSource = readFileSafe($menuControllerPath);
check(
    $failures,
    str_contains($menuControllerSource, 'is_external'),
    'MenuController does not branch on is_external — the guest would receive the full '
    . 'internal navigation menu'
);

$webIndexSource = readFileSafe($webIndexPath);
check(
    $failures,
    str_contains($webIndexSource, 'is_external') || str_contains($webIndexSource, 'IsExternalUser'),
    'web/index.php has no external-user page gate — guests could open internal page shells '
    . 'directly by URL even though the API would refuse the data'
);
check(
    $failures,
    str_contains($webIndexSource, 'external-accept'),
    'web/index.php does not expose the public external-accept route — invited portal users '
    . 'would be redirected to login and could never set a password'
);

// Dynamic portal actions use the browser i18n dictionary, not only server-rendered
// template text. Keep both namespaces in the shared client payload and verify that
// every supported web locale actually provides them; otherwise JavaScript silently
// falls back to English.
$webControllerSource = readFileSafe($webControllerPath);
if (preg_match('/CLIENT_MESSAGE_NAMESPACES\\s*=\\s*\\[(.*?)\\];/s', $webControllerSource, $namespaceMatch) !== 1) {
    $failures[] = 'Core\\\\Controller client message namespace registry could not be parsed';
} else {
    $namespaceBlock = $namespaceMatch[1];
    check(
        $failures,
        str_contains($namespaceBlock, "'external_users'")
            && str_contains($namespaceBlock, "'external_accept'"),
        'Core\\\\Controller::CLIENT_MESSAGE_NAMESPACES must include external_users and '
        . 'external_accept so portal JavaScript receives the selected locale dictionary'
    );
}

$webLocales = ['ru-ru', 'en-gb', 'zh-cn', 'es-es', 'pt-br', 'de-de', 'fr-fr'];
$referencePortalKeys = null;
foreach ($webLocales as $webLocale) {
    $localePath = $webLanguageRoot . '/' . $webLocale . '.php';
    $messages = is_file($localePath) ? require $localePath : null;
    check(
        $failures,
        is_array($messages),
        "web locale {$webLocale} is missing or does not return an array"
    );
    if (!is_array($messages)) {
        continue;
    }
    foreach (['external_users', 'external_accept'] as $namespace) {
        $namespaceMessages = $messages[$namespace] ?? null;
        check(
            $failures,
            is_array($namespaceMessages),
            "web locale {$webLocale} is missing the {$namespace} namespace"
        );
        if (!is_array($namespaceMessages)) {
            continue;
        }
        if ($referencePortalKeys === null) {
            $referencePortalKeys = [];
        }
        $keys = array_keys($namespaceMessages);
        sort($keys);
        if (!isset($referencePortalKeys[$namespace])) {
            $referencePortalKeys[$namespace] = $keys;
        } else {
            check(
                $failures,
                $referencePortalKeys[$namespace] === $keys,
                "web locale {$webLocale} {$namespace} keys differ from the reference locale"
            );
        }
    }
}

// ---------------------------------------------------------------------------
// 8. Allowed pages must not render internal-only blocks for guests.
// ---------------------------------------------------------------------------
// A guest may legitimately open task-detail and project-detail. Those templates
// also contain worklogs, estimates, activity, subtasks, AI, modules, knowledge
// and the team roster. Their JS initialises from the DOM, so rendering those
// containers fires a burst of requests the API answers with 403 and leaves dead
// widgets on the page. The flag is resolved once in web/index.php and exposed to
// templates by Core\\Controller::render().

$taskDetailTemplatePath = $webRoot . '/view/template/page/task_detail.php';
$projectDetailTemplatePath = $webRoot . '/view/template/page/project_detail.php';

check(
    $failures,
    str_contains($webIndexSource, 'crm_is_external_user'),
    'web/index.php does not publish the external-user flag for templates'
);
check(
    $failures,
    str_contains(readFileSafe($webControllerPath), 'is_external_user'),
    'Core\\Controller::render() does not expose is_external_user, so templates cannot '
    . 'gate internal-only blocks'
);

foreach ([
    'task_detail' => $taskDetailTemplatePath,
    'project_detail' => $projectDetailTemplatePath,
] as $label => $templatePath) {
    check(
        $failures,
        str_contains(readFileSafe($templatePath), 'is_external_user'),
        "{$label}.php renders every internal block unconditionally — a client-portal user "
        . 'would trigger a burst of 403s and see dead widgets on a page they are allowed '
        . 'to open'
    );
}

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL] external_users_security_check: {$failure}\n");
    }
    fwrite(STDERR, '=== Results: 0 passed, ' . count($failures) . " failed ===\n");
    exit(1);
}

fwrite(STDOUT, "[OK] external_users_security_check\n");
