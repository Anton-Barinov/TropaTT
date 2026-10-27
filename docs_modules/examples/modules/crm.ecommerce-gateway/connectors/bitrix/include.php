<?php
/**
 * Module bootstrap: class autoloading.
 *
 * Event handlers, the queue agent and the webhook entry point all resolve
 * classes through this map, so no `require` is needed anywhere else.
 */

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('tropatt.crm', array(
    'Tropatt\Crm\Config' => 'lib/Config.php',
    'Tropatt\Crm\GatewayUrl' => 'lib/GatewayUrl.php',
    'Tropatt\Crm\SignatureValidator' => 'lib/SignatureValidator.php',
    'Tropatt\Crm\Client' => 'lib/Client.php',
    'Tropatt\Crm\OrderMapper' => 'lib/OrderMapper.php',
    'Tropatt\Crm\StatusMapper' => 'lib/StatusMapper.php',
    'Tropatt\Crm\ConnectorLogger' => 'lib/ConnectorLogger.php',
    'Tropatt\Crm\Queue' => 'lib/Queue.php',
    'Tropatt\Crm\OrderHandler' => 'lib/OrderHandler.php',
    'Tropatt\Crm\WebhookHandler' => 'lib/WebhookHandler.php',
    'Tropatt\Crm\Agent\QueueAgent' => 'lib/Agent/QueueAgent.php',
));
