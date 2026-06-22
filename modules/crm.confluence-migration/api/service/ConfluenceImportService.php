<?php
declare(strict_types=1);

namespace Module\Crm\ConfluenceMigration\Service;

use Api\Model\Knowledge\KnowledgeRepository;
use Api\Model\Tag\TagRepository;
use Api\System\Library\Service\FileService;
use Module\Crm\ConfluenceMigration\Repository\ConfluenceMigrationRepository;
use PDO;

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

        $this->client->setConnectionId((int)$connection['id']);
        $this->migrationRepo->initRateLimit((int)$connection['id']);

        $this->migrationRepo->updateJobStatus($jobPublicId, 'running');
        $this->migrationRepo->updateJobProgress($jobPublicId, 'crawl', 0, []);

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

        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $this->migrationRepo->updateJobProgress($jobPublicId, 'import_spaces', 10, []);
        $this->importSpaces($job, $baseUrl, $email, $token);

        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $this->migrationRepo->updateJobProgress($jobPublicId, 'import_page_shells', 25, []);
        $this->importPageShells($job, $baseUrl, $email, $token);

        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $this->migrationRepo->updateJobProgress($jobPublicId, 'import_content', 50, []);
        $this->importPageContent($job, $baseUrl, $email, $token, $options, (int)$connection['id']);

        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $this->migrationRepo->updateJobProgress($jobPublicId, 'import_versions', 60, []);
        $this->importVersions($job, $baseUrl, $email, $token);

        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $this->migrationRepo->updateJobProgress($jobPublicId, 'import_attachments', 65, []);
        if (!empty($options['import_attachments'])) {
            $this->importAttachments($job, $baseUrl, $email, $token, $options, (int)$connection['id']);
        }

        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $this->migrationRepo->updateJobProgress($jobPublicId, 'import_labels', 80, []);
        if (!empty($options['import_labels'])) {
            $this->importLabels($job, $baseUrl, $email, $token);
        }

        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        if (!empty($options['import_comments'])) {
            $this->importComments($job, $baseUrl, $email, $token, (int)$connection['id']);
        }

        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $this->migrationRepo->updateJobProgress($jobPublicId, 'publish', 95, []);
        if (!empty($options['publish_pages'])) {
            $this->publishPages($job);
        }

        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $this->reindexJobPages($job);

        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $stats = $this->migrationRepo->countJobItemsByStatus($jobId);
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

    private function processableItems(array $items): array
    {
        return array_filter($items, fn($i) => in_array($i['source_type'], ['page', 'blogpost'], true) && $i['target_public_id'] !== null);
    }

    private function importPageShells(array $job, string $baseUrl, string $email, string $token): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];
        $userId = (int)($job['created_by_user_id'] ?? 0);

        $shellsCreated = [];
        $allPageItems = [];

        while (true) {
            $pendingItems = $this->migrationRepo->findJobItemsByStatus($jobId, 'pending', 500);
            $pageItems = array_filter($pendingItems, fn($i) => $i['source_type'] === 'page' || $i['source_type'] === 'blogpost');
            if ($pageItems === []) {
                break;
            }
            $allPageItems = array_merge($allPageItems, array_values($pageItems));

            foreach ($pageItems as $item) {
                try {
                    $pageId = $item['source_id'];
                    $payload = json_decode((string)($item['payload_json'] ?? '{}'), true) ?? [];
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
                        $spaceItems = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 500);
                        foreach ($spaceItems as $spaceItem) {
                            if ($spaceItem['source_type'] === 'space' && !empty($spaceItem['target_public_id'])) {
                                $itemPayload = json_decode((string)($spaceItem['payload_json'] ?? '{}'), true) ?? [];
                                if (($itemPayload['key'] ?? '') === $spaceKey || ($spaceItem['source_key'] ?? '') === $spaceKey) {
                                    $targetSpacePublicId = $spaceItem['target_public_id'];
                                    break;
                                }
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
                    $parentId = $item['source_parent_id'] ?? null;
                    if ($parentId && isset($shellsCreated[$parentId])) {
                        $parentPublicId = $shellsCreated[$parentId];
                    }

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
                        'created_at' => $payload['created_at'] ?? null,
                        'updated_at' => $payload['updated_at'] ?? null,
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
        }

        foreach ($allPageItems as $item) {
            $pageId = $item['source_id'];
            $sourceParentId = $item['source_parent_id'] ?? null;
            $targetPublicId = $shellsCreated[$pageId] ?? null;
            if ($sourceParentId && $targetPublicId && isset($shellsCreated[$sourceParentId])) {
                try {
                    $this->knowledgeRepo->updatePageParent($targetPublicId, $shellsCreated[$sourceParentId]);
                } catch (\Throwable) {
                }
            }
        }
    }

    private function importPageContent(array $job, string $baseUrl, string $email, string $token, array $options, int $connectionId = 0): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];
        $userId = (int)($job['created_by_user_id'] ?? 0);

        $pageMapping = [];
        $allImported = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 10000);
        foreach ($allImported as $item) {
            if (in_array($item['source_type'], ['page', 'blogpost'], true) && $item['target_public_id'] !== null) {
                $pageMapping[$item['source_id']] = $item['target_public_id'];
            }
        }

        while (true) {
            $importedPages = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 500);
            $pageItems = $this->processableItems($importedPages);
            if ($pageItems === []) {
                break;
            }

            foreach ($pageItems as $item) {
                $pageId = $item['source_id'];
                $targetPublicId = (string)$item['target_public_id'];

                try {
                    if ($item['source_type'] === 'blogpost') {
                        $confluencePage = $this->client->getBlogPost($baseUrl, $email, $token, $pageId);
                    } else {
                        $confluencePage = $this->client->getPage($baseUrl, $email, $token, $pageId);
                    }
                    $storageHtml = $confluencePage['body']['storage']['value'] ?? '';

                    if ($storageHtml === '') {
                        $this->migrationRepo->upsertJobItem($jobId, $item['source_type'], $pageId, [
                            'status' => 'imported',
                            'error_code' => 'EMPTY_CONTENT',
                        ]);
                        continue;
                    }

                    $authorId = $confluencePage['authorId'] ?? null;
                    if ($authorId !== null && $authorId !== '' && $connectionId > 0) {
                        $this->migrationRepo->upsertUserMapping($connectionId, $authorId, $confluencePage['title'] ?? '', null);
                    }

                    $transformed = $this->transformer->transform($storageHtml, $pageMapping);

                    foreach ($transformed['warnings'] as $warning) {
                        $this->migrationRepo->addUnsupportedMacro($jobPublicId, $pageId, $warning['macro'] ?? 'unknown', $warning['handling'] ?? 'placeholder', $warning['message'] ?? null);
                    }

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

                    if (!empty($confluencePage['metadata']['properties']['results'])) {
                        foreach ($confluencePage['metadata']['properties']['results'] as $prop) {
                            $propKey = (string)($prop['key'] ?? '');
                            $propValue = $prop['value'] ?? '';
                            if ($propKey !== '') {
                                try {
                                    $this->knowledgeRepo->setPageProperty($targetPublicId, 'confluence:' . $propKey, is_string($propValue) ? $propValue : json_encode($propValue, JSON_UNESCAPED_UNICODE), 'string', 'confluence', $pageId);
                                } catch (\Throwable) {
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $this->migrationRepo->upsertJobItem($jobId, $item['source_type'] ?? 'page', $pageId, [
                        'status' => 'failed',
                        'error_code' => 'CONTENT_ERROR',
                        'error_message' => $e->getMessage(),
                    ]);
                    $this->migrationRepo->addJobLog($jobPublicId, 'error', 'import_content', 'Failed to import content for ' . $pageId . ': ' . $e->getMessage());
                }
            }
        }
    }

    private function importVersions(array $job, string $baseUrl, string $email, string $token): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];
        $userId = (int)($job['created_by_user_id'] ?? 0);

        $importedPages = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 500);
        $pageItems = $this->processableItems($importedPages);

        foreach ($pageItems as $item) {
            $pageId = $item['source_id'];
            $targetPublicId = (string)$item['target_public_id'];

            try {
                $versions = $this->client->getPageVersions($baseUrl, $email, $token, $pageId);
                foreach ($versions as $version) {
                    $this->knowledgeRepo->legacyAddVersion($targetPublicId, $userId, '[Confluence v' . $version['number'] . '] ' . ($version['message'] ?: 'Imported from Confluence'));
                }
            } catch (\Throwable $e) {
                $this->migrationRepo->addJobLog($jobPublicId, 'warning', 'import_versions', 'Failed to import versions for page ' . $pageId . ': ' . $e->getMessage());
            }
        }
    }

    private function importAttachments(array $job, string $baseUrl, string $email, string $token, array $options, int $connectionId = 0): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];

        while (true) {
            $importedPages = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 500);
            $pageItems = $this->processableItems($importedPages);
            if ($pageItems === []) {
                break;
            }

            foreach ($pageItems as $item) {
                $pageId = $item['source_id'];
                $targetPagePublicId = (string)$item['target_public_id'];

                try {
                    $attachments = $this->client->getPageAttachments($baseUrl, $email, $token, $pageId);
                    foreach ($attachments as $attachment) {
                        $this->attachmentService->importAttachment($job, $baseUrl, $email, $token, $attachment, $targetPagePublicId);
                    }
                } catch (\Throwable $e) {
                    $this->migrationRepo->addJobLog($jobPublicId, 'error', 'import_attachments', 'Failed to import attachments for page ' . $pageId . ': ' . $e->getMessage());
                }
            }
        }
    }

    private function importLabels(array $job, string $baseUrl, string $email, string $token): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];

        while (true) {
            $importedPages = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 500);
            $pageItems = $this->processableItems($importedPages);
            if ($pageItems === []) {
                break;
            }

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
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $this->migrationRepo->addJobLog($jobPublicId, 'error', 'import_labels', 'Failed to import labels for page ' . $pageId . ': ' . $e->getMessage());
                }
            }
        }
    }

    private function importComments(array $job, string $baseUrl, string $email, string $token, int $connectionId = 0): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];
        $userId = (int)($job['created_by_user_id'] ?? 0);

        while (true) {
            $importedPages = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 500);
            $pageItems = $this->processableItems($importedPages);
            if ($pageItems === []) {
                break;
            }

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
                        $authorAccountId = $comment['authorId'] ?? null;

                        if ($authorAccountId !== null && $connectionId > 0) {
                            $this->migrationRepo->upsertUserMapping($connectionId, $authorAccountId, $authorName, null);
                        }

                        $commentBody = '<p><strong>Confluence author:</strong> ' . htmlspecialchars($authorName) . '</p>' . $body;

                        $this->knowledgeRepo->addCommentWithSource($targetPagePublicId, $commentBody, $userId, [
                            'source_type' => 'confluence',
                            'source_id' => $comment['id'],
                            'source_author_name' => $authorName,
                            'source_created_at' => $comment['createdAt'],
                        ], null);
                    }
                } catch (\Throwable $e) {
                    $this->migrationRepo->addJobLog($jobPublicId, 'error', 'import_comments', 'Failed to import comments for page ' . $pageId . ': ' . $e->getMessage());
                }
            }
        }
    }

    private function publishPages(array $job): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];
        $userId = (int)($job['created_by_user_id'] ?? 0);

        $importedPages = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 500);
        $pageItems = $this->processableItems($importedPages);

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
            if (in_array($item['source_type'], ['page', 'blogpost'], true) && $item['target_public_id'] !== null) {
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
