<?php
declare(strict_types=1);

namespace Module\Crm\ConfluenceMigration\Service;

use Api\Model\Knowledge\KnowledgeRepository;
use Api\Model\Tag\TagRepository;
use Api\System\Library\Service\FileService;
use Module\Crm\ConfluenceMigration\Repository\ConfluenceMigrationRepository;

final class ConfluenceImportService
{
    public function __construct(
        private KnowledgeRepository $knowledgeRepo,
        private ConfluenceMigrationRepository $migrationRepo,
        private ConfluenceClient $client,
        private ConfluenceTransformer $transformer,
        private ConfluenceAttachmentService $attachmentService,
        private FileService $fileService,
        private TagRepository $tagRepo,
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
            $this->migrationRepo->addJobLog($jobPublicId, 'error', 'init', 'Failed to decrypt connection token');
            $this->migrationRepo->updateJobStatus($jobPublicId, 'failed');
            return;
        }

        $baseUrl = (string)$connection['base_url'];
        $email = (string)$connection['email'];

        $this->migrationRepo->updateJobStatus($jobPublicId, 'running');
        $this->migrationRepo->updateJobProgress($jobPublicId, 'crawl', 0, []);

        // Step 1: Crawl spaces and create items
        $crawler = new ConfluenceCrawler($this->client, $this->migrationRepo);
        $crawlResult = $crawler->crawlSpaces($job, $baseUrl, $email, $token);

        $job = $this->migrationRepo->getJob($jobPublicId);
        $mode = (string)$job['mode'];

        if ($mode === 'dry_run') {
            $this->migrationRepo->updateJobProgress($jobPublicId, 'dry_run_complete', 100, [
                'total_items' => $crawlResult['items_created'],
                'warnings_count' => count($crawlResult['warnings']),
            ]);
            $this->migrationRepo->updateJobStatus($jobPublicId, 'completed');
            $this->migrationRepo->addJobLog($jobPublicId, 'info', 'dry_run', 'Dry run completed. ' . $crawlResult['items_created'] . ' items would be imported.');
            return;
        }

        $jobId = (int)$job['id'];
        $options = $this->getJobOptions($job);

        // Step 2: Import spaces
        $this->migrationRepo->updateJobProgress($jobPublicId, 'import_spaces', 10, []);
        $this->importSpaces($job, $baseUrl, $email, $token);

        // Step 3: Import page shells (all pages, no content)
        $this->migrationRepo->updateJobProgress($jobPublicId, 'import_page_shells', 25, []);
        $this->importPageShells($job, $baseUrl, $email, $token);

        // Step 4: Import page content
        $this->migrationRepo->updateJobProgress($jobPublicId, 'import_content', 50, []);
        $this->importPageContent($job, $baseUrl, $email, $token, $options);

        // Step 5: Import attachments
        $this->migrationRepo->updateJobProgress($jobPublicId, 'import_attachments', 70, []);
        if (!empty($options['import_attachments'])) {
            $this->importAttachments($job, $baseUrl, $email, $token, $options);
        }

        // Step 6: Import labels
        $this->migrationRepo->updateJobProgress($jobPublicId, 'import_labels', 85, []);
        if (!empty($options['import_labels'])) {
            $this->importLabels($job, $baseUrl, $email, $token);
        }

        // Step 7: Import comments
        if (!empty($options['import_comments'])) {
            $this->importComments($job, $baseUrl, $email, $token);
        }

        // Step 8: Publish pages
        $this->migrationRepo->updateJobProgress($jobPublicId, 'publish', 95, []);
        if (!empty($options['publish_pages'])) {
            $this->publishPages($job);
        }

        // Step 9: Reindex
        $this->reindexJobPages($job);

