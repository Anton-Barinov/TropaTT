<?php
declare(strict_types=1);

namespace Module\Crm\NotionMigration\Service;

use Api\Model\Knowledge\KnowledgeRepository;
use Module\Crm\NotionMigration\Repository\NotionMigrationRepository;

final class NotionImportService
{
    public function __construct(
        private KnowledgeRepository $knowledgeRepo,
        private NotionMigrationRepository $migrationRepo,
        private NotionClient $client,
        private NotionTransformer $transformer,
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

        $settings = $this->migrationRepo->getModuleSettings();
        $this->client = new NotionClient((int)($settings['request_timeout_seconds'] ?? 30), (int)($settings['max_retries'] ?? 4));

        $this->migrationRepo->updateJobStatus($jobPublicId, 'running');
        $this->migrationRepo->updateJobProgress($jobPublicId, 'crawl', 0, []);

        try {
            $crawlResult = $this->crawl($job, $token, $settings);
        } catch (\Throwable $e) {
            error_log('[NotionImportService::processJob] crawl failed: ' . $e->getMessage());
            $this->migrationRepo->addJobLog($jobPublicId, 'error', 'crawl', 'Crawl failed. Check server logs for details.');
            $this->migrationRepo->updateJobStatus($jobPublicId, 'failed');
            return;
        }

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
        $this->migrationRepo->updateJobProgress($jobPublicId, 'import_databases', 15, []);
        $this->importDatabases($job, $token);

        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $this->migrationRepo->updateJobProgress($jobPublicId, 'import_pages_shell', 35, []);
        $this->importPageShells($job, $token);

        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $this->migrationRepo->updateJobProgress($jobPublicId, 'import_content', 60, []);
        $this->importPageContent($job, $token);

        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        if (!empty($options['include_comments'])) {
            $this->migrationRepo->updateJobProgress($jobPublicId, 'import_comments', 85, []);
            $this->importComments($job, $token, (int)$connection['id']);
        }

        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $this->migrationRepo->updateJobProgress($jobPublicId, 'publish', 95, []);
        if (!empty($options['publish_pages'])) {
            $this->publishPages($job);
        }

        if ($this->isCancelling($job)) { $this->finaliseCancelled($jobPublicId); return; }
        $this->reindexJobPages($job);

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

    // ── Crawl ──

    private function crawl(array $job, string $token, array $settings): array
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];
        $selectedIds = $job['source_object_ids'] ?? json_decode((string)($job['source_object_ids_json'] ?? '[]'), true) ?? [];
        $maxDepth = (int)($settings['max_depth'] ?? 20);
        $maxPages = (int)($settings['max_pages_per_job'] ?? 0);

        $objectsById = [];
        try {
            foreach ($this->client->searchObjects($token, 'page') as $item) {
                $objectsById[(string)$item['id']] = ['object' => 'page', 'data' => $item];
            }
            foreach ($this->client->searchObjects($token, 'database') as $item) {
                $objectsById[(string)$item['id']] = ['object' => 'database', 'data' => $item];
            }
        } catch (\Throwable $e) {
            error_log('[NotionImportService::crawl] search failed: ' . $e->getMessage());
            throw $e;
        }

        $itemsCreated = 0;
        $warnings = [];
        $visited = [];

        $walkPage = function (string $pageId, ?string $parentId, string $parentType, int $depth) use (&$walkPage, &$itemsCreated, &$visited, $jobId, $jobPublicId, $token, $maxDepth, $maxPages, &$warnings): void {
            if (isset($visited[$pageId])) {
                return;
            }
            $visited[$pageId] = true;
            if ($maxPages > 0 && $itemsCreated >= $maxPages) {
                $warnings[] = 'max_pages_per_job reached, skipping deeper pages';
                return;
            }
            if ($depth > $maxDepth) {
                $warnings[] = 'max_depth reached for page ' . $pageId;
                return;
            }

            $title = '';
            try {
                $page = $this->client->getPage($token, $pageId);
                $title = $this->extractPageTitle($page);
            } catch (\Throwable $e) {
                error_log('[NotionImportService::crawl] getPage ' . $pageId . ': ' . $e->getMessage());
            }

            $this->migrationRepo->upsertJobItem($jobId, 'page', $pageId, [
                'source_parent_id' => $parentId,
                'payload_json' => [
                    'title' => $title,
                    'parent_type' => $parentType,
                ],
                'status' => 'pending',
            ]);
            $itemsCreated++;

            // Discover child pages via child_page blocks.
            try {
                $blocks = $this->client->getBlockChildren($token, $pageId);
                foreach ($blocks as $block) {
                    if (($block['type'] ?? '') === 'child_page' && !empty($block['id'])) {
                        $childId = (string)$block['id'];
                        if (!isset($visited[$childId])) {
                            $walkPage($childId, $pageId, 'page', $depth + 1);
                        }
                    }
                }
            } catch (\Throwable $e) {
                error_log('[NotionImportService::crawl] children of ' . $pageId . ': ' . $e->getMessage());
            }
        };

