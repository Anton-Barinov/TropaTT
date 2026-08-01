<?php
declare(strict_types=1);

function integrationsAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$path = dirname(__DIR__, 2) . '/config/integrations.php';
$config = require $path;
integrationsAssert(is_array($config), 'integrations config must return array');

$required = ['email', 'sms', 'payments', 'object_storage', 'external_crm_sync', 'message_broker'];
foreach ($required as $key) {
    integrationsAssert(isset($config[$key]) && is_array($config[$key]), 'Missing integration registry entry: ' . $key);
    integrationsAssert(array_key_exists('enabled', $config[$key]), 'Integration enabled flag required: ' . $key);
    integrationsAssert($config[$key]['enabled'] === false, 'Integrations must be disabled by default: ' . $key);
    integrationsAssert(!empty($config[$key]['provider']), 'Integration provider marker required: ' . $key);
    integrationsAssert(!empty($config[$key]['secret_policy']), 'Integration secret policy required: ' . $key);
}

integrationsAssert($config['message_broker']['provider'] === 'database', 'Message broker default must stay database-backed');
integrationsAssert($config['object_storage']['provider'] === 'local', 'Object storage default must stay local');

echo "[OK] integrations_config_contract_unit\n";
