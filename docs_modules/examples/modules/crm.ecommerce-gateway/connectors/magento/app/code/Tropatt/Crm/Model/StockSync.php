<?php

namespace Tropatt\Crm\Model;

use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;

/**
 * Stock synchronisation through the Multi-Source Inventory API
 * (`SourceItemRepositoryInterface` / `SourceItemsSaveInterface`).
 */
class StockSync
{
    /** @var SourceItemInterfaceFactory */
    private $sourceItemFactory;

    /** @var SourceItemsSaveInterface */
    private $sourceItemsSave;

    /** @var Config */
    private $config;

    public function __construct(
        SourceItemInterfaceFactory $sourceItemFactory,
        SourceItemsSaveInterface $sourceItemsSave,
        Config $config
    ) {
        $this->sourceItemFactory = $sourceItemFactory;
        $this->sourceItemsSave = $sourceItemsSave;
        $this->config = $config;
    }

    /**
     * CRM stock rows -> source items (pure, unit tested offline).
     *
     * @return array
     */
    public static function mapRows(array $rows, $sourceCode = 'default')
    {
        // The constants are read defensively so the mapper can also run outside
        // Magento (contract tests, tooling) where the MSI interfaces are absent.
        $inStock = class_exists(SourceItemInterface::class) ? SourceItemInterface::STATUS_IN_STOCK : 'in_stock';
        $outOfStock = class_exists(SourceItemInterface::class) ? SourceItemInterface::STATUS_OUT_OF_STOCK : 'out_of_stock';

        $items = [];

        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['sku'])) {
                continue;
            }

            $items[] = [
                'sku' => (string)$row['sku'],
                'quantity' => isset($row['quantity']) ? max(0, (int)$row['quantity']) : 0,
                'source_code' => (string)$sourceCode,
                'status' => !empty($row['quantity']) ? $inStock : $outOfStock,
            ];
        }

        return $items;
    }

    /**
     * @return int
     */
    public function applyBatch(array $items, $storeId = null)
    {
        $sourceCode = $this->config->sourceCode($storeId);
        $sourceItems = [];

        foreach ($items as $item) {
            /** @var SourceItemInterface $sourceItem */
            $sourceItem = $this->sourceItemFactory->create();
            $sourceItem->setSku($item['sku']);
            $sourceItem->setSourceCode($item['source_code'] ?? $sourceCode);
            $sourceItem->setQuantity((float)$item['quantity']);
            $sourceItem->setStatus((int)$item['status']);
            $sourceItems[] = $sourceItem;
        }

        if ($sourceItems !== []) {
            $this->sourceItemsSave->execute($sourceItems);
        }

        return count($sourceItems);
    }
}
