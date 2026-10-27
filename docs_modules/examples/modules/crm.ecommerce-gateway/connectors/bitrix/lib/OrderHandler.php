<?php

namespace Tropatt\Crm;

/**
 * Bitrix order events.
 *
 * Handlers never talk to the CRM directly: they enqueue the order and let the
 * CAgent deliver it, which keeps the checkout fast and survives downtime.
 */
class OrderHandler
{
    /**
     * Suppresses the event that our own status write would otherwise trigger.
     *
     * @var bool
     */
    public static $suppressEcho = false;

    /**
     * `sale::OnSaleOrderSaved`.
     *
     * @return void
     */
    public static function onSaleOrderSaved($order, $isNew = null)
    {
        $order = self::resolveOrder($order);
        if ($order === null) {
            return;
        }

        self::enqueueAfterCommit($order, 'created');
    }

    /**
     * `sale::OnSaleStatusOrderChange`.
     *
     * @return void
     */
    public static function onSaleStatusOrderChange($order)
    {
        $order = self::resolveOrder($order);
        if ($order === null) {
            return;
        }

        self::enqueueAfterCommit($order, 'status_changed');
    }

    /**
     * Queue the order after the database transaction is committed, so the agent
     * never reads an order that is not visible yet.
     *
     * @return void
     */
    public static function enqueueAfterCommit($order, $eventType)
    {
        if (self::$suppressEcho || !Config::isEnabled()) {
            return;
        }

        $orderId = (int)$order->getId();
        if ($orderId <= 0) {
            return;
        }

        $callback = function () use ($orderId, $eventType) {
            Queue::enqueue($orderId, $eventType);
        };

        if (method_exists($order, 'addExecPostCommitHandler')) {
            $order->addExecPostCommitHandler($callback);

            return;
        }

        $callback();
    }

    /**
     * Apply a CRM-driven status change without echoing it back.
     *
     * @return bool
     */
    public static function applyStatus($order, $statusId, $comment = '')
    {
        $statusId = (string)$statusId;
        if ($statusId === '') {
            return false;
        }

        self::$suppressEcho = true;

        try {
            $order->setField('STATUS_ID', $statusId);
            if ($comment !== '' && method_exists($order, 'setField')) {
                $order->setField('COMMENTS', (string)$comment);
            }
            $result = $order->save();
        } catch (\Throwable $exception) {
            self::$suppressEcho = false;
            ConnectorLogger::write('failed to apply the CRM status', array(
                'order_id' => (int)$order->getId(),
                'status_id' => $statusId,
                'error' => $exception->getMessage(),
            ));

            return false;
        }

        self::$suppressEcho = false;

        return $result ? $result->isSuccess() : true;
    }

    /**
     * @return object|null
     */
    private static function resolveOrder($candidate)
    {
        if (is_object($candidate) && class_exists('\Bitrix\Main\Event') && $candidate instanceof \Bitrix\Main\Event) {
            $candidate = $candidate->getParameter('ENTITY');
        }

        if (!is_object($candidate) || !method_exists($candidate, 'getId')) {
            return null;
        }

        return $candidate;
    }
}