        foreach ($selectedIds as $selectedId) {
            $selectedId = (string)$selectedId;
            $obj = $objectsById[$selectedId] ?? null;
            if ($obj === null) {
                $warnings[] = 'Selected object not accessible or not found: ' . $selectedId;
                $this->migrationRepo->addJobLog($jobPublicId, 'warning', 'crawl', 'Selected object not accessible: ' . $selectedId);
                continue;
            }

            if ($obj['object'] === 'database') {
                $title = $this->extractDatabaseTitle($obj['data']);
                $this->migrationRepo->upsertJobItem($jobId, 'database', $selectedId, [
                    'source_parent_id' => null,
                    'payload_json' => ['title' => $title],
                    'status' => 'pending',
                ]);
                $itemsCreated++;

                try {
                    foreach ($this->client->queryDatabase($token, $selectedId) as $row) {
                        $rowId = (string)($row['id'] ?? '');
                        if ($rowId === '' || isset($visited[$rowId])) {
                            continue;
                        }
                        $visited[$rowId] = true;
                        if ($maxPages > 0 && $itemsCreated >= $maxPages) {
                            break;
                        }
                        $rowTitle = $this->extractPageTitle($row);
                        $this->migrationRepo->upsertJobItem($jobId, 'page', $rowId, [
                            'source_parent_id' => $selectedId,
                            'payload_json' => ['title' => $rowTitle, 'parent_type' => 'database'],
                            'status' => 'pending',
                        ]);
                        $itemsCreated++;
                    }
                } catch (\Throwable $e) {
                    error_log('[NotionImportService::crawl] query database ' . $selectedId . ': ' . $e->getMessage());
                    $this->migrationRepo->addJobLog($jobPublicId, 'warning', 'crawl', 'Failed to query database ' . $selectedId . '. Check server logs for details.');
                }
            } else {
                $pageId = $selectedId;
                $parentType = (string)($obj['data']['parent']['type'] ?? 'workspace');
                $parentId = null;
                if ($parentType === 'page_id' && !empty($obj['data']['parent']['page_id'])) {
                    $parentId = (string)$obj['data']['parent']['page_id'];
                } elseif ($parentType === 'database_id' && !empty($obj['data']['parent']['database_id'])) {
                    $parentId = (string)$obj['data']['parent']['database_id'];
                }
                $walkPage($pageId, $parentId, $parentType, 0);
            }
        }

