<?php

namespace Tropatt\Crm;

use Bitrix\Main\Application;

/**
 * Order event queue (`b_tropatt_queue`).
 *
 * A row is written right after the order is committed and processed by the
 * CAgent, so a failed or slow CRM never blocks the checkout.
 */
class Queue
{
    const TABLE = 'b_tropatt_queue';

    const STATUS_PENDING = 'PENDING';
    const STATUS_DONE = 'DONE';
    const STATUS_ERROR = 'ERROR';

    /**
     * Deduplication key: one pending row per order and event type.
     *
     * @return string
     */
    public static function dedupeKey($orderId, $eventType)
    {
        return (int)$orderId . ':' . (string)$eventType;
    }

    /**
     * Enqueue an order event (idempotent while a row is still pending).
     *
     * @return int
     */
    public static function enqueue($orderId, $eventType = 'created')
    {
        $connection = Application::getConnection();
        $orderId = (int)$orderId;
        $eventType = (string)$eventType;

        $existing = $connection->query(
            'SELECT ID FROM ' . self::TABLE . " WHERE ORDER_ID = " . $orderId
            . " AND EVENT_TYPE = '" . $connection->getSqlHelper()->forSql($eventType) . "'"
            . " AND STATUS = '" . self::STATUS_PENDING . "'"
        )->fetch();

        if ($existing) {
            return (int)$existing['ID'];
        }

        $connection->add(self::TABLE, array(
            'ORDER_ID' => $orderId,
            'EVENT_TYPE' => $eventType,
            'STATUS' => self::STATUS_PENDING,
            'ATTEMPTS' => 0,
            'CREATED_AT' => new \Bitrix\Main\Type\DateTime(),
        ));

        return (int)$connection->getInsertedId();
    }

    /**
     * Oldest pending rows first.
     *
     * @return array
     */
    public static function fetchPending($limit = 20)
    {
        $limit = max(1, (int)$limit);
        $connection = Application::getConnection();

        $rows = $connection->query(
            'SELECT ID, ORDER_ID, EVENT_TYPE, ATTEMPTS FROM ' . self::TABLE
            . " WHERE STATUS = '" . self::STATUS_PENDING . "'"
            . ' ORDER BY ID ASC LIMIT ' . $limit
        );

        $result = array();
        while ($row = $rows->fetch()) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @return void
     */
    public static function markDone($id)
    {
        Application::getConnection()->update(self::TABLE, array(
            'STATUS' => self::STATUS_DONE,
            'UPDATED_AT' => new \Bitrix\Main\Type\DateTime(),
        ), array('=ID' => (int)$id));
    }

    /**
     * @return void
     */
    public static function markError($id, $error, $attempts)
    {
        $attempts = (int)$attempts;
        $maxAttempts = Config::maxAttempts();

        Application::getConnection()->update(self::TABLE, array(
            'STATUS' => $attempts >= $maxAttempts ? self::STATUS_ERROR : self::STATUS_PENDING,
            'ATTEMPTS' => $attempts,
            'LAST_ERROR' => substr((string)$error, 0, 1000),
            'UPDATED_AT' => new \Bitrix\Main\Type\DateTime(),
        ), array('=ID' => (int)$id));
    }

    /**
     * @return array
     */
    public static function stats()
    {
        $connection = Application::getConnection();
        $rows = $connection->query(
            'SELECT STATUS, COUNT(*) AS CNT FROM ' . self::TABLE . ' GROUP BY STATUS'
        );

        $stats = array(self::STATUS_PENDING => 0, self::STATUS_DONE => 0, self::STATUS_ERROR => 0);
        while ($row = $rows->fetch()) {
            $stats[(string)$row['STATUS']] = (int)$row['CNT'];
        }

        return $stats;
    }
}
