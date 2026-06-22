<?php
declare(strict_types=1);

namespace Module\Crm\JiraMigration\Service;

use Module\Crm\JiraMigration\Repository\JiraMigrationRepository;
use PDO;

/**
 * Orchestrates the full Jira import pipeline.
 * Pipeline: crawl -> create_projects -> create_skeleton_tasks -> resolve_hierarchy -> import_fields -> 
 *           import_comments -> import_attachments -> import_worklogs -> import_relations ->
 *           import_versions_components -> import_sprints -> create_knowledge_artifacts -> reindex -> report
 */
final class JiraImportService
{
    public function __construct(
        private JiraMigrationRepository $migrationRepo,
        private JiraClient $client,
        private JiraCrawler $crawler,
        private JiraAdfRenderer $adfRenderer,
        private PDO $pdo,
    ) {
    }

    public function processJob(string $jobPublicId): void
    {
        $job = $this->migrationRepo->getJob($jobPublicId);
        if (!$job) {
            return;
        }

        $connection = $this->migrationRepo->getConnectionById((int)$job['connection_id']);
        if (!$connection) {
            $this->migrationRepo->addJobLog($jobPublicId, 'error', 'init', 'Connection not found');
            $this->migrationRepo->updateJobStatus($jobPublicId, 'failed');
            return;
        }

        $token = EncryptionService::decrypt((string)($connection['token_encrypted'] ?? ''));
        if ($token === null) {
            $this->migrationRepo->addJobLog($jobPublicId, 'error', 'init', 'Failed to decrypt token');
            $this->migrationRepo->updateJobStatus($jobPublicId, 'failed');
            return;
        }

        $siteUrl = (string)$connection['site_url'];
        $email = (string)$connection['email'];

        $this->client->setConnectionId((int)$connection['id']);
        $this->migrationRepo->initRateLimit((int)$connection['id']);

        $this->migrationRepo->updateJobStatus($jobPublicId, 'running');
        $this->migrationRepo->updateJobProgress($jobPublicId, 'crawl', 0, []);

        // Step 1: Crawl
        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $crawlResult = $this->crawler->crawlProjects($job, $siteUrl, $email, $token);
        $this->migrationRepo->updateJobProgress($jobPublicId, 'create_projects', 15, $crawlResult);

        // Step 2: Create CRM projects from Jira projects
        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $this->importProjects($job, $siteUrl, $email, $token);
        $this->migrationRepo->updateJobProgress($jobPublicId, 'create_skeleton_tasks', 25, []);

        // Step 3: Create skeleton tasks
        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $this->createSkeletonTasks($job, $siteUrl, $email, $token);
        $this->migrationRepo->updateJobProgress($jobPublicId, 'resolve_hierarchy', 35, []);

        // Step 4: Resolve hierarchy (epics, subtasks, parents)
        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $this->resolveHierarchy($job);
        $this->migrationRepo->updateJobProgress($jobPublicId, 'import_fields', 45, []);

        // Step 5: Import issue fields (description, status, priority, assignee, etc.)
        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $this->importIssueFields($job, $siteUrl, $email, $token);
        $this->migrationRepo->updateJobProgress($jobPublicId, 'import_comments', 55, []);

        // Step 6: Import comments
        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $this->importComments($job, $siteUrl, $email, $token, (int)$connection['id']);
        $this->migrationRepo->updateJobProgress($jobPublicId, 'import_attachments', 62, []);

        // Step 7: Import attachments
        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $this->importAttachments($job, $siteUrl, $email, $token);
        $this->migrationRepo->updateJobProgress($jobPublicId, 'import_worklogs', 70, []);

        // Step 8: Import worklogs
        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $this->importWorklogs($job, $siteUrl, $email, $token, (int)$connection['id']);
        $this->migrationRepo->updateJobProgress($jobPublicId, 'import_relations', 78, []);

        // Step 9: Import relations and dependencies
        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $this->importRelationsDependencies($job, $siteUrl, $email, $token);
        $this->migrationRepo->updateJobProgress($jobPublicId, 'import_sprints', 85, []);

        // Step 10: Import sprints as cycles
        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $this->importSprints($job, $siteUrl, $email, $token);
        $this->migrationRepo->updateJobProgress($jobPublicId, 'import_versions_components', 90, []);

        // Step 11: Import versions/components
        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $this->importVersionsComponents($job, $siteUrl, $email, $token);

        // Step 12: Complete
        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $stats = $this->migrationRepo->countJobItemsByStatus((int)$job['id']);
        $this->migrationRepo->updateJobProgress($jobPublicId, 'completed', 100, $stats);
        $this->migrationRepo->updateJobStatus($jobPublicId, 'completed');
        $this->migrationRepo->addJobLog($jobPublicId, 'info', 'completed', 'Migration completed');
    }

