<?php
declare(strict_types=1);

namespace Module\Crm\ConfluenceMigration\Service;

use Module\Crm\ConfluenceMigration\Repository\ConfluenceMigrationRepository;
use PDO;

final class ConfluenceCrawler
{
    private ConfluenceClient $client;
    private ConfluenceMigrationRepository $repo;

    public function __construct(
        ConfluenceClient $client,
        ConfluenceMigrationRepository $repo,
    ) {
        $this->client = $client;
        $this->repo = $repo;
    }

    /**
     * Build snapshot of all pages for the selected spaces.
     * @return array{items_created: int, warnings: array}
     */
    public function crawlSpaces(array $job, string $baseUrl, string $email, string $token): array
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];
        $spaceKeys = json_decode((string)($job['source_space_keys_json'] ?? '[]'), true) ?? [];
        $mode = (string)$job['mode'];
        $options = json_decode((string)($job['options_json'] ?? '{}'), true) ?? [];
        $includeArchived = !empty($options['include_archived']);
        $warnings = [];
        $totalCreated = 0;

        // Get spaces from Confluence
        $spaces = $this->client->getSpaces($baseUrl, $email, $token, $spaceKeys, $includeArchived);

        foreach ($spaces as $space) {
            $spaceKey = $space['key'];
            $spaceId = $space['id'];

            // Create or update job item for space
            $this->repo->upsertJobItem($jobId, 'space', $spaceId, [
                'source_key' => $spaceKey,
                'status' => $mode === 'dry_run' ? 'skipped' : 'pending',
                'payload_json' => [
                    'name' => $space['name'],
                    'description' => $space['description'],
                ],
            ]);
            $totalCreated++;

            // Get all pages for this space
            $allPages = $this->client->getAllPagesForSpace($baseUrl, $email, $token, $spaceId);
            $pageIndex = [];
            foreach ($allPages as $page) {
                $pageIndex[$page['id']] = $page;
            }

            foreach ($allPages as $page) {
                $parentId = $page['parentId'] ?? null;
                $status = match ($page['status']) {
                    'current' => 'pending',
                    'trashed' => 'skipped',
                    'draft' => 'pending',
                    'archived' => $includeArchived ? 'pending' : 'skipped',
                    default => 'skipped',
                };

                if ($status === 'skipped' && !$includeArchived) {
                    continue;
                }

                $payload = [
                    'title' => $page['title'],
                    'space_key' => $spaceKey,
                    'space_id' => $spaceId,
                    'version' => $page['version'],
                    'created_at' => $page['createdAt'],
                    'updated_at' => $page['updatedAt'],
                ];

                $this->repo->upsertJobItem($jobId, 'page', $page['id'], [
                    'source_key' => $spaceKey . ':' . $page['id'],
                    'source_parent_id' => $parentId,
                    'status' => $mode === 'dry_run' ? 'skipped' : 'pending',
                    'source_updated_at' => $page['updatedAt'],
                    'payload_json' => $payload,
                ]);
                $totalCreated++;
            }
        }

        if ($mode === 'dry_run') {
            $this->repo->addJobLog($jobPublicId, 'info', 'crawl', 'Dry run: crawled ' . count($spaces) . ' spaces, ' . $totalCreated . ' total items');
        }

        return [
            'items_created' => $totalCreated,
            'warnings' => $warnings,
        ];
    }
}
