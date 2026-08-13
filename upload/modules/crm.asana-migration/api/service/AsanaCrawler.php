<?php
declare(strict_types=1);

namespace Module\Crm\AsanaMigration\Service;

use Module\Crm\AsanaMigration\Repository\AsanaMigrationRepository;
use RuntimeException;

final class AsanaCrawler
{
    public function __construct(private readonly AsanaClient $client, private readonly AsanaMigrationRepository $repo)
    {
    }

    /** @return array<string,mixed> */
    public function crawl(array $job, string $token, ?callable $heartbeat = null): array
    {
        $scope = (array)($job['source_scope'] ?? []);
        $workspace = (string)($job['workspace_gid'] ?? '');
        if ($workspace === '') throw new RuntimeException('ASANA_WORKSPACE_REQUIRED');
        $options = (array)($job['target_options'] ?? []);
        $selected = array_values(array_filter(array_map('strval', (array)($scope['project_gids'] ?? []))));
        $includeArchived = (bool)($scope['include_archived_projects'] ?? $options['include_archived_projects'] ?? false);
        $maxTasks = max(0, (int)($scope['max_tasks'] ?? $options['max_tasks'] ?? 0));
        $includeComments = array_key_exists('include_comments', $scope) ? (bool)$scope['include_comments'] : (bool)($options['include_comments'] ?? true);
        $includeAttachments = array_key_exists('include_attachments', $scope) ? (bool)$scope['include_attachments'] : (bool)($options['include_attachments'] ?? false);
        $includeArchivedTasks = (bool)($scope['include_archived_tasks'] ?? $options['include_archived_tasks'] ?? false);
        $maxDepth = max(1, min(20, (int)($scope['max_subtask_depth'] ?? 10)));

        $stats = ['projects' => 0, 'sections' => 0, 'tasks' => 0, 'subtasks' => 0, 'comments' => 0, 'attachments' => 0, 'dependencies' => 0, 'users' => 0, 'tags' => 0, 'warnings' => []];
        foreach ($this->client->users($token, $workspace) as $user) { if (!empty($user['gid'])) { $this->repo->upsertUserMapping((int)$job['connection_id'], $user); $stats['users']++; } }
        foreach ($this->client->tags($token, $workspace) as $tag) { if (!empty($tag['gid'])) { $this->repo->upsertItem((int)$job['id'], 'tag', (string)$tag['gid'], ['status'=>'pending','checksum'=>$this->checksum($tag),'payload_json'=>$tag]); $stats['tags']++; } }

        $projects = $this->client->projects($token, $workspace, $includeArchived);
        if ($selected !== []) $projects = array_values(array_filter($projects, static fn(array $p): bool => in_array((string)($p['gid'] ?? ''), $selected, true)));
        $seenTasks = [];
        foreach ($projects as $project) {
            if ($heartbeat !== null && !$heartbeat()) throw new RuntimeException('ASANA_JOB_LEASE_LOST');
            $projectGid = (string)($project['gid'] ?? ''); if ($projectGid === '') continue;
            $stats['projects']++;
            $this->repo->upsertItem((int)$job['id'], 'project', $projectGid, ['status'=>'pending','checksum'=>$this->checksum($project),'payload_json'=>$project]);
            foreach ($this->client->sections($token, $projectGid) as $section) {
                $sectionGid = (string)($section['gid'] ?? ''); if ($sectionGid === '') continue;
                $this->repo->upsertItem((int)$job['id'], 'section', $sectionGid, ['source_project_id'=>$projectGid,'status'=>'pending','checksum'=>$this->checksum($section),'payload_json'=>$section]); $stats['sections']++;
            }
            try {
                $tasks = $this->client->tasks($token, $projectGid, $includeArchivedTasks);
                foreach ($tasks as $task) {
                    if ($maxTasks > 0 && $stats['tasks'] >= $maxTasks) break 2;
                    $this->storeTaskTree($job, $token, $task, $projectGid, 0, $maxDepth, $seenTasks, $stats, $includeComments, $includeAttachments, $heartbeat, $maxTasks);
                }
            } catch (\Throwable $e) {
                if (in_array($e->getMessage(), ['ASANA_JOB_LEASE_LOST', 'ASANA_COLLECTION_LIMIT_EXCEEDED'], true)) throw $e;
                $stats['warnings'][] = 'Project ' . $projectGid . ' tasks could not be fully loaded.';
                $this->repo->addLog((int)$job['id'], 'warning', 'crawl', 'Project discovery failed.', ['project_gid' => $projectGid]);
            }
        }
        return $stats;
    }

