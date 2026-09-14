<?php
/**
 * TropaTT CRM — gateway connector for OpenCart / ocStore 2.3.x.
 *
 * Outbound side of the integration: builds the canonical order payload and
 * signs it (HMAC-SHA256), and serves the paginated stock batch used by
 * `extension/module/tropatt/syncStock`.
 *
 * The payload shape is the canonical E-COM-01 contract of the
 * `crm.ecommerce-gateway` module; the same shape is produced by the OpenCart
 * 3.0 / 4.0 and WooCommerce connectors.
 */
class ModelExtensionModuleTropatt extends Model {
    /** Canonical request path of the order ingestion endpoint. */
    const GATEWAY_PATH_ORDERS = '/_module/crm.ecommerce-gateway/v1/orders';

    /** Canonical request path of the connection check endpoint. */
    const GATEWAY_PATH_PING = '/_module/crm.ecommerce-gateway/v1/ping';

    /** sha256 of an empty body, part of the canonical string of GET requests. */
    const EMPTY_BODY_SHA256 = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    const STOCK_MAX_LIMIT = 200;

    /** Seconds before an ingestion request is abandoned. */
    const REQUEST_TIMEOUT = 15;

    /**
     * Push one order (and its current status) to TropaTT CRM.
     *
     * Called both from the registered event `model/checkout/order/addOrderHistory/after`
     * and from the OCMOD fallback patch.
     *
     * @param int $order_id
     * @param int $order_status_id
     * @return array{success:bool,code:?string,error:?string}
     */
    public function pushOrderToCrm($order_id, $order_status_id) {
        $order_id = (int)$order_id;
        $order_status_id = (int)$order_status_id;

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (!$order_info) {
            return array('success' => false, 'code' => null, 'error' => 'Order not found');
        }

        $settings = $this->settings();

        if ($settings['gateway_url'] === '' || $settings['store_key'] === '' || $settings['store_secret'] === '') {
            return array('success' => false, 'code' => null, 'error' => 'Module configuration missing');
        }

        $payload = $this->buildOrderPayload($order_info, $order_status_id);
        $raw_body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $result = $this->request(
            'POST',
            self::GATEWAY_PATH_ORDERS,
            $settings['gateway_url'] . '/orders',
            $raw_body,
            $settings['store_key'],
            $settings['store_secret'],
            $settings['store_key'] . ':order:' . $order_id
        );

        if ($result['success']) {
            $this->log('Order #' . $order_id . ' pushed to TropaTT CRM (' . $result['code'] . ')');
        } else {
            $this->log('Order #' . $order_id . ' push failed: ' . $result['error']);
        }

        return $result;
    }

