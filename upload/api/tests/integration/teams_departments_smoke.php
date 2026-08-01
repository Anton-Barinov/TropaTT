<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $authHeaders = authHeaders($auth['token']);
    $userPublicId = (string)$auth['user_public_id'];

    $teamCreate = request('POST', '/api/v1/teams', [
        'title' => 'Команда Smoke ' . date('His'),
        'manager_user_id' => $userPublicId,
    ], $authHeaders);
    assertTrue($teamCreate['status'] === 201, 'Team create status must be 201');
    assertTrue(($teamCreate['payload']['success'] ?? false) === true, 'Team create must be successful');

    $teamPublicId = (string)($teamCreate['payload']['data']['team']['public_id'] ?? '');
    assertTrue($teamPublicId !== '', 'Team public_id is required');

    $teamList = request('GET', '/api/v1/teams', [], $authHeaders);
    assertTrue($teamList['status'] === 200, 'Team list status must be 200');

    $teamGet = request('GET', '/api/v1/teams/' . $teamPublicId, [], $authHeaders);
    assertTrue($teamGet['status'] === 200, 'Team get status must be 200');

    $teamUpdate = request('PATCH', '/api/v1/teams/' . $teamPublicId, [
        'title' => 'Команда Smoke Updated ' . date('His'),
    ], $authHeaders);
    assertTrue($teamUpdate['status'] === 200, 'Team update status must be 200');

    $teamDelete = request('DELETE', '/api/v1/teams/' . $teamPublicId, [], $authHeaders);
    assertTrue($teamDelete['status'] === 200, 'Team delete status must be 200');

    $departmentCreate = request('POST', '/api/v1/departments', [
        'title' => 'Департамент Smoke ' . date('His'),
        'manager_user_id' => $userPublicId,
    ], $authHeaders);
    assertTrue($departmentCreate['status'] === 201, 'Department create status must be 201');
    assertTrue(($departmentCreate['payload']['success'] ?? false) === true, 'Department create must be successful');

    $departmentPublicId = (string)($departmentCreate['payload']['data']['department']['public_id'] ?? '');
    assertTrue($departmentPublicId !== '', 'Department public_id is required');

    $departmentList = request('GET', '/api/v1/departments', [], $authHeaders);
    assertTrue($departmentList['status'] === 200, 'Department list status must be 200');

    $departmentGet = request('GET', '/api/v1/departments/' . $departmentPublicId, [], $authHeaders);
    assertTrue($departmentGet['status'] === 200, 'Department get status must be 200');

    $departmentUpdate = request('PATCH', '/api/v1/departments/' . $departmentPublicId, [
        'title' => 'Департамент Smoke Updated ' . date('His'),
    ], $authHeaders);
    assertTrue($departmentUpdate['status'] === 200, 'Department update status must be 200');

    $departmentDelete = request('DELETE', '/api/v1/departments/' . $departmentPublicId, [], $authHeaders);
    assertTrue($departmentDelete['status'] === 200, 'Department delete status must be 200');

    echo "Teams/Departments smoke: OK\n";
    echo "team_public_id={$teamPublicId}\n";
    echo "department_public_id={$departmentPublicId}\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Teams/Departments smoke FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
