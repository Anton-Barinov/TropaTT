<?php

namespace Tropatt\Crm\Model\Queue;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Tropatt\Crm\Model\Config;
use Tropatt\Crm\Model\Http\Client;
use Tropatt\Crm\Model\Mapper\OrderMapper;
use Tropatt\Crm\Model\StatusMapper;

/**
 * Queue consumer: loads the order again and delivers it to the CRM.
 */
class Consumer
{
    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var OrderMapper */
    private $mapper;

    /** @var Client */
    private $client;

    /** @var Config */
    private $config;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderMapper $mapper,
        Client $client,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->mapper = $mapper;
        $this->client = $client;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @param string $message
     * @return void
     */
    public function process($message)
    {
        $data = json_decode((string)$message, true);
        if (!is_array($data) || empty($data['order_id'])) {
            $this->logger->warning('TropaTT CRM: malformed queue message', ['message' => (string)$message]);

            return;
        }

        $orderId = (int)$data['order_id'];

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $exception) {
            $this->logger->warning('TropaTT CRM: order not found for the queue message', ['order_id' => $orderId]);

            return;
        }

        $storeId = (int)$order->getStoreId();
        $stage = StatusMapper::crmStageFor($this->config->statusMapping($storeId), (string)$order->getStatus());
        $canonical = $this->mapper->toCanonical(
            $this->mapper->collect($order),
            $stage ?? $this->config->defaultStage($storeId)
        );

        $result = $this->client->pushOrder($canonical, $storeId);

        if (!$result['success']) {
            $this->logger->error('TropaTT CRM: order delivery failed', [
                'order_id' => $orderId,
                'http_code' => $result['http_code'],
                'error' => $result['error'],
            ]);
        }
    }
}
