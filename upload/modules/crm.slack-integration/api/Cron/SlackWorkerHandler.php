<?php
declare(strict_types=1);

namespace Module\Crm\SlackIntegration\Cron;

use Api\System\Library\Config;
use Api\System\Library\Database\ConnectionManager;
use Api\System\Library\Module\ModuleAutoloader;
use Api\System\Library\Support\Autoloader;
use Api\System\Library\Support\EnvLoader;
use Module\Crm\SlackIntegration\Repository\SlackRepository;
use Module\Crm\SlackIntegration\Service\EncryptionService;
use Module\Crm\SlackIntegration\Service\SlackClient;

final class SlackWorkerHandler
{
    public function run(): string
    {
        $basePath = dirname(__DIR__, 4);

        $autoloader = new Autoloader($basePath . '/api');
        $autoloader->register();

        if (class_exists(EnvLoader::class)) {
            EnvLoader::loadFiles([
                $basePath . '/.env',
                $basePath . '/.env.local',
                $basePath . '/api/.env',
                $basePath . '/api/.env.local',
            ]);
        }

        $config = new Config($basePath . '/api/config');
        $config->load($basePath . '/api/config/database.php', 'database');

        $connectionManager = new ConnectionManager($config);
        $pdo = $connectionManager->connect();

        $moduleAutoloader = new ModuleAutoloader($basePath);
        $moduleAutoloader->registerModule('crm.slack-integration', 'crm');
        $moduleAutoloader->register();

        $repo = new SlackRepository($pdo);
        $client = new SlackClient(10);

        $maxRetries = 3;
        $backoffSeconds = 30;
        $processed = 0;

        for ($i = 0; $i < 50; $i++) {
            try {
                $delivery = $repo->claimNextDelivery();
            } catch (\Throwable $e) {
                error_log('[SlackWorkerHandler] claim failed: ' . $e->getMessage());
                break;
            }
            if ($delivery === null) {
                break;
            }

            $publicId = (string)$delivery['public_id'];
            $processed++;

            try {
                $connection = $repo->getConnectionById((int)($delivery['connection_id'] ?? 0));
                if (!$connection) {
                    $repo->markFailed($publicId, 0, 'Connection not found', $maxRetries, $backoffSeconds);
                    continue;
                }
                $webhookUrl = EncryptionService::decrypt((string)($connection['webhook_url_encrypted'] ?? ''));
                if ($webhookUrl === null) {
                    $repo->markFailed($publicId, 0, 'Failed to decrypt webhook URL', $maxRetries, $backoffSeconds);
                    continue;
                }

                $payload = json_decode((string)($delivery['payload_json'] ?? '{}'), true);
                if (!is_array($payload)) {
                    $payload = [];
                }

                $result = $client->send($webhookUrl, $payload);
                if ($result['success']) {
                    $repo->markDelivered($publicId, $result['response_code']);
                } else {
                    $repo->markFailed($publicId, $result['response_code'], $result['message'], $maxRetries, $backoffSeconds);
                }
            } catch (\Throwable $e) {
                error_log('[SlackWorkerHandler] delivery ' . $publicId . ' failed: ' . $e->getMessage());
                $repo->markFailed($publicId, 0, 'Delivery failed. Check server logs for details.', $maxRetries, $backoffSeconds);
            }
        }

        return json_encode(['processed' => $processed], JSON_UNESCAPED_UNICODE);
    }
}