    /** @param array<string,bool> $seenTasks @param array<string,int> $stats */
    private function storeTaskTree(array $job, string $token, array $task, string $projectGid, int $depth, int $maxDepth, array &$seenTasks, array &$stats, bool $comments, bool $attachments, ?callable $heartbeat, int $maxTasks): void
    {
        $gid = (string)($task['gid'] ?? ''); if ($gid === '' || isset($seenTasks[$gid])) return;
        if ($maxTasks > 0 && $stats['tasks'] >= $maxTasks) return;
        $seenTasks[$gid] = true;
        $parentGid = is_array($task['parent'] ?? null) ? (string)($task['parent']['gid'] ?? '') : '';
        $type = $parentGid !== '' ? 'subtask' : 'task';
        $this->repo->upsertItem((int)$job['id'], $type, $gid, ['source_parent_id'=>$parentGid !== '' ? $parentGid : null,'source_project_id'=>$projectGid,'status'=>'pending','checksum'=>$this->checksum($task),'source_updated_at'=>$this->date((string)($task['modified_at'] ?? '')),'payload_json'=>$task]);
        $stats['tasks']++; if ($type === 'subtask') $stats['subtasks']++;
        foreach ((array)($task['dependencies'] ?? []) as $dependency) {
            $dependencyGid = is_array($dependency) ? (string)($dependency['gid'] ?? '') : (string)$dependency;
            if ($dependencyGid === '' || $dependencyGid === $gid) continue;
            $this->repo->upsertItem((int)$job['id'], 'dependency', $gid . ':' . $dependencyGid, [
                'source_parent_id' => $gid,
                'source_project_id' => $projectGid,
                'status' => 'pending',
                'checksum' => $this->checksum(['task' => $gid, 'depends_on' => $dependencyGid]),
                'payload_json' => ['source_task_gid' => $gid, 'depends_on_task_gid' => $dependencyGid, 'dependency_type' => 'FS'],
            ]);
            $stats['dependencies']++;
        }
        if ($comments) foreach ($this->client->stories($token, $gid) as $story) {
            if ((string)($story['resource_subtype'] ?? '') !== 'comment_added') continue;
            $storyGid=(string)($story['gid']??''); if($storyGid==='')continue;
            $this->repo->upsertItem((int)$job['id'],'comment',$storyGid,['source_parent_id'=>$gid,'status'=>'pending','checksum'=>$this->checksum($story),'payload_json'=>$story]); $stats['comments']++;
        }
        if ($attachments) foreach ($this->client->attachments($token, $gid) as $attachment) {
            $attachmentGid=(string)($attachment['gid']??''); if($attachmentGid==='')continue;
            $this->repo->upsertItem((int)$job['id'],'attachment',$attachmentGid,['source_parent_id'=>$gid,'status'=>'pending','checksum'=>$this->checksum($attachment),'payload_json'=>$attachment]); $stats['attachments']++;
        }
        if ($depth >= $maxDepth) return;
        if ($heartbeat !== null && !$heartbeat()) throw new RuntimeException('ASANA_JOB_LEASE_LOST');
        foreach ($this->client->subtasks($token, $gid) as $child) $this->storeTaskTree($job, $token, $child, $projectGid, $depth + 1, $maxDepth, $seenTasks, $stats, $comments, $attachments, $heartbeat, $maxTasks);
    }

    private function checksum(array $payload): string { return hash('sha256', (string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)); }
    private function date(string $value): ?string { if ($value==='') return null; $time=strtotime($value); return $time===false?null:gmdate('Y-m-d H:i:s',$time); }
}
