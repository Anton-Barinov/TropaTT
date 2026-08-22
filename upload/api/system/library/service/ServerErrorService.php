<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\System\Library\Support\Ulid;

/**
 * Logs PHP errors, uncaught exceptions, and fatal errors to the database.
 *
 * Register via: ServerErrorService::register($pdo) in the bootstrap.
 */
final class ServerErrorService
{
    private static ?self $instance = null;
    private \PDO $pdo;
    private bool $enabled = true;

    private function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function getInstance(\PDO $pdo): self
    {
        if (self::$instance === null) {
            self::$instance = new self($pdo);
        }
        return self::$instance;
    }

    /**
     * Register error/exception handlers. Call once during bootstrap.
     */
    public static function register(\PDO $pdo): void
    {
        $service = self::getInstance($pdo);

        // Only register if the table exists
        try {
            $pdo->query('SELECT 1 FROM server_errors LIMIT 1');
        } catch (\Throwable $e) {
            return; // Table doesn't exist yet, skip
        }

        set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline) use ($service): bool {
            // Only log actual errors, not warnings/notices
            if (!in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                return false; // Let PHP handle it
            }
            $service->logError('error', $errstr, $errfile, $errline, $errno);
            return true;
        });

        set_exception_handler(static function (\Throwable $e) use ($service): void {
            $service->logError(
                $e instanceof \ErrorException ? 'error' : 'exception',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getCode(),
                $e->getTraceAsString()
            );
        });

        register_shutdown_function(static function () use ($service): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $service->logError('fatal', $error['message'], $error['file'], $error['line'], $error['type']);
            }
        });
    }

    /**
     * Log an error to the database.
     */
    public function logError(
        string $level,
        string $message,
        ?string $file = null,
        ?int $line = null,
        ?int $code = null,
        ?string $stackTrace = null
    ): void {
        if (!$this->enabled) {
            return;
        }

        try {
            // Don't log empty messages
            if (trim($message) === '') {
                return;
            }

            $publicId = Ulid::generate('srv');
            $now = gmdate('Y-m-d H:i:s');

            // Get request context
            $url = $_SERVER['REQUEST_URI'] ?? null;
            $method = $_SERVER['REQUEST_METHOD'] ?? null;
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;

            // Try to get user from session
            $userPublicId = $this->resolveUserPublicId();

            // Truncate long values
            $message = mb_substr($message, 0, 10000);
            $file = $file !== null ? mb_substr($file, 0, 512) : null;
            $url = $url !== null ? mb_substr($url, 0, 1024) : null;
            $stackTrace = $stackTrace !== null ? mb_substr($stackTrace, 0, 50000) : null;

            $stmt = $this->pdo->prepare(
                'INSERT INTO server_errors (public_id, level, message, file, line, code, url, method, ip, user_public_id, stack_trace, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $publicId,
                $level,
                $message,
                $file,
                $line,
                $code,
                $url,
                $method,
                $ip,
                $userPublicId,
                $stackTrace,
                $now,
            ]);
        } catch (\Throwable $e) {
            // Silently fail — don't crash on logging failure
            $this->enabled = false;
        }
    }

    /**
     * Get server errors with filters.
     */
    public function list(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['level'])) {
            $where[] = 'level = ?';
            $params[] = $filters['level'];
        }

        if (!empty($filters['from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['from'];
        }

        if (!empty($filters['to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['to'];
        }

        if (!empty($filters['user_public_id'])) {
            $where[] = 'user_public_id = ?';
            $params[] = $filters['user_public_id'];
        }

        $limit = min((int)($filters['limit'] ?? 100), 500);
        $offset = max((int)($filters['offset'] ?? 0), 0);

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM server_errors WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT * FROM server_errors WHERE {$whereSql} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get a single error by public_id.
     */
    public function get(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM server_errors WHERE public_id = ?');
        $stmt->execute([$publicId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Delete old errors (retention).
     */
    public function cleanup(int $daysToKeep = 30): int
    {
        $cutoff = gmdate('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
        $stmt = $this->pdo->prepare('DELETE FROM server_errors WHERE created_at < ?');
        $stmt->execute([$cutoff]);
        return $stmt->rowCount();
    }

    private function resolveUserPublicId(): ?string
    {
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                return null;
            }
            $user = $_SESSION['user'] ?? null;
            if (is_array($user) && !empty($user['public_id'])) {
                return $user['public_id'];
            }
        } catch (\Throwable $e) {
            // Ignore
        }
        return null;
    }
}
