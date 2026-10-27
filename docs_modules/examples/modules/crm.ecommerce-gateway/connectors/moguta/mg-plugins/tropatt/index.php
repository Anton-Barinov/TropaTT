<?php
/*
Plugin Name: TropaTT CRM — шлюз интернет-магазина
Description: Двусторонняя синхронизация заказов, покупателей и статусов между Moguta.CMS и TropaTT CRM.
Version: 1.0.1
Author: Anton Barinov
Author URI: https://github.com/Anton-Barinov
*/

/**
 * Moguta plugin bootstrap.
 *
 * The file MUST be named `index.php`: Moguta only detects a plugin when its
 * folder holds `index.php` with the plugin description comment
 * (https://wiki.moguta.ru/devhelp/plugins/kak-sdelat-plagin). A differently
 * named bootstrap is simply never loaded.
 *
 * Hooks registered below are the documented system events of
 * `Models_Order::addOrder()` / `Models_Order::updateOrder()`, lowercased per the
 * hook naming rule `<class>_<method>`
 * (https://wiki.moguta.ru/devhelp/plugins/huki-i-obrabotchiki):
 *   models_order_addorder    — after a new order has been saved
 *   models_order_updateorder — an order was changed from the admin, via API, on
 *                              payment, or by the customer's payment change
 *
 * The CRM request is never executed inside the checkout request: the order is
 * spooled and delivered with a short (< 3 s) non-blocking request, so a slow CRM
 * cannot delay the storefront.
 */

if (!defined('MG')) {
    die('Access denied');
}

require_once __DIR__ . '/lib/Config.php';
require_once __DIR__ . '/lib/SignatureValidator.php';
require_once __DIR__ . '/lib/OrderMapper.php';
require_once __DIR__ . '/lib/StatusMapper.php';
require_once __DIR__ . '/lib/CrmClient.php';
require_once __DIR__ . '/lib/FileQueue.php';
require_once __DIR__ . '/lib/ConnectorLogger.php';

// Both order hooks are "complex" hooks (created through
// `MG::createHook(name, $result, $args)`), so the handler receives exactly one
// argument: array('result' => ..., 'arg' => array(...)).
MG::addAction('models_order_addorder', 'tropatt_crm_on_order_add', 1, 10);
MG::addAction('models_order_updateorder', 'tropatt_crm_on_order_update', 1, 10);

/**
 * @param mixed $arg
 * @return void
 */
function tropatt_crm_on_order_add($arg)
{
    tropatt_crm_dispatch($arg, 'created');
}

/**
 * @param mixed $arg
 * @return void
 */
function tropatt_crm_on_order_update($arg)
{
    if (tropatt_crm_is_echo()) {
        return;
    }

    tropatt_crm_dispatch($arg, 'status_changed');
}

/**
 * @return bool
 */
function tropatt_crm_is_echo()
{
    return !empty($GLOBALS['tropatt_crm_skip_status_sync']);
}

/**
 * Map the order and deliver it; a failure is spooled, never thrown at the shop.
 *
 * @param mixed  $order
 * @param string $event
 * @return void
 */
function tropatt_crm_dispatch($order, $event)
{
    $config = Tropatt\Moguta\Config::current();

    if (!$config['enabled'] || !Tropatt\Moguta\Config::isConfigured($config)) {
        return;
    }

    $orderData = tropatt_crm_order_data($order);
    if ($orderData === array()) {
        return;
    }

    $stage = Tropatt\Moguta\StatusMapper::crmStageFor($config['status_mapping'], (string)($orderData['status'] ?? ''));
    $canonical = Tropatt\Moguta\OrderMapper::toCanonical($orderData, $stage === null ? $config['default_stage'] : $stage);

    // Retry what failed earlier before sending the current event: the spool used
    // to be write-only, so an order that failed once stayed on disk forever.
    $queueDir = $config['queue_dir'];
    if (Tropatt\Moguta\FileQueue::count($queueDir) > 0) {
        $flushed = Tropatt\Moguta\FileQueue::flush($queueDir, function ($spooled) use ($config) {
            $sender = new Tropatt\Moguta\CrmClient($config['gateway_url'], $config['store_key'], $config['store_secret'], $config['timeout']);

            return $sender->pushOrder($spooled);
        });

        Tropatt\Moguta\ConnectorLogger::write($config, 'spool drained', $flushed);
    }

    $client = new Tropatt\Moguta\CrmClient($config['gateway_url'], $config['store_key'], $config['store_secret'], $config['timeout']);
    $result = $client->pushOrder($canonical);

    if (!$result['success']) {
        Tropatt\Moguta\FileQueue::enqueue($canonical, $config['queue_dir']);
        Tropatt\Moguta\ConnectorLogger::write($config, 'order spooled after a delivery failure', array(
            'external_id' => $canonical['external_id'],
            'http_code' => $result['http_code'],
            'event' => $event,
        ));
    }
}

/**
 * Resolve the order data out of a hook argument.
 *
 * Moguta passes different shapes depending on the hook and the engine version:
 *   - a complex-hook payload: array('result' => <id|array>, 'arg' => array(...));
 *   - a positional argument list (the arguments of the hooked method);
 *   - an order id (so the row is loaded through Models_Order::getOrder());
 *   - an order array / an object exposing getData().
 *
 * A partial row (for example the admin edit payload of updateOrder()) is merged
 * over the stored order so the CRM always receives complete data.
 *
 * @param mixed $order
 * @return array
 */
function tropatt_crm_order_data($order)
{
    if (is_object($order)) {
        if (method_exists($order, 'getData')) {
            $data = $order->getData();

            return is_array($data) ? $data : array();
        }

        return array();
    }

    if (is_numeric($order)) {
        return tropatt_crm_load_order((int)$order);
    }

    if (!is_array($order) || $order === array()) {
        return array();
    }

    // A complete or partial order row.
    if (isset($order['id']) && is_numeric($order['id'])) {
        $stored = tropatt_crm_load_order((int)$order['id']);

        return $stored === array() ? $order : array_merge($stored, $order);
    }

    // Complex-hook payload: 'result' is the hooked method result, 'arg' its input.
    foreach (array('result', 'arg') as $key) {
        if (array_key_exists($key, $order)) {
            $candidate = tropatt_crm_order_data($order[$key]);
            if ($candidate !== array()) {
                return $candidate;
            }
        }
    }

    // Positional argument list: the first entry that resolves to an order wins.
    foreach ($order as $value) {
        $candidate = tropatt_crm_order_data($value);
        if ($candidate !== array()) {
            return $candidate;
        }
    }

    return array();
}

/**
 * Load an order row through the documented model API.
 *
 * `Models_Order::getOrder($where)` returns the rows matching a WHERE clause; the
 * id is cast to int, so no user input reaches the query. Engines without the
 * model fall back to the raw payload of the hook.
 *
 * @param int $orderId
 * @return array
 */
function tropatt_crm_load_order($orderId)
{
    if ($orderId <= 0) {
        return array();
    }

    if (class_exists('Models_Order')) {
        try {
            $model = new Models_Order();
            $rows = $model->getOrder('id = ' . (int)$orderId);

            if (is_array($rows) && $rows !== array()) {
                $row = reset($rows);

                return is_array($row) ? $row : array();
            }
        } catch (Exception $exception) {
            // The order cannot be reloaded (deleted row, engine without the
            // model) — the hook payload itself is used instead.
        }
    }

    if (function_exists('mgGetOrderData')) {
        $data = mgGetOrderData($orderId);

        return is_array($data) ? $data : array();
    }

    return array();
}
