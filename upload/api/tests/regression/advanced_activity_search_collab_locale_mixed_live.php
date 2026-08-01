<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicCollab(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicCollab($v, $context . '.' . (string)$k);
        }
    }
}

/**
 * @param array<string,string> $headers
 * @return array{status:int,headers:array<int,string>,body:string,payload:array<string,mixed>}
 */
function liveRequestQuery(string $routeWithQuery, array $headers = []): array
{
    $url = LIVE_API_BASE . '?route=' . $routeWithQuery;

    $headerLines = [
        'Accept: application/json',
    ];

    foreach ($headers as $name => $value) {
        $headerLines[] = $name . ': ' . $value;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headerLines),
            'ignore_errors' => true,
            'timeout' => 20,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header;
    if (!is_string($body)) {
        $body = '';
    }

    $status = 0;
    if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $m)) {
        $status = (int)$m[1];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        $decoded = [];
    }

    return [
        'status' => $status,
        'headers' => $responseHeaders,
        'body' => $body,
        'payload' => $decoded,
    ];
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'asc_locale_' . $suffix,
        'title' => 'ASC Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => [
            'project.manage',
            'task.manage',
            'company.manage',
            'client.manage',
            'logs.view',
        ],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'asc_locale_' . $suffix;
    $token = 'asc-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'AscLocale123!',
        'token' => $token,
        'email' => $login . '@crm.local',
        'locale' => 'en-gb',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($userCreate['status'] === 201, 'User create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($userPublicId !== '', 'User public_id is required');

    $userLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'AscLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $companyCreate = liveRequest('POST', 'api/v1/companies', [
        'title' => 'ASC Company ' . $suffix,
    ], $headers);
    liveAssert($companyCreate['status'] === 201, 'Company create must return 201');
    $companyPublicId = (string)($companyCreate['payload']['data']['company']['public_id'] ?? '');
    liveAssert($companyPublicId !== '', 'Company public_id is required');

    $clientCreate = liveRequest('POST', 'api/v1/clients', [
        'title' => 'ASC Client ' . $suffix,
        'company_public_id' => $companyPublicId,
    ], $headers);
    liveAssert($clientCreate['status'] === 201, 'Client create must return 201');
    $clientPublicId = (string)($clientCreate['payload']['data']['client']['public_id'] ?? '');
    liveAssert($clientPublicId !== '', 'Client public_id is required');

    $projectCreate = liveRequest('POST', 'api/v1/projects', [
        'title' => 'ASC Project ' . $suffix,
        'client_public_id' => $clientPublicId,
    ], $headers);
    liveAssert($projectCreate['status'] === 201, 'Project create must return 201');
    $projectPublicId = (string)($projectCreate['payload']['data']['project']['public_id'] ?? '');
    liveAssert($projectPublicId !== '', 'Project public_id is required');

    $taskCreate = liveRequest('POST', 'api/v1/tasks', [
        'project_public_id' => $projectPublicId,
        'title' => 'ASC Task ' . $suffix,
    ], $headers);
    liveAssert($taskCreate['status'] === 201, 'Task create must return 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    liveAssert($taskPublicId !== '', 'Task public_id is required');

    $commentCreate = liveRequest('POST', 'api/v1/tasks/' . $taskPublicId . '/comments', [
        'body' => 'ASC comment ' . $suffix,
    ], $headers);
    liveAssert($commentCreate['status'] === 201, 'Comment create must return 201');

    $activityFeed = liveRequest('GET', 'api/v1/activity/feed', [], $headers);
    liveAssert($activityFeed['status'] === 200, 'Activity feed must return 200');
    liveAssert((string)($activityFeed['payload']['message'] ?? '') === 'Activity feed', 'Activity feed message mismatch');

    $entityHistory = liveRequest('GET', 'api/v1/history/entity/task/' . $taskPublicId, [], $headers);
    liveAssert($entityHistory['status'] === 200, 'Entity history must return 200');
    liveAssert((string)($entityHistory['payload']['message'] ?? '') === 'Entity history', 'Entity history message mismatch');

    $entityHistoryValidation = liveRequestQuery('api/v1/history/entity', $headers);
    liveAssert($entityHistoryValidation['status'] === 422, 'Entity history validation must return 422');
    liveAssert((string)($entityHistoryValidation['payload']['message'] ?? '') === 'Validation error', 'Entity history validation message mismatch');
    assertNoCyrillicCollab($entityHistoryValidation['payload']['errors'] ?? [], 'activity.history.validation.errors');

    $auditList = liveRequest('GET', 'api/v1/audit/list', [], $headers);
    liveAssert($auditList['status'] === 200, 'Audit list must return 200');
    liveAssert((string)($auditList['payload']['message'] ?? '') === 'Audit log', 'Audit list message mismatch');

    $auditByUser = liveRequest('GET', 'api/v1/audit/user/' . $userPublicId, [], $headers);
    liveAssert($auditByUser['status'] === 200, 'Audit by user must return 200');
    liveAssert((string)($auditByUser['payload']['message'] ?? '') === 'Audit by user', 'Audit by user message mismatch');

    $auditByEntity = liveRequest('GET', 'api/v1/audit/entity/task/' . $taskPublicId, [], $headers);
    liveAssert($auditByEntity['status'] === 200, 'Audit by entity must return 200');
    liveAssert((string)($auditByEntity['payload']['message'] ?? '') === 'Audit by entity', 'Audit by entity message mismatch');

    $searchValidation = liveRequestQuery('api/v1/search/global&q=a', $headers);
    liveAssert($searchValidation['status'] === 422, 'Search validation must return 422');
    liveAssert((string)($searchValidation['payload']['message'] ?? '') === 'Validation error', 'Search validation message mismatch');
    assertNoCyrillicCollab($searchValidation['payload']['errors'] ?? [], 'search.validation.errors');

    $searchGlobal = liveRequestQuery('api/v1/search/global&q=' . rawurlencode($suffix), $headers);
    liveAssert($searchGlobal['status'] === 200, 'Global search must return 200');
    liveAssert((string)($searchGlobal['payload']['message'] ?? '') === 'Global search', 'Global search message mismatch');

    $searchTasks = liveRequestQuery('api/v1/search/tasks&q=' . rawurlencode($suffix), $headers);
    liveAssert($searchTasks['status'] === 200, 'Task search must return 200');
    liveAssert((string)($searchTasks['payload']['message'] ?? '') === 'Search tasks', 'Task search message mismatch');

    $searchProjects = liveRequestQuery('api/v1/search/projects&q=' . rawurlencode($suffix), $headers);
    liveAssert($searchProjects['status'] === 200, 'Project search must return 200');
    liveAssert((string)($searchProjects['payload']['message'] ?? '') === 'Search projects', 'Project search message mismatch');

    $searchClients = liveRequestQuery('api/v1/search/clients&q=' . rawurlencode($suffix), $headers);
    liveAssert($searchClients['status'] === 200, 'Client search must return 200');
    liveAssert((string)($searchClients['payload']['message'] ?? '') === 'Search clients', 'Client search message mismatch');

    $searchSuggestions = liveRequestQuery('api/v1/search/suggestions&q=' . rawurlencode($suffix), $headers);
    liveAssert($searchSuggestions['status'] === 200, 'Search suggestions must return 200');
    liveAssert((string)($searchSuggestions['payload']['message'] ?? '') === 'Search suggestions', 'Search suggestions message mismatch');

    $mentionValidation = liveRequest('POST', 'api/v1/mentions', [
        'entity_type' => 'invalid',
    ], $headers);
    liveAssert($mentionValidation['status'] === 422, 'Mention validation must return 422');
    liveAssert((string)($mentionValidation['payload']['message'] ?? '') === 'Validation error', 'Mention validation message mismatch');
    assertNoCyrillicCollab($mentionValidation['payload']['errors'] ?? [], 'mention.validation.errors');

    $mentionCreate = liveRequest('POST', 'api/v1/mentions', [
        'entity_type' => 'task',
        'entity_public_id' => $taskPublicId,
        'mentioned_user_public_id' => $userPublicId,
    ], $headers);
    liveAssert($mentionCreate['status'] === 201, 'Mention create must return 201');
    liveAssert((string)($mentionCreate['payload']['message'] ?? '') === 'Mention added', 'Mention create message mismatch');
    $mentionPublicId = (string)($mentionCreate['payload']['data']['mention']['public_id'] ?? '');
    liveAssert($mentionPublicId !== '', 'Mention public_id is required');

    $mentionList = liveRequest('GET', 'api/v1/mentions', [], $headers);
    liveAssert($mentionList['status'] === 200, 'Mention list must return 200');
    liveAssert((string)($mentionList['payload']['message'] ?? '') === 'Mentions list', 'Mention list message mismatch');

    $reactionValidation = liveRequest('POST', 'api/v1/reactions', [
        'entity_type' => 'task',
        'entity_public_id' => $taskPublicId,
        'reaction' => 'invalid',
    ], $headers);
    liveAssert($reactionValidation['status'] === 422, 'Reaction validation must return 422');
    liveAssert((string)($reactionValidation['payload']['message'] ?? '') === 'Validation error', 'Reaction validation message mismatch');
    assertNoCyrillicCollab($reactionValidation['payload']['errors'] ?? [], 'reaction.validation.errors');

    $reactionCreate = liveRequest('POST', 'api/v1/reactions', [
        'entity_type' => 'task',
        'entity_public_id' => $taskPublicId,
        'reaction' => 'like',
    ], $headers);
    liveAssert($reactionCreate['status'] === 201, 'Reaction create must return 201');
    liveAssert((string)($reactionCreate['payload']['message'] ?? '') === 'Reaction added', 'Reaction create message mismatch');
    $reactionPublicId = (string)($reactionCreate['payload']['data']['reaction']['public_id'] ?? '');
    liveAssert($reactionPublicId !== '', 'Reaction public_id is required');

    $reactionList = liveRequest('GET', 'api/v1/reactions', [], $headers);
    liveAssert($reactionList['status'] === 200, 'Reaction list must return 200');
    liveAssert((string)($reactionList['payload']['message'] ?? '') === 'Reactions list', 'Reaction list message mismatch');

    $subscriptionValidation = liveRequest('POST', 'api/v1/subscriptions', [
        'entity_type' => 'invalid',
    ], $headers);
    liveAssert($subscriptionValidation['status'] === 422, 'Subscription validation must return 422');
    liveAssert((string)($subscriptionValidation['payload']['message'] ?? '') === 'Validation error', 'Subscription validation message mismatch');
    assertNoCyrillicCollab($subscriptionValidation['payload']['errors'] ?? [], 'subscription.validation.errors');

    $subscriptionCreate = liveRequest('POST', 'api/v1/subscriptions', [
        'entity_type' => 'task',
        'entity_public_id' => $taskPublicId,
    ], $headers);
    liveAssert($subscriptionCreate['status'] === 201, 'Subscription create must return 201');
    liveAssert((string)($subscriptionCreate['payload']['message'] ?? '') === 'Subscription created', 'Subscription create message mismatch');
    $subscriptionPublicId = (string)($subscriptionCreate['payload']['data']['subscription']['public_id'] ?? '');
    liveAssert($subscriptionPublicId !== '', 'Subscription public_id is required');

    $subscriptionList = liveRequest('GET', 'api/v1/subscriptions', [], $headers);
    liveAssert($subscriptionList['status'] === 200, 'Subscription list must return 200');
    liveAssert((string)($subscriptionList['payload']['message'] ?? '') === 'Subscriptions list', 'Subscription list message mismatch');

    $favoriteValidation = liveRequest('POST', 'api/v1/favorites', [
        'entity_type' => 'invalid',
    ], $headers);
    liveAssert($favoriteValidation['status'] === 422, 'Favorite validation must return 422');
    liveAssert((string)($favoriteValidation['payload']['message'] ?? '') === 'Validation error', 'Favorite validation message mismatch');
    assertNoCyrillicCollab($favoriteValidation['payload']['errors'] ?? [], 'favorite.validation.errors');

    $favoriteCreate = liveRequest('POST', 'api/v1/favorites', [
        'entity_type' => 'task',
        'entity_public_id' => $taskPublicId,
    ], $headers);
    liveAssert($favoriteCreate['status'] === 201, 'Favorite create must return 201');
    liveAssert((string)($favoriteCreate['payload']['message'] ?? '') === 'Added to favorites', 'Favorite create message mismatch');
    $favoritePublicId = (string)($favoriteCreate['payload']['data']['favorite']['public_id'] ?? '');
    liveAssert($favoritePublicId !== '', 'Favorite public_id is required');

    $favoriteList = liveRequest('GET', 'api/v1/favorites', [], $headers);
    liveAssert($favoriteList['status'] === 200, 'Favorite list must return 200');
    liveAssert((string)($favoriteList['payload']['message'] ?? '') === 'Favorites list', 'Favorite list message mismatch');

    $viewValidation = liveRequest('POST', 'api/v1/views', [
        'entity_type' => 'invalid',
    ], $headers);
    liveAssert($viewValidation['status'] === 422, 'View validation must return 422');
    liveAssert((string)($viewValidation['payload']['message'] ?? '') === 'Validation error', 'View validation message mismatch');
    assertNoCyrillicCollab($viewValidation['payload']['errors'] ?? [], 'view.validation.errors');

    $viewCreate = liveRequest('POST', 'api/v1/views', [
        'entity_type' => 'task',
        'title' => 'ASC View ' . $suffix,
        'filters' => ['search' => $suffix],
    ], $headers);
    liveAssert($viewCreate['status'] === 201, 'View create must return 201');
    liveAssert((string)($viewCreate['payload']['message'] ?? '') === 'View saved', 'View create message mismatch');
    $viewPublicId = (string)($viewCreate['payload']['data']['view']['public_id'] ?? '');
    liveAssert($viewPublicId !== '', 'View public_id is required');

    $viewList = liveRequest('GET', 'api/v1/views', [], $headers);
    liveAssert($viewList['status'] === 200, 'View list must return 200');
    liveAssert((string)($viewList['payload']['message'] ?? '') === 'Saved views list', 'View list message mismatch');

    $viewUpdate = liveRequest('PATCH', 'api/v1/views/' . $viewPublicId, [
        'title' => 'ASC View Updated ' . $suffix,
    ], $headers);
    liveAssert($viewUpdate['status'] === 200, 'View update must return 200');
    liveAssert((string)($viewUpdate['payload']['message'] ?? '') === 'View updated', 'View update message mismatch');

    $mentionDelete = liveRequest('DELETE', 'api/v1/mentions/' . $mentionPublicId, [], $headers);
    liveAssert($mentionDelete['status'] === 200, 'Mention delete must return 200');
    liveAssert((string)($mentionDelete['payload']['message'] ?? '') === 'Mention removed', 'Mention delete message mismatch');

    $reactionDelete = liveRequest('DELETE', 'api/v1/reactions/' . $reactionPublicId, [], $headers);
    liveAssert($reactionDelete['status'] === 200, 'Reaction delete must return 200');
    liveAssert((string)($reactionDelete['payload']['message'] ?? '') === 'Reaction removed', 'Reaction delete message mismatch');

    $subscriptionDelete = liveRequest('DELETE', 'api/v1/subscriptions/' . $subscriptionPublicId, [], $headers);
    liveAssert($subscriptionDelete['status'] === 200, 'Subscription delete must return 200');
    liveAssert((string)($subscriptionDelete['payload']['message'] ?? '') === 'Subscription deleted', 'Subscription delete message mismatch');

    $favoriteDelete = liveRequest('DELETE', 'api/v1/favorites/' . $favoritePublicId, [], $headers);
    liveAssert($favoriteDelete['status'] === 200, 'Favorite delete must return 200');
    liveAssert((string)($favoriteDelete['payload']['message'] ?? '') === 'Removed from favorites', 'Favorite delete message mismatch');

    $viewDelete = liveRequest('DELETE', 'api/v1/views/' . $viewPublicId, [], $headers);
    liveAssert($viewDelete['status'] === 200, 'View delete must return 200');
    liveAssert((string)($viewDelete['payload']['message'] ?? '') === 'View deleted', 'View delete message mismatch');

    $favoriteNotFound = liveRequest('DELETE', 'api/v1/favorites/' . $favoritePublicId, [], $headers);
    liveAssert($favoriteNotFound['status'] === 404, 'Favorite not found must return 404');
    liveAssert((string)($favoriteNotFound['payload']['message'] ?? '') === 'Favorite item not found', 'Favorite not found message mismatch');
    assertNoCyrillicCollab($favoriteNotFound['payload']['errors'] ?? [], 'favorite.not_found.errors');

    liveRequest('DELETE', 'api/v1/tasks/' . $taskPublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/projects/' . $projectPublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/clients/' . $clientPublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/companies/' . $companyPublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_activity_search_collab_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_activity_search_collab_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
