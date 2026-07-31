<?php
declare(strict_types=1);

namespace Api\System\Library\Update;

final class CoreUpdateSessionService
{
    public function __construct(private readonly string $storageDir)
    {
    }

    public function create(int $userId, array $actions = ['preflight', 'download', 'apply', 'resume', 'rollback']): array
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $dir = $this->storageDir . '/sessions';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $session = [
            'token_hash' => $hash,
            'user_id' => $userId,
            'created_at' => gmdate('c'),
            'expires_at' => gmdate('c', time() + 600),
            'allowed_actions' => $actions,
            'used' => false,
        ];
        file_put_contents($dir . '/' . $hash . '.json', json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return ['updater_token' => $token, 'expires_at' => $session['expires_at'], 'allowed_actions' => $actions];
    }
}
