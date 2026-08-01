<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $project = request('POST', '/api/v1/projects', [
        'title' => 'Project summary smoke ' . randomSuffix(),
        'description' => 'summary endpoints smoke',
    ], $headers);
    assertTrue($project['status'] === 201, 'Project create status must be 201');

    $projectPublicId = (string)($project['payload']['data']['project']['public_id'] ?? '');
    assertTrue($projectPublicId !== '', 'Project public_id required');

    $taskA = request('POST', '/api/v1/tasks', [
        'title' => 'Summary smoke task A',
        'project_public_id' => $projectPublicId,
        'status' => 'in_progress',
        'priority' => 'high',
    ], $headers);
    assertTrue($taskA['status'] === 201, 'Task A create status must be 201');
    $taskAPublicId = (string)($taskA['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskAPublicId !== '', 'Task A public_id required');

    $taskB = request('POST', '/api/v1/tasks', [
        'title' => 'Summary smoke task B',
        'project_public_id' => $projectPublicId,
        'status' => 'blocked',
        'priority' => 'urgent',
    ], $headers);
    assertTrue($taskB['status'] === 201, 'Task B create status must be 201');
    $taskBPublicId = (string)($taskB['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskBPublicId !== '', 'Task B public_id required');

    $milestone = request('POST', '/api/v1/milestones', [
        'project_public_id' => $projectPublicId,
        'title' => 'Summary smoke milestone',
        'due_at' => '2026-01-01 10:00:00',
        'status' => 'open',
    ], $headers);
    assertTrue($milestone['status'] === 201, 'Milestone create status must be 201');

    $dependency = request('POST', '/api/v1/dependencies', [
        'task_public_id' => $taskBPublicId,
        'depends_on_task_public_id' => $taskAPublicId,
    ], $headers);
    assertTrue($dependency['status'] === 201, 'Dependency create status must be 201');

    $summary = request('GET', '/api/v1/projects/' . $projectPublicId . '/summary', [], $headers);
    assertTrue($summary['status'] === 200, 'Project summary status must be 200');
    assertTrue(($summary['payload']['code'] ?? '') === 'PROJECT_SUMMARY', 'Project summary code mismatch');

    $summaryData = (array)($summary['payload']['data']['summary'] ?? []);
    assertTrue((((array)($summaryData['project'] ?? []))['public_id'] ?? '') === $projectPublicId, 'Summary project public_id mismatch');
    assertTrue((int)(((array)($summaryData['milestones'] ?? []))['total'] ?? 0) >= 1, 'Milestones total must be >= 1');
    assertTrue((int)(((array)($summaryData['risks'] ?? []))['total_tasks'] ?? 0) >= 2, 'Risks total_tasks must be >= 2');

    $milestonesSummary = request('GET', '/api/v1/projects/' . $projectPublicId . '/milestones-summary', [], $headers);
    assertTrue($milestonesSummary['status'] === 200, 'Milestones summary status must be 200');
    assertTrue(($milestonesSummary['payload']['code'] ?? '') === 'PROJECT_MILESTONES_SUMMARY', 'Milestones summary code mismatch');

    $risksSummary = request('GET', '/api/v1/projects/' . $projectPublicId . '/risks', [], $headers);
    assertTrue($risksSummary['status'] === 200, 'Risks summary status must be 200');
    assertTrue(($risksSummary['payload']['code'] ?? '') === 'PROJECT_RISKS_SUMMARY', 'Risks summary code mismatch');

    $workloadSummary = request('GET', '/api/v1/projects/' . $projectPublicId . '/workload', [], $headers);
    assertTrue($workloadSummary['status'] === 200, 'Workload summary status must be 200');
    assertTrue(($workloadSummary['payload']['code'] ?? '') === 'PROJECT_WORKLOAD_SUMMARY', 'Workload summary code mismatch');

    $aliasSummary = request('GET', '/api/v1/project/summary/' . $projectPublicId, [], $headers);
    assertTrue($aliasSummary['status'] === 200, 'Alias project summary status must be 200');

    $unauthorized = request('GET', '/api/v1/projects/' . $projectPublicId . '/summary');
    assertTrue($unauthorized['status'] === 401, 'Unauthorized summary status must be 401');

    echo "[OK] Project summary smoke passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
