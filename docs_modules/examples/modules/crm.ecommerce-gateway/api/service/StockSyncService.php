<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

use Module\Crm\EcommerceGateway\Repository\IngestRepository;
use Module\Crm\EcommerceGateway\Repository\StockRepository;
use Throwable;

/**
 * Stock intake pipeline (the CRM counterpart of the connectors' `syncStock`).
 *
 * The OpenCart/WooCommerce connectors expose a signed `syncStock` route with
 * `mode=pull` (paged catalogue snapshot) and `mode=push` (apply a stock batch),
 * but the CRM had no receiving side, so stock could be read from a store and
 * nowhere to send it. This service is that receiving side: it accepts a batch
 * of stock lines over the same HMAC transport as orders (`StoreAuthService`
 * verifies the signature before this service runs) and upserts them per store.
 *
 * Accepted line shape — both the canonical connector form
 * `{ "sku", "quantity", "name", "price": {"amount_minor", "currency"}, "status" }`
 * and the flat convenience form `{ "sku", "quantity", "title", "price_minor",
 * "currency", "is_active" }` are understood (see `normalizeItem()`).
 *
 * Each SKU is idempotent on its own key `{store_key}:stock:{sku}` derived from
 * the payload, so a connector may resend a whole catalogue page, a single SKU
 * or an out-of-order batch without duplicates: an unchanged repeat is reported
 * as `unchanged`, a changed quantity is applied and the idempotency record is
 * refreshed. The whole packet is journaled in `ecommerce_stock_sync_log` for
 * diagnostics and reconciliation, whatever the outcome.
 */
final class StockSyncService
{
    public const MAX_ITEMS = 500;
    public const TYPE = 'stock';

    public function __construct(
        private readonly StockRepository $stock,
        private readonly IdempotencyService $idempotency,
        private readonly IngestRepository $repository
    ) {
    }

    /**
     * @param array<string,mixed> $store Authenticated store row from StoreAuthService.
     * @return array<string,mixed>
     */
    public function apply(array $store, string $rawBody, ?string $idempotencyHeader, ?string $requestId): array
    {
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return $this->fail('INGESTION_PAYLOAD_INVALID', 400);
        }

        // The connector sends `products` on pull and `items`/`lines` on push;
        // accept all three so the CRM can replay a store's own page verbatim.
        $items = $payload['items'] ?? $payload['lines'] ?? $payload['products'] ?? null;
        if (!is_array($items) || $items === []) {
            return $this->fail('STOCK_ITEMS_REQUIRED', 400);
        }
        $items = array_values($items);
        if (count($items) > self::MAX_ITEMS) {
            return $this->fail('STOCK_BATCH_TOO_LARGE', 400);
        }

