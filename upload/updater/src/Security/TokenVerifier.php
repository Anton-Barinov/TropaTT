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
        if (!is_array($session)) {
            return false;
        }
        $isContinuation = in_array($action, ['apply_step', 'rollback_step'], true);
        // Single-use gate: apply/rollback mark the token used on the first
        // call, so the same token can never START a second job. Continuation
        // steps (apply_step/rollback_step) of an already-started job may use
        // a used token - that is exactly their purpose.
        if (!$isContinuation && (bool)($session['used'] ?? false)) {
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
        }
        if ($isContinuation) {
            // Sliding window: a long multi-request job keeps the token fresh
            // on every step, so a huge update/rollback never expires mid-way.
            $session['expires_at'] = gmdate('c', time() + 600);
        }
        file_put_contents($file, json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return true;
    }
}
