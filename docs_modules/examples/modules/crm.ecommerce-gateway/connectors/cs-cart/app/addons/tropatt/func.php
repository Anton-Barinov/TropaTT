<?php
/**
 * CS-Cart hook handlers: a placed or updated order is queued and delivered to the
 * TropaTT gateway. The hooks never block the checkout: the CRM request happens
 * after the order is saved, and a failure is spooled for the retry cron.
 */

use Tropatt\CsCart\Config;
use Tropatt\CsCart\ConnectorLogger;
use Tropatt\CsCart\FileQueue;
use Tropatt\CsCart\OrderMapper;
use Tropatt\CsCart\StatusMapper;

/**
 * @param array  $orderInfo
 * @param array  $cart
 * @param string $action
 * @return void
 */
function fn_tropatt_place_order($orderInfo, $cart, $action)
{
    fn_tropatt_queue_order((int)$orderInfo['order_id'], 'created');
}

/**
 * @param int    $orderId
 * @param string $status
 * @param string $notify
 * @return void
 */
function fn_tropatt_change_order_status($orderId, $status, $notify)
{
    // A status written by our own webhook handler must not bounce back.
    if (Registry::get('runtime.tropatt_skip_status_sync')) {
        return;
    }

    fn_tropatt_queue_order((int)$orderId, 'status_changed');
}

/**
 * @param int   $orderId
 * @param array $orderInfo
 * @return void
 */
function fn_tropatt_update_order($orderId, $orderInfo)
{
    if (Registry::get('runtime.tropatt_skip_status_sync')) {
        return;
    }

    fn_tropatt_queue_order((int)$orderId, 'updated');
}

/**
 * Deliver one order event, or spool it when the gateway is unavailable.
 *
 * @param int    $orderId
 * @param string $event
 * @return void
 */
function fn_tropatt_queue_order($orderId, $event)
{
    $config = Config::fromRegistry();

    if (empty($config['enabled']) || !Config::isConfigured($config)) {
        return;
    }

    $orderInfo = fn_get_order_info($orderId);
    if (empty($orderInfo)) {
        return;
    }

    $stage = StatusMapper::crmStageFor($config['status_mapping'], (string)$orderInfo['status']);

    // Retry what failed earlier before sending the current event: the spool used
    // to be write-only, so an order that failed once stayed on disk forever.
    $queueDir = FileQueue::directory($config);
    if (FileQueue::count($queueDir) > 0) {
        $flushed = FileQueue::flush($queueDir, function ($spooled) use ($config) {
            $sender = new \Tropatt\CsCart\CrmClient($config['gateway_url'], $config['store_key'], $config['store_secret']);

            return $sender->pushOrder($spooled);
        });

        ConnectorLogger::write($config, 'spool drained', $flushed);
    }

    foreach (OrderMapper::splitByVendor($orderInfo, $config['split_by_vendor']) as $group) {
        $canonical = OrderMapper::toCanonical($group, $stage === null ? $config['default_stage'] : $stage);

        $client = new \Tropatt\CsCart\CrmClient($config['gateway_url'], $config['store_key'], $config['store_secret']);
        $result = $client->pushOrder($canonical);
        if (!$result['success']) {
            FileQueue::enqueue($canonical, FileQueue::directory($config));
            ConnectorLogger::write($config, 'order spooled after a delivery failure', array(
                'external_id' => $canonical['external_id'],
                'http_code' => $result['http_code'],
            ));
        }
    }
}
