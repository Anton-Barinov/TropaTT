<?php

namespace Tropatt\Crm\Model\Mapper;

use Magento\Sales\Api\Data\OrderInterface;

/**
 * Magento order -> canonical TropaTT E-COM-01 payload.
 *
 * `collect()` reads the order through the Magento API; `toCanonical()` is pure
 * PHP and unit tested offline.
 */
class OrderMapper
{
    /**
     * @return array
     */
    public function collect(OrderInterface $order)
    {
        $items = [];
        foreach ($order->getItems() ?? [] as $item) {
            $items[] = [
                'name' => (string)$item->getName(),
                'sku' => (string)$item->getSku(),
                'quantity' => (float)$item->getQtyOrdered(),
                'price_minor' => $this->toMinor($item->getPriceInclTax() ?? $item->getPrice()),
                'line_total_minor' => $this->toMinor($item->getRowTotalInclTax() ?? $item->getRowTotal()),
                'options' => $this->itemOptions($item),
            ];
        }

        $shipping = $order->getShippingAddress();
        $billing = $order->getBillingAddress();
        $address = $shipping ?? $billing;

        return [
            'id' => (int)$order->getEntityId(),
            'increment_id' => (string)$order->getIncrementId(),
            'status' => (string)$order->getStatus(),
            'state' => (string)$order->getState(),
            'currency' => (string)$order->getOrderCurrencyCode(),
            'total_minor' => $this->toMinor($order->getGrandTotal()),
            'subtotal_minor' => $this->toMinor($order->getSubtotalInclTax() ?? $order->getSubtotal()),
            'shipping_minor' => $this->toMinor($order->getShippingInclTax() ?? $order->getShippingAmount()),
            'tax_minor' => $this->toMinor($order->getTaxAmount()),
            'paid' => $order->getTotalPaid() !== null && (float)$order->getTotalPaid() > 0,
            'items' => $items,
            'customer' => [
                'full_name' => (string)($order->getCustomerName() ?? trim((string)$order->getCustomerFirstname() . ' ' . (string)$order->getCustomerLastname())),
                'phone' => $address !== null ? (string)($address->getTelephone() ?? '') : '',
                'email' => (string)$order->getCustomerEmail(),
            ],
            'shipping_method' => (string)$order->getShippingDescription(),
            'payment_method' => $this->paymentMethod($order),
            'address' => $address === null ? [] : [
                'city' => (string)$address->getCity(),
                'street' => trim(implode(' ', (array)$address->getStreet())),
                'postal_code' => (string)$address->getPostcode(),
                'country' => (string)$address->getCountryId(),
            ],
            'store_id' => (int)$order->getStoreId(),
            'customer_note' => (string)$order->getCustomerNote(),
        ];
    }

    /**
     * @return array
     */
    public function toCanonical(array $order, $statusCode = 'new')
    {
        $currency = isset($order['currency']) && $order['currency'] !== '' ? strtoupper((string)$order['currency']) : 'USD';
        $items = [];

        foreach ((array)($order['items'] ?? []) as $item) {
            $quantity = isset($item['quantity']) ? (float)$item['quantity'] : 1.0;
            $priceMinor = isset($item['price_minor']) ? (int)$item['price_minor'] : $this->toMinor($item['price'] ?? 0);
            $lineMinor = isset($item['line_total_minor']) ? (int)$item['line_total_minor'] : (int)round($priceMinor * $quantity);

            $entry = [
                'name' => (string)($item['name'] ?? ''),
                'sku' => (string)($item['sku'] ?? ''),
                'quantity' => $quantity,
                'price' => ['amount_minor' => $priceMinor, 'currency' => $currency],
                'line_total' => ['amount_minor' => $lineMinor, 'currency' => $currency],
            ];

            if (!empty($item['options'])) {
                $entry['options'] = $item['options'];
            }

            $items[] = $entry;
        }

        $address = (array)($order['address'] ?? []);
        $customer = (array)($order['customer'] ?? []);

        $customFields = [
            'magento_order_id' => (string)($order['id'] ?? ''),
            'magento_increment_id' => (string)($order['increment_id'] ?? ''),
            'magento_status' => (string)($order['status'] ?? ''),
            'magento_state' => (string)($order['state'] ?? ''),
            'magento_payment' => (string)($order['payment_method'] ?? ''),
            'magento_shipping' => (string)($order['shipping_method'] ?? ''),
            'magento_store_id' => (string)($order['store_id'] ?? ''),
            'magento_tax_total' => (string)($order['tax_minor'] ?? 0),
            'magento_customer_note' => (string)($order['customer_note'] ?? ''),
        ];

        $subtotalMinor = isset($order['subtotal_minor']) ? (int)$order['subtotal_minor'] : $this->sumItems($items);
        $shippingMinor = isset($order['shipping_minor']) ? (int)$order['shipping_minor'] : 0;
        $totalMinor = isset($order['total_minor']) ? (int)$order['total_minor'] : $subtotalMinor + $shippingMinor;

        return [
            'external_id' => (string)($order['id'] ?? ''),
            'payload' => [
                'order_number' => (string)($order['increment_id'] ?? ($order['id'] ?? '')),
                'order_status' => (string)$statusCode,
                'items' => $items,
                'subtotal' => ['amount_minor' => $subtotalMinor, 'currency' => $currency],
                'delivery_total' => ['amount_minor' => $shippingMinor, 'currency' => $currency],
                'total' => ['amount_minor' => $totalMinor, 'currency' => $currency],
                'paid' => !empty($order['paid']),
                'customer' => [
                    'full_name' => (string)($customer['full_name'] ?? ''),
                    'phone' => (string)($customer['phone'] ?? ''),
                    'email' => (string)($customer['email'] ?? ''),
                ],
                'delivery_method' => (string)($order['shipping_method'] ?? ''),
                'delivery_address' => [
                    'city' => (string)($address['city'] ?? ''),
                    'street' => (string)($address['street'] ?? ''),
                    'postal_code' => (string)($address['postal_code'] ?? ''),
                    'country' => (string)($address['country'] ?? ''),
                ],
                'payment_method' => (string)($order['payment_method'] ?? ''),
                'custom_fields' => $customFields,
            ],
        ];
    }

    /**
     * Payment method title (PHP 7.4 compatible: no nullsafe operator).
     *
     * @return string
     */
    private function paymentMethod($order)
    {
        $payment = $order->getPayment();

        return $payment !== null ? (string)$payment->getMethod() : '';
    }

    /**
     * @return array
     */
    private function itemOptions($item)
    {
        $options = [];

        foreach ((array)($item->getProductOptions()['options'] ?? []) as $option) {
            if (!is_array($option)) {
                continue;
            }
            $options[] = [
                'name' => (string)($option['label'] ?? ''),
                'value' => (string)($option['value'] ?? ''),
            ];
        }

        return $options;
    }

    /**
     * @return int
     */
    private function sumItems(array $items)
    {
        $sum = 0;
        foreach ($items as $item) {
            $sum += (int)$item['line_total']['amount_minor'];
        }

        return $sum;
    }

    /**
     * @return int
     */
    private function toMinor($amount)
    {
        if (is_array($amount) || is_object($amount)) {
            return 0;
        }

        return (int)round(((float)str_replace([' ', ','], ['', '.'], (string)$amount)) * 100);
    }
}
