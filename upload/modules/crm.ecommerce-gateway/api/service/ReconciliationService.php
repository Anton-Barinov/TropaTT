<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

use Module\Crm\EcommerceGateway\Repository\OutboxRepository;
use Module\Crm\EcommerceGateway\Repository\StoreRepository;
use PDO;

/**
 * Periodic order audit, catch-up ingestion and discrepancy reconciliation (E-COM-10 §2).
 *
 * Reconciles state between CMS storefronts and TropaTT CRM:
 * 1. Fetches recent order manifest from CMS storefront via signed GET request.
 * 2. Compares with local database records (ecommerce_order_sync_log, tasks, outbox).
 * 3. Identifies missing orders and triggers catch-up ingestion.
 * 4. Identifies status discrepancies and syncs accordingly.
 * 5. Cleans up zombie dead-letter queue records older than 30 days.
 */
final class ReconciliationService
{
    /** @var (callable(string, array<string,string>, int): array{status: int, body: string, error: ?string})|null */
    private $httpTransport = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly StoreRepository $storeRepo,
        private readonly OutboxRepository $outboxRepo,
        private readonly ?object $ingestService = null,
        private readonly array $config = [],
        ?callable $httpTransport = null
    ) {
        $this->httpTransport = $httpTransport;
    }

    /**
     * Executes order reconciliation for a given store.
     *
     * @param array<string,mixed> $store
     * @param array<string,mixed> $options ['since' => timestamp, 'orders' => [...]]
     * @return array{
     *     store_id: int,
     *     store_public_id: string,
     *     scanned: int,
     *     matched: int,
     *     missing_ingested: int,
     *     status_mismatches: int,
     *     errors: list<string>,
     *     archived_dlq_count: int
     * }
     */
    public function reconcile(array $store, array $options = []): array
    {
        $storeId = (int)$store['id'];
        $storePublicId = (string)$store['public_id'];
        $errors = [];
        $scanned = 0;
        $matched = 0;
        $missingIngested = 0;
        $statusMismatches = 0;

        // Auto-archive zombie dead-letter events older than 30 days
        $archivedDlqCount = $this->outboxRepo->archiveStaleDeadLetterEvents(30);

        // Orders list can be provided directly (for manual sync / testing) or fetched from CMS
        $cmsOrders = $options['orders'] ?? null;
        if (!is_array($cmsOrders)) {
            $cmsOrders = $this->fetchCmsAuditManifest($store, $options);
        }

        if ($cmsOrders === null) {
            $errors[] = 'Failed to fetch audit manifest from store';
            return [
                'store_id' => $storeId,
                'store_public_id' => $storePublicId,
                'scanned' => 0,
                'matched' => 0,
                'missing_ingested' => 0,
                'status_mismatches' => 0,
                'errors' => $errors,
                'archived_dlq_count' => $archivedDlqCount,
            ];
        }

        $scanned = count($cmsOrders);

        foreach ($cmsOrders as $cmsOrder) {
            if (!is_array($cmsOrder)) {
                continue;
            }

            $externalId = trim((string)($cmsOrder['external_order_id'] ?? $cmsOrder['order_id'] ?? $cmsOrder['id'] ?? ''));
            if ($externalId === '') {
                continue;
            }

            $cmsStatus = trim((string)($cmsOrder['status'] ?? $cmsOrder['order_status'] ?? ''));
            $localRecord = $this->findLocalOrder($storeId, $externalId);

            if ($localRecord === null) {
                // Missing order detected: trigger catch-up ingestion if payload is present
                $ingested = $this->catchUpIngest($store, $cmsOrder, $externalId);
                if ($ingested) {
                    $missingIngested++;
                    $this->storeRepo->audit('reconciliation.order_recovered', $storeId, 'system', 'reconciliation', [
                        'external_order_id' => $externalId,
                        'reason' => 'missing_in_crm',
                        'cms_status' => $cmsStatus,
                    ]);
                } else {
                    $errors[] = "Failed to catch-up ingest missing order #{$externalId}";
                }
            } else {
                $matched++;
                // Check for status discrepancy if status is provided in CMS manifest
                if ($cmsStatus !== '') {
                    $localStatus = (string)($localRecord['task_status'] ?? $localRecord['status'] ?? '');
                    if ($localStatus !== '' && $this->isStatusDivergent($localStatus, $cmsStatus)) {
                        $statusMismatches++;
                        $this->storeRepo->audit('reconciliation.status_mismatch', $storeId, 'system', 'reconciliation', [
                            'external_order_id' => $externalId,
                            'crm_status' => $localStatus,
                            'cms_status' => $cmsStatus,
                            'crm_task_id' => $localRecord['crm_task_id'] ?? null,
                        ]);
                    }
                }
            }
        }

        return [
            'store_id' => $storeId,
            'store_public_id' => $storePublicId,
            'scanned' => $scanned,
            'matched' => $matched,
            'missing_ingested' => $missingIngested,
            'status_mismatches' => $statusMismatches,
            'errors' => $errors,
            'archived_dlq_count' => $archivedDlqCount,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findLocalOrder(int $storeId, string $externalId): ?array
    {
        // 1. Check ecommerce_order_sync_log
        $stmt = $this->pdo->prepare(
            'SELECT l.*, t.status as task_status
             FROM ecommerce_order_sync_log l
             LEFT JOIN tasks t ON t.id = l.crm_task_id
             WHERE l.store_id = :store_id AND l.external_order_id = :order_id
             ORDER BY l.id DESC LIMIT 1'
        );
        $stmt->execute(['store_id' => $storeId, 'order_id' => $externalId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row) && !empty($row['id'])) {
            return $row;
        }

        // 2. Check ecommerce_ingest_events
        $stmt = $this->pdo->prepare(
            'SELECT id, public_id, store_id, external_id as external_order_id, entity_type, entity_public_id, status
             FROM ecommerce_ingest_events
             WHERE store_id = :store_id AND external_id = :order_id AND status = "accepted"
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['store_id' => $storeId, 'order_id' => $externalId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row) && !empty($row['id'])) {
            return $row;
        }

        // 3. Check tasks table by source_id
        $stmt = $this->pdo->prepare(
            'SELECT t.id as crm_task_id, t.public_id as crm_task_public_id, t.status as task_status, t.source_id as external_order_id
             FROM tasks t
             WHERE t.source_id = :order_id
             ORDER BY t.id DESC LIMIT 1'
        );
        $stmt->execute(['order_id' => $externalId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row) && !empty($row['crm_task_id'])) {
            return $row;
        }

        return null;
    }

    private function isStatusDivergent(string $crmStatus, string $cmsStatus): bool
    {
        $normCrm = OrderStatusStateMachine::normalize($crmStatus);
        $normCms = OrderStatusStateMachine::normalize($cmsStatus);

        return $normCrm !== $normCms;
    }

    /**
     * @param array<string,mixed> $store
     * @param array<string,mixed> $cmsOrder
     */
    private function catchUpIngest(array $store, array $cmsOrder, string $externalId): bool
    {
        if ($this->ingestService === null) {
            return false;
        }

        // Build valid order ingestion payload
        $payload = [
            'external_id' => $externalId,
            'currency' => (string)($cmsOrder['currency'] ?? 'RUB'),
            'total_amount' => (int)($cmsOrder['total_amount'] ?? ($cmsOrder['total'] ?? 0)),
            'items' => is_array($cmsOrder['items'] ?? null) ? $cmsOrder['items'] : [
                [
                    'name' => (string)($cmsOrder['comment'] ?? 'Заказ из сверки (Reconciliation) #' . $externalId),
                    'quantity' => 1,
                    'price' => (int)($cmsOrder['total_amount'] ?? ($cmsOrder['total'] ?? 0)),
                ]
            ],
            'contact' => is_array($cmsOrder['contact'] ?? null) ? $cmsOrder['contact'] : [
                'name' => (string)($cmsOrder['customer_name'] ?? 'Клиент ' . $externalId),
                'phone' => (string)($cmsOrder['customer_phone'] ?? ''),
                'email' => (string)($cmsOrder['customer_email'] ?? ''),
            ],
            'comment' => 'Восстановлено из периодической сверки заказов (Reconciliation Audit)',
        ];

        $rawBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $requestId = 'rec_' . bin2hex(random_bytes(8));

        $result = $this->ingestService->ingest(
            $store,
            'order',
            $rawBody !== false ? $rawBody : '{}',
            $requestId,
            '127.0.0.1',
            'TropaTT-Reconciliation/1.1'
        );

        return !empty($result['ok']);
    }

    /**
     * Fetches order audit manifest from CMS storefront.
     *
     * @param array<string,mixed> $store
     * @param array<string,mixed> $options
     * @return list<array<string,mixed>>|null
     */
    private function fetchCmsAuditManifest(array $store, array $options = []): ?array
    {
        $baseUrl = trim((string)($store['webhook_url'] ?? $store['store_url'] ?? ''));
        if ($baseUrl === '') {
            return null;
        }

        // Construct audit endpoint URL
        $parsed = parse_url($baseUrl);
        if (!$parsed || empty($parsed['host'])) {
            return null;
        }

        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'];
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = rtrim($parsed['path'] ?? '', '/');

        // Target: {base}/audit or {scheme}://{host}/tropatt-api/orders/audit
        $auditUrl = "{$scheme}://{$host}{$port}" . ($path !== '' ? "{$path}/audit" : '/tropatt-api/orders/audit');
        $since = isset($options['since']) ? (int)$options['since'] : (time() - 86400 * 2);
        $auditUrl .= '?since=' . $since;

        $secretEncrypted = (string)($store['webhook_secret_encrypted'] ?? $store['api_secret_encrypted'] ?? '');
        $secret = EncryptionService::decrypt($secretEncrypted) ?? '';
        $timestamp = (string)time();
        $nonce = bin2hex(random_bytes(16));

        $canonical = SignatureService::canonicalString('GET', (string)parse_url($auditUrl, PHP_URL_PATH), $timestamp, $nonce, '');
        $signature = base64_encode(hash_hmac('sha256', $canonical, $secret, true));

        $headers = [
            'Accept' => 'application/json',
            SignatureService::HEADER_STORE_KEY => (string)$store['api_key'],
            SignatureService::HEADER_TIMESTAMP => $timestamp,
            SignatureService::HEADER_NONCE => $nonce,
            SignatureService::HEADER_SIGNATURE => $signature,
            'User-Agent' => 'TropaTT-Reconciliation/1.1 (+https://tropatt.com)',
        ];

        $timeout = (int)($this->config['request_timeout_seconds'] ?? 10);
        $response = $this->sendHttpRequest($auditUrl, $headers, $timeout);

        if ($response['status'] >= 200 && $response['status'] < 300) {
            $data = json_decode($response['body'], true);
            if (is_array($data)) {
                $orders = $data['orders'] ?? $data['data']['orders'] ?? $data['data'] ?? null;
                if (is_array($orders)) {
                    /** @var list<array<string,mixed>> */
                    return array_values(array_filter($orders, 'is_array'));
                }
            }
        }

        return null;
    }

    /**
     * @param array<string,string> $headers
     * @return array{status: int, body: string, error: ?string}
     */
    private function sendHttpRequest(string $url, array $headers, int $timeout): array
    {
        if ($this->httpTransport !== null) {
            return ($this->httpTransport)($url, $headers, $timeout);
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $formattedHeaders = [];
            foreach ($headers as $k => $v) {
                $formattedHeaders[] = "{$k}: {$v}";
            }

            curl_setopt($ch, CURLOPT_HTTPGET, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $formattedHeaders);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $responseBody = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            return [
                'status' => $httpCode,
                'body' => is_string($responseBody) ? $responseBody : '',
                'error' => $curlError !== '' ? $curlError : null,
            ];
        }

        return [
            'status' => 0,
            'body' => '',
            'error' => 'cURL extension not available',
        ];
    }
}
