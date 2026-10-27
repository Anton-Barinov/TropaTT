<?php

/**
 * Stock synchronisation: pushes quantities into PrestaShop through
 * `StockAvailable::setQuantity()`, per shop (Multi-Store) and per combination.
 */
class TropattStockSync
{
    /**
     * CRM stock rows -> PrestaShop lines (pure, unit tested offline).
     *
     * @return array
     */
    public static function mapRows(array $rows)
    {
        $lines = array();

        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['sku'])) {
                continue;
            }

            $lines[] = array(
                'reference' => (string)$row['sku'],
                'quantity' => isset($row['quantity']) ? max(0, (int)$row['quantity']) : 0,
            );
        }

        return $lines;
    }

    /**
     * Apply a batch: the product is resolved by reference (or by id when the
     * row carries `product_id`).
     *
     * @return array
     */
    public static function applyBatch(array $lines, $idShop = null)
    {
        $results = array();

        foreach ($lines as $line) {
            $quantity = isset($line['quantity']) ? max(0, (int)$line['quantity']) : 0;
            $idProduct = isset($line['product_id']) ? (int)$line['product_id'] : 0;
            $idAttribute = isset($line['combination_id']) ? (int)$line['combination_id'] : 0;

            if (!$idProduct && !empty($line['reference']) && class_exists('Product')) {
                $idProduct = self::resolveProductId((string)$line['reference']);
            }

            if (!$idProduct) {
                $results[] = array('reference' => isset($line['reference']) ? (string)$line['reference'] : '', 'result' => 'skipped', 'reason' => 'product_not_found');
                continue;
            }

            if (class_exists('StockAvailable')) {
                StockAvailable::setQuantity($idProduct, $idAttribute, $quantity, $idShop);
            }

            $results[] = array('reference' => isset($line['reference']) ? (string)$line['reference'] : '', 'product_id' => $idProduct, 'quantity' => $quantity, 'result' => 'updated');
        }

        return $results;
    }

    /**
     * Resolve a product id from its reference.
     *
     * `Product::getIdByReference()` only appeared in PrestaShop 1.7: on 1.6 the
     * call raised `Error: Call to undefined method Product::getIdByReference()`
     * and the whole batch died before a single quantity was applied — the module
     * advertises 1.6 support in `config.xml`, so every stock sync on 1.6 failed.
     * 1.6 gets the same lookup against the reference index instead.
     *
     * @return int
     */
    private static function resolveProductId($reference)
    {
        $reference = (string)$reference;
        if ($reference === '') {
            return 0;
        }

        if (method_exists('Product', 'getIdByReference')) {
            return (int)Product::getIdByReference($reference);
        }

        if (!class_exists('Db') || !defined('_DB_PREFIX_')) {
            return 0;
        }

        $sql = 'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product`'
            . " WHERE `reference` = '" . pSQL($reference) . "'";

        return (int)Db::getInstance()->getValue($sql);
    }
}