        return ['items_created' => $itemsCreated, 'warnings' => $warnings];
    }

    // ── Import steps ──

    private function importDatabases(array $job, string $token): void
    {
        $jobId = (int)$job['id'];
        $userId = (int)($job['created_by_user_id'] ?? 0);

        $items = $this->allItems($jobId);
        foreach ($items as $item) {
            if ($item['source_type'] !== 'database') {
                continue;
            }
            $payload = json_decode((string)($item['payload_json'] ?? '{}'), true) ?? [];
            $title = (string)($payload['title'] ?? 'Database');
            try {
                $existing = $this->knowledgeRepo->findSpaceBySource('notion', $item['source_id']);
                if ($existing) {
                    $this->migrationRepo->upsertJobItem($jobId, 'database', $item['source_id'], [
                        'target_type' => 'knowledge_space',
                        'target_public_id' => (string)$existing['public_id'],
                        'status' => 'imported',
                    ]);
                    continue;
                }
                $created = $this->knowledgeRepo->createSpaceWithSource([
                    'title' => $title,
                    'slug' => 'notion-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title)),
                    'description' => 'Импортировано из Notion',
                    'icon' => 'table-cells',
                    'color' => '#37352f',
                    'visibility' => 'public',
                    'source_type' => 'notion',
                    'source_id' => $item['source_id'],
                    'source_url' => 'https://www.notion.so/' . $item['source_id'],
                    'source_payload_json' => ['title' => $title],
                ], $userId);
                $targetPublicId = (string)($created['public_id'] ?? '');
                if ($targetPublicId !== '') {
                    $this->migrationRepo->upsertJobItem($jobId, 'database', $item['source_id'], [
                        'target_type' => 'knowledge_space',
                        'target_public_id' => $targetPublicId,
                        'status' => 'imported',
                    ]);
                }
            } catch (\Throwable $e) {
                error_log('[NotionImportService::importDatabases] ' . $item['source_id'] . ': ' . $e->getMessage());
                $this->migrationRepo->upsertJobItem($jobId, 'database', $item['source_id'], [
                    'status' => 'failed',
                    'error_code' => 'IMPORT_ERROR',
                    'error_message' => 'Database import failed. Check server logs for details.',
                ]);
            }
        }
    }

    private function importPageShells(array $job, string $token): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];
        $userId = (int)($job['created_by_user_id'] ?? 0);

        $rootSpacePublicId = $this->resolveRootSpace($job, $userId);

        // Build lookup maps from imported items.
        $databaseTargets = [];
        $pageTargets = [];
        $pageParentById = [];
        $pagePayloads = [];
        foreach ($this->allItems($jobId) as $item) {
            if ($item['source_type'] === 'database' && $item['target_public_id'] !== null) {
                $databaseTargets[$item['source_id']] = (string)$item['target_public_id'];
            }
            if ($item['source_type'] === 'page') {
                $pageParentById[$item['source_id']] = $item['source_parent_id'];
                $pagePayloads[$item['source_id']] = json_decode((string)($item['payload_json'] ?? '{}'), true) ?? [];
            }
        }

        $pendingPages = [];
        foreach ($this->allItems($jobId) as $item) {
            if ($item['source_type'] === 'page' && in_array($item['status'], ['pending', 'failed'], true)) {
                $pendingPages[] = $item;
            }
        }

        foreach ($pendingPages as $item) {
            $pageId = $item['source_id'];
            $parentId = $item['source_parent_id'] ?? null;
            $payload = $pagePayloads[$pageId] ?? [];
            $parentType = (string)($payload['parent_type'] ?? '');

            try {
                $spacePublicId = $rootSpacePublicId;
                if ($parentType === 'database' && $parentId !== null && isset($databaseTargets[$parentId])) {
                    $spacePublicId = $databaseTargets[$parentId];
                } elseif ($parentType === 'page' && $parentId !== null && isset($pageTargets[$parentId])) {
                    $spacePublicId = $pageTargets[$parentId]['space'] ?? $rootSpacePublicId;
                }

                $parentPagePublicId = null;
                if ($parentType === 'page' && $parentId !== null && isset($pageTargets[$parentId])) {
                    $parentPagePublicId = $pageTargets[$parentId]['page'] ?? null;
                }

                $existing = $this->knowledgeRepo->findPageBySource('notion', $pageId);
                if ($existing) {
                    $targetPublicId = (string)$existing['public_id'];
                } else {
                    $created = $this->knowledgeRepo->createPageShell([
                        'space_public_id' => $spacePublicId,
                        'parent_public_id' => $parentPagePublicId,
                        'title' => (string)($payload['title'] ?? 'Untitled'),
                        'page_type' => 'article',
                        'status' => 'draft',
                        'source_type' => 'notion',
                        'source_id' => $pageId,
                        'source_url' => 'https://www.notion.so/' . $pageId,
                        'source_payload_json' => $payload,
                    ], $userId);
                    $targetPublicId = (string)($created['public_id'] ?? '');
                }

                if ($targetPublicId !== '') {
                    $pageTargets[$pageId] = ['page' => $targetPublicId, 'space' => $spacePublicId];
                    $this->migrationRepo->upsertJobItem($jobId, 'page', $pageId, [
                        'target_type' => 'knowledge_page',
                        'target_public_id' => $targetPublicId,
                        'status' => 'imported',
                    ]);
                } else {
                    $this->migrationRepo->upsertJobItem($jobId, 'page', $pageId, [
                        'status' => 'failed',
                        'error_code' => 'SHELL_ERROR',
                        'error_message' => 'Page shell creation failed.',
                    ]);
                }
            } catch (\Throwable $e) {
                error_log('[NotionImportService::importPageShells] ' . $pageId . ': ' . $e->getMessage());
                $this->migrationRepo->upsertJobItem($jobId, 'page', $pageId, [
                    'status' => 'failed',
                    'error_code' => 'SHELL_ERROR',
                    'error_message' => 'Page shell creation failed. Check server logs for details.',
                ]);
            }
        }

        if ($rootSpacePublicId === null || $rootSpacePublicId === '') {
            $this->migrationRepo->addJobLog($jobPublicId, 'warning', 'import_pages_shell', 'Root space could not be resolved; some pages may be skipped.');
        }
    }

    private function importPageContent(array $job, string $token): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];
        $userId = (int)($job['created_by_user_id'] ?? 0);
        $settings = $this->migrationRepo->getModuleSettings();
        $maxDepth = (int)($settings['max_depth'] ?? 20);

        // Build page mapping (source_id => target_public_id) for child_page links.
        $pageMapping = [];
        $imported = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 10000);
        foreach ($imported as $item) {
            if ($item['source_type'] === 'page' && $item['target_public_id'] !== null) {
                $pageMapping[$item['source_id']] = (string)$item['target_public_id'];
            }
        }

        foreach ($imported as $item) {
            if ($item['source_type'] !== 'page' || $item['target_public_id'] === null) {
                continue;
            }
            $pageId = $item['source_id'];
            $targetPublicId = (string)$item['target_public_id'];

            try {
                $blockTree = $this->client->fetchBlockTree($token, $pageId, $maxDepth);
                $transformed = $this->transformer->transform($blockTree, $pageMapping);

                foreach ($transformed['warnings'] as $warning) {
                    $this->migrationRepo->addJobLog($jobPublicId, 'warning', 'import_content', (string)($warning['message'] ?? ''));
                }

                $this->knowledgeRepo->updatePage($targetPublicId, [
                    'content_html' => $transformed['content_html'],
                    'content_text' => $transformed['content_text'],
                    'content_json' => [
                        'source' => 'notion',
                        'notion' => [
                            'page_id' => $pageId,
                            'url' => 'https://www.notion.so/' . $pageId,
                        ],
                    ],
                ], $userId);

                $this->migrationRepo->upsertJobItem($jobId, 'page', $pageId, ['status' => 'imported']);
            } catch (\Throwable $e) {
                error_log('[NotionImportService::importPageContent] ' . $pageId . ': ' . $e->getMessage());
                $this->migrationRepo->upsertJobItem($jobId, 'page', $pageId, [
                    'status' => 'failed',
                    'error_code' => 'CONTENT_ERROR',
                    'error_message' => 'Content import failed. Check server logs for details.',
                ]);
            }
        }
    }

    private function importComments(array $job, string $token, int $connectionId): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];
        $userId = (int)($job['created_by_user_id'] ?? 0);

        $imported = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 10000);
        foreach ($imported as $item) {
            if ($item['source_type'] !== 'page' || $item['target_public_id'] === null) {
                continue;
            }
            $pageId = $item['source_id'];
            $targetPagePublicId = (string)$item['target_public_id'];

            try {
                $comments = $this->client->listComments($token, $pageId);
                foreach ($comments as $comment) {
                    $richText = $comment['rich_text'] ?? [];
                    $body = trim($this->plainTextFromRichText($richText));
                    if ($body === '') {
                        continue;
                    }

                    $authorName = (string)($comment['created_by']['name'] ?? 'Notion user');
                    $authorId = (string)($comment['created_by']['id'] ?? '');
                    if ($authorId !== '' && $connectionId > 0) {
                        $this->migrationRepo->upsertUserMapping($connectionId, $authorId, $authorName, null);
                    }

                    $commentBody = '<p><strong>Notion author:</strong> ' . htmlspecialchars($authorName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p><p>' . htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';

                    $this->knowledgeRepo->addCommentWithSource($targetPagePublicId, $commentBody, $userId, [
                        'source_type' => 'notion',
                        'source_id' => (string)($comment['id'] ?? ''),
                        'source_author_name' => $authorName,
                        'source_created_at' => $comment['created_time'] ?? null,
                    ], null);
                }
            } catch (\Throwable $e) {
                error_log('[NotionImportService::importComments] ' . $pageId . ': ' . $e->getMessage());
                $this->migrationRepo->addJobLog($jobPublicId, 'warning', 'import_comments', 'Failed to import comments for page ' . $pageId . '. Check server logs for details.');
            }
        }
    }

    private function publishPages(array $job): void
    {
        $jobId = (int)$job['id'];
        $userId = (int)($job['created_by_user_id'] ?? 0);

        $imported = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 10000);
        foreach ($imported as $item) {
            if ($item['source_type'] === 'page' && $item['target_public_id'] !== null) {
                try {
                    $this->knowledgeRepo->batchPublish((string)$item['target_public_id'], $userId, true);
                } catch (\Throwable $e) {
                    error_log('[NotionImportService::publishPages] ' . $item['target_public_id'] . ': ' . $e->getMessage());
                }
            }
        }
    }

    private function reindexJobPages(array $job): void
    {
        $jobId = (int)$job['id'];
        $imported = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 10000);

        foreach ($imported as $item) {
            if ($item['source_type'] === 'page' && $item['target_public_id'] !== null) {
                try {
                    $page = $this->knowledgeRepo->page((string)$item['target_public_id']);
                    if ($page) {
                        $this->knowledgeRepo->reindexPage((int)$page['id']);
                    }
                } catch (\Throwable $e) {
                    error_log('[NotionImportService::reindexJobPages] ' . $item['target_public_id'] . ': ' . $e->getMessage());
                }
            }
        }
    }

    // ── Helpers ──

    private function allItems(int $jobId): array
    {
        $all = [];
        $offset = 0;
        while (true) {
            $batch = $this->migrationRepo->findJobItemsByStatus($jobId, 'pending', 500, $offset);
            $imported = $this->migrationRepo->findJobItemsByStatus($jobId, 'imported', 500, $offset);
            $failed = $this->migrationRepo->findJobItemsByStatus($jobId, 'failed', 500, $offset);
            $skipped = $this->migrationRepo->findJobItemsByStatus($jobId, 'skipped', 500, $offset);
            $batch = array_merge($batch, $imported, $failed, $skipped);
            if ($batch === []) {
                break;
            }
            $all = array_merge($all, $batch);
            $offset += 500;
        }
        return $all;
    }

    private function resolveRootSpace(array $job, int $userId): ?string
    {
        $targetRoot = (string)($job['target_root_space_public_id'] ?? '');
        if ($targetRoot !== '') {
            return $targetRoot;
        }

        $connectionId = (int)$job['connection_id'];
        $sourceId = 'notion-root-' . $connectionId;
        try {
            $existing = $this->knowledgeRepo->findSpaceBySource('notion', $sourceId);
            if ($existing) {
                return (string)$existing['public_id'];
            }
            $created = $this->knowledgeRepo->createSpaceWithSource([
                'title' => 'Notion import',
                'slug' => 'notion-import-' . $connectionId,
                'description' => 'Импортированные страницы Notion (верхний уровень)',
                'icon' => 'book',
                'color' => '#37352f',
                'visibility' => 'public',
                'source_type' => 'notion',
                'source_id' => $sourceId,
                'source_url' => null,
                'source_payload_json' => ['connection_id' => $connectionId],
            ], $userId);
            return (string)($created['public_id'] ?? '');
        } catch (\Throwable $e) {
            error_log('[NotionImportService::resolveRootSpace] ' . $e->getMessage());
            return null;
        }
    }

    private function extractPageTitle(array $page): string
    {
        $properties = $page['properties'] ?? [];
        foreach ($properties as $prop) {
            if (($prop['type'] ?? '') === 'title') {
                return $this->plainTextFromRichText($prop['title'] ?? []);
            }
        }
        return '';
    }

    private function extractDatabaseTitle(array $database): string
    {
        return $this->plainTextFromRichText($database['title'] ?? []);
    }

    private function plainTextFromRichText(array $richText): string
    {
        $parts = [];
        foreach ($richText as $rt) {
            $plain = (string)($rt['plain_text'] ?? '');
            if ($plain !== '') {
                $parts[] = $plain;
            }
        }
        return trim(implode('', $parts));
    }
}
