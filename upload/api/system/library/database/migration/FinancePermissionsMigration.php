<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Migration: Seed finance permission codes and grant defaults.
 *
 * Seeds the 6 permission codes defined in PermissionService (TZ 6.1):
 *   - finance.rate.view_own_payout
 *   - finance.rate.view_own_cost
 *   - finance.rate.view_cost
 *   - finance.rate.view_bill
 *   - finance.rate.manage
 *   - finance.ratecard.manage
 *
 * Grant defaults (TZ 2.12.1, 15.1):
 *   - external_guest → finance.rate.view_own_payout (so freelancers can see
 *     their own earnings; the menu item appears only when payout data exists).
 *   - No other roles receive any finance permissions automatically.
 *
 * Idempotent: re-running this migration is safe — it only inserts missing
 * permission rows and ensures exactly the intended role-permission links.
 */
final class FinancePermissionsMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260821_000005_finance_permissions';
    }

    public function description(): string
    {
        return 'Finance permissions: seed codes + grant view_own_payout to external_guest';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $this->seedPermissionCodes($pdo);
        $this->grantExternalGuestPayout($pdo);
    }

    private function seedPermissionCodes(PDO $pdo): void
    {
        $now = gmdate('Y-m-d H:i:s');

        $codes = [
            'finance.rate.view_own_payout' => 'Finance: view own payout',
            'finance.rate.view_own_cost'   => 'Finance: view own cost',
            'finance.rate.view_cost'       => 'Finance: view team costs',
            'finance.rate.view_bill'       => 'Finance: view bill rates and margin',
            'finance.rate.manage'          => 'Finance: manage rates, recalculate, lock periods',
            'finance.ratecard.manage'      => 'Finance: manage rate cards and assignments',
        ];

        $stmt = $pdo->prepare(
            'SELECT id FROM permissions WHERE code = :code'
        );

        $insert = $pdo->prepare(
            'INSERT INTO permissions (public_id, code, title, created_at)
             VALUES (:public_id, :code, :title, :created_at)'
        );

        foreach ($codes as $code => $title) {
            $stmt->execute([':code' => $code]);
            if ($stmt->fetch()) {
                continue; // already exists
            }

            $publicId = 'perm_fin_' . substr(bin2hex(random_bytes(8)), 0, 16);
            $insert->execute([
                ':public_id' => $publicId,
                ':code' => $code,
                ':title' => $title,
                ':created_at' => $now,
            ]);
        }
    }

    /**
     * Grant finance.rate.view_own_payout to the external_guest role.
     *
     * This is the ONLY automatic grant (TZ 15.1). The right is harmless
     * without data: payout_rate must be set on the user or in a rate card
     * for any amounts to appear. The menu item is gated by the
     * /me/earnings/available endpoint, which returns false when there is
     * no payout data, so companies that don't use hourly payouts won't
     * see any visible change.
     */
    private function grantExternalGuestPayout(PDO $pdo): void
    {
        $now = gmdate('Y-m-d H:i:s');

        $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE code = 'external_guest'");
        $roleStmt->execute();
        $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
        if (!$roleRow) {
            return; // external_guest role doesn't exist on this install
        }
        $roleId = (int)$roleRow['id'];

        $permStmt = $pdo->prepare(
            "SELECT id FROM permissions WHERE code = 'finance.rate.view_own_payout'"
        );
        $permStmt->execute();
        $permRow = $permStmt->fetch(PDO::FETCH_ASSOC);
        if (!$permRow) {
            return; // permission code not yet seeded — shouldn't happen
        }
        $permId = (int)$permRow['id'];

        $check = $pdo->prepare(
            'SELECT id FROM role_permissions WHERE role_id = :role_id AND permission_id = :permission_id'
        );
        $check->execute([':role_id' => $roleId, ':permission_id' => $permId]);

        if ($check->fetch()) {
            return; // already granted
        }

        $pdo->prepare(
            'INSERT INTO role_permissions (role_id, permission_id, created_at)
             VALUES (:role_id, :permission_id, :created_at)'
        )->execute([
            ':role_id' => $roleId,
            ':permission_id' => $permId,
            ':created_at' => $now,
        ]);
    }
}