<?php

namespace Tropatt\Shopify;

/**
 * Stock synchronisation: resolves SKU -> inventory item id and pushes quantities
 * through `inventorySetQuantities`.
 */
class StockSync
{
    /**
     * CRM stock rows -> Shopify quantity rows (pure, unit tested offline).
     *
     * @return array
     */
    public static function mapRows(array $rows, array $inventoryItemIds = array())
    {
        $result = array();

        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['sku'])) {
                continue;
            }

            $sku = (string)$row['sku'];
            if (!isset($inventoryItemIds[$sku])) {
                continue;
            }

            $result[] = array(
                'sku' => $sku,
                'inventory_item_id' => (string)$inventoryItemIds[$sku],
                'quantity' => isset($row['quantity']) ? max(0, (int)$row['quantity']) : 0,
            );
        }

        return $result;
    }

    /**
     * Split a batch so that a single GraphQL request stays small.
     *
     * @return array
     */
    public static function batches(array $rows, $size = 100)
    {
        return array_chunk($rows, max(1, (int)$size));
    }
}
