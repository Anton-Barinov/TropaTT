<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Store a masked preview of the plain API key (first 6 + last 4 chars) so the
 * admin table can show "apk_RIkQja*****00A" instead of the opaque public_id.
 * The full plain key is never stored — only this cosmetic preview.
 */
final class ApiKeyPreviewMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260908_000001_api_key_preview';
    }

    public function description(): string
    {
        return 'Add key_preview column to api_keys for identification in admin UI';
    }

    public function up(PDO $pdo, string $driver): void
    {
        IndexHelper::addColumnIfNotExists($pdo, 'api_keys', 'key_preview', 'VARCHAR(64) NULL', $driver);
    }
}
