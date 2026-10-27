<?php

namespace Tropatt\Crm\Plugin;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Tropatt\Crm\Model\Config;
use Tropatt\Crm\Model\Queue\Publisher;
use Tropatt\Crm\Model\Registry\EchoGuard;

/**
 * Interceptor: order saves are published to the message queue instead of being
 * sent to the CRM inside the checkout request.
 */
class OrderPlugin
{
    /** @var Publisher */
    private $publisher;

    /** @var Config */
    private $config;

    /** @var EchoGuard */
    private $echoGuard;

    public function __construct(Publisher $publisher, Config $config, EchoGuard $echoGuard)
    {
        $this->publisher = $publisher;
        $this->config = $config;
        $this->echoGuard = $echoGuard;
    }

    /**
     * @return OrderInterface
     */
    public function afterSave(OrderRepositoryInterface $subject, OrderInterface $order)
    {
        if ($this->echoGuard->isSuppressed() || !$this->config->isEnabled((int)$order->getStoreId()) || !$this->config->isConfigured((int)$order->getStoreId())) {
            return $order;
        }

        $this->publisher->publish($order);

        return $order;
    }
}