    /**
     * Build the canonical order payload (E-COM-01 §5) from an OpenCart order.
     *
     * Differences to the OpenCart 3.0 connector, both deliberate:
     *   - custom fields are mapped to the first-class `billing_entity` block
     *     (INN → tax_id, KPP → kpp, legal name → company_name) and to
     *     `attributes`, instead of the free-form `custom_fields` bag that the
     *     gateway validator drops;
     *   - money is scaled by the store currency's `decimal_place`, so
     *     zero-decimal currencies (JPY, KRW) are sent correctly.
     *
     * @param array<string,mixed> $order_info result of ModelCheckoutOrder::getOrder()
     * @param int $order_status_id
     * @return array<string,mixed>
     */
    public function buildOrderPayload(array $order_info, $order_status_id) {
        $order_id = (int)$order_info['order_id'];
        $order_status_id = (int)$order_status_id;

        $minor_unit = $this->currencyMinorUnit(
            !empty($order_info['currency_code']) ? strtoupper((string)$order_info['currency_code']) : ''
        );
        $currency = $minor_unit['currency'];

        $this->load->model('account/order');
        $order_products = $this->model_account_order->getOrderProducts($order_id);
        $order_totals = $this->model_account_order->getOrderTotals($order_id);

        $items = array();
        foreach ($order_products as $product) {
            $options = array();
            foreach ($this->model_account_order->getOrderOptions($order_id, (int)$product['order_product_id']) as $option) {
                $label = trim((string)$option['name']);
                $value = trim((string)$option['value']);
                $options[] = ($label === '' ? $value : $label . ': ' . $value);
            }

            // The contract requires a whole quantity >= 1. A fractional or
            // zero quantity (weight-based products, free lines) must not
            // reject the whole order with HTTP 400: the line is rounded up to
            // 1 and the real value travels in the line options.
            $quantity_raw = (float)$product['quantity'];
            $quantity = max(1, (int)round($quantity_raw));
            if (abs($quantity_raw - $quantity) > 0.0001) {
                $options[] = 'opencart_quantity: ' . (string)$product['quantity'];
            }

            $items[] = array(
                'name' => (string)$product['name'],
                'sku' => !empty($product['model']) ? (string)$product['model'] : (string)$product['product_id'],
                'quantity' => $quantity,
                'price' => $this->money((float)$product['price'], $currency, $minor_unit['minor_unit']),
                'line_total' => $this->money((float)$product['total'], $currency, $minor_unit['minor_unit']),
                'tax' => $this->money((float)$product['tax'], $currency, $minor_unit['minor_unit']),
                'options' => $options,
            );
        }

        $total = $this->money((float)$order_info['total'], $currency, $minor_unit['minor_unit']);
        $subtotal = null;
        $delivery_total = $this->zeroMoney($currency, $minor_unit['minor_unit']);
        $tax_total = $this->zeroMoney($currency, $minor_unit['minor_unit']);
        $discount_total = $this->zeroMoney($currency, $minor_unit['minor_unit']);

        foreach ($order_totals as $row) {
            $code = isset($row['code']) ? (string)$row['code'] : '';
            $value = isset($row['value']) ? (float)$row['value'] : 0.0;

            switch ($code) {
                case 'sub_total':
                    $subtotal = $this->money($value, $currency, $minor_unit['minor_unit']);
                    break;
                case 'shipping':
                    $delivery_total = $this->money($value, $currency, $minor_unit['minor_unit']);
                    break;
                case 'tax':
                    $tax_total['amount_minor'] += $this->minor($value, $minor_unit['minor_unit']);
                    break;
                case 'discount':
                case 'coupon':
                case 'voucher':
                case 'reward':
                case 'credit':
                    // OpenCart stores reductions as negative totals; the
                    // gateway rejects negative amount_minor.
                    $discount_total['amount_minor'] += abs($this->minor($value, $minor_unit['minor_unit']));
                    break;
            }
        }

        if ($subtotal === null) {
            $subtotal = $this->zeroMoney($currency, $minor_unit['minor_unit']);
            foreach ($items as $item) {
                $subtotal['amount_minor'] += $item['line_total']['amount_minor'];
            }
        }

        return array(
            'external_id' => (string)$order_id,
            'payload' => array(
                'order_number' => (string)$order_id,
                'order_status' => (string)$order_status_id,
                'created_at_store' => $this->isoDate(isset($order_info['date_added']) ? $order_info['date_added'] : ''),
                'items' => $items,
                'subtotal' => $subtotal,
                'discount_total' => $discount_total,
                'delivery_total' => $delivery_total,
                'tax_total' => $tax_total,
                'total' => $total,
                'paid' => $this->isPaidStatus($order_status_id),
                'payment_method' => (string)(isset($order_info['payment_method']) ? $order_info['payment_method'] : ''),
                'delivery_method' => (string)(isset($order_info['shipping_method']) ? $order_info['shipping_method'] : ''),
                'comment' => $this->comment($order_info),
                'delivery_address' => $this->deliveryAddress($order_info),
                'billing_entity' => $this->billingEntity($order_info),
                'attributes' => $this->attributes($order_info),
                'customer' => array(
                    'full_name' => $this->customerName($order_info),
                    'phone' => (string)(isset($order_info['telephone']) ? $order_info['telephone'] : ''),
                    'email' => (string)(isset($order_info['email']) ? $order_info['email'] : ''),
                ),
            ),
        );
    }

