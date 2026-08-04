<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use PDO;
use Api\System\Library\Database\IndexHelper;

final class ModuleWebhookDispatcher
{
    private PDO $pdo;
    private string $tableName = 'module_webhooks';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Register a webhook for a module.
     */
    public function register(string $moduleName, string $event, string $url, string $secret = '', array $headers = []): int
    {
        $now = date('Y-m-d H:i:s');
        $headersJson = json_encode($headers, JSON_UNESCAPED_UNICODE);

        $stmt = $this->pdo->prepare("INSERT INTO {$this->tableName} (module_name, event_name, url, secret, is_active, headers, retry_count, timeout, created_at) VALUES (:module, :event, :url, :secret, 1, :headers, 3, 30, :now)");
        $stmt->execute([
            'module' => $moduleName,
            'event' => $event,
            'url' => $url,
            'secret' => $secret,
            'headers' => $headersJson,
            'now' => $now,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Dispatch a webhook event.
     * @return array{status: string, code: int|null}
     */
    public function dispatch(string $moduleName, string $event, array $payload): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE module_name = :module AND event_name = :event AND is_active = 1");
        $stmt->execute(['module' => $moduleName, 'event' => $event]);
        $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($webhooks as $webhook) {
            $results[] = $this->sendWebhook($webhook, $payload);
        }

        return $results !== [] ? $results[0] : ['status' => 'no_webhooks', 'code' => null];
    }

    /**
     * @param array<string, mixed> $webhook
     * @param array<string, mixed> $payload
     * @return array{status: string, code: int|null}
     */
    private function sendWebhook(array $webhook, array $payload): array
    {
        $url = $webhook['url'];
        $secret = $webhook['secret'] ?? '';
        $headers = json_decode($webhook['headers'] ?? '[]', true) ?: [];
        $timeout = (int)($webhook['timeout'] ?? 30);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        $requestHeaders = array_merge($headers, ['Content-Type: application/json']);
        if ($secret !== '') {
            $requestHeaders[] = 'X-Webhook-Signature: ' . hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE), $secret);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '' || $httpCode >= 400) {
            return ['status' => 'failed', 'code' => $httpCode ?: 0];
        }

        return ['status' => 'sent', 'code' => $httpCode];
    }

    /** @return array<int, array<string, mixed>> */
    public function getWebhooks(string $moduleName): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE module_name = :module ORDER BY id DESC");
        $stmt->execute(['module' => $moduleName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function deleteWebhooks(string $moduleName): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE module_name = :module");
        $stmt->execute(['module' => $moduleName]);
    }

    public function ensureTable(string $driver): void
    {
        $id = match ($driver) {
            'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'pgsql' => 'SERIAL PRIMARY KEY',
            'sqlsrv' => 'INT IDENTITY(1,1) PRIMARY KEY',
            default => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        };

        $dt = $driver === 'sqlsrv' ? 'DATETIME2' : 'DATETIME';
        $nowDefault = $driver === 'sqlite' ? "DEFAULT (datetime('now'))" : 'DEFAULT CURRENT_TIMESTAMP';
        $keyType = $driver === 'mysql' ? 'VARCHAR(190)' : 'TEXT';

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->tableName} (id {$id}, module_name {$keyType} NOT NULL, event_name {$keyType} NOT NULL, url {$keyType} NOT NULL, secret {$keyType}, is_active INTEGER NOT NULL DEFAULT 1, headers {$keyType}, retry_count INTEGER NOT NULL DEFAULT 3, timeout INTEGER NOT NULL DEFAULT 30, created_at {$dt} NOT NULL {$nowDefault})");

        try {
            IndexHelper::createIndexIfNotExists($this->pdo, $this->tableName, 'idx_module_webhooks_module', 'module_name');
        } catch (\Throwable $e) {
            error_log('[ModuleWebhookDispatcher::ensureTable] ensureTable failed: ' . $e->getMessage());
        }
    }
}