    private function finaliseCancelled(string $jobPublicId): void
    {
        $this->migrationRepo->updateJobStatus($jobPublicId, 'cancelled');
        $this->migrationRepo->addJobLog($jobPublicId, 'info', 'cancelled', 'Job cancelled gracefully');
    }

    private function isCancelling(array $job): bool
    {
        $current = $this->migrationRepo->getJob((string)$job['public_id']);
        return $current !== null && ($current['status'] ?? '') === 'cancelling';
    }

    // ── Project Import ──

    private function importProjects(array $job, string $siteUrl, string $email, string $token): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];
        $scope = json_decode((string)($job['source_scope_json'] ?? '[]'), true) ?? [];
        $projectKeys = $scope['project_keys'] ?? [];

        $projects = $this->client->getProjects($siteUrl, $email, $token, $projectKeys);

        foreach ($projects as $project) {
            $projectId = $project['id'];
            $projectKey = $project['key'];

            try {
                // Check if project already imported
                $existing = $this->migrationRepo->findJobItem($jobId, 'project', $projectId);
                if ($existing && $existing['status'] === 'imported' && $existing['target_public_id']) {
                    continue;
                }

                // Create CRM project via core API
                // We're using direct PDO for MVP but ideally use core API
                $projectPublicId = $this->createCrmProject($project);

                if ($projectPublicId !== null) {
                    $this->migrationRepo->upsertJobItem($jobId, 'project', $projectId, [
                        'target_type' => 'project',
                        'target_public_id' => $projectPublicId,
                        'status' => 'imported',
                        'source_key' => $projectKey,
                        'payload_json' => ['name' => $project['name'], 'key' => $projectKey],
                    ]);
                    $this->migrationRepo->addJobLog($jobPublicId, 'info', 'import_projects', "Project {$projectKey} imported as {$projectPublicId}");
                }
            } catch (\Throwable $e) {
                $this->migrationRepo->upsertJobItem($jobId, 'project', $projectId, [
                    'status' => 'failed',
                    'error_code' => 'PROJECT_IMPORT_ERROR',
                    'error_message' => $e->getMessage(),
                ]);
                $this->migrationRepo->addJobLog($jobPublicId, 'error', 'import_projects', "Failed project {$projectKey}: " . $e->getMessage());
            }
        }
    }

    private function createCrmProject(array $project): ?string
    {
        try {
            $now = gmdate('Y-m-d H:i:s');
            $publicId = 'prj_' . bin2hex(random_bytes(10));
            $key = $project['key'];

            // Check if prefix already exists
            $check = $this->pdo->prepare("SELECT id FROM projects WHERE task_key_prefix = :prefix LIMIT 1");
            $check->execute(['prefix' => $key]);
            if ($check->fetchColumn()) {
                $key = substr($key, 0, 8) . bin2hex(random_bytes(2));
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO projects (public_id, title, description, status_code, priority_code, task_key_prefix, created_at, updated_at) 
                VALUES (:public_id, :title, :desc, 'active', 'normal', :prefix, :created_at, :updated_at)
            ");
            $stmt->execute([
                'public_id' => $publicId,
                'title' => $project['name'],
                'desc' => 'Imported from Jira: ' . $project['key'] . ' (' . ($project['project_type_key'] ?? 'software') . ')',
                'prefix' => $key,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $publicId;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ── Skeleton Tasks ──

    private function createSkeletonTasks(array $job, string $siteUrl, string $email, string $token): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];
        $scope = json_decode((string)($job['source_scope_json'] ?? '[]'), true) ?? [];
        $projectKeys = $scope['project_keys'] ?? [];

        foreach ($projectKeys as $projectKey) {
            $jql = "project = \"{$projectKey}\" ORDER BY created ASC";

            try {
                $issues = $this->client->searchIssues($siteUrl, $email, $token, $jql, ['id', 'key', 'issuetype', 'parent', 'summary', 'status', 'priority', 'assignee', 'created', 'updated', 'duedate', 'labels', 'components', 'fixVersions', 'description', 'customfield_*', 'attachment', 'comment', 'worklog']);

                // Find project target
                $projectMapping = null;
                $projectItems = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 1000);
                foreach ($projectItems as $pi) {
                    if ($pi['source_type'] === 'project') {
                        $payload = json_decode((string)($pi['payload_json'] ?? '{}'), true) ?? [];
                        if (($payload['key'] ?? '') === $projectKey) {
                            $projectMapping = $pi['target_public_id'];
                            break;
                        }
                    }
                }

                if (!$projectMapping) {
                    $this->migrationRepo->addJobLog($jobPublicId, 'error', 'create_tasks', "No project mapping found for {$projectKey}");
                    continue;
                }

                foreach ($issues as $issue) {
                    $issueId = (string)($issue['id'] ?? '');
                    $issueKey = (string)($issue['key'] ?? '');
                    $fields = $issue['fields'] ?? [];
                    $summary = $fields['summary'] ?? '';

                    try {
                        // Check if already imported
                        $existing = $this->migrationRepo->findJobItem($jobId, 'issue', $issueId);
                        if ($existing && $existing['status'] === 'imported' && $existing['target_public_id']) {
                            continue;
                        }

                        // Create task via direct PDO (MVP approach)
                        $taskPublicId = $this->createCrmTask($projectMapping, $issueKey, $summary, $fields);

                        if ($taskPublicId !== null) {
                            $this->migrationRepo->upsertJobItem($jobId, 'issue', $issueId, [
                                'target_type' => 'task',
                                'target_public_id' => $taskPublicId,
                                'status' => 'pending', // Will be updated after field import
                                'source_key' => $issueKey,
                                'source_parent_id' => !empty($fields['parent']) ? (string)$fields['parent']['id'] : null,
                                'payload_json' => [
                                    'key' => $issueKey,
                                    'summary' => $summary,
                                    'issue_type' => $fields['issuetype']['name'] ?? '',
                                    'status' => $fields['status']['name'] ?? '',
                                    'priority' => $fields['priority']['name'] ?? '',
                                ],
                            ]);
                        }
                    } catch (\Throwable $e) {
                        $this->migrationRepo->addJobLog($jobPublicId, 'error', 'create_tasks', "Failed to create task for {$issueKey}: " . $e->getMessage());
                    }
                }
            } catch (\Throwable $e) {
                $this->migrationRepo->addJobLog($jobPublicId, 'error', 'create_tasks', "Failed to search issues for {$projectKey}: " . $e->getMessage());
            }
        }
    }

    private function createCrmTask(string $projectPublicId, string $issueKey, string $summary, array $fields): ?string
    {
        try {
            $publicId = 'tsk_' . bin2hex(random_bytes(10));
            $now = gmdate('Y-m-d H:i:s');

            // Map status
            $statusName = $fields['status']['name'] ?? 'new';
            $statusCode = $this->mapJiraStatusToCrm($statusName);

            // Map priority
            $priorityName = $fields['priority']['name'] ?? 'normal';
            $priorityCode = $this->mapJiraPriorityToCrm($priorityName);

            // Assignee
            $assigneeUserId = null;
            if (!empty($fields['assignee'])) {
                $assigneeUserId = $this->resolveUser((string)$fields['assignee']['accountId']);
            }

            // Due date
            $dueAt = !empty($fields['duedate']) ? $fields['duedate'] : null;

            $stmt = $this->pdo->prepare("
                INSERT INTO tasks (public_id, project_id, title, description, status_code, priority_code, assignee_user_id, creator_user_id, due_at, created_at, updated_at)
                VALUES (:public_id, (SELECT id FROM projects WHERE public_id = :project_pub), :title, :description, :status_code, :priority_code, :assignee, (SELECT id FROM users ORDER BY id LIMIT 1), :due_at, :created_at, :updated_at)
            ");
            $stmt->execute([
                'public_id' => $publicId,
                'project_pub' => $projectPublicId,
                'title' => mb_substr($summary ?: $issueKey, 0, 255),
                'description' => 'Imported from Jira: ' . $issueKey,
                'status_code' => $statusCode,
                'priority_code' => $priorityCode,
                'assignee' => $assigneeUserId,
                'due_at' => $dueAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Store Jira key in task description metadata
            $desc = 'Imported from Jira: ' . $issueKey;
            $this->pdo->prepare('UPDATE tasks SET description = CONCAT(description, :meta) WHERE public_id = :pub')
                ->execute(['meta' => "\n\n[Source: Jira {$issueKey}]", 'pub' => $publicId]);

            return $publicId;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ── Hierarchy Resolution ──

    private function resolveHierarchy(array $job): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];

        $importedIssues = $this->migrationRepo->findJobItemsByStatus($jobId, 'pending', 10000);
        $allItems = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 10000);
        $issueMap = [];

        foreach ($allItems as $item) {
            if ($item['source_type'] === 'issue' && $item['target_public_id']) {
                $issueMap[$item['source_id']] = $item['target_public_id'];
            }
        }

        // First pass: update status to imported for all created tasks
        foreach ($importedIssues as $item) {
            if ($item['source_type'] === 'issue' && $item['target_public_id']) {
                $this->migrationRepo->upsertJobItem($jobId, 'issue', $item['source_id'], [
                    'status' => 'imported',
                ]);
            }
        }

        // Second pass: set parent_task_public_id for subtasks
        foreach ($importedIssues as $item) {
            if ($item['source_type'] !== 'issue' || !$item['target_public_id']) {
                continue;
            }

            $sourceParentId = $item['source_parent_id'] ?? null;
            if ($sourceParentId && isset($issueMap[$sourceParentId])) {
                $childPublicId = $item['target_public_id'];
                $parentPublicId = $issueMap[$sourceParentId];
                try {
                    // Resolve parent task internal ID
                $parentIdStmt = $this->pdo->prepare("SELECT id FROM tasks WHERE public_id = :pub LIMIT 1");
                $parentIdStmt->execute(['pub' => $parentPublicId]);
                $parentIdVal = (int)$parentIdStmt->fetchColumn();
                if ($parentIdVal > 0) {
                    $this->pdo->prepare("UPDATE tasks SET parent_task_id = :parent_id WHERE public_id = :child")
                        ->execute(['parent_id' => $parentIdVal, 'child' => $childPublicId]);
                }
                } catch (\Throwable) {
                }
            }
        }

        // Third pass: create relation for epics
        foreach ($importedIssues as $item) {
            $payload = json_decode((string)($item['payload_json'] ?? '{}'), true) ?? [];
            $issueType = $payload['issue_type'] ?? '';
            $issueKey = $payload['key'] ?? '';

            // Epic detection based on issue type
            if ($issueType === 'Epic' && $item['target_public_id']) {
                // Epic relation will be created later from issue links
                $this->migrationRepo->addJobLog($jobPublicId, 'info', 'hierarchy', "Epic {$issueKey} mapped to task {$item['target_public_id']}");
            }
        }
    }

    // ── Issue Fields ──

    private function importIssueFields(array $job, string $siteUrl, string $email, string $token): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];

        $pendingItems = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 10000);
        $issueItems = array_filter($pendingItems, fn($i) => $i['source_type'] === 'issue');

        foreach ($issueItems as $item) {
            $issueKey = $item['source_key'] ?? '';
            $targetPublicId = (string)$item['target_public_id'];
            if ($targetPublicId === '') {
                continue;
            }

            try {
                $issue = $this->client->getIssue($siteUrl, $email, $token, $issueKey);
                $fields = $issue['fields'] ?? [];

                // Update task description from ADF
                $description = '';
                if (!empty($fields['description'])) {
                    $description = $this->adfRenderer->toPlainText($fields['description']);
                    $description = mb_substr($description, 0, 8000);
                }

                // Update task
                $statusName = $fields['status']['name'] ?? '';
                $statusCode = $this->mapJiraStatusToCrm($statusName);
                $priorityName = $fields['priority']['name'] ?? '';
                $priorityCode = $this->mapJiraPriorityToCrm($priorityName);

                $assigneeUserId = null;
                if (!empty($fields['assignee'])) {
                    $assigneeUserId = $this->resolveUser((string)$fields['assignee']['accountId']);
                }

                $dueAt = !empty($fields['duedate']) ? $fields['duedate'] : null;

                $this->pdo->prepare("UPDATE tasks SET description = :desc, status_code = :status, priority_code = :priority, assignee_user_id = :assignee, due_at = :due_at WHERE public_id = :pub")
                    ->execute([
                        'desc' => $description,
                        'status' => $statusCode,
                        'priority' => $priorityCode,
                        'assignee' => $assigneeUserId,
                        'due_at' => $dueAt,
                        'pub' => $targetPublicId,
                    ]);

                // Import labels as tags
                $labels = $fields['labels'] ?? [];
                foreach ($labels as $label) {
                    $this->ensureTag($label, $targetPublicId);
                }

            } catch (\Throwable $e) {
                $this->migrationRepo->addJobLog($jobPublicId, 'warning', 'import_fields', "Failed to import fields for {$issueKey}: " . $e->getMessage());
            }
        }
    }

    // ── Comments ──

    private function importComments(array $job, string $siteUrl, string $email, string $token, int $connectionId): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];

        $issueItems = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 10000);

        foreach ($issueItems as $item) {
            if ($item['source_type'] !== 'issue' || !$item['target_public_id']) {
                continue;
            }

            $issueKey = (string)$item['source_key'];
            $taskPublicId = (string)$item['target_public_id'];

            try {
                $comments = $this->client->getIssueComments($siteUrl, $email, $token, $issueKey);

                foreach ($comments as $rawComment) {
                    $commentId = (string)($rawComment['id'] ?? '');

                    // Check if already imported
                    $existing = $this->migrationRepo->findJobItem($jobId, 'comment', $commentId);
                    if ($existing && $existing['status'] === 'imported') {
                        continue;
                    }

                    $author = $rawComment['author']['displayName'] ?? 'Unknown Jira user';
                    $authorAccountId = $rawComment['author']['accountId'] ?? null;
                    $body = $rawComment['body'] ?? '';
                    $created = $rawComment['created'] ?? null;

                    if ($authorAccountId) {
                        $this->migrationRepo->upsertMapping($connectionId, 'user', $authorAccountId, $author);
                    }

                    // Convert ADF body to plain text
                    $bodyText = $this->adfRenderer->toPlainText($body);

                    // Create task comment
                    $commentPublicId = 'com_' . bin2hex(random_bytes(10));
                    $now = gmdate('Y-m-d H:i:s');

                    $commentText = "**Imported from Jira ({$issueKey})**\n\n*Author: {$author}*\n\n{$bodyText}";

                    $this->pdo->prepare("INSERT INTO comments (public_id, task_id, author_user_id, body, visibility, created_at, updated_at) VALUES (:pub, (SELECT id FROM tasks WHERE public_id = :task_pub), (SELECT id FROM users ORDER BY id LIMIT 1), :body, 'internal', :created_at, :updated_at)")
                        ->execute([
                            'pub' => $commentPublicId,
                            'task_pub' => $taskPublicId,
                            'body' => $commentText,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                    $this->migrationRepo->upsertJobItem($jobId, 'comment', $commentId, [
                        'source_key' => $issueKey . ':' . $commentId,
                        'target_type' => 'task_comment',
                        'target_public_id' => $commentPublicId,
                        'status' => 'imported',
                        'payload_json' => ['author' => $author, 'created' => $created],
                    ]);
                }
            } catch (\Throwable $e) {
                $this->migrationRepo->addJobLog($jobPublicId, 'warning', 'import_comments', "Failed comments for {$issueKey}: " . $e->getMessage());
            }
        }
    }

    // ── Attachments ──

    private function importAttachments(array $job, string $siteUrl, string $email, string $token): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];

        $issueItems = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 10000);

        foreach ($issueItems as $item) {
            if ($item['source_type'] !== 'issue' || !$item['target_public_id']) {
                continue;
            }

            $issueKey = (string)$item['source_key'];
            $taskPublicId = (string)$item['target_public_id'];

            try {
                $issue = $this->client->getIssue($siteUrl, $email, $token, $issueKey);
                $attachments = $issue['fields']['attachment'] ?? [];

                foreach ($attachments as $attachment) {
                    $attachmentId = (string)($attachment['id'] ?? '');

                    $existing = $this->migrationRepo->findJobItem($jobId, 'attachment', $attachmentId);
                    if ($existing && $existing['status'] === 'imported') {
                        continue;
                    }

                    $filename = $attachment['filename'] ?? 'file.bin';
                    $mimeType = $attachment['mimeType'] ?? 'application/octet-stream';
                    $size = (int)($attachment['size'] ?? 0);
                    $contentUrl = $attachment['content'] ?? '';

                    if ($contentUrl === '') {
                        continue;
                    }

                    // Check size limit
                    $maxSize = 20 * 1024 * 1024;
                    if ($size > $maxSize) {
                        $this->migrationRepo->addUnresolvedEntity($jobPublicId, 'attachment', $attachmentId,
                            'FILE_TOO_LARGE', "Attachment {$filename} ({$size} bytes) exceeds limit"
                        );
                        continue;
                    }

                    $tmpPath = sys_get_temp_dir() . '/jira_attachment_' . bin2hex(random_bytes(8)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

                    $downloadResult = $this->client->downloadAttachment($siteUrl, $email, $token, $contentUrl, $tmpPath);
                    if (!$downloadResult['success']) {
                        $this->migrationRepo->addUnresolvedEntity($jobPublicId, 'attachment', $attachmentId,
                            'DOWNLOAD_FAILED', $downloadResult['error'] ?? 'Unknown'
                        );
                        continue;
                    }

                    // Read file and create file record
                    $content = file_get_contents($tmpPath);
                    $checksum = hash('sha256', $content);

                    $filePublicId = 'fil_' . bin2hex(random_bytes(10));
                    $now = gmdate('Y-m-d H:i:s');

                    // Store file to disk
                    $storageDir = dirname(__DIR__, 4) . '/storage/uploads/jira-import';
                    if (!is_dir($storageDir)) {
                        @mkdir($storageDir, 0755, true);
                    }
                    $storageFilename = $filePublicId . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
                    $storagePath = 'uploads/jira-import/' . $storageFilename;
                    $fullPath = $storageDir . '/' . $storageFilename;
                    file_put_contents($fullPath, $content);

                    $this->pdo->prepare("INSERT INTO files (public_id, entity_type, entity_public_id, uploader_user_id, original_name, storage_path, mime_type, size_bytes, created_at) VALUES (:pub, 'task', :task_pub, (SELECT id FROM users ORDER BY id LIMIT 1), :name, :storage_path, :mime, :size, :created_at)")
                        ->execute([
                            'pub' => $filePublicId,
                            'task_pub' => $taskPublicId,
                            'name' => $filename,
                            'storage_path' => $storagePath,
                            'mime' => $mimeType,
                            'size' => $size,
                            'created_at' => $now,
                        ]);

                    @unlink($tmpPath);

                    $this->migrationRepo->upsertJobItem($jobId, 'attachment', $attachmentId, [
                        'source_key' => $filename,
                        'target_type' => 'file',
                        'target_public_id' => $filePublicId,
                        'status' => 'imported',
                        'checksum' => $checksum,
                    ]);
                }
            } catch (\Throwable $e) {
                $this->migrationRepo->addJobLog($jobPublicId, 'warning', 'import_attachments', "Failed attachments for {$issueKey}: " . $e->getMessage());
            }
        }
    }

    // ── Worklogs ──

    private function importWorklogs(array $job, string $siteUrl, string $email, string $token, int $connectionId): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];

        $issueItems = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 10000);

        foreach ($issueItems as $item) {
            if ($item['source_type'] !== 'issue' || !$item['target_public_id']) {
                continue;
            }

            $issueKey = (string)$item['source_key'];
            $taskPublicId = (string)$item['target_public_id'];

            try {
                $worklogs = $this->client->getIssueWorklogs($siteUrl, $email, $token, $issueKey);

                foreach ($worklogs as $wl) {
                    $wlId = (string)($wl['id'] ?? '');

                    $existing = $this->migrationRepo->findJobItem($jobId, 'worklog', $wlId);
                    if ($existing && $existing['status'] === 'imported') {
                        continue;
                    }

                    $author = $wl['author']['displayName'] ?? 'Unknown';
                    $authorAccountId = $wl['author']['accountId'] ?? null;
                    $timeSpentSeconds = (int)($wl['timeSpentSeconds'] ?? 0);
                    $started = $wl['started'] ?? null;
                    $comment = $wl['comment'] ?? '';

                    if ($authorAccountId) {
                        $this->migrationRepo->upsertMapping($connectionId, 'user', $authorAccountId, $author);
                    }

                    $commentText = $this->adfRenderer->toPlainText($comment);

                    $wlPublicId = 'wkl_' . bin2hex(random_bytes(10));
                    $now = gmdate('Y-m-d H:i:s');

                    $this->pdo->prepare("INSERT INTO work_logs (public_id, task_id, user_id, minutes_spent, note, logged_at, created_at) VALUES (:pub, (SELECT id FROM tasks WHERE public_id = :task_pub), (SELECT id FROM users ORDER BY id LIMIT 1), :minutes, :desc, :logged_at, :created_at)")
                        ->execute([
                            'pub' => $wlPublicId,
                            'task_pub' => $taskPublicId,
                            'minutes' => (int)($timeSpentSeconds / 60),
                            'desc' => 'Imported from Jira: ' . ($commentText ?: $issueKey),
                            'logged_at' => $started ?: $now,
                            'created_at' => $now,
                        ]);

                    $this->migrationRepo->upsertJobItem($jobId, 'worklog', $wlId, [
                        'source_key' => $issueKey . ':' . $wlId,
                        'target_type' => 'worklog',
                        'target_public_id' => $wlPublicId,
                        'status' => 'imported',
                    ]);
                }
            } catch (\Throwable $e) {
                $this->migrationRepo->addJobLog($jobPublicId, 'warning', 'import_worklogs', "Failed worklogs for {$issueKey}: " . $e->getMessage());
            }
        }
    }

    // ── Relations & Dependencies ──

    private function importRelationsDependencies(array $job, string $siteUrl, string $email, string $token): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];

        $issueItems = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 10000);
        $taskIds = [];
        $targetByKey = [];
        foreach ($issueItems as $item) {
            if ($item['source_type'] === 'issue' && $item['target_public_id']) {
                $targetByKey[$item['source_key']] = $item['target_public_id'];
                $taskIds[$item['source_id']] = $item['target_public_id'];
            }
        }

        foreach ($issueItems as $item) {
            if ($item['source_type'] !== 'issue' || !$item['target_public_id']) {
                continue;
            }

            $issueKey = (string)$item['source_key'];
            $taskPublicId = (string)$item['target_public_id'];

            try {
                $issue = $this->client->getIssue($siteUrl, $email, $token, $issueKey);
                $fields = $issue['fields'] ?? [];
                $issueLinks = $fields['issuelinks'] ?? [];

                foreach ($issueLinks as $link) {
                    $linkId = (string)($link['id'] ?? '');

                    $existing = $this->migrationRepo->findJobItem($jobId, 'issuelink', $linkId);
                    if ($existing && $existing['status'] === 'imported') {
                        continue;
                    }

                    // Determine link type and direction
                    $inwardType = $link['type']['inward'] ?? 'relates to';
                    $outwardType = $link['type']['outward'] ?? 'relates to';
                    $inwardIssue = $link['inwardIssue'] ?? null;
                    $outwardIssue = $link['outwardIssue'] ?? null;

                    $sourceTaskId = null;
                    $targetTaskId = null;
                    $relationType = 'relates_to';

                    if ($inwardIssue && isset($targetByKey[$inwardIssue['key']])) {
                        $sourceTaskId = $taskPublicId;
                        $targetTaskId = $targetByKey[$inwardIssue['key']];
                        $relationType = $this->mapJiraLinkType($outwardType);
                    } elseif ($outwardIssue && isset($targetByKey[$outwardIssue['key']])) {
                        $sourceTaskId = $taskPublicId;
                        $targetTaskId = $targetByKey[$outwardIssue['key']];
                        $relationType = $this->mapJiraLinkType($inwardType);
                    }

                    if ($sourceTaskId && $targetTaskId) {
                        $relationPublicId = 'trl2_' . bin2hex(random_bytes(10));
                        $now = gmdate('Y-m-d H:i:s');

                        try {
                            // Generate a unique active_key to avoid duplicates
                $activeKey = 'jira_' . bin2hex(random_bytes(8));
                $this->pdo->prepare("INSERT INTO task_relations_v2 (public_id, source_task_id, target_task_id, relation_type, note, created_by_user_id, created_at, updated_at) VALUES (:pub, (SELECT id FROM tasks WHERE public_id = :src), (SELECT id FROM tasks WHERE public_id = :tgt), :type, :note, (SELECT id FROM users ORDER BY id LIMIT 1), :created_at, :updated_at)")
                                ->execute([
                                    'pub' => $relationPublicId,
                                    'src' => $sourceTaskId,
                                    'tgt' => $targetTaskId,
                                    'type' => $relationType,
                                    'note' => 'Imported from Jira link',
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ]);

                            $this->migrationRepo->upsertJobItem($jobId, 'issuelink', $linkId, [
                                'source_key' => $issueKey . ':' . $linkId,
                                'target_type' => 'task_relation',
                                'target_public_id' => $relationPublicId,
                                'status' => 'imported',
                            ]);
                        } catch (\Throwable) {
                            // Skip duplicate relations
                        }
                    }
                }
            } catch (\Throwable $e) {
                $this->migrationRepo->addJobLog($jobPublicId, 'warning', 'import_relations', "Failed relations for {$issueKey}: " . $e->getMessage());
            }
        }
    }

    // ── Sprints (Cycles) ──

    private function importSprints(array $job, string $siteUrl, string $email, string $token): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];

        try {
            $boards = $this->client->getBoards($siteUrl, $email, $token);
            $projectItems = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 1000);
            $projectTargets = [];
            foreach ($projectItems as $pi) {
                if ($pi['source_type'] === 'project' && $pi['target_public_id']) {
                    $projectTargets[$pi['source_key']] = $pi['target_public_id'];
                }
            }

            foreach ($boards as $board) {
                $boardId = (int)$board['id'];
                $sprints = $this->client->getBoardSprints($siteUrl, $email, $token, $boardId);

                foreach ($sprints as $sprint) {
                    $sprintId = (string)$sprint['id'];

                    $existing = $this->migrationRepo->findJobItem($jobId, 'sprint', $sprintId);
                    if ($existing && $existing['status'] === 'imported') {
                        continue;
                    }

                    // Find project for this sprint (use first project from board)
                    $firstProjectKey = '';
                    foreach ($projectTargets as $pk => $pub) {
                        $firstProjectKey = $pk;
                        break;
                    }

                    $cyclePublicId = 'cyc_' . bin2hex(random_bytes(10));
                    $now = gmdate('Y-m-d H:i:s');

                    $cycleStatus = match ($sprint['state']) {
                        'active' => 'active',
                        'closed' => 'completed',
                        default => 'planned',
                    };

                    $this->pdo->prepare("INSERT INTO work_cycles (public_id, project_id, title, goal, status, start_at, end_at, created_by_user_id, sort_order, created_at, updated_at) VALUES (:pub, (SELECT id FROM projects WHERE public_id = :project_pub), :title, :goal, :status, :start_at, :end_at, (SELECT id FROM users ORDER BY id LIMIT 1), 65535, :created_at, :updated_at)")
                        ->execute([
                            'pub' => $cyclePublicId,
                            'project_pub' => $projectTargets[$firstProjectKey] ?? reset($projectTargets),
                            'title' => $sprint['name'],
                            'goal' => $sprint['goal'] ?? '',
                            'status' => $cycleStatus,
                            'start_at' => $sprint['start_date'],
                            'end_at' => $sprint['end_date'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                    $this->migrationRepo->upsertJobItem($jobId, 'sprint', $sprintId, [
                        'source_key' => $sprint['name'],
                        'target_type' => 'cycle',
                        'target_public_id' => $cyclePublicId,
                        'status' => 'imported',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            $this->migrationRepo->addJobLog($jobPublicId, 'warning', 'import_sprints', "Failed to import sprints: " . $e->getMessage());
        }
    }

    // ── Versions & Components ──

    private function importVersionsComponents(array $job, string $siteUrl, string $email, string $token): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];
        $scope = json_decode((string)($job['source_scope_json'] ?? '[]'), true) ?? [];
        $projectKeys = $scope['project_keys'] ?? [];

        foreach ($projectKeys as $projectKey) {
            try {
                $versions = $this->client->getProjectVersions($siteUrl, $email, $token, $projectKey);
                foreach ($versions as $version) {
                    $this->migrationRepo->addJobLog($jobPublicId, 'info', 'import_versions', "Version {$version['name']} for {$projectKey} recorded");
                }

                $components = $this->client->getProjectComponents($siteUrl, $email, $token, $projectKey);
                foreach ($components as $component) {
                    $this->migrationRepo->addJobLog($jobPublicId, 'info', 'import_components', "Component {$component['name']} for {$projectKey} recorded");
                }
            } catch (\Throwable $e) {
                $this->migrationRepo->addJobLog($jobPublicId, 'warning', 'import_versions', "Failed versions/components for {$projectKey}: " . $e->getMessage());
            }
        }
    }

    // ── Helpers ──

    private function mapJiraStatusToCrm(string $jiraStatus): string
    {
        $map = [
            'To Do' => 'new',
            'Backlog' => 'new',
            'Open' => 'new',
            'In Progress' => 'in_progress',
            'In Review' => 'review',
            'Review' => 'review',
            'Done' => 'done',
            'Closed' => 'done',
            'Resolved' => 'done',
            'Canceled' => 'cancelled',
            'Cancelled' => 'cancelled',
            'Blocked' => 'on_hold',
        ];
        return $map[$jiraStatus] ?? 'new';
    }

    private function mapJiraPriorityToCrm(string $jiraPriority): string
    {
        $map = [
            'Highest' => 'urgent',
            'High' => 'high',
            'Medium' => 'normal',
            'Low' => 'low',
            'Lowest' => 'low',
            'Blocker' => 'urgent',
            'Critical' => 'urgent',
            'Major' => 'high',
            'Minor' => 'low',
            'Trivial' => 'low',
        ];
        return $map[$jiraPriority] ?? 'normal';
    }

    private function mapJiraLinkType(string $jiraType): string
    {
        $map = [
            'blocks' => 'blocked_by',
            'is blocked by' => 'blocked_by',
            'depends upon' => 'blocked_by',
            'is depended upon by' => 'relates_to',
            'relates to' => 'relates_to',
            'duplicates' => 'duplicate',
            'is duplicated by' => 'duplicate',
            'cloners' => 'relates_to',
            'is cloned by' => 'relates_to',
            'parent of' => 'parent_of',
            'child of' => 'parent_of',
        ];
        // Reverse direction for outward links
        return $map[strtolower($jiraType)] ?? 'relates_to';
    }

    private function resolveUser(string $accountId): ?string
    {
        // For MVP, return null (use mapping later)
        return null;
    }

    private function ensureTag(string $labelName, string $taskPublicId): void
    {
        try {
            // Find or create tag
            $check = $this->pdo->prepare("SELECT id, public_id FROM tags WHERE code = :code LIMIT 1");
            $check->execute(['code' => $labelName]);
            $tag = $check->fetch(PDO::FETCH_ASSOC);

            if (!$tag) {
                $tagPublicId = 'tag_' . bin2hex(random_bytes(10));
                $now = gmdate('Y-m-d H:i:s');
                $this->pdo->prepare("INSERT INTO tags (public_id, code, title, color, created_at) VALUES (:pub, :code, :title, :color, :now)")
                    ->execute(['pub' => $tagPublicId, 'code' => $labelName, 'title' => $labelName, 'color' => '#6b7280', 'now' => $now]);
                $tagId = (int)$this->pdo->lastInsertId();
            } else {
                $tagId = (int)$tag['id'];
            }

            // Attach to task
            $taskId = $this->pdo->prepare("SELECT id FROM tasks WHERE public_id = :pub LIMIT 1");
            $taskId->execute(['pub' => $taskPublicId]);
            $taskIdVal = (int)$taskId->fetchColumn();

            if ($taskIdVal > 0) {
                $this->pdo->prepare("INSERT IGNORE INTO entity_tags (entity_type, entity_public_id, tag_id) VALUES ('task', :entity_pub_id, :tag_id)")
                    ->execute(['entity_pub_id' => $taskPublicId, 'tag_id' => $tagId]);
            }
        } catch (\Throwable) {
        }
    }
}
