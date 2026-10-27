<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Repository;

use PDO;

/**
 * Stock rows received from a store (the CRM side of the connector `syncStock`).
 *
 * Every write is an upsert keyed by (store_id, sku), so a store may resend a
 * whole catalogue page, a single SKU or an out-of-order batch without creating
 * duplicates. `ecommerce_stock_sync_log` records one row per applied packet.
 */
final class StockRepository
{
    private string $driver;

    public function __construct(private readonly PDO $pdo)
    {
        $this->driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Insert or update one SKU.
     *
     * A stock line may carry only the quantity (the connector's push shape);
     * fields the store did not send must keep their stored value instead of
     * being nulled out, so the update branch is guarded by the `set_*` flags
     * the service derives from the raw line. `quantity` is always written.
     *
     * @param array<string,mixed> $item Normalized item (see StockSyncService::normalizeItem()).
     */
    public function applyItem(int $storeId, array $item, string $source, string $now): bool
    {
        $provided = is_array($item['provided'] ?? null) ? $item['provided'] : [];
        $params = [
            ':store_id' => $storeId,
            ':sku' => (string)$item['sku'],
            ':title' => isset($item['title']) && $item['title'] !== '' ? mb_substr((string)$item['title'], 0, 255) : null,
            ':quantity' => (int)($item['quantity'] ?? 0),
            ':price_minor' => isset($item['price_minor']) ? (int)$item['price_minor'] : null,
            ':currency' => isset($item['currency']) && $item['currency'] !== '' ? mb_substr((string)$item['currency'], 0, 8) : null,
            ':is_active' => array_key_exists('is_active', $item) ? ((int)(bool)$item['is_active']) : 1,
            ':source' => $source,
            ':synced_at' => $now,
            ':created_at' => $now,
            ':updated_at' => $now,
            ':set_title' => !empty($provided['title']) ? 1 : 0,
            ':set_price' => !empty($provided['price_minor']) ? 1 : 0,
            ':set_currency' => !empty($provided['currency']) ? 1 : 0,
            ':set_active' => !empty($provided['is_active']) ? 1 : 0,
            ':title_u' => isset($item['title']) && $item['title'] !== '' ? mb_substr((string)$item['title'], 0, 255) : null,
            ':price_u' => isset($item['price_minor']) ? (int)$item['price_minor'] : null,
            ':currency_u' => isset($item['currency']) && $item['currency'] !== '' ? mb_substr((string)$item['currency'], 0, 8) : null,
            ':active_u' => array_key_exists('is_active', $item) ? ((int)(bool)$item['is_active']) : 1,
            ':quantity_u' => (int)($item['quantity'] ?? 0),
            ':source_u' => $source,
            ':synced_u' => $now,
            ':updated_u' => $now,
        ];

        if ($this->driver === 'mysql') {
            $sql = 'INSERT INTO ecommerce_stock (store_id, sku, title, quantity, price_minor, currency, is_active, source, synced_at, created_at, updated_at)'
                . ' VALUES (:store_id, :sku, :title, :quantity, :price_minor, :currency, :is_active, :source, :synced_at, :created_at, :updated_at)'
                . ' ON DUPLICATE KEY UPDATE'
                . ' title = IF(:set_title = 1, :title_u, title),'
                . ' quantity = :quantity_u,'
                . ' price_minor = IF(:set_price = 1, :price_u, price_minor),'
                . ' currency = IF(:set_currency = 1, :currency_u, currency),'
                . ' is_active = IF(:set_active = 1, :active_u, is_active),'
                . ' source = :source_u, synced_at = :synced_u, updated_at = :updated_u';
        } else {
            $sql = 'INSERT INTO ecommerce_stock (store_id, sku, title, quantity, price_minor, currency, is_active, source, synced_at, created_at, updated_at)'
                . ' VALUES (:store_id, :sku, :title, :quantity, :price_minor, :currency, :is_active, :source, :synced_at, :created_at, :updated_at)'
                . ' ON CONFLICT(store_id, sku) DO UPDATE SET'
                . ' title = CASE WHEN :set_title = 1 THEN :title_u ELSE title END,'
                . ' quantity = :quantity_u,'
                . ' price_minor = CASE WHEN :set_price = 1 THEN :price_u ELSE price_minor END,'
                . ' currency = CASE WHEN :set_currency = 1 THEN :currency_u ELSE currency END,'
                . ' is_active = CASE WHEN :set_active = 1 THEN :active_u ELSE is_active END,'
                . ' source = :source_u, synced_at = :synced_u, updated_at = :updated_u';
        }

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    /** @return array<string,mixed>|null */
    public function findBySku(int $storeId, string $sku): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ecommerce_stock WHERE store_id = ? AND sku = ? LIMIT 1');
        $stmt->execute([$storeId, $sku]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array{items:int,quantity:int,out_of_stock:int,last_synced_at:?string} */
    public function summary(int $storeId): array
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS items, COALESCE(SUM(quantity), 0) AS quantity,'
            . ' COALESCE(SUM(CASE WHEN quantity <= 0 THEN 1 ELSE 0 END), 0) AS out_of_stock, MAX(synced_at) AS last_synced_at'
            . ' FROM ecommerce_stock WHERE store_id = ?');
        $stmt->execute([$storeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'items' => (int)($row['items'] ?? 0),
            'quantity' => (int)($row['quantity'] ?? 0),
            'out_of_stock' => (int)($row['out_of_stock'] ?? 0),
            'last_synced_at' => $row['last_synced_at'] ?? null,
        ];
    }

    /**
     * Write one journal row for a sync packet.
     *
     * @param array{total:int,applied:int,skipped:int,failed:int,status:string,error?:?string} $counters
     */
    public function logSync(int $storeId, string $direction, array $counters, ?string $requestId): string
    {
        $publicId = 'stl_' . bin2hex(random_bytes(8));
        $this->pdo->prepare('INSERT INTO ecommerce_stock_sync_log (public_id, store_id, direction, items_total, items_applied, items_skipped, items_failed, status, error, request_id, created_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $publicId,
                $storeId,
                $direction,
                (int)$counters['total'],
                (int)$counters['applied'],
                (int)$counters['skipped'],
                (int)$counters['failed'],
                (string)$counters['status'],
                $counters['error'] ?? null,
                $requestId,
                gmdate('Y-m-d H:i:s'),
            ]);

        return $publicId;
    }

    /** @return list<array<string,mixed>> */
    public function recentLog(int $storeId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare('SELECT * FROM ecommerce_stock_sync_log WHERE store_id = ? ORDER BY id DESC LIMIT ' . $limit);
        $stmt->execute([$storeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
