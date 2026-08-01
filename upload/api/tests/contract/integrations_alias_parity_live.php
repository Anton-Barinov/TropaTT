<?php
declare(strict_types=1);

require __DIR__ . '/../_live_http.php';

function runIntegrationsAliasParityLive(): void
{
    $auth = liveLoginRoot();
    $headers = ['Authorization' => 'Bearer ' . $auth['token']];

    $pairs = [
        ['canonical' => 'api/v1/api-clients', 'alias' => 'api/v1/api-client/list', 'expected_code' => 'API_CLIENT_LIST'],
        ['canonical' => 'api/v1/webhooks', 'alias' => 'api/v1/webhook/list', 'expected_code' => 'WEBHOOK_LIST'],
        ['canonical' => 'api/v1/security/invitations', 'alias' => 'api/v1/security/invitations/list', 'expected_code' => 'INVITATION_LIST'],
    ];

    foreach ($pairs as $pair) {
        $canonical = liveRequest('GET', $pair['canonical'], [], $headers);
        $alias = liveRequest('GET', $pair['alias'], [], $headers);

        liveAssert($canonical['status'] === 200, 'Canonical route must return 200: ' . $pair['canonical']);
        liveAssert($alias['status'] === 200, 'Alias route must return 200: ' . $pair['alias']);
        liveAssert((string)($canonical['payload']['code'] ?? '') === $pair['expected_code'], 'Canonical code mismatch for ' . $pair['canonical']);
        liveAssert((string)($alias['payload']['code'] ?? '') === $pair['expected_code'], 'Alias code mismatch for ' . $pair['alias']);
        liveAssert(isset($canonical['payload']['meta']['request_id']), 'Canonical meta.request_id is required');
    }

    $passwordResetCanonical = liveRequest('POST', 'api/v1/security/password-reset', [
        'identifier' => 'root',
    ]);
    $passwordResetAlias = liveRequest('POST', 'api/v1/security/password-reset/request', [
        'identifier' => 'root',
    ]);

    liveAssert($passwordResetCanonical['status'] === 200, 'Canonical password-reset request must return 200');
    liveAssert($passwordResetAlias['status'] === 200, 'Alias password-reset request must return 200');
    liveAssert((string)($passwordResetCanonical['payload']['code'] ?? '') === 'PASSWORD_RESET_REQUESTED', 'Canonical password-reset code mismatch');
    liveAssert((string)($passwordResetAlias['payload']['code'] ?? '') === 'PASSWORD_RESET_REQUESTED', 'Alias password-reset code mismatch');
}

runIntegrationsAliasParityLive();
echo "[OK] integrations_alias_parity_live\n";
