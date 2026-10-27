<?php

namespace Tropatt\Crm\Agent;

use Tropatt\Crm\Client;
use Tropatt\Crm\Config;
use Tropatt\Crm\ConnectorLogger;
use Tropatt\Crm\OrderMapper;
use Tropatt\Crm\Queue;

/**
 * CAgent that delivers queued orders and status changes to the CRM.
 *
 * The agent is rescheduled even when a send fails, so a temporarily unavailable
 * CRM never loses an order: the pending row is retried with backoff until
 * `max_attempts` is reached, then it stays in the queue as ERROR for the admin.
 */
class QueueAgent
{
    const BATCH_SIZE = 20;

    /**
     * @return string
     */
    public static function processQueue()
    {
        if (!Config::isEnabled() || !Config::isConfigured()) {
            return '\Tropatt\Crm\Agent\QueueAgent::processQueue();';
        }

        $client = new Client();

        foreach (Queue::fetchPending(self::BATCH_SIZE) as $row) {
            $orderId = (int)$row['ORDER_ID'];
            $attempts = (int)$row['ATTEMPTS'] + 1;

            $order = self::loadOrder($orderId);
            if ($order === null) {
                Queue::markError((int)$row['ID'], 'Order not found: ' . $orderId, Config::maxAttempts());

                continue;
            }

            $normalized = OrderMapper::fromBitrixOrder($order);
            $statusCode = self::crmStageFor($normalized['status_id']);
            $payload = OrderMapper::buildPayload($normalized, $statusCode);

            $result = $client->pushOrder($payload);

            if ($result['success']) {
                Queue::markDone((int)$row['ID']);
                ConnectorLogger::write('order delivered', array('order_id' => $orderId, 'code' => $result['code']));

                continue;
            }

            Queue::markError((int)$row['ID'], (string)$result['error'], $attempts);
            ConnectorLogger::write('order delivery failed', array(
                'order_id' => $orderId,
                'attempt' => $attempts,
                'http_code' => $result['http_code'],
            ));
        }

        return '\Tropatt\Crm\Agent\QueueAgent::processQueue();';
    }

    /**
     * @return string
     */
    private static function crmStageFor($statusId)
    {
        $mapping = Config::statusMapping();
        $stage = \Tropatt\Crm\StatusMapper::crmStageFor($mapping, $statusId);

        return $stage === null ? (string)$statusId : $stage;
    }

    /**
     * @return object|null
     */
    private static function loadOrder($orderId)
    {
        if (!class_exists('\Bitrix\Sale\Order')) {
            return null;
        }

        try {
            return \Bitrix\Sale\Order::load((int)$orderId);
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
