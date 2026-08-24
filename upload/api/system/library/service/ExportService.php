<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Export\ExportJobRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Support\Ulid;
use RuntimeException;
use Throwable;

final class ExportService
{
    private const EXPORT_LIMIT = 5000;
    private const PAGE_LIMIT = 100;
    private const RETRY_MAX_ATTEMPTS = 3;
    private const RETRY_BACKOFF_SEC = 30;

    public function __construct(
        private readonly ExportJobRepository $exports,
        private readonly ProjectService $projects,
        private readonly TaskService $tasks,
        private readonly JsonLogger $logger,
        private readonly string $basePath,
        private readonly string $storageBase
    ) {
    }

    public function list(array $filters, array $actor): array
    {
        [$items, $total, $page, $limit] = $this->exports->list(
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
        $publicId = Ulid::generate('exp');
        $now = gmdate('Y-m-d H:i:s');
        $type = (string)$input['type'];

        $isAsync = ((string)($input['async'] ?? '0') === '1' || (bool)($input['async'] ?? false) === true);
        $payload = [
            'type' => $type,
            'format' => 'csv',
            'filters' => is_array($input['filters'] ?? null) ? $input['filters'] : [],
            'source_input' => $input,
        ];
        $this->exports->create([
            'public_id' => $publicId,
            'user_id' => (int)($actor['id'] ?? 0),
            'type' => $type,
            'status' => 'queued',
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'result' => json_encode(['summary' => ['rows_total' => 0]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
            $rows = $this->collectRows($type, (array)($input['filters'] ?? []), $actor);
            $csv = $this->buildCsv($rows);

            $directory = rtrim($this->storageBase, '/') . '/generated/exports';
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('EXPORT_STORAGE_CREATE_FAILED');
            }

            $fileName = $type . '_export_' . $publicId . '.csv';
            $filePath = $directory . '/' . $fileName;
            file_put_contents($filePath, $csv);

            $result = [
                'summary' => [
                    'rows_total' => count($rows),
                    'columns' => array_keys($rows[0] ?? []),
                    'download_available' => true,
                ],
                'file' => [
                    'name' => $fileName,
                    'mime' => 'text/csv; charset=utf-8',
                    'size_bytes' => filesize($filePath) ?: strlen($csv),
                    'path' => $filePath,
                ],
            ];

            $this->exports->updateByPublicId($publicId, [
                'status' => 'completed',
                'result' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'locked_at' => null,
                'next_run_at' => null,
                'finished_at' => gmdate('Y-m-d H:i:s'),
                'last_error' => null,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);

            $this->logger->audit([
                'action' => 'export_job_create',
                'actor_public_id' => $actor['public_id'] ?? null,
                'entity_type' => 'export_job',
                'entity_public_id' => $publicId,
                'export_type' => $type,
                'rows_total' => count($rows),
            ]);
        } catch (Throwable $e) {
            error_log('[ExportService::create] ' . $e->getMessage());
            $this->exports->updateByPublicId($publicId, [
                'status' => 'retry',
                'result' => json_encode([
                    'summary' => [
                        'rows_total' => 0,
                        'download_available' => false,
                    ],
                    'errors' => [
                        ['message' => 'Export job failed. Check server logs for details.'],
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'attempts' => 1,
                'next_run_at' => gmdate('Y-m-d H:i:s', time() + self::RETRY_BACKOFF_SEC),
                'locked_at' => null,
                'last_error' => 'Export job failed.',
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);

            $this->logger->error([
                'action' => 'export_job_failed',
                'actor_public_id' => $actor['public_id'] ?? null,
                'entity_type' => 'export_job',
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
            $job = $this->exports->claimNextRunnable($now);
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
                    throw new RuntimeException('EXPORT_JOB_PAYLOAD_INVALID');
                }

                $rows = $this->collectRows($type, (array)($sourceInput['filters'] ?? []), ['id' => (int)($job['user_id'] ?? 0)]);
                $csv = $this->buildCsv($rows);
                $directory = rtrim($this->storageBase, '/') . '/generated/exports';
                if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                    throw new RuntimeException('EXPORT_STORAGE_CREATE_FAILED');
                }
                $fileName = $type . '_export_' . $publicId . '.csv';
                $filePath = $directory . '/' . $fileName;
                file_put_contents($filePath, $csv);

                $result = [
                    'summary' => [
                        'rows_total' => count($rows),
                        'columns' => array_keys($rows[0] ?? []),
                        'download_available' => true,
                    ],
                    'file' => [
                        'name' => $fileName,
                        'mime' => 'text/csv; charset=utf-8',
                        'size_bytes' => filesize($filePath) ?: strlen($csv),
                        'path' => $filePath,
                    ],
                ];

                $this->exports->updateByPublicId($publicId, [
                    'status' => 'completed',
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
                error_log('[ExportService::runQueued] job=' . $publicId . ' ' . $e->getMessage());
                $this->exports->updateByPublicId($publicId, [
                    'attempts' => $attempts,
                    'status' => $isDead ? 'dead_letter' : 'retry',
                    'dead_letter' => $isDead ? 1 : 0,
                    'next_run_at' => $isDead ? null : gmdate('Y-m-d H:i:s', time() + self::RETRY_BACKOFF_SEC * $attempts),
                    'locked_at' => null,
                    'last_error' => 'Export job failed.',
                    'finished_at' => $isDead ? gmdate('Y-m-d H:i:s') : null,
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
                if ($isDead) {
                    $deadLettered++;
                } else {
                    $retried++;
                }
                $failed++;
                $errors[] = ['public_id' => $publicId, 'error' => 'Export job failed. Check server logs for details.'];
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
        $job = $this->exports->findByPublicId($publicId);
        if (!$job || !$this->canAccess($job, $actor)) {
            return ['ok' => false, 'code' => 'EXPORT_JOB_NOT_FOUND'];
        }

        $status = strtolower(trim((string)($job['status'] ?? '')));
        if (!in_array($status, ['queued', 'processing', 'running'], true)) {
            return ['ok' => false, 'code' => 'EXPORT_JOB_CANCEL_NOT_ALLOWED'];
        }

        $this->exports->updateByPublicId($publicId, [
            'status' => 'cancelled',
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $updated = $this->exports->findByPublicId($publicId);
        return ['ok' => true, 'job' => $updated ? $this->normalizeJob($updated) : ['public_id' => $publicId]];
    }

    public function retry(string $publicId, array $actor): array
    {
        $job = $this->exports->findByPublicId($publicId);
        if (!$job || !$this->canAccess($job, $actor)) {
            return ['ok' => false, 'code' => 'EXPORT_JOB_NOT_FOUND'];
        }

        $status = strtolower(trim((string)($job['status'] ?? '')));
        if (in_array($status, ['queued', 'processing', 'running'], true)) {
            return ['ok' => false, 'code' => 'EXPORT_JOB_RETRY_NOT_ALLOWED'];
        }

        $payload = $this->decodeJson((string)($job['payload'] ?? ''));
        $sourceInput = is_array($payload['source_input'] ?? null) ? $payload['source_input'] : [];
        if ($sourceInput === []) {
            return ['ok' => false, 'code' => 'EXPORT_JOB_NOT_RETRYABLE'];
        }

        $created = $this->create($sourceInput, $actor);
        return ['ok' => true, 'job' => is_array($created['job'] ?? null) ? $created['job'] : []];
    }

    public function get(string $publicId, array $actor): ?array
    {
        $job = $this->exports->findByPublicId($publicId);
        if (!$job || !$this->canAccess($job, $actor)) {
            return null;
        }

        return ['job' => $this->normalizeJob($job)];
    }

    public function download(string $publicId, array $actor): array
    {
        $job = $this->exports->findByPublicId($publicId);
        if (!$job || !$this->canAccess($job, $actor)) {
            return ['error' => 'EXPORT_JOB_NOT_FOUND'];
        }

        $result = $this->decodeJson((string)($job['result'] ?? ''));
        $file = is_array($result['file'] ?? null) ? $result['file'] : [];
        $path = (string)($file['path'] ?? '');
        if ($path === '' || !is_file($path)) {
            return ['error' => 'EXPORT_FILE_NOT_FOUND'];
        }

        $this->logger->audit([
            'action' => 'export_job_download',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'export_job',
            'entity_public_id' => $publicId,
        ]);

        return [
            'path' => $path,
            'name' => (string)($file['name'] ?? basename($path)),
            'mime' => (string)($file['mime'] ?? 'text/csv; charset=utf-8'),
            'size' => (int)($file['size_bytes'] ?? filesize($path)),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function collectRows(string $type, array $filters, array $actor): array
    {
        return match ($type) {
            'projects' => $this->collectProjectRows($filters, $actor),
            'tasks' => $this->collectTaskRows($filters, $actor),
            default => throw new RuntimeException('EXPORT_TYPE_UNSUPPORTED'),
        };
    }

    /** @return array<int,array<string,mixed>> */
    private function collectProjectRows(array $filters, array $actor): array
    {
        $page = 1;
        $rows = [];

        while (count($rows) < self::EXPORT_LIMIT) {
            $result = $this->projects->list(array_merge($filters, [
                'page' => $page,
                'limit' => self::PAGE_LIMIT,
                'pagination_mode' => 'offset',
            ]), $actor);
            $items = (array)($result['items'] ?? []);
            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                $rows[] = [
                    'public_id' => (string)($item['public_id'] ?? ''),
                    'title' => (string)($item['title'] ?? ''),
                    'description' => (string)($item['description'] ?? ''),
                    'status' => (string)($item['status_code'] ?? ''),
                    'priority' => (string)($item['priority_code'] ?? ''),
                    'client_public_id' => (string)($item['client_public_id'] ?? ''),
                    'archived_at' => (string)($item['archived_at'] ?? ''),
                    'created_at' => (string)($item['created_at'] ?? ''),
                    'updated_at' => (string)($item['updated_at'] ?? ''),
                    'row_version' => (string)($item['row_version'] ?? ''),
                ];

                if (count($rows) >= self::EXPORT_LIMIT) {
                    break 2;
                }
            }

            if (count($items) < self::PAGE_LIMIT) {
                break;
            }

            $page++;
        }

        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    private function collectTaskRows(array $filters, array $actor): array
    {
        $page = 1;
        $rows = [];

        while (count($rows) < self::EXPORT_LIMIT) {
            $result = $this->tasks->list(array_merge($filters, [
                'page' => $page,
                'limit' => self::PAGE_LIMIT,
                'pagination_mode' => 'offset',
            ]), $actor);
            $items = (array)($result['items'] ?? []);
            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                $rows[] = [
                    'public_id' => (string)($item['public_id'] ?? ''),
                    'title' => (string)($item['title'] ?? ''),
                    'description' => (string)($item['description'] ?? ''),
                    'status' => (string)($item['status_code'] ?? ''),
                    'priority' => (string)($item['priority_code'] ?? ''),
                    'project_public_id' => (string)($item['project_public_id'] ?? ''),
                    'project_title' => (string)($item['project_title'] ?? ''),
                    'due_at' => (string)($item['due_at'] ?? ''),
                    'created_at' => (string)($item['created_at'] ?? ''),
                    'updated_at' => (string)($item['updated_at'] ?? ''),
                    'row_version' => (string)($item['row_version'] ?? ''),
                ];

                if (count($rows) >= self::EXPORT_LIMIT) {
                    break 2;
                }
            }

            if (count($items) < self::PAGE_LIMIT) {
                break;
            }

            $page++;
        }

        return $rows;
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function buildCsv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('EXPORT_STREAM_ERROR');
        }

        fwrite($stream, "\xEF\xBB\xBF");

        $headers = array_keys($rows[0] ?? []);
        if ($headers !== []) {
            fputcsv($stream, $headers, ',', '"', '\\');
            foreach ($rows as $row) {
                $line = [];
                foreach ($headers as $header) {
                    $val = (string)($row[$header] ?? '');
                    // L-14 fix: prefix formula-triggering characters to prevent
                    // CSV injection when opened in Excel/LibreOffice.
                    if ($val !== '' && in_array($val[0], ['=', '+', '-', '@', '|', '%'], true)) {
                        $val = "'" . $val;
                    }
                    $line[] = $val;
                }
                fputcsv($stream, $line, ',', '"', '\\');
            }
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return is_string($csv) ? $csv : '';
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
        $result = $this->decodeJson((string)($job['result'] ?? ''));
        $file = is_array($result['file'] ?? null) ? $result['file'] : [];

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
            'result' => [
                'summary' => is_array($result['summary'] ?? null) ? $result['summary'] : [],
                'errors' => is_array($result['errors'] ?? null) ? $result['errors'] : [],
                'file' => [
                    'name' => (string)($file['name'] ?? ''),
                    'mime' => (string)($file['mime'] ?? ''),
                    'size_bytes' => (int)($file['size_bytes'] ?? 0),
                    'download_url' => '/api/index.php?route=api/v1/export/jobs/' . rawurlencode((string)($job['public_id'] ?? '')) . '/download',
                ],
            ],
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
