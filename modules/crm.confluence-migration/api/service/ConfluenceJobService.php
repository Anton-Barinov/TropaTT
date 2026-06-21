<?php
declare(strict_types=1);

namespace Module\Crm\ConfluenceMigration\Service;

use Api\Model\Knowledge\KnowledgeRepository;
use Api\Model\Tag\TagRepository;
use Api\System\Library\Service\FileService;
use Module\Crm\ConfluenceMigration\Repository\ConfluenceMigrationRepository;
use PDO;

final class ConfluenceJobService
{
    public function __construct(
        private ConfluenceMigrationRepository $migrationRepo,
        private KnowledgeRepository $knowledgeRepo,
        private FileService $fileService,
        private TagRepository $tagRepo,
        private PDO $pdo,
    ) {
    }

    public function claimNextRunnable(): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT j.*, c.base_url, c.email, c.token_encrypted, c.name AS connection_name
                FROM module_confluence_import_jobs j
                JOIN module_confluence_connections c ON c.id = j.connection_id
                WHERE j.status IN ('queued', 'running')
                ORDER BY j.created_at ASC
                LIMIT 1");
            $stmt->execute();
            $job = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$job) {
                $this->pdo->commit();
                return null;
            }

            $jobPublicId = (string)$job['public_id'];

            if ($job['status'] === 'queued') {
                $this->migrationRepo->updateJobStatus($jobPublicId, 'running');
                $this->migrationRepo->addJobLog($jobPublicId, 'info', 'init', 'Worker claimed job');
            }

            if (isset($job['source_space_keys_json']) && is_string($job['source_space_keys_json'])) {
                $job['source_space_keys'] = json_decode($job['source_space_keys_json'], true);
            }
            if (isset($job['options_json']) && is_string($job['options_json'])) {
                $job['options'] = json_decode($job['options_json'], true);
            }

            $this->pdo->commit();
            return $job;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return null;
        }
    }

    public function releaseJob(string $jobPublicId): void
    {
        $this->migrationRepo->updateJobStatus($jobPublicId, 'queued');
        $this->migrationRepo->addJobLog($jobPublicId, 'warning', 'worker', 'Job released back to queue');
    }

    public function runQueued(int $limit = 10): array
    {
        $results = ['processed' => 0, 'completed' => 0, 'failed' => 0, 'released' => 0, 'errors' => []];

        for ($i = 0; $i < $limit; $i++) {
            $job = $this->claimNextRunnable();
            if ($job === null) {
                break;
            }

            $jobPublicId = (string)$job['public_id'];
            $results['processed']++;

            try {
                $token = EncryptionService::decrypt((string)($job['token_encrypted'] ?? ''));
                if ($token === null) {
                    $this->migrationRepo->addJobLog($jobPublicId, 'error', 'init', 'Failed to decrypt connection token');
                    $this->migrationRepo->updateJobStatus($jobPublicId, 'failed');
                    $results['failed']++;
                    continue;
                }

                $importService = $this->buildImportService();
                $importService->processJob($jobPublicId);

                $results['completed']++;
            } catch (\Throwable $e) {
                $this->migrationRepo->addJobLog($jobPublicId, 'error', 'worker', 'Worker error: ' . $e->getMessage());
                $this->migrationRepo->updateJobStatus($jobPublicId, 'failed');
                $results['failed']++;
                $results['errors'][] = $jobPublicId . ': ' . $e->getMessage();
            }
        }

        return $results;
    }

    private function buildImportService(): ConfluenceImportService
    {
        return new ConfluenceImportService(
            $this->knowledgeRepo,
            $this->migrationRepo,
            new ConfluenceClient(),
            new ConfluenceTransformer(
                new ConfluenceMacroRenderer(),
                new ConfluenceLinkRewriter($this->migrationRepo),
            ),
            new ConfluenceAttachmentService($this->fileService, $this->migrationRepo, $this->pdo),
            $this->fileService,
            $this->tagRepo,
            $this->pdo,
        );
    }
}