        // Complete
        $stats = $this->migrationRepo->countJobItemsByStatus($jobId);
        $this->migrationRepo->updateJobProgress($jobPublicId, 'completed', 100, $stats);
        $this->migrationRepo->updateJobStatus($jobPublicId, 'completed');
        $this->migrationRepo->addJobLog($jobPublicId, 'info', 'completed', 'Migration completed');
    }

    private function getJobOptions(array $job): array
    {
        $json = $job['options_json'] ?? (isset($job['options']) ? json_encode($job['options']) : '{}');
        if (is_array($json)) {
            return $json;
        }
        return json_decode((string)($json ?: '{}'), true) ?? [];
    }

    private function importSpaces(array $job, string $baseUrl, string $email, string $token): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];
        $mode = (string)$job['mode'];
        $userId = (int)($job['created_by_user_id'] ?? 0);
        $spaceKeys = json_decode((string)($job['source_space_keys_json'] ?? '[]'), true) ?? [];

        $spaces = $this->client->getSpaces($baseUrl, $email, $token, $spaceKeys, false);

        foreach ($spaces as $space) {
            try {
                $existingSpace = $this->knowledgeRepo->findSpaceBySource('confluence', $space['id']);

                if ($existingSpace) {
                    $this->knowledgeRepo->updateSpace((string)$existingSpace['public_id'], [
                        'title' => $space['name'],
                        'description' => $space['description'],
                        'icon' => 'cloud-arrow-down',
                        'color' => '#0f8f72',
                    ]);
                    $targetPublicId = (string)$existingSpace['public_id'];

                    $this->migrationRepo->upsertJobItem($jobId, 'space', $space['id'], [
                        'target_type' => 'knowledge_space',
                        'target_public_id' => $targetPublicId,
                        'status' => 'imported',
                    ]);
                } else {
                    $created = $this->knowledgeRepo->createSpaceWithSource([
                        'title' => $space['name'],
                        'slug' => 'confluence-' . strtolower($space['key']),
                        'description' => $space['description'] . "\n\nSource: " . ($space['_links']['webui'] ?? $baseUrl . '/wiki/spaces/' . $space['key']),
                        'icon' => 'cloud-arrow-down',
                        'color' => '#0f8f72',
                        'visibility' => 'public',
                        'source_type' => 'confluence',
                        'source_id' => $space['id'],
                        'source_url' => $baseUrl . '/wiki/spaces/' . $space['key'],
                        'source_payload_json' => [
                            'key' => $space['key'],
                            'name' => $space['name'],
                            'web_url' => $baseUrl . '/wiki/spaces/' . $space['key'],
                        ],
                    ], $userId);

                    $targetPublicId = (string)($created['public_id'] ?? '');
                    if ($targetPublicId !== '') {
                        $this->migrationRepo->upsertJobItem($jobId, 'space', $space['id'], [
                            'target_type' => 'knowledge_space',
                            'target_public_id' => $targetPublicId,
                            'status' => 'imported',
                            'payload_json' => ['title' => $space['name'], 'key' => $space['key']],
                        ]);
                    }
                !empty($targetPublicId) ? null : null;
                }
            } catch (\Throwable $e) {
                $this->migrationRepo->upsertJobItem($jobId, 'space', $space['id'], [
                    'status' => 'failed',
                    'error_code' => 'IMPORT_ERROR',
                    'error_message' => $e->getMessage(),
                ]);
                $this->migrationRepo->addJobLog($jobPublicId, 'error', 'import_spaces', 'Failed to import space ' . $space['key'] . ': ' . $e->getMessage());
            }
        }
    }

    private function importPageShells(array $job, string $baseUrl, string $email, string $token): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];
        $userId = (int)($job['created_by_user_id'] ?? 0);
        $spaceKeys = json_decode((string)($job['source_space_keys_json'] ?? '[]'), true) ?? [];

        // Get pending page items
        $pendingPages = $this->migrationRepo->findJobItemsByStatus($jobId, 'pending', 500);
        $pageItems = array_filter($pendingPages, fn($i) => $i['source_type'] === 'page');

        // Need a 2-pass approach: first create all shells, then set parents
        $shellsCreated = []; // confluence_id => target_public_id

        // Get spaces mapping
        foreach ($pageItems as $item) {
            try {
                $pageId = $item['source_id'];
                $payload = json_decode((string)($item['payload_json'] ?? '{}'), true) ?? [];

                // Find target space from page's space
                $spaceKey = $payload['space_key'] ?? '';
                $spaceId = $payload['space_id'] ?? '';
                $targetSpacePublicId = null;

                if ($spaceId !== '') {
                    $spaceItem = $this->migrationRepo->findJobItem($jobId, 'space', $spaceId);
                    if ($spaceItem && $spaceItem['target_public_id']) {
                        $targetSpacePublicId = $spaceItem['target_public_id'];
                    }
                }

                if (!$targetSpacePublicId) {
                    // Find space by key
                    foreach ($spaceKeys as $sk) {
                        $spaceData = $this->migrationRepo->findJobItem($jobId, 'space', '');
                        // Look for space with matching key in payload
                        $stmt = $this->pdo->prepare('SELECT target_public_id FROM module_confluence_import_items WHERE job_id = :job_id AND source_type = :st AND source_key LIKE :sk AND target_public_id IS NOT NULL LIMIT 1');
                        $stmt->execute(['job_id' => $jobId, 'st' => 'space', 'sk' => $sk . '%']);
                        $tpid = $stmt->fetchColumn();
                        if ($tpid) {
                            $targetSpacePublicId = (string)$tpid;
                            break;
                        }
                    }
                }

                if (!$targetSpacePublicId) {
                    $this->migrationRepo->upsertJobItem($jobId, 'page', $pageId, [
                        'status' => 'failed',
                        'error_code' => 'NO_TARGET_SPACE',
                        'error_message' => 'No target space found for page ' . $pageId,
                    ]);
                    continue;
                }

                $parentPublicId = null;
                $parentId = $item['source_parent_id'] ?? $payload['parent_public_id'] ?? null;
                if ($parentId && isset($shellsCreated[$parentId])) {
                    $parentPublicId = $shellsCreated[$parentId];
                }

                $sourceCreatedAt = $payload['created_at'] ?? null;
                $sourceUpdatedAt = $payload['updated_at'] ?? null;

                $page = $this->knowledgeRepo->createPageShell([
                    'space_public_id' => $targetSpacePublicId,
                    'parent_public_id' => $parentPublicId,
                    'title' => $payload['title'] ?? 'Untitled',
                    'page_type' => 'article',
                    'status' => 'draft',
                    'source_type' => 'confluence',
                    'source_id' => $pageId,
                    'source_url' => $baseUrl . '/wiki/spaces/' . $spaceKey . '/pages/' . $pageId,
                    'source_payload_json' => $payload,
                    'created_at' => $sourceCreatedAt,
                    'updated_at' => $sourceUpdatedAt,
                ], $userId);

                $targetPublicId = (string)($page['public_id'] ?? '');
                $shellsCreated[$pageId] = $targetPublicId;

                $this->migrationRepo->upsertJobItem($jobId, 'page', $pageId, [
                    'target_type' => 'knowledge_page',
                    'target_public_id' => $targetPublicId,
                    'status' => 'imported',
                ]);
            } catch (\Throwable $e) {
                $this->migrationRepo->upsertJobItem($jobId, 'page', $item['source_id'], [
                    'status' => 'failed',
                    'error_code' => 'SHELL_ERROR',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }

        // Second pass: fix parent-child relationships
        foreach ($pageItems as $item) {
            $sourceParentId = $item['source_parent_id'] ?? null;
            $targetPublicId = $item['target_public_id'] ?? null;
            if ($sourceParentId && $targetPublicId && isset($shellsCreated[$sourceParentId])) {
                try {
                    $this->knowledgeRepo->updatePageParent($targetPublicId, $shellsCreated[$sourceParentId]);
                } catch (\Throwable) {
                }
            }
        }
    }

    private function importPageContent(array $job, string $baseUrl, string $email, string $token, array $options): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];
        $userId = (int)($job['created_by_user_id'] ?? 0);

        $importedPages = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 500);
        $pageItems = array_filter($importedPages, fn($i) => $i['source_type'] === 'page' && $i['target_public_id'] !== null);

        // Build page mapping for link rewriting
        $pageMapping = [];
        foreach ($pageItems as $item) {
            $pageMapping[$item['source_id']] = $item['target_public_id'];
        }

        foreach ($pageItems as $item) {
            $pageId = $item['source_id'];
            $targetPublicId = (string)$item['target_public_id'];

            try {
                // Fetch page body from Confluence
                $confluencePage = $this->client->getPage($baseUrl, $email, $token, $pageId);
                $storageHtml = $confluencePage['body']['storage']['value'] ?? '';

                if ($storageHtml === '') {
                    $this->migrationRepo->upsertJobItem($jobId, 'page', $pageId, [
                        'status' => 'imported',
                        'error_code' => 'EMPTY_CONTENT',
                    ]);
                    continue;
                }

                // Transform
                $transformed = $this->transformer->transform($storageHtml, $pageMapping);

                // Log macro warnings
                foreach ($transformed['warnings'] as $warning) {
                    $macroName = $warning['macro'] ?? 'unknown';
                    $handling = $warning['handling'] ?? 'placeholder';
                    $this->migrationRepo->addUnsupportedMacro($jobPublicId, $pageId, $macroName, $handling, $warning['message'] ?? null);
                }

                // Update page content
                $this->knowledgeRepo->updatePage($targetPublicId, [
                    'content_html' => $transformed['content_html'],
                    'content_text' => $transformed['content_text'],
                    'content_json' => [
                        'source' => 'confluence',
                        'confluence' => [
                            'page_id' => $pageId,
                            'version' => $confluencePage['version'],
                            'author_account_id' => $confluencePage['authorId'] ?? null,
                        ],
                    ],
                ], $userId);
            } catch (\Throwable $e) {
                $this->migrationRepo->upsertJobItem($jobId, 'page', $pageId, [
                    'status' => 'failed',
                    'error_code' => 'CONTENT_ERROR',
                    'error_message' => $e->getMessage(),
                ]);
                $this->migrationRepo->addJobLog($jobPublicId, 'error', 'import_content', 'Failed to import content for page ' . $pageId . ': ' . $e->getMessage());
            }
        }
    }

    private function importAttachments(array $job, string $baseUrl, string $email, string $token, array $options): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];

        $importedPages = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 500);
        $pageItems = array_filter($importedPages, fn($i) => $i['source_type'] === 'page' && $i['target_public_id'] !== null);

        foreach ($pageItems as $item) {
            $pageId = $item['source_id'];
            $targetPagePublicId = (string)$item['target_public_id'];

            try {
                $attachments = $this->client->getPageAttachments($baseUrl, $email, $token, $pageId);
                foreach ($attachments as $attachment) {
                    $this->attachmentService->importAttachment(
                        $job,
                        $baseUrl,
                        $email,
                        $token,
                        $attachment,
                        $targetPagePublicId,
                    );
                }
            } catch (\Throwable $e) {
                $this->migrationRepo->addJobLog($jobPublicId, 'error', 'import_attachments', 'Failed to import attachments for page ' . $pageId . ': ' . $e->getMessage());
            }
        }
    }

    private function importLabels(array $job, string $baseUrl, string $email, string $token): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];

        $importedPages = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 500);
        $pageItems = array_filter($importedPages, fn($i) => $i['source_type'] === 'page' && $i['target_public_id'] !== null);

        foreach ($pageItems as $item) {
            $pageId = $item['source_id'];
            $targetPagePublicId = (string)$item['target_public_id'];

            try {
                $labels = $this->client->getPageLabels($baseUrl, $email, $token, $pageId);
                foreach ($labels as $label) {
                    $labelName = $label['name'];
                    if ($labelName === '') {
                        continue;
                    }

                    // Find or create tag
                    $tag = $this->tagRepo->findByCode($labelName);
                    if (!$tag) {
                        $tag = $this->tagRepo->create([
                            'code' => $labelName,
                            'title' => $labelName,
                            'color' => '#6b7280',
                            'description' => 'Imported from Confluence',
                        ]);
                    }
                    $tagPublicId = (string)($tag['public_id'] ?? '');
                    if ($tagPublicId !== '') {
                        try {
                            $this->tagRepo->attachToEntity('knowledge_page', $targetPagePublicId, (int)$tag['id']);
                        } catch (\Throwable) {
                            // Duplicate tag, skip
                        }
                    }
                }
            } catch (\Throwable $e) {
                $this->migrationRepo->addJobLog($jobPublicId, 'error', 'import_labels', 'Failed to import labels for page ' . $pageId . ': ' . $e->getMessage());
            }
        }
    }

    private function importComments(array $job, string $baseUrl, string $email, string $token): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];
        $userId = (int)($job['created_by_user_id'] ?? 0);

        $importedPages = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 500);
        $pageItems = array_filter($importedPages, fn($i) => $i['source_type'] === 'page' && $i['target_public_id'] !== null);

        foreach ($pageItems as $item) {
            $pageId = $item['source_id'];
            $targetPagePublicId = (string)$item['target_public_id'];

            try {
                $comments = $this->client->getPageComments($baseUrl, $email, $token, $pageId);
                foreach ($comments as $comment) {
                    $body = $comment['body'] ?? '';
                    if ($body === '') {
                        continue;
                    }

                    $authorName = $comment['authorName'] ?? 'Unknown Confluence user';
                    $commentBody = '<p><strong>Confluence author:</strong> ' . htmlspecialchars($authorName) . '</p>' . $body;

                    $sourceData = [
                        'source_type' => 'confluence',
                        'source_id' => $comment['id'],
                        'source_author_name' => $authorName,
                        'source_created_at' => $comment['createdAt'],
                    ];

                    $this->knowledgeRepo->addCommentWithSource(
                        $targetPagePublicId,
                        $commentBody,
                        $userId,
                        $sourceData,
                        null, // parent comment not mapped initially
                    );
                }
            } catch (\Throwable $e) {
                $this->migrationRepo->addJobLog($jobPublicId, 'error', 'import_comments', 'Failed to import comments for page ' . $pageId . ': ' . $e->getMessage());
            }
        }
    }

    private function publishPages(array $job): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];
        $userId = (int)($job['created_by_user_id'] ?? 0);

        $importedPages = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 500);
        $pageItems = array_filter($importedPages, fn($i) => $i['source_type'] === 'page' && $i['target_public_id'] !== null);

        foreach ($pageItems as $item) {
            $targetPublicId = (string)$item['target_public_id'];
            try {
                $this->knowledgeRepo->batchPublish($targetPublicId, $userId, true);
            } catch (\Throwable $e) {
                $this->migrationRepo->addJobLog($jobPublicId, 'warning', 'publish', 'Failed to publish page ' . $targetPublicId . ': ' . $e->getMessage());
            }
        }
    }

    private function reindexJobPages(array $job): void
    {
        $jobId = (int)$job['id'];
        $importedPages = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 500);

        foreach ($importedPages as $item) {
            if ($item['source_type'] === 'page' && $item['target_public_id'] !== null) {
                try {
                    $page = $this->knowledgeRepo->page((string)$item['target_public_id']);
                    if ($page) {
                        $this->knowledgeRepo->reindexPage((int)$page['id']);
                    }
                } catch (\Throwable) {
                }
            }
        }
    }
}
