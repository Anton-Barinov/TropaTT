<?php
declare(strict_types=1);

namespace Api\Controller\Health;

use Api\Controller\Common\BaseController;
use Api\System\Library\Database\ConnectionManager;
use Api\System\Library\Security\EnvironmentCapabilities;
use Throwable;

final class HealthController extends BaseController
{
    public function status(): \Api\System\Library\Http\JsonResponse
    {
        return $this->success('HEALTH_OK', $this->t('health/messages.status'), [
            'status' => 'ok',
            'version' => 'v1',
        ]);
    }

    public function deep(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth || !(bool)($auth['user']['is_root'] ?? false)) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403, [
                'health' => [$this->t('common/messages.permission_denied_action')],
            ]);
        }

        $dbRead = false;
        $dbWrite = false;
        $storageRw = false;
        $queueReady = false;
        $checks = [];

        try {
            /** @var ConnectionManager $connections */
            $connections = $this->container->get('db.connection_manager');
            $pdo = $connections->connect();

            $dbRead = (int)$pdo->query('SELECT 1')->fetchColumn() === 1;

            $probeTable = 'health_probe_' . substr(bin2hex(random_bytes(6)), 0, 8);
            $pdo->exec('CREATE TEMPORARY TABLE ' . $probeTable . ' (v INTEGER)');
            $pdo->exec('INSERT INTO ' . $probeTable . ' (v) VALUES (1)');
            $dbWrite = (int)$pdo->query('SELECT COUNT(*) FROM ' . $probeTable)->fetchColumn() >= 1;
            $pdo->exec('DROP TABLE ' . $probeTable);
        } catch (Throwable $e) {
            $checks['db'] = false;
        }

        try {
            $storageBase = (string)$this->container->get('config')->get('default.storage.base', dirname(__DIR__, 3) . '/storage');
            $tempDir = rtrim($storageBase, '/\\') . '/temp';
            if (!is_dir($tempDir)) {
                @mkdir($tempDir, 0775, true);
            }

            $probeFile = $tempDir . '/health_rw_probe_' . bin2hex(random_bytes(4)) . '.tmp';
            $written = @file_put_contents($probeFile, 'ok');
            if ($written !== false) {
                $storageRw = true;
                @unlink($probeFile);
            }
        } catch (Throwable $e) {
            $checks['storage'] = false;
        }

        try {
            $storageBase = (string)$this->container->get('config')->get('default.storage.base', dirname(__DIR__, 3) . '/storage');
            $queueDir = rtrim($storageBase, '/\\') . '/queue';
            $queueReady = is_dir($queueDir) && is_writable($queueDir);
            $checks['queue'] = $queueReady;
        } catch (Throwable $e) {
            $checks['queue'] = false;
        }

        $degraded = !($dbRead && $dbWrite && $storageRw && $queueReady);

        // SEC-TASK-00: Environment capabilities check
        $env = new EnvironmentCapabilities();
        $environment = $env->toArray();

        // SEC-TASK-01.E: Self-check storage protection
        $storageProtectionOk = null; // null = not checked
        $storageProbeError = null;
        try {
            $config = $this->container->get('config');
            $uploadsBase = rtrim((string)$config->get('default.storage_api.base', dirname(__DIR__, 3) . '/storage_api'), '/\\') . '/uploads';
            if (is_dir($uploadsBase) && is_writable($uploadsBase)) {
                $probeName = '.health_probe_' . bin2hex(random_bytes(6)) . '.txt';
                $probeFile = $uploadsBase . '/' . $probeName;
                $probeContent = bin2hex(random_bytes(8));
                $written = @file_put_contents($probeFile, $probeContent);
                if ($written !== false) {
                    // Probe file written. Check if accessible via HTTP.
                    // Only check if storage is inside DocumentRoot.
                    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
                    $resolvedDocRoot = $docRoot !== '' ? realpath($docRoot) : false;
                    $resolvedUploads = realpath($uploadsBase);
                    if ($resolvedDocRoot && $resolvedUploads && str_starts_with($resolvedUploads, $resolvedDocRoot)) {
                        // Storage is inside DocumentRoot — try HTTP access
                        $scheme = $env->isHttps() ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        // Compute relative path from docroot to uploads
                        $relPath = substr($resolvedUploads, strlen($resolvedDocRoot));
                        $probeUrl = $scheme . '://' . $host . '/' . ltrim($relPath, '/\\') . '/' . $probeName;

                        $ctx = stream_context_create(['http' => ['timeout' => 3, 'method' => 'GET']]);
                        $fetched = @file_get_contents($probeUrl, false, $ctx);
                        if ($fetched === $probeContent) {
                            $storageProtectionOk = false; // File is publicly accessible! Warning.
                            $storageProbeError = 'STORAGE_PUBLICLY_ACCESSIBLE';
                        } else {
                            $storageProtectionOk = true; // Blocked or not found — good
                        }
                    } else {
                        $storageProtectionOk = true; // Outside docroot — no HTTP check needed
                    }
                    @unlink($probeFile);
                }
            } else {
                $storageProtectionOk = true; // Uploads dir not available — no check
            }
        } catch (Throwable $e) {
            // Clean up probe file if exception occurred after file creation
            if (isset($probeFile) && is_file($probeFile)) {
                @unlink($probeFile);
            }
            $storageProtectionOk = null;
            $storageProbeError = $e->getMessage();
        }

        // Add storage protection result to environment
        $environment['storage_protection_verified'] = $storageProtectionOk;
        if ($storageProtectionOk === false) {
            $environment['warnings'][] = [
                'code' => 'ENV_STORAGE_PUBLICLY_ACCESSIBLE',
                'severity' => 'critical',
                'message_key' => 'health/messages.env_storage_public',
            ];
        } elseif ($storageProtectionOk === null && $storageProbeError !== null) {
            $environment['warnings'][] = [
                'code' => 'ENV_STORAGE_PROBE_FAILED',
                'severity' => 'warning',
                'message_key' => 'health/messages.env_storage_probe_failed',
            ];
        }

        return $this->success(
            $degraded ? 'HEALTH_DEEP_DEGRADED' : 'HEALTH_DEEP_OK',
            $degraded ? $this->t('health/messages.deep_degraded') : $this->t('health/messages.deep_ok'),
            [
                'status' => $degraded ? 'degraded' : 'ok',
                'version' => 'v1',
                'checks' => [
                    'db_read' => $dbRead,
                    'db_write' => $dbWrite,
                    'storage_rw' => $storageRw,
                    'queue_ready' => $queueReady,
                ],
                'degraded_mode' => $degraded,
                'details' => $checks,
                'environment' => $environment,
            ]
        );
    }
}
