<?php
declare(strict_types=1);

// SEC-002: Block direct web access
if (PHP_SAPI !== 'cli' && ($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}


/**
 * Integration registry.
 *
 * External providers are disabled by default. Enabling any channel must be paired
 * with an adapter/service, secret storage policy, audit logging and contract tests.
 */
$bool = static fn(string $name): bool => in_array(strtolower((string)getenv($name)), ['1', 'true', 'yes', 'on'], true);
$value = static fn(string $name, string $default = ''): string => trim((string)(getenv($name) ?: $default));

return [
    'email' => [
        'enabled' => $bool('CRM_EMAIL_ENABLED'),
        'provider' => $value('CRM_EMAIL_PROVIDER', 'disabled'),
        'from' => $value('CRM_EMAIL_FROM'),
        'timeout_sec' => max(1, (int)($value('CRM_EMAIL_TIMEOUT_SEC', '10'))),
        'required_secrets' => ['CRM_EMAIL_API_KEY'],
        'secret_policy' => 'store encrypted/hash/last4 only; never log raw values',
    ],
    'sms' => [
        'enabled' => $bool('CRM_SMS_ENABLED'),
        'provider' => $value('CRM_SMS_PROVIDER', 'disabled'),
        'timeout_sec' => max(1, (int)($value('CRM_SMS_TIMEOUT_SEC', '10'))),
        'required_secrets' => ['CRM_SMS_API_KEY'],
        'secret_policy' => 'store encrypted/hash/last4 only; never log raw values',
    ],
    'payments' => [
        'enabled' => $bool('CRM_PAYMENTS_ENABLED'),
        'provider' => $value('CRM_PAYMENTS_PROVIDER', 'disabled'),
        'webhook_signing_secret_env' => 'CRM_PAYMENTS_WEBHOOK_SECRET',
        'required_secrets' => ['CRM_PAYMENTS_API_KEY'],
        'secret_policy' => 'store encrypted/hash/last4 only; never log raw values',
    ],
    'object_storage' => [
        'enabled' => $bool('CRM_OBJECT_STORAGE_ENABLED'),
        'provider' => $value('CRM_OBJECT_STORAGE_PROVIDER', 'local'),
        'bucket' => $value('CRM_OBJECT_STORAGE_BUCKET'),
        'endpoint' => $value('CRM_OBJECT_STORAGE_ENDPOINT'),
        'required_secrets' => ['CRM_OBJECT_STORAGE_ACCESS_KEY', 'CRM_OBJECT_STORAGE_SECRET_KEY'],
        'secret_policy' => 'keep object storage credentials outside webroot and logs',
    ],
    'external_crm_sync' => [
        'enabled' => $bool('CRM_EXTERNAL_SYNC_ENABLED'),
        'provider' => $value('CRM_EXTERNAL_SYNC_PROVIDER', 'disabled'),
        'direction' => $value('CRM_EXTERNAL_SYNC_DIRECTION', 'outbound'),
        'required_secrets' => ['CRM_EXTERNAL_SYNC_API_KEY'],
        'secret_policy' => 'log sync ids/status only, not payload secrets',
    ],
    'message_broker' => [
        'enabled' => $bool('CRM_MESSAGE_BROKER_ENABLED'),
        'provider' => $value('CRM_MESSAGE_BROKER_PROVIDER', 'database'),
        'dsn_env' => 'CRM_MESSAGE_BROKER_DSN',
        'required_secrets' => ['CRM_MESSAGE_BROKER_DSN'],
        'secret_policy' => 'DSN must be env-only and masked in diagnostics',
    ],
];
