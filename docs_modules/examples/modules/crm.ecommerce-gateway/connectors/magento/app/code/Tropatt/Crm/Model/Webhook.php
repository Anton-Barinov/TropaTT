<?php

namespace Tropatt\Crm\Model;

use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Tropatt\Crm\Model\Registry\EchoGuard;

/**
 * Applies a verified CRM status change to the order.
 *
 * The HTTP entry point is `Controller\Webhook\Index` (a service contract cannot
 * expose headers or the raw body); this class owns the verification hand-off, the
 * status resolution and the anti-echo save.
 */
class Webhook
{
    /** @var Config */
    private $config;

    /** @var InboundWebhook */
    private $inbound;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var EchoGuard */
    private $echoGuard;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        Config $config,
        InboundWebhook $inbound,
        OrderRepositoryInterface $orderRepository,
        EchoGuard $echoGuard,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->inbound = $inbound;
        $this->orderRepository = $orderRepository;
        $this->echoGuard = $echoGuard;
        $this->logger = $logger;
    }

    /**
     * Verify the signed CRM packet and apply the mapped order status.
     *
     * @param string $rawBody
     * @param string $signature
     * @param string $timestamp
     * @return array{http_code:int, body:array}
     */
    public function handle($rawBody, $signature, $timestamp)
    {
        $packet = $this->inbound->parse($rawBody, $signature, $timestamp, $this->config->webhookSecret());

        if (!$packet['ok']) {
            return array(
                'http_code' => $packet['http_code'],
                'body' => array('error' => $packet['error']),
            );
        }

        $data = $packet['data'];
        $orderId = (int)$packet['external_order_id'];
        $crmStage = isset($data['new_status']) ? (string)$data['new_status'] : '';

        if (!empty($data['external_status'])) {
            $status = (string)$data['external_status'];
        } else {
            $status = StatusMapper::magentoStatusFor($this->config->statusMapping(), $crmStage);
        }

        if ($status === null || $status === '') {
            return array(
                'http_code' => 200,
                'body' => array('success' => true, 'notice' => 'Ignored: no mapping for status'),
            );
        }

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (\Throwable $exception) {
            return array(
                'http_code' => 404,
                'body' => array('error' => 'Order not found: ' . $orderId),
            );
        }

        // Anti-echo: the save below must not publish the same change back to the CRM.
        $this->echoGuard->suppress(true);

        try {
            $order->setStatus($status);
            $this->orderRepository->save($order);
        } catch (\Throwable $exception) {
            $this->logger->error('TropaTT CRM: failed to apply the order status', array(
                'order_id' => $orderId,
                'status' => $status,
                'error' => $exception->getMessage(),
            ));
            $this->echoGuard->suppress(false);

            return array(
                'http_code' => 500,
                'body' => array('error' => 'Failed to apply the status'),
            );
        }

        $this->echoGuard->suppress(false);

        return array(
            'http_code' => 200,
            'body' => array(
                'success' => true,
                'order_id' => $orderId,
                'new_status' => $status,
            ),
        );
    }
}