    /**
     * One page of the catalogue stock, read as a consistent snapshot.
     *
     * A plain `START TRANSACTION` (InnoDB REPEATABLE READ) makes COUNT(*) and
     * the page SELECT observe the same snapshot, so a concurrent stock write
     * cannot shift rows between the two queries. MyISAM tables are not
     * transactional — the page stays correct, the snapshot guarantee does not.
     *
     * @param int $page 1-based
     * @param int $limit
     * @return array<string,mixed>
     */
    public function getStockPage($page = 1, $limit = 50) {
        $page = max(1, (int)$page);
        $limit = min(self::STOCK_MAX_LIMIT, max(1, (int)$limit));
        $offset = ($page - 1) * $limit;

        $currency = $this->currencyMinorUnit((string)$this->config->get('config_currency'));
        $language_id = (int)$this->config->get('config_language_id');

        $this->db->query('START TRANSACTION');

        $total_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product`");
        $rows_query = $this->db->query(
            "SELECT p.product_id, p.model, p.quantity, p.price, p.status, p.date_modified, pd.name
             FROM `" . DB_PREFIX . "product` p
             LEFT JOIN `" . DB_PREFIX . "product_description` pd
                ON (pd.product_id = p.product_id AND pd.language_id = '" . $language_id . "')
             ORDER BY p.product_id ASC
             LIMIT " . (int)$offset . "," . (int)$limit
        );

        $this->db->query('COMMIT');

        $total = isset($total_query->row['total']) ? (int)$total_query->row['total'] : 0;

        $products = array();
        foreach ($rows_query->rows as $row) {
            $products[] = array(
                'product_id' => (int)$row['product_id'],
                'sku' => (string)$row['model'],
                'name' => (string)$row['name'],
                'quantity' => (int)$row['quantity'],
                'price' => $this->money((float)$row['price'], $currency['currency'], $currency['minor_unit']),
                'status' => (int)$row['status'],
                'updated_at' => (string)$row['date_modified'],
            );
        }

        return array(
            'success' => true,
            'mode' => 'pull',
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'has_more' => ($offset + count($products)) < $total,
            'products' => $products,
        );
    }

    /**
     * Apply a batch of stock levels inside one transaction, locking every
     * affected product row with SELECT ... FOR UPDATE while it is rewritten.
     *
     * Lines are addressed by `product_id` or by `sku` (OpenCart `model`).
     * Unknown SKUs and unchanged quantities are reported, not fatal: a batch
     * keeps its per-line results so the caller can retry only what is left.
     *
     * @param array<int,array<string,mixed>> $lines
     * @param bool $dry_run
     * @return array<string,mixed>
     */
    public function applyStockBatch(array $lines, $dry_run = false) {
        $summary = array('requested' => count($lines), 'updated' => 0, 'unchanged' => 0, 'skipped' => 0);
        $results = array();

        $this->db->query('START TRANSACTION');

        try {
            foreach (array_values($lines) as $index => $line) {
                if (!is_array($line)) {
                    $summary['skipped']++;
                    $results[] = array('index' => $index, 'result' => 'skipped', 'reason' => 'invalid_line');
                    continue;
                }

                $quantity = isset($line['quantity']) ? $line['quantity'] : null;
                if (!is_numeric($quantity) || (int)$quantity < 0) {
                    $summary['skipped']++;
                    $results[] = array('index' => $index, 'result' => 'skipped', 'reason' => 'invalid_quantity');
                    continue;
                }
                $quantity = (int)$quantity;

                $row = $this->lockedProduct($line);
                if ($row === null) {
                    $summary['skipped']++;
                    $results[] = array(
                        'index' => $index,
                        'result' => 'skipped',
                        'reason' => 'product_not_found',
                        'sku' => isset($line['sku']) ? (string)$line['sku'] : '',
                    );
                    continue;
                }

                $product_id = (int)$row['product_id'];
                $current = (int)$row['quantity'];

                if ($current === $quantity) {
                    $summary['unchanged']++;
                    $results[] = array('index' => $index, 'product_id' => $product_id, 'quantity' => $quantity, 'result' => 'unchanged');
                    continue;
                }

                if (!$dry_run) {
                    $this->db->query(
                        "UPDATE `" . DB_PREFIX . "product` SET quantity = '" . (int)$quantity . "', date_modified = NOW()
                         WHERE product_id = '" . $product_id . "'"
                    );
                }

                $summary['updated']++;
                $results[] = array(
                    'index' => $index,
                    'product_id' => $product_id,
                    'previous_quantity' => $current,
                    'quantity' => $quantity,
                    'result' => $dry_run ? 'would_update' : 'updated',
                );
            }

            if ($dry_run) {
                $this->db->query('ROLLBACK');
            } else {
                $this->db->query('COMMIT');
            }
        } catch (Exception $exception) {
            $this->db->query('ROLLBACK');
            $this->log('Stock batch rolled back: ' . $exception->getMessage());

            throw $exception;
        }

        return array(
            'success' => true,
            'mode' => 'push',
            'dry_run' => (bool)$dry_run,
            'summary' => $summary,
            'results' => $results,
        );
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    /**
     * @return array{gateway_url:string,store_key:string,store_secret:string}
     */
    public function settings() {
        return array(
            'gateway_url' => rtrim((string)$this->config->get('module_tropatt_gateway_url'), '/'),
            'store_key' => trim((string)$this->config->get('module_tropatt_store_key')),
            'store_secret' => (string)$this->config->get('module_tropatt_store_secret'),
        );
    }

    /**
     * Signed request against the TropaTT ingestion API.
     *
     * @param string $method
     * @param string $path canonical request path, e.g. /_module/crm.ecommerce-gateway/v1/orders
     * @param string $endpoint fully qualified URL
     * @param string $raw_body
     * @param string $store_key
     * @param string $store_secret
     * @param string $idempotency_key
     * @return array{success:bool,code:?string,error:?string,http_code:int}
     */
    public function request($method, $path, $endpoint, $raw_body, $store_key, $store_secret, $idempotency_key = '') {
        $timestamp = (string)time();
        $nonce = $this->nonce();
        $signature = base64_encode(hash_hmac(
            'sha256',
            strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $raw_body),
            $store_secret,
            true
        ));

        $headers = array(
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Store-Key: ' . $store_key,
            'X-TropaTT-Timestamp: ' . $timestamp,
            'X-TropaTT-Nonce: ' . $nonce,
            'X-TropaTT-Signature: ' . $signature,
        );
        if ($idempotency_key !== '') {
            $headers[] = 'X-TropaTT-Idempotency-Key: ' . $idempotency_key;
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if (strtoupper($method) !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $raw_body);
        }

        $response = curl_exec($ch);
        $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return array('success' => false, 'code' => null, 'error' => $error, 'http_code' => 0);
        }

        $decoded = json_decode($response, true);

        if ($http_code >= 200 && $http_code < 300) {
            $code = null;
            if (is_array($decoded) && isset($decoded['code'])) {
                $code = (string)$decoded['code'];
            }

            return array('success' => true, 'code' => $code, 'error' => null, 'http_code' => $http_code);
        }

        $code = null;
        if (is_array($decoded) && isset($decoded['code'])) {
            $code = (string)$decoded['code'];
        }

        return array('success' => false, 'code' => $code, 'error' => 'HTTP ' . $http_code . ': ' . (string)$response, 'http_code' => $http_code);
    }

    /**
     * Nonce for the signature: 32 hex characters ([A-Za-z0-9_-] on the CRM side).
     *
     * @return string
     */
    public function nonce() {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(16));
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes(16));
        }

        return md5(uniqid((string)mt_rand(), true));
    }

    /**
     * Constant-time comparison (hash_equals() is PHP 5.6+).
     *
     * @param string $known
     * @param string $given
     * @return bool
     */
    public function secureEquals($known, $given) {
        if (function_exists('hash_equals')) {
            return hash_equals((string)$known, (string)$given);
        }

        $known = (string)$known;
        $given = (string)$given;
        if (strlen($known) !== strlen($given)) {
            return false;
        }

        $diff = 0;
        for ($i = 0, $length = strlen($known); $i < $length; $i++) {
            $diff |= ord($known[$i]) ^ ord($given[$i]);
        }

        return $diff === 0;
    }

    /**
     * @param int $order_status_id
     * @return bool true when the status is listed as "order is paid"
     */
    private function isPaidStatus($order_status_id) {
        $paid = (array)$this->config->get('module_tropatt_paid_statuses');
        foreach ($paid as $status_id) {
            if ((int)$status_id === (int)$order_status_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $order_info
     * @return string
     */
    private function comment(array $order_info) {
        $comment = trim((string)(isset($order_info['comment']) ? $order_info['comment'] : ''));
        if (function_exists('mb_substr')) {
            return mb_substr($comment, 0, 4000, 'UTF-8');
        }

        return substr($comment, 0, 4000);
    }

    /**
     * @param array<string,mixed> $order_info
     * @return string
     */
    private function customerName(array $order_info) {
        $name = trim(
            (string)(isset($order_info['firstname']) ? $order_info['firstname'] : '') . ' ' .
            (string)(isset($order_info['lastname']) ? $order_info['lastname'] : '')
        );

        if ($name === '') {
            $name = trim((string)(isset($order_info['email']) ? $order_info['email'] : ''));
        }

        if ($name === '') {
            $name = trim((string)(isset($order_info['telephone']) ? $order_info['telephone'] : ''));
        }

        if ($name === '') {
            $name = 'Store customer #' . (int)$order_info['order_id'];
        }

        return $name;
    }

    /**
     * @param array<string,mixed> $order_info
     * @return array<string,string>
     */
    private function deliveryAddress(array $order_info) {
        $address = array();

        $country_code = strtoupper(trim((string)(isset($order_info['shipping_iso_code_2']) ? $order_info['shipping_iso_code_2'] : '')));
        if ($country_code === '') {
            $country_code = strtoupper(trim((string)(isset($order_info['payment_iso_code_2']) ? $order_info['payment_iso_code_2'] : '')));
        }
        if ($country_code !== '') {
            $address['country_code'] = substr($country_code, 0, 2);
        }

        $fields = array(
            'region' => 'shipping_zone',
            'city' => 'shipping_city',
            'street' => 'shipping_address_1',
            'house' => 'shipping_address_2',
            'postal_code' => 'shipping_postcode',
        );

        foreach ($fields as $key => $source) {
            $value = trim((string)(isset($order_info[$source]) ? $order_info[$source] : ''));
            if ($value !== '') {
                $address[$key] = $value;
            }
        }

        return $address;
    }

    /**
     * Legal entity requisites (SimpleCheckout 4.x / 4.9.x custom fields and the
     * standard OpenCart "Company" address field).
     *
     * @param array<string,mixed> $order_info
     * @return array<string,mixed>
     */
    private function billingEntity(array $order_info) {
        $custom = $this->customFields($order_info);

        $company_name = trim((string)(isset($order_info['payment_company']) ? $order_info['payment_company'] : ''));
        if ($company_name === '') {
            $company_name = trim((string)(isset($order_info['shipping_company']) ? $order_info['shipping_company'] : ''));
        }
        if ($company_name === '' && $custom['company_name'] !== '') {
            $company_name = $custom['company_name'];
        }

        $entity = array(
            'company_name' => $company_name,
            'tax_id' => $custom['tax_id'],
            'kpp' => $custom['kpp'],
        );

        $legal_address = array();
        if ($custom['legal_street'] !== '') {
            $legal_address['street'] = $custom['legal_street'];
        }
        if ($legal_address) {
            $entity['legal_address'] = $legal_address;
        }

        $bank = array();
        if ($custom['bank_account'] !== '') {
            $bank['account'] = $custom['bank_account'];
        }
        if ($custom['bank_name'] !== '') {
            $bank['bank_name'] = $custom['bank_name'];
        }
        if ($custom['bik'] !== '') {
            $bank['bik'] = $custom['bik'];
        }
        if ($bank) {
            $entity['bank_details'] = $bank;
        }

        return $entity;
    }

    /**
     * Everything else worth keeping: store meta plus the custom fields that
     * did not map to a first-class contract field.
     *
     * @param array<string,mixed> $order_info
     * @return array<string,mixed>
     */
    private function attributes(array $order_info) {
        $custom = $this->customFields($order_info);

        $attributes = array(
            'store_platform' => 'opencart-2.3',
            'opencart_comment' => $this->comment($order_info),
        );

        foreach (array('ip', 'payment_code', 'shipping_code', 'store_name') as $key) {
            $value = trim((string)(isset($order_info[$key]) ? $order_info[$key] : ''));
            if ($value !== '') {
                $attributes['opencart_' . $key] = $value;
            }
        }

        foreach ($custom['extra'] as $name => $value) {
            $attributes['cf_' . $name] = $value;
        }

        return $attributes;
    }

    /**
     * Read the order's custom fields with their human-readable names.
     *
     * OpenCart keeps the values as `{custom_field_id: value}` json; the names
     * live in `*_custom_field_description`, which is what makes INN/KPP/legal
     * address recognition possible at all.
     *
     * @param array<string,mixed> $order_info
     * @return array<string,string>
     */
    private function customFields(array $order_info) {
        $result = array(
            'tax_id' => '',
            'kpp' => '',
            'company_name' => '',
            'legal_street' => '',
            'bank_account' => '',
            'bank_name' => '',
            'bik' => '',
            'extra' => array(),
        );

        $values = array();
        foreach (array('custom_field', 'payment_custom_field', 'shipping_custom_field') as $key) {
            if (empty($order_info[$key]) || !is_array($order_info[$key])) {
                continue;
            }
            foreach ($order_info[$key] as $field_id => $value) {
                if (!is_scalar($value)) {
                    continue;
                }
                $value = trim((string)$value);
                if ($value === '') {
                    continue;
                }
                $values[(string)$field_id] = $value;
            }
        }

        if (!$values) {
            return $result;
        }

        $names = $this->customFieldNames(array_keys($values));

        foreach ($values as $field_id => $value) {
            $haystack = isset($names[$field_id]) ? $names[$field_id] : '';
            $haystack = $haystack . ' ' . $field_id;

            if ($result['tax_id'] === '' && $this->matches($haystack, array('инн', 'inn', 'tax id', 'tax_id', 'vat', 'идентификационный номер'))) {
                $result['tax_id'] = $value;
            } elseif ($result['kpp'] === '' && $this->matches($haystack, array('кпп', 'kpp'))) {
                $result['kpp'] = $value;
            } elseif ($result['legal_street'] === '' && $this->matches($haystack, array('юр. адрес', 'юридический адрес', 'legal address', 'uraddress', 'адрес для документов'))) {
                $result['legal_street'] = $value;
            } elseif ($result['bank_account'] === '' && $this->matches($haystack, array('расчетный счет', 'расчётный счёт', 'р/с', 'account number', 'bank account'))) {
                $result['bank_account'] = $value;
            } elseif ($result['bik'] === '' && $this->matches($haystack, array('бик', 'bik'))) {
                $result['bik'] = $value;
            } elseif ($result['bank_name'] === '' && $this->matches($haystack, array('банк', 'bank name'))) {
                $result['bank_name'] = $value;
            } elseif ($result['company_name'] === '' && $this->matches($haystack, array('компан', 'организац', 'юр. лицо', 'юридическое лицо', 'наименование', 'company', 'legal name'))) {
                $result['company_name'] = $value;
            } else {
                $label = isset($names[$field_id]) ? $names[$field_id] : '';
                $key = $this->attributeKey($label);
                if ($key === '') {
                    // Non-Latin labels cannot become a slug: keep the field id
                    // as the key and the label inside the value so nothing is
                    // lost (and no two fields collide on one key).
                    $result['extra']['field_' . $field_id] = ($label !== '' ? $label . ': ' : '') . $value;
                } else {
                    $result['extra'][$key] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * @param array<int,string> $field_ids
     * @return array<string,string> custom_field_id => name
     */
    private function customFieldNames(array $field_ids) {
        $ids = array();
        foreach ($field_ids as $field_id) {
            $ids[] = "'" . $this->db->escape((string)$field_id) . "'";
        }

        $names = array();
        if (!$ids) {
            return $names;
        }

        $query = $this->db->query(
            "SELECT cf.custom_field_id, cfd.name
             FROM `" . DB_PREFIX . "custom_field` cf
             LEFT JOIN `" . DB_PREFIX . "custom_field_description` cfd
                ON (cf.custom_field_id = cfd.custom_field_id AND cfd.language_id = '" . (int)$this->config->get('config_language_id') . "')
             WHERE cf.custom_field_id IN (" . implode(',', $ids) . ")"
        );

        foreach ($query->rows as $row) {
            $names[(string)$row['custom_field_id']] = (string)$row['name'];
        }

        return $names;
    }

    /**
     * Attribute keys must stay stable and free-form friendly (a-z0-9_).
     * Returns an empty string when the label has nothing slug-able left.
     *
     * @param string $name
     * @return string
     */
    private function attributeKey($name) {
        $key = strtolower(trim((string)$name));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);
        $key = trim($key, '_');

        return $key === '' ? '' : substr($key, 0, 60);
    }

    /**
     * @param string $haystack
     * @param array<int,string> $keywords
     * @return bool
     */
    private function matches($haystack, array $keywords) {
        $haystack = mb_strtolower($haystack, 'UTF-8');
        foreach ($keywords as $keyword) {
            if (mb_stripos($haystack, $keyword, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $date
     * @return string|null
     */
    private function isoDate($date) {
        $date = trim((string)$date);
        if ($date === '') {
            return null;
        }

        $timestamp = strtotime($date);

        return $timestamp === false ? null : gmdate('c', $timestamp);
    }

    /**
     * Resolve the currency code and its minor unit from the store currency table.
     *
     * @param string $code
     * @return array{currency:string,minor_unit:int}
     */
    private function currencyMinorUnit($code) {
        $code = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)$code));
        if (strlen($code) < 3) {
            $code = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)$this->config->get('config_currency')));
        }
        if (strlen($code) < 3) {
            $code = 'RUB';
        }
        $code = substr($code, 0, 3);

        $minor_unit = 2;
        $query = $this->db->query("SELECT decimal_place FROM `" . DB_PREFIX . "currency` WHERE code = '" . $this->db->escape($code) . "' LIMIT 1");
        if ($query->num_rows) {
            $minor_unit = max(0, min(4, (int)$query->row['decimal_place']));
        }

        return array('currency' => $code, 'minor_unit' => $minor_unit);
    }

    /**
     * @param float $amount
     * @param int $minor_unit
     * @return int
     */
    private function minor($amount, $minor_unit) {
        return (int)round((float)$amount * pow(10, (int)$minor_unit));
    }

    /**
     * @param float $amount
     * @param string $currency
     * @param int $minor_unit
     * @return array{amount_minor:int,currency:string,currency_minor_unit:int}
     */
    private function money($amount, $currency, $minor_unit) {
        $value = $this->minor($amount, $minor_unit);
        if ($value < 0) {
            $value = 0;
        }

        return array(
            'amount_minor' => $value,
            'currency' => $currency,
            'currency_minor_unit' => (int)$minor_unit,
        );
    }

    /**
     * @param string $currency
     * @param int $minor_unit
     * @return array{amount_minor:int,currency:string,currency_minor_unit:int}
     */
    private function zeroMoney($currency, $minor_unit) {
        return array(
            'amount_minor' => 0,
            'currency' => $currency,
            'currency_minor_unit' => (int)$minor_unit,
        );
    }

    /**
     * Lock one product row for the duration of the stock transaction.
     *
     * @param array<string,mixed> $line
     * @return array<string,mixed>|null
     */
    private function lockedProduct(array $line) {
        if (!empty($line['product_id'])) {
            $where = "product_id = '" . (int)$line['product_id'] . "'";
        } elseif (!empty($line['sku'])) {
            $where = "model = '" . $this->db->escape((string)$line['sku']) . "'";
        } else {
            return null;
        }

        $query = $this->db->query("SELECT product_id, quantity FROM `" . DB_PREFIX . "product` WHERE " . $where . " LIMIT 1 FOR UPDATE");

        return $query->num_rows ? $query->row : null;
    }

    /**
     * @param string $message
     * @return void
     */
    private function log($message) {
        if ($this->config->get('module_tropatt_debug')) {
            $log = new Log('tropatt.log');
            $log->write($message);
        }
    }
}
