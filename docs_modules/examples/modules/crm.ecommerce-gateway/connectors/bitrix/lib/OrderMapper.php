<?php

namespace Tropatt\Crm;

/**
 * Converts a Bitrix order into the canonical TropaTT E-COM-01 payload.
 *
 * `fromBitrixOrder()` talks to the D7 Sale API, while `buildPayload()` is pure
 * PHP (no Bitrix classes) so the contract can be unit tested offline.
 */
class OrderMapper
{
    /**
     * Read a Bitrix order through the D7 API.
     *
     * @return array
     */
    public static function fromBitrixOrder($order)
    {
        $properties = self::properties($order);
        $items = array();

        foreach ($order->getBasket() as $basketItem) {
            $quantity = (float)$basketItem->getQuantity();
            $price = (float)$basketItem->getPrice();
            $total = (float)$basketItem->getFinalPrice();

            $items[] = array(
                'name' => (string)$basketItem->getField('NAME'),
                'sku' => (string)$basketItem->getField('PRODUCT_ID'),
                'quantity' => $quantity,
                'price_minor' => (int)round($price * 100),
                'line_total_minor' => (int)round($total * 100),
                'options' => array(),
            );
        }

        return array(
            'id' => (int)$order->getId(),
            'account_number' => (string)$order->getField('ACCOUNT_NUMBER'),
            'status_id' => (string)$order->getField('STATUS_ID'),
            'currency' => (string)$order->getCurrency(),
            'paid' => (string)$order->getField('PAYED') === 'Y',
            'items' => $items,
            'subtotal_minor' => (int)round((float)$order->getBasket()->getPrice() * 100),
            'delivery_minor' => (int)round((float)$order->getDeliveryPrice() * 100),
            'total_minor' => (int)round((float)$order->getPrice() * 100),
            'customer' => array(
                'full_name' => self::firstNonEmpty(array($properties['fio'], $properties['name'])),
                'phone' => $properties['phone'],
                'email' => $properties['email'],
            ),
            'delivery_method' => (string)$order->getField('DELIVERY_ID'),
            'payment_method' => (string)$order->getField('PAY_SYSTEM_ID'),
            'delivery_address' => array(
                'city' => $properties['city'],
                'street' => $properties['street'],
                'postal_code' => $properties['zip'],
                'country' => $properties['country'],
            ),
            'company' => array(
                'name' => $properties['company'],
                'inn' => $properties['inn'],
                'kpp' => $properties['kpp'],
                'address' => $properties['legal_address'],
            ),
            'custom_fields' => array(
                'bitrix_order_id' => (string)$order->getId(),
                'bitrix_status_id' => (string)$order->getField('STATUS_ID'),
                'user_description' => (string)$order->getField('USER_DESCRIPTION'),
                'person_type_id' => (string)$order->getField('PERSON_TYPE_ID'),
            ),
        );
    }

    /**
     * Canonical payload, identical to the other TropaTT connectors.
     *
     * @return array
     */
    public static function buildPayload(array $order, $statusCode)
    {
        $currency = isset($order['currency']) && $order['currency'] !== '' ? strtoupper((string)$order['currency']) : 'RUB';

        $items = array();
        foreach ((array)$order['items'] as $item) {
            $priceMinor = isset($item['price_minor']) ? (int)$item['price_minor'] : 0;
            $lineMinor = isset($item['line_total_minor']) ? (int)$item['line_total_minor'] : $priceMinor * (int)($item['quantity'] ?? 1);

            $entry = array(
                'name' => (string)($item['name'] ?? ''),
                'sku' => (string)($item['sku'] ?? ''),
                'quantity' => (float)($item['quantity'] ?? 1),
                'price' => self::money($priceMinor, $currency),
                'line_total' => self::money($lineMinor, $currency),
            );

            if (!empty($item['options'])) {
                $entry['options'] = $item['options'];
            }

            $items[] = $entry;
        }

        $customer = (array)($order['customer'] ?? array());
        $address = (array)($order['delivery_address'] ?? array());
        $company = (array)($order['company'] ?? array());

        $customFields = (array)($order['custom_fields'] ?? array());

        // Legal-entity requisites travel in the first-class billing_entity block.
        if (trim((string)($company['name'] ?? '')) !== '' || trim((string)($company['inn'] ?? '')) !== '') {
            $billing = array();
            foreach (array('name', 'inn', 'kpp', 'address') as $key) {
                if (trim((string)($company[$key] ?? '')) !== '') {
                    $billing[$key] = (string)$company[$key];
                }
            }
            $customFields['billing_entity'] = $billing;
        }

        if (trim((string)($company['legal_address'] ?? '')) !== '') {
            $customFields['legal_address'] = (string)$company['legal_address'];
        }

        return array(
            'external_id' => (string)($order['id'] ?? ''),
            'payload' => array(
                'order_number' => (string)($order['account_number'] ?? ($order['id'] ?? '')),
                'order_status' => (string)$statusCode,
                'items' => $items,
                'subtotal' => self::money((int)($order['subtotal_minor'] ?? 0), $currency),
                'delivery_total' => self::money((int)($order['delivery_minor'] ?? 0), $currency),
                'total' => self::money((int)($order['total_minor'] ?? 0), $currency),
                'paid' => !empty($order['paid']),
                'customer' => array(
                    'full_name' => (string)($customer['full_name'] ?? ''),
                    'phone' => (string)($customer['phone'] ?? ''),
                    'email' => (string)($customer['email'] ?? ''),
                ),
                'delivery_method' => (string)($order['delivery_method'] ?? ''),
                'delivery_address' => array(
                    'city' => (string)($address['city'] ?? ''),
                    'street' => (string)($address['street'] ?? ''),
                    'postal_code' => (string)($address['postal_code'] ?? ''),
                    'country' => (string)($address['country'] ?? ''),
                ),
                'payment_method' => (string)($order['payment_method'] ?? ''),
                'custom_fields' => $customFields,
            ),
        );
    }

    /**
     * @return array
     */
    private static function money($amountMinor, $currency)
    {
        return array('amount_minor' => (int)$amountMinor, 'currency' => (string)$currency);
    }

    /**
     * Order properties (fiz/ur лица) via the D7 property collection.
     *
     * @return array
     */
    private static function properties($order)
    {
        $result = array(
            'fio' => '', 'name' => '', 'phone' => '', 'email' => '', 'zip' => '',
            'city' => '', 'street' => '', 'country' => '', 'company' => '',
            'inn' => '', 'kpp' => '', 'legal_address' => '',
        );

        $map = array(
            'FIO' => 'fio',
            'NAME' => 'name',
            'PHONE' => 'phone',
            'EMAIL' => 'email',
            'ZIP' => 'zip',
            'CITY' => 'city',
            'ADDRESS' => 'street',
            'COUNTRY' => 'country',
            'COMPANY' => 'company',
            'INN' => 'inn',
            'KPP' => 'kpp',
            'LEGAL_ADDRESS' => 'legal_address',
        );

        $collection = $order->getPropertyCollection();
        if (!is_object($collection)) {
            return $result;
        }

        foreach ($collection as $property) {
            $code = (string)$property->getField('CODE');
            if (isset($map[$code])) {
                $result[$map[$code]] = (string)$property->getValue();
            }
        }

        return $result;
    }

    /**
     * @return string
     */
    private static function firstNonEmpty(array $values)
    {
        foreach ($values as $value) {
            if (trim((string)$value) !== '') {
                return (string)$value;
            }
        }

        return '';
    }
}
