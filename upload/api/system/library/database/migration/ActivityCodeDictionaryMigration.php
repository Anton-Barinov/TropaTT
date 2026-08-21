<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Service\ActivityCodeService;
use PDO;

/**
 * Migration: seed the work-type (activity) dictionary (TZ 2.5, Phase 6).
 *
 * Activity codes are stored in the existing `statuses` table under the
 * `worklog_activity` scope (reusing the generic dictionary, not a new entity).
 * This seeds the five default codes so rate-card lines can reference them and
 * the time-entry form has a ready default list.
 *
 * Idempotent: re-running only inserts codes that are missing.
 */
final class ActivityCodeDictionaryMigration implements MigrationInterface
{
    private const DEFAULTS = [
        'dev'        => ['Разработка', '#2563eb', 10],
        'design'     => ['Дизайн', '#7c3aed', 20],
        'analysis'   => ['Аналитика', '#0891b2', 30],
        'consulting' => ['Консультации', '#d97706', 40],
        'support'    => ['Поддержка', '#16a34a', 50],
    ];

    public function key(): string
    {
        return '20260821_000006_activity_code_dictionary';
    }

    public function description(): string
    {
        return 'Seed the worklog activity code dictionary (statuses scope worklog_activity)';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $now = gmdate('Y-m-d H:i:s');

        $check = $pdo->prepare(
            'SELECT id FROM statuses WHERE scope = :scope AND code = :code'
        );
        $insert = $pdo->prepare(
            'INSERT INTO statuses (public_id, scope, code, title, color, sort_order, is_active, created_at, updated_at)
             VALUES (:public_id, :scope, :code, :title, :color, :sort_order, 1, :created_at, :updated_at)'
        );

        foreach (self::DEFAULTS as $code => [$title, $color, $sortOrder]) {
            $check->execute([':scope' => ActivityCodeService::SCOPE, ':code' => $code]);
            if ($check->fetch()) {
                continue;
            }

            $insert->execute([
                ':public_id' => 'sts_' . strtoupper(bin2hex(random_bytes(8))),
                ':scope' => ActivityCodeService::SCOPE,
                ':code' => $code,
                ':title' => $title,
                ':color' => $color,
                ':sort_order' => $sortOrder,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }
    }
}
