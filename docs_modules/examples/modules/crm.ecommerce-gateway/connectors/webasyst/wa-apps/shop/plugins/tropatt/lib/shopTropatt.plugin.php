<?php

/**
 * Shop-Script plugin: order events are queued and delivered to the TropaTT CRM.
 *
 * Handlers registered in `lib/config/plugin.php`:
 *   order_action.create — a new order
 *   order_action.*      — every other workflow action (paid, shipped, …)
 */
class shopTropattPlugin extends shopPlugin
{
    /** @var string */
    const STORAGE_KEY = 'tropatt/skip_status_sync';

    /**
     * @param array $params
     * @return void
     */
    public function orderActionCreate($params)
    {
        $this->dispatch(isset($params['order_id']) ? (int)$params['order_id'] : 0, 'created');
    }

    /**
     * @param array $params
     * @return void
     */
    public function orderActionUpdate($params)
    {
        // A status written by our own webhook must not bounce back to the CRM.
        if (wa()->getStorage()->read(self::STORAGE_KEY)) {
            return;
        }

        $this->dispatch(isset($params['order_id']) ? (int)$params['order_id'] : 0, 'status_changed');
    }

    /**
     * Map the order and deliver it; a failure is spooled, never thrown at the shop.
     *
     * @param int    $orderId
     * @param string $event
     * @return void
     */
    public function dispatch($orderId, $event)
    {
        if (!$orderId || !$this->getSettings('enabled')) {
            return;
        }

        $config = shopTropattConfig::fromSettings($this->getSettings());

        if (!shopTropattConfig::isConfigured($config)) {
            return;
        }

        $order = shopTropattOrderMapper::loadOrder($orderId);
        if ($order === array()) {
            return;
        }

        $stage = shopTropattStatusMapper::crmStageFor($config['status_mapping'], (string)($order['state_id'] ?? ''));
        $canonical = shopTropattOrderMapper::toCanonical($order, $stage === null ? $config['default_stage'] : $stage);

        // Retry what failed earlier before sending the current event: the spool
        // used to be write-only, so an order that failed once stayed on disk
        // forever (Shop-Script plugins on shared hosting have no cron to lean on).
        $queueDir = $config['queue_dir'];
        if (shopTropattFileQueue::count($queueDir) > 0) {
            $flushed = shopTropattFileQueue::flush($queueDir, function ($spooled) use ($config) {
                $sender = new shopTropattClient($config['gateway_url'], $config['store_key'], $config['store_secret'], $config['timeout']);

                return $sender->pushOrder($spooled);
            });

            shopTropattLogger::write($config, 'spool drained', $flushed);
        }

        $client = new shopTropattClient($config['gateway_url'], $config['store_key'], $config['store_secret'], $config['timeout']);
        $result = $client->pushOrder($canonical);

        if (!$result['success']) {
            shopTropattFileQueue::enqueue($canonical, $config['queue_dir']);
            shopTropattLogger::write($config, 'order spooled after a delivery failure', array(
                'external_id' => $canonical['external_id'],
                'http_code' => $result['http_code'],
                'event' => $event,
            ));
        }
    }
}
