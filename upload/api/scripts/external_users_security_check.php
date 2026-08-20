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
$externalInvitationMigrationPath = $apiRoot . '/system/library/database/migration/ExternalInvitationLifecycleMigration.php';
$userRepositoryPath = $apiRoot . '/model/common/UserRepository.php';
$webIndexPath = $webRoot . '/index.php';

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
    '/admin/', '/chat', '/knowledge', '/users', '/roles', '/permissions', '/settings',
    '/webhooks', '/api-clients', '/import', '/export', '/recycle-bin', '/teams',
    '/departments', '/companies', '/clients', '/counterparties', '/contacts',
    '/external-users', '/analytics', '/worklogs', '/approvals', '/modules',
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

    // PATCH/PUT is permitted only for marking one's own notifications read/unread.
    $isNotificationReadToggle = str_contains($pattern, '/notifications/')
        && (str_ends_with($pattern, '/read') || str_ends_with($pattern, '/unread'));
    foreach (['PATCH', 'PUT'] as $writeMethod) {
        if (in_array($writeMethod, $methods, true) && !$isNotificationReadToggle) {
            $failures[] = "external_ok route allows {$writeMethod} outside the notification "
                . "read/unread toggle (guests must not mutate CRM records): {$pattern}";
        }
    }

    foreach ($forbiddenSubstrings as $needle) {
        if (str_contains($pattern, $needle)) {
            $failures[] = "external_ok route touches a restricted area ({$needle}): {$pattern}";
        }
    }
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
    (bool)preg_match('/external_ok[^;]*!==\s*true/', $appSource),
    'app.php external gate must deny by default (`!== true`); a truthy/loose check would '
    . 'let unmarked routes through for external guests'
);

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
    str_contains($externalUserServiceSource, 'contactService->list'),
    'ExternalUserService::listExternalUsers() does not scope non-root results through '
    . 'the contacts access policy'
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

// ---------------------------------------------------------------------------
// 8. Allowed pages must not render internal-only blocks for guests.
// ---------------------------------------------------------------------------
// A guest may legitimately open task-detail and project-detail. Those templates
// also contain worklogs, estimates, activity, subtasks, AI, modules, knowledge
// and the team roster. Their JS initialises from the DOM, so rendering those
// containers fires a burst of requests the API answers with 403 and leaves dead
// widgets on the page. The flag is resolved once in web/index.php and exposed to
// templates by Core\\Controller::render().

$webControllerPath = $webRoot . '/system/Core/Controller.php';
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