        $storeId = (int)($store['id'] ?? 0);
        $storeKey = (string)($store['api_key'] ?? '');
        $dryRun = !empty($payload['dry_run']);
        $now = gmdate('Y-m-d H:i:s');
        $applied = 0;
        $skipped = 0;
        $failed = 0;
        $results = [];
        $errors = [];

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                $failed++;
                $errors['items.' . $index] = 'item must be an object';
                $results[] = ['index' => $index, 'result' => 'failed', 'reason' => 'invalid_line'];
                continue;
            }

            $invalid = $this->validateItem($item);
            if ($invalid !== null) {
                $failed++;
                $field = $invalid === 'quantity' || $invalid === 'price_minor' || $invalid === 'price.amount_minor'
                    ? 'items.' . $index . '.' . $invalid
                    : 'items.' . $index . '.' . $invalid;
                $errors[$field] = $invalid === 'sku' ? 'sku is required' : $invalid . ' must be a number';
                $results[] = ['index' => $index, 'result' => 'failed', 'reason' => 'invalid_' . str_replace('.', '_', $invalid)];
                continue;
            }

            $normalized = $this->normalizeItem($item);
            $sku = (string)$normalized['sku'];
            $quantity = (int)$normalized['quantity'];

            $hash = hash('sha256', implode('|', [
                (string)$storeId,
                $sku,
                (string)$quantity,
                $normalized['price_minor'] === null ? '' : (string)$normalized['price_minor'],
                (string)$normalized['currency'],
            ]));

            // A packet holding exactly one SKU may carry an explicit key; a
            // batch always keys per SKU, so retrying one line never rewrites
            // the whole page.
            $key = ($idempotencyHeader !== null && $idempotencyHeader !== '' && count($items) === 1)
                ? $idempotencyHeader
                : $storeKey . ':stock:' . $sku;

            if ($dryRun) {
                $results[] = ['index' => $index, 'sku' => $sku, 'quantity' => $quantity, 'result' => 'would_apply'];
                $applied++;
                continue;
            }

            try {
                $decision = $this->idempotency->decide($storeId, self::TYPE, $sku, $hash, $key, 'merge', 90);
                $state = (string)($decision['state'] ?? '');

                if ($state === IdempotencyService::STATE_DUPLICATE) {
                    $skipped++;
                    $results[] = ['index' => $index, 'sku' => $sku, 'result' => 'unchanged', 'reason' => 'duplicate'];
                    continue;
                }
                if ($state === IdempotencyService::STATE_CONFLICT) {
                    $failed++;
                    $errors['items.' . $index . '.sku'] = 'idempotency key is bound to another sku';
                    $results[] = ['index' => $index, 'sku' => $sku, 'result' => 'failed', 'reason' => 'idempotency_conflict'];
                    continue;
                }

                // `merge` keeps the idempotency record's original hash, so a
                // later repeat of an *updated* quantity would look like a new
                // body. Compare against the stored row instead: equal stock is
                // `unchanged` (the vocabulary the connectors' syncStock uses)
                // and must not touch the database.
                if ($state === IdempotencyService::STATE_MERGED && $this->isUnchanged($storeId, $normalized)) {
                    $skipped++;
                    $results[] = ['index' => $index, 'sku' => $sku, 'result' => 'unchanged', 'reason' => 'same_values'];
                    continue;
                }

                $this->stock->applyItem($storeId, $normalized, 'push', $now);

                $this->repository->completeIdempotency(
                    $storeId,
                    (string)$decision['key'],
                    'stock_item',
                    $storeKey . ':' . $sku,
                    ['sku' => $sku, 'quantity' => $quantity, 'state' => $state]
                );

                $applied++;
                $results[] = ['index' => $index, 'sku' => $sku, 'quantity' => $quantity, 'result' => 'applied'];
            } catch (Throwable $e) {
                $failed++;
                $errors['items.' . $index] = 'could not apply stock item';
                $results[] = ['index' => $index, 'sku' => $sku, 'result' => 'failed', 'reason' => 'storage_error'];
                $this->logItemFailure($storeId, $storeKey, $sku, $e);
            }
        }

        $status = $failed === 0 ? 'ok' : ($applied > 0 ? 'partial' : 'failed');
        $total = count($items);

        $logPublicId = $this->stock->logSync($storeId, $dryRun ? 'dry_run' : 'push', [
            'total' => $total,
            'applied' => $applied,
            'skipped' => $skipped,
            'failed' => $failed,
            'status' => $status,
            'error' => $failed > 0 ? (string)json_encode($errors, JSON_UNESCAPED_UNICODE) : null,
        ], $requestId);

        return [
            'ok' => $failed === 0,
            'code' => $failed === 0 ? 'INGESTION_STOCK_APPLIED' : 'INGESTION_VALIDATION_FAILED',
            'http_status' => $failed === 0 ? 200 : 422,
            'errors' => $errors,
            'items' => $results,
            'counters' => ['total' => $total, 'applied' => $applied, 'skipped' => $skipped, 'failed' => $failed],
            'dry_run' => $dryRun,
            'log_public_id' => $logPublicId,
            'summary' => $this->stock->summary($storeId),
        ];
    }

    /**
     * Validate the raw line before normalization, returning the offending field
     * or null when the line is usable.
     *
     * @param array<string,mixed> $item
     */
    private function validateItem(array $item): ?string
    {
        if (trim((string)($item['sku'] ?? '')) === '') {
            return 'sku';
        }
        // A stock line without a quantity means nothing, and treating it as 0
        // would silently zero the SKU out. The connectors' push shape always
        // sends it, so requiring it keeps both sides symmetric.
        if (!array_key_exists('quantity', $item) || !is_numeric($item['quantity'])) {
            return 'quantity';
        }
        if ((float)$item['quantity'] < 0) {
            return 'quantity';
        }
        if (isset($item['price_minor']) && !is_numeric($item['price_minor'])) {
            return 'price_minor';
        }

        $price = is_array($item['price'] ?? null) ? $item['price'] : [];
        if (isset($price['amount_minor']) && !is_numeric($price['amount_minor'])) {
            return 'price.amount_minor';
        }

        return null;
    }

    /**
     * Normalize a stock line to the storage columns. Accepts both the canonical
     * connector shape (`name`, nested `price`, `status`) and the flat form
     * (`title`, `price_minor`, `currency`, `is_active`).
     *
     * Fields the store omitted are reported in `provided` so the repository can
     * keep their stored value instead of nulling it (a connector push line is
     * usually just `{sku, quantity}`).
     *
     * @param array<string,mixed> $item
     * @return array{sku:string,title:?string,quantity:int,price_minor:?int,currency:?string,is_active:int,provided:array<string,bool>}
     */
    private function normalizeItem(array $item): array
    {
        $price = is_array($item['price'] ?? null) ? $item['price'] : [];

        $hasPrice = array_key_exists('amount_minor', $price);
        $priceMinor = $item['price_minor'] ?? $price['amount_minor'] ?? null;
        $currency = $item['currency'] ?? $price['currency'] ?? null;
        $title = $item['title'] ?? $item['name'] ?? null;
        $active = $item['is_active'] ?? $item['status'] ?? 1;

        return [
            'sku' => trim((string)($item['sku'] ?? '')),
            'title' => $title === null ? null : (string)$title,
            'quantity' => max(0, (int)$item['quantity']),
            'price_minor' => is_numeric($priceMinor) ? (int)$priceMinor : null,
            'currency' => $currency === null ? null : strtoupper(trim((string)$currency)),
            'is_active' => (int)(bool)$active,
            'provided' => [
                'title' => array_key_exists('title', $item) || array_key_exists('name', $item),
                'price_minor' => array_key_exists('price_minor', $item) || $hasPrice,
                'currency' => array_key_exists('currency', $item) || array_key_exists('currency', $price),
                'is_active' => array_key_exists('is_active', $item) || array_key_exists('status', $item),
            ],
        ];
    }

    /**
     * True when the stored row already holds exactly the incoming values.
     *
     * Only the fields the store actually sent are compared, mirroring the
     * update semantics of the repository.
     *
     * @param array<string,mixed> $normalized
     */
    private function isUnchanged(int $storeId, array $normalized): bool
    {
        $row = $this->stock->findBySku($storeId, (string)$normalized['sku']);
        if ($row === null) {
            return false;
        }

        $provided = is_array($normalized['provided'] ?? null) ? $normalized['provided'] : [];

        if ((int)$row['quantity'] !== (int)$normalized['quantity']) {
            return false;
        }
        if (!empty($provided['price_minor'])
            && ($row['price_minor'] === null ? null : (int)$row['price_minor']) !== $normalized['price_minor']
        ) {
            return false;
        }
        if (!empty($provided['currency'])
            && (string)($row['currency'] ?? '') !== (string)($normalized['currency'] ?? '')
        ) {
            return false;
        }
        if (!empty($provided['is_active']) && (int)$row['is_active'] !== (int)$normalized['is_active']) {
            return false;
        }

        return true;
    }

    private function logItemFailure(int $storeId, string $storeKey, string $sku, Throwable $e): void
    {
        if (!class_exists('Api\\System\\Library\\Support\\AppLog')) {
            return;
        }
        \Api\System\Library\Support\AppLog::error('ecommerce-gateway stock item failed', [
            'store_id' => $storeId,
            'store_key' => $storeKey,
            'sku' => $sku,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function fail(string $code, int $status): array
    {
        return [
            'ok' => false,
            'code' => $code,
            'http_status' => $status,
            'errors' => [],
            'items' => [],
            'counters' => ['total' => 0, 'applied' => 0, 'skipped' => 0, 'failed' => 0],
            'dry_run' => false,
            'log_public_id' => null,
            'summary' => null,
        ];
    }
}
