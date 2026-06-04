<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Import\ImportJobRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Support\Ulid;
use RuntimeException;
use Throwable;

final class ImportService
{
    private const TASK_STATUSES = ['new', 'in_progress', 'blocked', 'done'];
    private const TASK_PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    private const RETRY_MAX_ATTEMPTS = 3;
    private const RETRY_BACKOFF_SEC = 30;

    public function __construct(
        private readonly ImportJobRepository $imports,
        private readonly ProjectService $projects,
        private readonly TaskService $tasks,
        private readonly JsonLogger $logger
    ) {
    }

    public function list(array $filters, array $actor): array
    {
        [$items, $total, $page, $limit] = $this->imports->list(
            $filters,
            (int)($actor['id'] ?? 0),
            (bool)($actor['is_root'] ?? false)
        );

        return [
            'items' => array_map([$this, 'normalizeJob'], $items),
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
            ],
        ];
    }

    public function create(array $input, array $actor): array
    {
        $publicId = Ulid::generate('imp');
        $now = gmdate('Y-m-d H:i:s');
        $type = (string)$input['type'];

        $isAsync = ((string)($input['async'] ?? '0') === '1' || (bool)($input['async'] ?? false) === true);
        $payload = [
            'type' => $type,
            'format' => $this->detectFormat($input),
            'delimiter' => (string)($input['delimiter'] ?? ','),
            'has_header' => !array_key_exists('has_header', $input) || (bool)$input['has_header'],
            'rows_count_hint' => is_array($input['rows'] ?? null) ? count((array)$input['rows']) : null,
            'source_input' => $input,
        ];
        $this->imports->create([
            'public_id' => $publicId,
            'user_id' => (int)($actor['id'] ?? 0),
            'type' => $type,
            'status' => 'queued',
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'result' => json_encode(['summary' => ['processed' => 0, 'created' => 0, 'failed' => 0]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'attempts' => 0,
            'next_run_at' => $now,
            'locked_at' => null,
            'started_at' => null,
            'finished_at' => null,
            'last_error' => null,
            'dead_letter' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($isAsync) {
            return $this->get($publicId, $actor) ?? ['job' => ['public_id' => $publicId]];
        }

        try {
            $result = $this->execute($type, $input, $actor);
            $status = (int)($result['summary']['failed'] ?? 0) > 0 ? 'completed_with_errors' : 'completed';

            $this->imports->updateByPublicId($publicId, [
                'status' => $status,
                'result' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'locked_at' => null,
                'next_run_at' => null,
                'finished_at' => gmdate('Y-m-d H:i:s'),
                'last_error' => null,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);

            $this->logger->audit([
                'action' => 'import_job_create',
                'actor_public_id' => $actor['public_id'] ?? null,
                'entity_type' => 'import_job',
                'entity_public_id' => $publicId,
                'import_type' => $type,
                'status' => $status,
                'summary' => $result['summary'] ?? [],
            ]);
        } catch (Throwable $e) {
            $errorResult = [
                'summary' => [
                    'processed' => 0,
                    'created' => 0,
                    'failed' => 1,
                ],
                'errors' => [
                    ['line' => 0, 'message' => $e->getMessage()],
                ],
            ];
            $this->imports->updateByPublicId($publicId, [
                'status' => 'retry',
                'result' => json_encode($errorResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'attempts' => 1,
                'next_run_at' => gmdate('Y-m-d H:i:s', time() + self::RETRY_BACKOFF_SEC),
                'locked_at' => null,
                'last_error' => $e->getMessage(),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);

            $this->logger->error([
                'action' => 'import_job_failed',
                'actor_public_id' => $actor['public_id'] ?? null,
                'entity_type' => 'import_job',
                'entity_public_id' => $publicId,
                'message' => $e->getMessage(),
            ]);
        }

        return $this->get($publicId, $actor) ?? ['job' => ['public_id' => $publicId]];
    }

    public function runQueued(int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));
        $processed = 0;
        $completed = 0;
        $retried = 0;
        $deadLettered = 0;
        $failed = 0;
        $errors = [];

        for ($i = 0; $i < $limit; $i++) {
            $now = gmdate('Y-m-d H:i:s');
            $job = $this->imports->claimNextRunnable($now);
            if (!is_array($job)) {
                break;
            }
            $processed++;

            $publicId = (string)($job['public_id'] ?? '');
            $payload = $this->decodeJson((string)($job['payload'] ?? ''));
            $sourceInput = is_array($payload['source_input'] ?? null) ? $payload['source_input'] : [];
            $type = trim((string)($job['type'] ?? ''));

            try {
                if ($sourceInput === [] || $type === '') {
                    throw new RuntimeException('IMPORT_JOB_PAYLOAD_INVALID');
                }

                $result = $this->execute($type, $sourceInput, ['id' => (int)($job['user_id'] ?? 0)]);
                $status = (int)($result['summary']['failed'] ?? 0) > 0 ? 'completed_with_errors' : 'completed';
                $this->imports->updateByPublicId($publicId, [
                    'status' => $status,
                    'result' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'locked_at' => null,
                    'next_run_at' => null,
                    'finished_at' => gmdate('Y-m-d H:i:s'),
                    'last_error' => null,
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
                $completed++;
            } catch (Throwable $e) {
                $attempts = (int)($job['attempts'] ?? 0) + 1;
                $isDead = $attempts >= self::RETRY_MAX_ATTEMPTS;
                $this->imports->updateByPublicId($publicId, [
                    'attempts' => $attempts,
                    'status' => $isDead ? 'dead_letter' : 'retry',
                    'dead_letter' => $isDead ? 1 : 0,
                    'next_run_at' => $isDead ? null : gmdate('Y-m-d H:i:s', time() + self::RETRY_BACKOFF_SEC * $attempts),
                    'locked_at' => null,
                    'last_error' => $e->getMessage(),
                    'finished_at' => $isDead ? gmdate('Y-m-d H:i:s') : null,
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
                if ($isDead) {
                    $deadLettered++;
                } else {
                    $retried++;
                }
                $failed++;
                $errors[] = ['public_id' => $publicId, 'error' => $e->getMessage()];
            }
        }

        return [
            'processed' => $processed,
            'completed' => $completed,
            'retried' => $retried,
            'dead_lettered' => $deadLettered,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    public function cancel(string $publicId, array $actor): array
    {
        $job = $this->imports->findByPublicId($publicId);
        if (!$job || !$this->canAccess($job, $actor)) {
            return ['ok' => false, 'code' => 'IMPORT_JOB_NOT_FOUND'];
        }

        $status = strtolower(trim((string)($job['status'] ?? '')));
        if (!in_array($status, ['queued', 'processing', 'running'], true)) {
            return ['ok' => false, 'code' => 'IMPORT_JOB_CANCEL_NOT_ALLOWED'];
        }

        $this->imports->updateByPublicId($publicId, [
            'status' => 'cancelled',
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $updated = $this->imports->findByPublicId($publicId);
        return ['ok' => true, 'job' => $updated ? $this->normalizeJob($updated) : ['public_id' => $publicId]];
    }

    public function retry(string $publicId, array $actor): array
    {
        $job = $this->imports->findByPublicId($publicId);
        if (!$job || !$this->canAccess($job, $actor)) {
            return ['ok' => false, 'code' => 'IMPORT_JOB_NOT_FOUND'];
        }

        $status = strtolower(trim((string)($job['status'] ?? '')));
        if (in_array($status, ['queued', 'processing', 'running'], true)) {
            return ['ok' => false, 'code' => 'IMPORT_JOB_RETRY_NOT_ALLOWED'];
        }

        $payload = $this->decodeJson((string)($job['payload'] ?? ''));
        $sourceInput = is_array($payload['source_input'] ?? null) ? $payload['source_input'] : [];
        if ($sourceInput === []) {
            return ['ok' => false, 'code' => 'IMPORT_JOB_NOT_RETRYABLE'];
        }

        $created = $this->create($sourceInput, $actor);
        return ['ok' => true, 'job' => is_array($created['job'] ?? null) ? $created['job'] : []];
    }

    public function get(string $publicId, array $actor): ?array
    {
        $job = $this->imports->findByPublicId($publicId);
        if (!$job || !$this->canAccess($job, $actor)) {
            return null;
        }

        return ['job' => $this->normalizeJob($job)];
    }

    private function execute(string $type, array $input, array $actor): array
    {
        $rows = $this->extractRows($input);

        return match ($type) {
            'projects' => $this->importProjects($rows, $actor),
            'tasks' => $this->importTasks($rows, $actor),
            default => throw new RuntimeException('IMPORT_TYPE_UNSUPPORTED'),
        };
    }

    private function detectFormat(array $input): string
    {
        if (is_array($input['rows'] ?? null)) {
            return 'json_rows';
        }

        return (string)($input['format'] ?? 'csv');
    }

    /** @return array<int,array<string,mixed>> */
    private function extractRows(array $input): array
    {
        if (is_array($input['rows'] ?? null)) {
            return array_values(array_filter(
                array_map(static fn($row): array => is_array($row) ? $row : [], (array)$input['rows']),
                static fn(array $row): bool => $row !== []
            ));
        }

        $base64 = (string)($input['content_base64'] ?? '');
        if ($base64 === '') {
            throw new RuntimeException('IMPORT_CONTENT_REQUIRED');
        }

        $decoded = base64_decode($base64, true);
        if ($decoded === false || $decoded === '') {
            throw new RuntimeException('IMPORT_CONTENT_INVALID');
        }

        $delimiter = (string)($input['delimiter'] ?? ',');
        if ($delimiter === '') {
            $delimiter = ',';
        }

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('IMPORT_STREAM_ERROR');
        }

        fwrite($stream, $decoded);
        rewind($stream);

        $headers = [];
        $rows = [];
        $hasHeader = !array_key_exists('has_header', $input) || (bool)$input['has_header'];
        $providedColumns = array_values(array_filter(
            array_map(static fn($value): string => trim((string)$value), (array)($input['columns'] ?? [])),
            static fn(string $value): bool => $value !== ''
        ));

        while (($row = fgetcsv($stream, 0, $delimiter, '"', '\\')) !== false) {
            $row = array_map(static fn($value): string => trim((string)$value), $row);
            if ($row === [] || count(array_filter($row, static fn(string $value): bool => $value !== '')) === 0) {
                continue;
            }

            if ($headers === []) {
                if ($hasHeader) {
                    $headers = $row;
                    continue;
                }

                if ($providedColumns === []) {
                    throw new RuntimeException('IMPORT_COLUMNS_REQUIRED');
                }

                $headers = $providedColumns;
            }

            $mapped = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $mapped[$header] = $row[$index] ?? '';
            }

            if ($mapped !== []) {
                $rows[] = $mapped;
            }
        }

        fclose($stream);

        return $rows;
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function importProjects(array $rows, array $actor): array
    {
        $created = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 1;
            $title = trim((string)($row['title'] ?? $row['name'] ?? ''));
            if ($title === '') {
                $errors[] = ['line' => $line, 'message' => 'Project title is required'];
                continue;
            }

            $item = $this->projects->create([
                'title' => $title,
                'description' => trim((string)($row['description'] ?? '')),
                'status' => (string)($row['status'] ?? 'active'),
                'priority' => (string)($row['priority'] ?? 'normal'),
                'client_public_id' => (string)($row['client_public_id'] ?? ''),
            ], $actor);

            $created[] = [
                'public_id' => (string)($item['public_id'] ?? ''),
                'title' => (string)($item['title'] ?? $title),
            ];
        }

        return [
            'summary' => [
                'processed' => count($rows),
                'created' => count($created),
                'failed' => count($errors),
            ],
            'created_items' => $created,
            'errors' => $errors,
        ];
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function importTasks(array $rows, array $actor): array
    {
        $created = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 1;
            $title = trim((string)($row['title'] ?? $row['name'] ?? ''));
            if ($title === '') {
                $errors[] = ['line' => $line, 'message' => 'Task title is required'];
                continue;
            }

            $status = (string)($row['status'] ?? 'new');
            if (!in_array($status, self::TASK_STATUSES, true)) {
                $errors[] = ['line' => $line, 'message' => 'Invalid task status'];
                continue;
            }

            $priority = (string)($row['priority'] ?? 'normal');
            if (!in_array($priority, self::TASK_PRIORITIES, true)) {
                $errors[] = ['line' => $line, 'message' => 'Invalid task priority'];
                continue;
            }

            $dueAt = trim((string)($row['due_at'] ?? ''));
            if ($dueAt !== '' && strtotime($dueAt) === false) {
                $errors[] = ['line' => $line, 'message' => 'Invalid due_at format'];
                continue;
            }

            $projectPublicId = trim((string)($row['project_public_id'] ?? ''));
            if ($projectPublicId !== '' && $this->projects->get($projectPublicId, $actor) === null) {
                $errors[] = ['line' => $line, 'message' => 'Project not found or access denied'];
                continue;
            }

            $item = $this->tasks->create([
                'title' => $title,
                'description' => trim((string)($row['description'] ?? '')),
                'status' => $status,
                'priority' => $priority,
                'project_public_id' => $projectPublicId,
                'due_at' => $dueAt,
                'start_at' => trim((string)($row['start_at'] ?? '')),
                'end_at' => trim((string)($row['end_at'] ?? '')),
            ], $actor);

            $created[] = [
                'public_id' => (string)($item['public_id'] ?? ''),
                'title' => (string)($item['title'] ?? $title),
                'project_public_id' => (string)($item['project_public_id'] ?? ''),
            ];
        }

        return [
            'summary' => [
                'processed' => count($rows),
                'created' => count($created),
                'failed' => count($errors),
            ],
            'created_items' => $created,
            'errors' => $errors,
        ];
    }

    private function canAccess(array $job, array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        return (int)($job['user_id'] ?? 0) === (int)($actor['id'] ?? 0);
    }

    /** @param array<string,mixed> $job */
    private function normalizeJob(array $job): array
    {
        return [
            'public_id' => (string)($job['public_id'] ?? ''),
            'type' => (string)($job['type'] ?? ''),
            'status' => (string)($job['status'] ?? ''),
            'attempts' => (int)($job['attempts'] ?? 0),
            'next_run_at' => (string)($job['next_run_at'] ?? ''),
            'locked_at' => (string)($job['locked_at'] ?? ''),
            'started_at' => (string)($job['started_at'] ?? ''),
            'finished_at' => (string)($job['finished_at'] ?? ''),
            'last_error' => (string)($job['last_error'] ?? ''),
            'dead_letter' => (int)($job['dead_letter'] ?? 0),
            'payload' => $this->decodeJson((string)($job['payload'] ?? '')),
            'result' => $this->decodeJson((string)($job['result'] ?? '')),
            'user' => [
                'public_id' => (string)($job['user_public_id'] ?? ''),
                'login' => (string)($job['user_login'] ?? ''),
                'full_name' => (string)($job['user_full_name'] ?? ''),
            ],
            'created_at' => (string)($job['created_at'] ?? ''),
            'updated_at' => (string)($job['updated_at'] ?? ''),
        ];
    }

    /** @return array<string,mixed> */
    private function decodeJson(string $json): array
    {
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
