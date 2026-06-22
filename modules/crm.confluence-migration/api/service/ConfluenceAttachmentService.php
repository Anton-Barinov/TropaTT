<?php
declare(strict_types=1);

namespace Module\Crm\ConfluenceMigration\Service;

use Api\System\Library\Service\FileService;
use Api\System\Library\Support\Ulid;
use Module\Crm\ConfluenceMigration\Repository\ConfluenceMigrationRepository;
use PDO;

final class ConfluenceAttachmentService
{
    private int $maxSizeBytes;

    public function __construct(
        private FileService $fileService,
        private ConfluenceMigrationRepository $migrationRepo,
        private PDO $pdo,
        int $maxSizeMb = 50,
    ) {
        $this->maxSizeBytes = $maxSizeMb * 1024 * 1024;
    }

    public function importAttachment(array $job, string $baseUrl, string $email, string $token, array $attachment, string $targetPagePublicId): void
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];
        $attachmentId = $attachment['id'];
        $fileSize = (int)($attachment['fileSize'] ?? 0);
        $filename = $attachment['title'] ?? 'file.bin';
        $mediaType = $attachment['mediaType'] ?? 'application/octet-stream';

        // Check size limit
        if ($fileSize > $this->maxSizeBytes) {
            $this->migrationRepo->upsertJobItem($jobId, 'attachment', $attachmentId, [
                'source_key' => $filename,
                'status' => 'skipped',
                'error_code' => 'FILE_TOO_LARGE',
                'error_message' => 'File size ' . $fileSize . ' exceeds limit ' . $this->maxSizeBytes,
            ]);
            return;
        }

        // Check for existing attachment by source_id
        $existingItem = $this->migrationRepo->findJobItem($jobId, 'attachment', $attachmentId);
        if ($existingItem && $existingItem['status'] === 'imported') {
            return;
        }

        $tmpPath = sys_get_temp_dir() . '/confluence_attachment_' . bin2hex(random_bytes(8)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        try {
            // Download attachment
            $downloadClient = new ConfluenceClient(repo: $this->migrationRepo);
            $downloadClient->setConnectionId((int)$job['connection_id']);
            $downloadResult = $downloadClient->downloadAttachment($baseUrl, $email, $token, $attachment, $tmpPath);

            if (!$downloadResult['success']) {
                $this->migrationRepo->upsertJobItem($jobId, 'attachment', $attachmentId, [
                    'source_key' => $filename,
                    'status' => 'failed',
                    'error_code' => 'DOWNLOAD_FAILED',
                    'error_message' => $downloadResult['error'] ?? 'Download failed',
                ]);
                return;
            }

            // Upload via FileService
            $uploadResult = $this->fileService->create(
                [
                    'entity_type' => 'knowledge_page',
                    'entity_public_id' => $targetPagePublicId,
                    'name' => $filename,
                    'mime_type' => $mediaType,
                    'content_base64' => base64_encode(file_get_contents($tmpPath)),
                    'source_type' => 'confluence',
                    'source_id' => $attachmentId,
                    'source_url' => $this->resolveDownloadUrl($baseUrl, $attachment['downloadLink'] ?? ''),
                    'checksum' => hash_file('sha256', $tmpPath),
                ],
                [],
                0, // uploader user id - will use system
                []
            );

            $filePublicId = $uploadResult['public_id'] ?? null;
            $status = $filePublicId ? 'imported' : 'failed';

            $this->migrationRepo->upsertJobItem($jobId, 'attachment', $attachmentId, [
                'source_key' => $filename,
                'target_type' => 'file',
                'target_public_id' => $filePublicId,
                'status' => $status,
                'checksum' => hash_file('sha256', $tmpPath),
                'payload_json' => [
                    'filename' => $filename,
                    'mime_type' => $mediaType,
                    'size' => $fileSize,
                ],
            ]);

            // Update attachment count on page
            if ($status === 'imported') {
                $this->pdo->prepare('UPDATE knowledge_pages SET attachments_count = attachments_count + 1 WHERE public_id = :pub')->execute(['pub' => $targetPagePublicId]);
            }
        } catch (\Throwable $e) {
            $this->migrationRepo->upsertJobItem($jobId, 'attachment', $attachmentId, [
                'source_key' => $filename,
                'status' => 'failed',
                'error_code' => 'IMPORT_ERROR',
                'error_message' => $e->getMessage(),
            ]);
            $this->migrationRepo->addJobLog($jobPublicId, 'error', 'import_attachments', 'Failed to import attachment ' . $filename . ': ' . $e->getMessage());
        } finally {
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    private function resolveDownloadUrl(string $baseUrl, string $downloadLink): string
    {
        if ($downloadLink === '') {
            return $baseUrl;
        }
        if (str_starts_with($downloadLink, 'http://') || str_starts_with($downloadLink, 'https://')) {
            return $downloadLink;
        }
        $origin = parse_url($baseUrl, PHP_URL_SCHEME) . '://' . parse_url($baseUrl, PHP_URL_HOST);
        return $origin . $downloadLink;
    }
}
