<?php
declare(strict_types=1);

namespace Updater\Security;

final class TokenVerifier
{
    public function __construct(private readonly string $storageDir)
    {
    }

    public function verify(string $token, string $action): bool
    {
        if ($token === '') {
            return false;
        }
        $hash = hash('sha256', $token);
        $file = $this->storageDir . '/sessions/' . $hash . '.json';
        if (!is_file($file)) {
            return false;
        }
        $session = json_decode((string)file_get_contents($file), true);
        if (!is_array($session) || (bool)($session['used'] ?? false)) {
            return false;
        }
        if (strtotime((string)($session['expires_at'] ?? '')) < time()) {
            return false;
        }
        $actions = is_array($session['allowed_actions'] ?? null) ? $session['allowed_actions'] : [];
        if (!in_array($action, $actions, true)) {
            return false;
        }
        if (in_array($action, ['apply', 'rollback'], true)) {
            $session['used'] = true;
            $session['used_at'] = gmdate('c');
            file_put_contents($file, json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        return true;
    }
}
