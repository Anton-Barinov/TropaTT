<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $roleCode = 'obj_' . randomSuffix();
    $roleCreate = request('POST', '/api/v1/roles', [
        'code' => $roleCode,
        'title' => 'Object Policy Role ' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($roleCreate['status'] === 201, 'Role create status must be 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    assertTrue($rolePublicId !== '', 'Role public_id is required');

    $setPerms = request('PUT', '/api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['project.manage', 'task.manage'],
    ], $rootHeaders);
    assertTrue($setPerms['status'] === 200, 'Role permission set status must be 200');

    $createScopedUser = static function (string $login, string $token, string $rolePublicId, array $headers): string {
        $create = request('POST', '/api/v1/users', [
            'login' => $login,
            'password' => 'ObjPass123!',
            'token' => $token,
            'email' => $login . '@crm.local',
            'full_name' => 'Object Policy User ' . $login,
            'role_public_ids' => [$rolePublicId],
        ], $headers);
        assertTrue($create['status'] === 201, 'Scoped user create status must be 201');
        $publicId = (string)($create['payload']['data']['user']['public_id'] ?? '');
        assertTrue($publicId !== '', 'Scoped user public_id is required');
        return $publicId;
    };

    $loginA = 'obj_a_' . randomSuffix();
    $tokenA = 'obj-token-a-' . randomSuffix();
    $loginB = 'obj_b_' . randomSuffix();
    $tokenB = 'obj-token-b-' . randomSuffix();

    $userAPublicId = $createScopedUser($loginA, $tokenA, $rolePublicId, $rootHeaders);
    $userBPublicId = $createScopedUser($loginB, $tokenB, $rolePublicId, $rootHeaders);

    $aLogin = request('POST', '/api/v1/auth/login', ['login' => $loginA, 'password' => 'ObjPass123!', 'token' => $tokenA]);
    $bLogin = request('POST', '/api/v1/auth/login', ['login' => $loginB, 'password' => 'ObjPass123!', 'token' => $tokenB]);
    assertTrue($aLogin['status'] === 200, 'User A login status must be 200');
    assertTrue($bLogin['status'] === 200, 'User B login status must be 200');

    $aHeaders = authHeaders((string)$aLogin['payload']['data']['access_token']);
    $bHeaders = authHeaders((string)$bLogin['payload']['data']['access_token']);

    $projectA = request('POST', '/api/v1/projects', [
        'title' => 'Object A Project ' . randomSuffix(),
        'description' => 'owned by A',
    ], $aHeaders);
    assertTrue($projectA['status'] === 201, 'Project A create status must be 201');
    $projectAPublicId = (string)($projectA['payload']['data']['project']['public_id'] ?? '');

    $projectB = request('POST', '/api/v1/projects', [
        'title' => 'Object B Project ' . randomSuffix(),
        'description' => 'owned by B',
    ], $bHeaders);
    assertTrue($projectB['status'] === 201, 'Project B create status must be 201');
    $projectBPublicId = (string)($projectB['payload']['data']['project']['public_id'] ?? '');

    $taskA = request('POST', '/api/v1/tasks', [
        'title' => 'Object A Task ' . randomSuffix(),
        'project_public_id' => $projectAPublicId,
    ], $aHeaders);
    assertTrue($taskA['status'] === 201, 'Task A create status must be 201');
    $taskAPublicId = (string)($taskA['payload']['data']['task']['public_id'] ?? '');

    $taskB = request('POST', '/api/v1/tasks', [
        'title' => 'Object B Task ' . randomSuffix(),
        'project_public_id' => $projectBPublicId,
    ], $bHeaders);
    assertTrue($taskB['status'] === 201, 'Task B create status must be 201');
    $taskBPublicId = (string)($taskB['payload']['data']['task']['public_id'] ?? '');

    $aTaskList = request('GET', '/api/v1/tasks', [], $aHeaders);
    assertTrue($aTaskList['status'] === 200, 'User A task list status must be 200');
    $aTaskIds = array_map(
        static fn(array $item): string => (string)($item['public_id'] ?? ''),
        (array)($aTaskList['payload']['data']['items'] ?? [])
    );
    assertTrue(in_array($taskAPublicId, $aTaskIds, true), 'User A must see own task');
    assertTrue(!in_array($taskBPublicId, $aTaskIds, true), 'User A must not see user B task');

    $aTaskBGet = request('GET', '/api/v1/tasks/' . $taskBPublicId, [], $aHeaders);
    assertTrue($aTaskBGet['status'] === 404, 'User A must not access user B task');

    $aTaskBComments = request('GET', '/api/v1/tasks/' . $taskBPublicId . '/comments', [], $aHeaders);
    assertTrue($aTaskBComments['status'] === 404, 'User A must not access user B comments');

    $aFileOnBTask = request('POST', '/api/v1/files', [
        'entity_type' => 'task',
        'entity_public_id' => $taskBPublicId,
        'name' => 'object_policy_payload.txt',
        'mime_type' => 'text/plain',
        'content_base64' => base64_encode('object policy payload'),
    ], $aHeaders);
    assertTrue($aFileOnBTask['status'] === 403, 'User A must not upload file to user B task');

    $rootTaskBGet = request('GET', '/api/v1/tasks/' . $taskBPublicId, [], $rootHeaders);
    assertTrue($rootTaskBGet['status'] === 200, 'Root must bypass object restrictions');

    request('DELETE', '/api/v1/users/' . $userAPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/users/' . $userBPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "Object policy smoke: OK\n";
    echo "task_a_public_id={$taskAPublicId}\n";
    echo "task_b_public_id={$taskBPublicId}\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Object policy smoke FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
