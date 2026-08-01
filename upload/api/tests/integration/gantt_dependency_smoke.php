<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $project = request('POST', '/api/v1/projects', [
        'title' => 'Gantt smoke project ' . randomSuffix(),
        'description' => 'gantt/dependency smoke',
    ], $headers);
    assertTrue($project['status'] === 201, 'Project create status must be 201');
    $projectPublicId = (string)($project['payload']['data']['project']['public_id'] ?? '');
    assertTrue($projectPublicId !== '', 'Project public_id required');

    $taskA = request('POST', '/api/v1/tasks', [
        'title' => 'Gantt task A',
        'project_public_id' => $projectPublicId,
        'status' => 'new',
    ], $headers);
    $taskB = request('POST', '/api/v1/tasks', [
        'title' => 'Gantt task B',
        'project_public_id' => $projectPublicId,
        'status' => 'new',
    ], $headers);
    assertTrue($taskA['status'] === 201 && $taskB['status'] === 201, 'Task create status must be 201');

    $taskAPublicId = (string)($taskA['payload']['data']['task']['public_id'] ?? '');
    $taskBPublicId = (string)($taskB['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskAPublicId !== '' && $taskBPublicId !== '', 'Task public_id required');

    $milestone = request('POST', '/api/v1/milestones', [
        'project_public_id' => $projectPublicId,
        'title' => 'Release milestone',
        'due_at' => '2026-05-10 12:00:00',
        'status' => 'planned',
    ], $headers);
    assertTrue($milestone['status'] === 201, 'Milestone create status must be 201');
    $milestonePublicId = (string)($milestone['payload']['data']['milestone']['public_id'] ?? '');
    assertTrue($milestonePublicId !== '', 'Milestone public_id required');

    $dependency = request('POST', '/api/v1/dependencies', [
        'task_public_id' => $taskBPublicId,
        'depends_on_task_public_id' => $taskAPublicId,
        'dependency_type' => 'FS',
    ], $headers);
    assertTrue($dependency['status'] === 201, 'Dependency create status must be 201');
    $dependencyPublicId = (string)($dependency['payload']['data']['dependency']['public_id'] ?? '');
    assertTrue($dependencyPublicId !== '', 'Dependency public_id required');

    $timeline = request('GET', '/api/v1/projects/' . $projectPublicId . '/timeline', [], $headers);
    assertTrue($timeline['status'] === 200, 'Timeline status must be 200');
    assertTrue(($timeline['payload']['code'] ?? '') === 'PROJECT_TIMELINE', 'Timeline code mismatch');
    assertTrue(count((array)($timeline['payload']['data']['timeline']['tasks'] ?? [])) >= 2, 'Timeline tasks must be >= 2');
    assertTrue(count((array)($timeline['payload']['data']['timeline']['dependencies'] ?? [])) >= 1, 'Timeline dependencies must be >= 1');
    assertTrue(count((array)($timeline['payload']['data']['timeline']['milestones'] ?? [])) >= 1, 'Timeline milestones must be >= 1');

    $aliasTimeline = request('GET', '/api/v1/project/timeline/' . $projectPublicId, [], $headers);
    assertTrue($aliasTimeline['status'] === 200, 'Alias timeline status must be 200');

    $aliasMilestoneList = request('GET', '/api/v1/milestone/list?project_public_id=' . $projectPublicId, [], $headers);
    assertTrue($aliasMilestoneList['status'] === 200, 'Alias milestone list status must be 200');

    $aliasDependencyList = request('GET', '/api/v1/dependency/list?project_public_id=' . $projectPublicId, [], $headers);
    assertTrue($aliasDependencyList['status'] === 200, 'Alias dependency list status must be 200');

    $dependencyDelete = request('DELETE', '/api/v1/dependencies/' . $dependencyPublicId, [], $headers);
    assertTrue($dependencyDelete['status'] === 200, 'Dependency delete status must be 200');

    $milestoneDelete = request('DELETE', '/api/v1/milestones/' . $milestonePublicId, [], $headers);
    assertTrue($milestoneDelete['status'] === 200, 'Milestone delete status must be 200');

    $unauthorizedTimeline = request('GET', '/api/v1/projects/' . $projectPublicId . '/timeline');
    assertTrue($unauthorizedTimeline['status'] === 401, 'Timeline unauthorized status must be 401');

    echo "[OK] Gantt timeline + dependency smoke passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
