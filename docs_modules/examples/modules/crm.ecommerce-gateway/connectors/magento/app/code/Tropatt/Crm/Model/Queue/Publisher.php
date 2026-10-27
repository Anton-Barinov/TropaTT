<?php

namespace Tropatt\Crm\Model\Queue;

use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Publishes order events to the `tropatt.order.sync` queue (Message Queue
 * framework, `communication.xml` / `queue_topology.xml` / `queue_consumer.xml`).
 */
class Publisher
{
    public const TOPIC = 'tropatt.order.sync';

    /** @var PublisherInterface */
    private $publisher;

    public function __construct(PublisherInterface $publisher)
    {
        $this->publisher = $publisher;
    }

    /**
     * @return void
     */
    public function publish(OrderInterface $order)
    {
        $message = json_encode([
            'order_id' => (int)$order->getEntityId(),
            'store_id' => (int)$order->getStoreId(),
            'status' => (string)$order->getStatus(),
            'event' => 'order_saved',
            'queued_at' => gmdate('c'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->publisher->publish(self::TOPIC, (string)$message);
    }
}
