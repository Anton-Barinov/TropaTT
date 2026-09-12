<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

/**
 * Validation and normalization of ingestion envelopes and payloads (E-COM-01 §6).
 *
 * The class is intentionally stateless and dependency-free so the whole
 * contract (money in minor units, contact requirement, free-form limits,
 * string sanitization) can be unit tested without a database. It never touches
 * storage: the caller decides what to do with the normalized data.
 *
 * Error keys mirror the OpenAPI error example (`payload.customer.phone`), with
 * envelope-level fields unprefixed.
 *
 * @phpstan-type ValidationResult array{
 *     ok: bool,
 *     code: string,
 *     status: int,
 *     errors: array<string,list<string>>,
 *     data: array<string,mixed>
 * }
 */
final class PayloadValidator
{
    public const TYPES = ['order', 'quick_order', 'callback', 'feedback', 'form'];

    /** Route suffixes → ingest type (E-COM-01 §5). */
    public const ROUTE_TYPES = [
        'orders' => 'order',
        'quick-orders' => 'quick_order',
        'callbacks' => 'callback',
        'feedback' => 'feedback',
        'forms' => 'form',
    ];

    public const MAX_EXTERNAL_ID = 191;
    public const MAX_FORM_DATA_KEYS = 200;
    public const MAX_FREE_FORM_VALUE = 2000;
    public const MAX_FREE_FORM_LIST = 100;
    public const MAX_FILES = 20;
    public const MAX_JSON_DEPTH = 6;
    private const MAX_CONSENT_KEYS = 20;

    public static function typeFromRoute(string $suffix): ?string
    {
        $normalized = strtolower(trim($suffix));

        return self::ROUTE_TYPES[$normalized] ?? null;
    }

    /**
     * @param array<string,mixed> $raw decoded JSON body
     * @return ValidationResult
     */
    public function validate(string $type, array $raw): array
    {
        if (!in_array($type, self::TYPES, true)) {
            return self::failure('INGESTION_UNKNOWN_TYPE', 400, [
                'type' => ['Unknown ingestion type'],
            ]);
        }

        $errors = [];
        if (self::jsonDepth($raw) > self::MAX_JSON_DEPTH) {
            $errors['payload'] = ['Payload nesting is too deep'];
            return self::failure('INGESTION_PAYLOAD_INVALID', 400, $errors);
        }

        $payloadRaw = $raw['payload'] ?? null;
        if (!is_array($payloadRaw)) {
            $errors['payload'] = ['The payload object is required'];
            return self::failure('INGESTION_PAYLOAD_INVALID', 400, $errors);
        }

        $envelope = $this->validateEnvelope($raw, $errors);
        $payload = $this->validatePayloadByType($type, $payloadRaw, $errors);

        // Contact may live either in the envelope or inside the payload
        // (payload wins, matching the OpenAPI schemas). At least one channel
        // (phone / email / messenger) is mandatory (E-COM-01 §6.3).
        $contactSource = null;
        if (is_array($payloadRaw['customer'] ?? null)) {
            $contactSource = $payloadRaw['customer'];
        } elseif (is_array($raw['contact'] ?? null)) {
            $contactSource = $raw['contact'];
        } elseif (is_array($payloadRaw['contact'] ?? null)) {
            $contactSource = $payloadRaw['contact'];
        }

        $contact = self::emptyContact();
        if ($contactSource !== null) {
            $contact = $this->normalizeContact($contactSource, $errors, 'payload.customer');
        }
        if (!$this->contactHasChannel($contact)) {
            $errors['payload.customer'] = ['At least one contact channel (phone, email or messenger) is required'];
        }

        if ($errors !== []) {
            // Contract codes from the TZ catalogue take precedence over the
            // generic validation code (E-COM-01 §7.2).
            $code = 'INGESTION_VALIDATION_FAILED';
            if (self::hasMoneyIntegerError($errors)) {
                $code = 'INGESTION_AMOUNT_NOT_INTEGER';
            } elseif (self::onlyContactError($errors)) {
                $code = 'INGESTION_CONTACT_REQUIRED';
            }

            return self::failure($code, 422, $errors);
        }

        $externalId = trim((string)($envelope['external_id'] ?? ''));
        $externalIdSynthetic = false;
        if ($externalId === '') {
            $externalId = $this->syntheticExternalId($type, $payloadRaw, $contact);
            $externalIdSynthetic = true;
        }

        return [
            'ok' => true,
            'code' => 'OK',
            'status' => 200,
            'errors' => [],
            'data' => [
                'external_id' => mb_substr($externalId, 0, self::MAX_EXTERNAL_ID),
                'external_id_synthetic' => $externalIdSynthetic,
                'occurred_at' => $envelope['occurred_at'] ?? null,
                'channel' => $envelope['channel'] ?? null,
                'contact' => $contact,
                'utm' => $envelope['utm'],
                'page' => $envelope['page'],
                'form_data' => $envelope['form_data'],
                'meta' => $envelope['meta'],
                'payload' => $payload,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $raw
     * @param array<string,list<string>> $errors
     * @return array<string,mixed>
     */
    private function validateEnvelope(array $raw, array &$errors): array
    {
        $out = [
            'external_id' => null,
            'occurred_at' => null,
            'channel' => null,
            'utm' => [],
            'page' => [],
            'form_data' => [],
            'meta' => [],
        ];

        if (array_key_exists('external_id', $raw)) {
            if (!is_scalar($raw['external_id'])) {
                $errors['external_id'] = ['external_id must be a string'];
            } else {
                $externalId = trim((string)$raw['external_id']);
                if ($externalId === '') {
                    $errors['external_id'] = ['external_id must not be empty'];
                } elseif (mb_strlen($externalId) > self::MAX_EXTERNAL_ID) {
                    $errors['external_id'] = ['external_id exceeds ' . self::MAX_EXTERNAL_ID . ' characters'];
                } else {
                    $out['external_id'] = $externalId;
                }
            }
        }

        if (array_key_exists('occurred_at', $raw) && $raw['occurred_at'] !== null && $raw['occurred_at'] !== '') {
            $normalized = self::normalizeDateTime($raw['occurred_at']);
            if ($normalized === null) {
                $errors['occurred_at'] = ['occurred_at must be an ISO 8601 date-time'];
            } else {
                $out['occurred_at'] = $normalized;
            }
        }

        if (array_key_exists('channel', $raw) && $raw['channel'] !== null && $raw['channel'] !== '') {
            $out['channel'] = mb_substr(self::cleanString($raw['channel']), 0, 64);
        }

        if (is_array($raw['utm'] ?? null)) {
            $out['utm'] = self::normalizeStringMap($raw['utm'], [
                'source', 'medium', 'campaign', 'term', 'content', 'referrer', 'landing_page',
            ], [512, 512, 512, 512, 512, 512, 512]);
        }

        if (is_array($raw['page'] ?? null)) {
            $out['page'] = self::normalizeStringMap($raw['page'], [
                'page_url', 'page_title', 'user_agent', 'ip', 'language',
            ], [1024, 255, 512, 64, 16]);
        }

        if (is_array($raw['form_data'] ?? null)) {
            $out['form_data'] = self::normalizeFreeForm($raw['form_data']);
        } elseif (array_key_exists('form_data', $raw) && $raw['form_data'] !== null) {
            $errors['form_data'] = ['form_data must be an object'];
        }

        if (is_array($raw['meta'] ?? null)) {
            $out['meta'] = self::normalizeFreeForm($raw['meta']);
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,list<string>> $errors
     * @return array<string,mixed>
     */
    private function validatePayloadByType(string $type, array $payload, array &$errors): array
    {
        return match ($type) {
            'order' => $this->validateOrder($payload, $errors),
            'quick_order' => $this->validateQuickOrder($payload, $errors),
            'callback' => $this->validateCallback($payload, $errors),
            'feedback' => $this->validateFeedback($payload, $errors),
            'form' => $this->validateDynamicForm($payload, $errors),
            default => [],
        };
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,list<string>> $errors
     * @return array<string,mixed>
     */
    private function validateOrder(array $payload, array &$errors): array
    {
        $out = ['kind' => 'order'];

        $orderNumber = $this->requiredString($payload, 'order_number', 128, 'payload.order_number', $errors);
        $out['order_number'] = $orderNumber;

        $out['order_status'] = self::optionalString($payload, 'order_status', 64);
        $out['created_at_store'] = self::optionalDateTime($payload, 'created_at_store', 'payload.created_at_store', $errors);

        $itemsRaw = $payload['items'] ?? null;
        if (!is_array($itemsRaw) || $itemsRaw === []) {
            $errors['payload.items'] = ['At least one order item is required'];
            $out['items'] = [];
        } elseif (count($itemsRaw) > 500) {
            $errors['payload.items'] = ['At most 500 order items are allowed'];
            $out['items'] = [];
        } else {
            $items = [];
            foreach (array_values($itemsRaw) as $index => $line) {
                if (!is_array($line)) {
                    $errors['payload.items.' . $index] = ['Each order item must be an object'];
                    continue;
                }
                $items[] = $this->normalizeOrderLine($index, $line, $errors);
            }
            $out['items'] = $items;
        }

        // Only `total` is required by the contract; the breakdown is optional.
        $out['total'] = $this->requiredMoney($payload, 'total', 'payload.total', $errors);
        foreach (['subtotal', 'discount_total', 'delivery_total', 'tax_total'] as $moneyField) {
            $out[$moneyField] = null;
            if (array_key_exists($moneyField, $payload) && $payload[$moneyField] !== null) {
                $out[$moneyField] = $this->normalizeMoney($payload[$moneyField], 'payload.' . $moneyField, $errors);
            }
        }

        $out['paid'] = self::boolValue($payload['paid'] ?? false);
        $out['payment_method'] = self::optionalString($payload, 'payment_method', 128);
        $out['delivery_method'] = self::optionalString($payload, 'delivery_method', 128);
        $out['pickup_point'] = self::optionalString($payload, 'pickup_point', 255);
        $out['comment'] = self::optionalString($payload, 'comment', 4000);

        $out['delivery_address'] = is_array($payload['delivery_address'] ?? null)
            ? $this->normalizeAddress($payload['delivery_address'])
            : null;

        $out['billing_entity'] = is_array($payload['billing_entity'] ?? null)
            ? $this->normalizeBillingEntity($payload['billing_entity'])
            : null;

        $out['files'] = $this->normalizeFiles($payload['files'] ?? null);
        $out['attributes'] = is_array($payload['attributes'] ?? null)
            ? self::normalizeFreeForm($payload['attributes'])
            : [];

        return $out;
    }

    /**
     * @param array<string,mixed> $line
     * @param array<string,list<string>> $errors
     * @return array<string,mixed>
     */
    private function normalizeOrderLine(int $index, array $line, array &$errors): array
    {
        $prefix = 'payload.items.' . $index;
        $out = [
            'sku' => self::optionalString($line, 'sku', 128),
            'external_line_id' => self::optionalString($line, 'external_line_id', 128),
            'name' => '',
            'quantity' => 1,
            'price' => null,
            'line_total' => null,
            'discount' => null,
            'tax' => null,
            'tax_rate' => null,
            'product_url' => self::optionalString($line, 'product_url', 1024),
            'options' => is_array($line['options'] ?? null) ? self::normalizeFreeForm($line['options']) : [],
        ];

        $name = trim(self::cleanString($line['name'] ?? ''));
        if ($name === '') {
            $errors[$prefix . '.name'] = ['Item name is required'];
        }
        $out['name'] = mb_substr($name, 0, 512);

        $quantity = $line['quantity'] ?? null;
        if (filter_var($quantity, FILTER_VALIDATE_INT) === false) {
            $errors[$prefix . '.quantity'] = ['quantity must be an integer'];
        } else {
            $quantity = (int)$quantity;
            if ($quantity < 1 || $quantity > 100000) {
                $errors[$prefix . '.quantity'] = ['quantity must be between 1 and 100000'];
            } else {
                $out['quantity'] = $quantity;
            }
        }

        $out['price'] = $this->requiredMoney($line, 'price', $prefix . '.price', $errors);
        foreach (['line_total', 'discount', 'tax'] as $moneyField) {
            if (array_key_exists($moneyField, $line) && $line[$moneyField] !== null) {
                $out[$moneyField] = $this->normalizeMoney($line[$moneyField], $prefix . '.' . $moneyField, $errors);
            }
        }

        if (array_key_exists('tax_rate', $line) && $line['tax_rate'] !== null && $line['tax_rate'] !== '') {
            if (!is_numeric($line['tax_rate'])) {
                $errors[$prefix . '.tax_rate'] = ['tax_rate must be a number'];
            } else {
                $rate = (float)$line['tax_rate'];
                if ($rate < 0 || $rate > 100) {
                    $errors[$prefix . '.tax_rate'] = ['tax_rate must be between 0 and 100'];
                } else {
                    $out['tax_rate'] = $rate;
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,list<string>> $errors
     * @return array<string,mixed>
     */
    private function validateQuickOrder(array $payload, array &$errors): array
    {
        $out = ['kind' => 'quick_order'];

        $product = $payload['product'] ?? null;
        if (!is_array($product)) {
            $errors['payload.product'] = ['The product object is required'];
            $out['product'] = null;
        } else {
            $name = trim(self::cleanString($product['name'] ?? ''));
            if ($name === '') {
                $errors['payload.product.name'] = ['Product name is required'];
            }
            $out['product'] = [
                'sku' => self::optionalString($product, 'sku', 128),
                'name' => mb_substr($name, 0, 512),
                'url' => self::optionalString($product, 'url', 1024),
                'price' => array_key_exists('price', $product) && $product['price'] !== null
                    ? $this->normalizeMoney($product['price'], 'payload.product.price', $errors)
                    : null,
            ];
        }

        $quantity = $payload['quantity'] ?? 1;
        if (filter_var($quantity, FILTER_VALIDATE_INT) === false) {
            $errors['payload.quantity'] = ['quantity must be an integer'];
        } else {
            $quantity = (int)$quantity;
            if ($quantity < 1 || $quantity > 1000) {
                $errors['payload.quantity'] = ['quantity must be between 1 and 1000'];
            } else {
                $out['quantity'] = $quantity;
            }
        }

        $out['comment'] = self::optionalString($payload, 'comment', 2000);
        $out['consent'] = self::normalizeConsent($payload['consent'] ?? null);

        return $out;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,list<string>> $errors
     * @return array<string,mixed>
     */
    private function validateCallback(array $payload, array &$errors): array
    {
        $out = ['kind' => 'callback'];

        $out['preferred_time'] = is_array($payload['preferred_time'] ?? null)
            ? $this->normalizePreferredTime($payload['preferred_time'], $errors)
            : null;
        $out['page_url'] = self::optionalString($payload, 'page_url', 1024);
        $out['page_title'] = self::optionalString($payload, 'page_title', 255);
        $out['topic'] = self::optionalString($payload, 'topic', 255);
        $out['comment'] = self::optionalString($payload, 'comment', 2000);
        $out['product'] = is_array($payload['product'] ?? null) ? [
            'sku' => self::optionalString($payload['product'], 'sku', 128),
            'name' => self::optionalString($payload['product'], 'name', 512),
        ] : null;
        $out['consent'] = self::normalizeConsent($payload['consent'] ?? null);

        return $out;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,list<string>> $errors
     * @return array<string,mixed>
     */
    private function validateFeedback(array $payload, array &$errors): array
    {
        $out = ['kind' => 'feedback'];

        $message = trim(self::cleanString($payload['message'] ?? ''));
        if ($message === '') {
            $errors['payload.message'] = ['Message is required'];
        } elseif (mb_strlen($message) > 10000) {
            $errors['payload.message'] = ['Message exceeds 10000 characters'];
        }
        $out['message'] = mb_substr($message, 0, 10000);

        $out['subject'] = self::optionalString($payload, 'subject', 255);
        $out['order_number'] = self::optionalString($payload, 'order_number', 128);
        $out['product'] = is_array($payload['product'] ?? null) ? [
            'sku' => self::optionalString($payload['product'], 'sku', 128),
            'name' => self::optionalString($payload['product'], 'name', 512),
            'url' => self::optionalString($payload['product'], 'url', 1024),
        ] : null;
        $out['files'] = $this->normalizeFiles($payload['files'] ?? null);
        $out['consent'] = self::normalizeConsent($payload['consent'] ?? null);

        return $out;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,list<string>> $errors
     * @return array<string,mixed>
     */
    private function validateDynamicForm(array $payload, array &$errors): array
    {
        $out = ['kind' => 'form'];

        $formId = trim(self::cleanString($payload['form_id'] ?? ''));
        if ($formId === '') {
            $errors['payload.form_id'] = ['form_id is required'];
        }
        $out['form_id'] = mb_substr($formId, 0, 128);
        $out['form_name'] = self::optionalString($payload, 'form_name', 255);
        $out['title'] = self::optionalString($payload, 'title', 255);

        $formData = $payload['form_data'] ?? null;
        if (!is_array($formData) || $formData === []) {
            $errors['payload.form_data'] = ['form_data must be a non-empty object'];
            $out['form_data'] = [];
        } else {
            $out['form_data'] = self::normalizeFreeForm($formData);
        }

        $out['is_lead'] = self::boolValue($payload['is_lead'] ?? true);
        $out['files'] = $this->normalizeFiles($payload['files'] ?? null);
        $out['consent'] = self::normalizeConsent($payload['consent'] ?? null);

        return $out;
    }

    // ── Shared normalizers ─────────────────────────────────────────────

    /**
     * @param array<string,mixed> $contact
     * @param array<string,list<string>> $errors
     * @return array<string,mixed>
     */
    private function normalizeContact(array $contact, array &$errors, string $prefix): array
    {
        $out = self::emptyContact();

        $fullName = trim(self::cleanString($contact['full_name'] ?? ''));
        if ($fullName === '') {
            $parts = array_filter([
                trim(self::cleanString($contact['last_name'] ?? '')),
                trim(self::cleanString($contact['first_name'] ?? '')),
                trim(self::cleanString($contact['middle_name'] ?? '')),
            ], static fn(string $v): bool => $v !== '');
            $fullName = trim(implode(' ', $parts));
        }
        $out['full_name'] = mb_substr($fullName, 0, 255);

        $out['contact_type'] = strtolower(self::cleanString($contact['contact_type'] ?? '')) === 'company' ? 'company' : 'person';
        $out['company_name'] = mb_substr(trim(self::cleanString($contact['company_name'] ?? '')), 0, 255);
        $out['tax_id'] = mb_substr(trim(self::cleanString($contact['tax_id'] ?? '')), 0, 32);
        $locale = strtolower(trim((string)($contact['preferred_locale'] ?? '')));
        $out['preferred_locale'] = in_array($locale, ['ru-ru', 'en-gb'], true) ? $locale : null;

        $rawPhone = trim((string)($contact['phone'] ?? ''));
        if ($rawPhone !== '') {
            $phone = self::normalizePhone($rawPhone);
            if ($phone === null) {
                $errors[$prefix . '.phone'] = ['Phone number could not be parsed'];
            } else {
                $out['phone'] = $phone;
            }
        }

        $rawEmail = trim((string)($contact['email'] ?? ''));
        if ($rawEmail !== '') {
            $email = self::normalizeEmail($rawEmail);
            if ($email === null) {
                $errors[$prefix . '.email'] = ['Email address is not valid'];
            } else {
                $out['email'] = $email;
            }
        }

        if (is_array($contact['messenger'] ?? null)) {
            $channel = strtolower(trim((string)($contact['messenger']['channel'] ?? '')));
            if (!in_array($channel, ['telegram', 'whatsapp', 'viber', 'signal', 'other'], true)) {
                $errors[$prefix . '.messenger.channel'] = ['Unsupported messenger channel'];
            } else {
                $out['messenger'] = [
                    'channel' => $channel,
                    'handle' => mb_substr(trim(self::cleanString($contact['messenger']['handle'] ?? '')), 0, 128),
                ];
            }
        }

        $out['extra'] = is_array($contact['extra'] ?? null) ? self::normalizeFreeForm($contact['extra']) : [];

        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    public static function emptyContact(): array
    {
        return [
            'contact_type' => 'person',
            'full_name' => '',
            'company_name' => '',
            'tax_id' => '',
            'preferred_locale' => null,
            'phone' => null,
            'email' => null,
            'messenger' => null,
            'extra' => [],
        ];
    }

    /**
     * @param array<string,mixed> $contact
     */
    public static function contactHasChannel(array $contact): bool
    {
        if (trim((string)($contact['phone'] ?? '')) !== '') {
            return true;
        }
        if (trim((string)($contact['email'] ?? '')) !== '') {
            return true;
        }
        $messenger = $contact['messenger'] ?? null;
        if (is_array($messenger) && trim((string)($messenger['handle'] ?? '')) !== '') {
            return true;
        }

        return false;
    }

    /**
     * Best-effort E.164 normalization (RU-first, +7 for bare 10/11-digit numbers).
     */
    public static function normalizePhone(string $phone): ?string
    {
        $trimmed = trim($phone);
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';
        if ($digits === '') {
            return null;
        }

        $hasPlus = str_starts_with($trimmed, '+');
        if ($hasPlus) {
            $normalized = '+' . $digits;
        } elseif (strlen($digits) === 11 && ($digits[0] === '8' || $digits[0] === '7')) {
            $normalized = '+7' . substr($digits, 1);
        } elseif (strlen($digits) === 10) {
            $normalized = '+7' . $digits;
        } else {
            $normalized = '+' . $digits;
        }

        if (strlen($normalized) < 8 || strlen($normalized) > 17) {
            return null;
        }

        return $normalized;
    }

    public static function normalizeEmail(string $email): ?string
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '' || strlen($normalized) > 255) {
            return null;
        }
        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $normalized;
    }

    /**
     * @param array<string,list<string>> $errors
     * @return array<string,mixed>|null
     */
    private function normalizeMoney(mixed $value, string $field, array &$errors): ?array
    {
        if (!is_array($value)) {
            $errors[$field] = ['Money values must be an object with amount_minor and currency'];
            return null;
        }

        $amount = $value['amount_minor'] ?? null;
        if (is_float($amount) || (is_string($amount) && !ctype_digit(ltrim($amount, '-')))) {
            $errors[$field . '.amount_minor'] = ['Money must be an integer amount in minor units'];
            return null;
        }
        if (!is_int($amount) && !(is_string($amount) && $amount !== '')) {
            $errors[$field . '.amount_minor'] = ['amount_minor is required'];
            return null;
        }
        if (is_string($amount) && !ctype_digit($amount)) {
            $errors[$field . '.amount_minor'] = ['amount_minor must be a non-negative integer'];
            return null;
        }
        $amount = (int)$amount;
        if ($amount < 0) {
            $errors[$field . '.amount_minor'] = ['amount_minor must not be negative'];
            return null;
        }

        $currency = strtoupper(trim((string)($value['currency'] ?? '')));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            $errors[$field . '.currency'] = ['currency must be an ISO 4217 code'];
        }

        $minorUnit = $value['currency_minor_unit'] ?? 2;
        if (filter_var($minorUnit, FILTER_VALIDATE_INT) === false) {
            $errors[$field . '.currency_minor_unit'] = ['currency_minor_unit must be an integer'];
            $minorUnit = 2;
        } else {
            $minorUnit = (int)$minorUnit;
            if ($minorUnit < 0 || $minorUnit > 4) {
                $errors[$field . '.currency_minor_unit'] = ['currency_minor_unit must be between 0 and 4'];
                $minorUnit = 2;
            }
        }

        return [
            'amount_minor' => $amount,
            'currency' => $currency,
            'currency_minor_unit' => $minorUnit,
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,list<string>> $errors
     * @return array<string,mixed>|null
     */
    private function requiredMoney(array $source, string $key, string $field, array &$errors): ?array
    {
        if (!array_key_exists($key, $source) || $source[$key] === null) {
            $errors[$field] = ['This money object is required'];
            return null;
        }

        return $this->normalizeMoney($source[$key], $field, $errors);
    }

    /**
     * @param array<string,mixed> $source
     * @param list<string> $keys
     * @param list<int> $lengths
     * @return array<string,string>
     */
    private static function normalizeStringMap(array $source, array $keys, array $lengths): array
    {
        $out = [];
        foreach ($keys as $index => $key) {
            if (!array_key_exists($key, $source) || !is_scalar($source[$key])) {
                continue;
            }
            $value = trim(self::cleanString($source[$key]));
            if ($value === '') {
                continue;
            }
            $out[$key] = mb_substr($value, 0, (int)($lengths[$index] ?? 255));
        }

        return $out;
    }

    /**
     * Free-form map with bounded keys, values, list length and depth.
     *
     * Invalid keys are dropped so a malformed marketing payload cannot grow the
     * stored JSON without bound; string values are tag-stripped and truncated.
     *
     * @return array<string,mixed>
     */
    public static function normalizeFreeForm(mixed $value, int $depth = 0): array
    {
        if (!is_array($value) || $depth > 1) {
            return [];
        }

        $out = [];
        $count = 0;
        foreach ($value as $key => $item) {
            if ($count >= self::MAX_FORM_DATA_KEYS) {
                break;
            }
            $key = strtolower(trim((string)$key));
            if (preg_match('/^[a-z0-9_.-]{1,64}$/', $key) !== 1) {
                continue;
            }
            $count++;
            $out[$key] = self::normalizeFreeFormValue($item, $depth);
        }

        return $out;
    }

    private static function normalizeFreeFormValue(mixed $value, int $depth): mixed
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value)) {
            return mb_substr(self::cleanString($value), 0, self::MAX_FREE_FORM_VALUE);
        }
        if (is_array($value)) {
            if ($depth > 0) {
                // Depth is capped at 2: nested arrays hold scalars only.
                return [];
            }
            $out = [];
            foreach (array_slice(array_values($value), 0, self::MAX_FREE_FORM_LIST) as $item) {
                if (is_scalar($item)) {
                    $out[] = is_string($item)
                        ? mb_substr(self::cleanString($item), 0, self::MAX_FREE_FORM_VALUE)
                        : $item;
                }
            }

            return $out;
        }

        return null;
    }

    /**
     * @param array<string,mixed> $time
     * @param array<string,list<string>> $errors
     * @return array<string,mixed>
     */
    private function normalizePreferredTime(array $time, array &$errors): array
    {
        $out = ['from' => null, 'to' => null, 'timezone' => null, 'asap' => self::boolValue($time['asap'] ?? false)];
        foreach (['from', 'to'] as $key) {
            if (!array_key_exists($key, $time) || $time[$key] === null || $time[$key] === '') {
                continue;
            }
            $normalized = self::normalizeDateTime($time[$key]);
            if ($normalized === null) {
                $errors['payload.preferred_time.' . $key] = ['Must be an ISO 8601 date-time'];
            } else {
                $out[$key] = $normalized;
            }
        }
        if (!empty($time['timezone'])) {
            $out['timezone'] = mb_substr(self::cleanString($time['timezone']), 0, 64);
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $address
     * @return array<string,string>
     */
    private function normalizeAddress(array $address): array
    {
        $out = [];
        $limits = [
            'country_code' => 2,
            'region' => 128,
            'city' => 128,
            'street' => 255,
            'house' => 64,
            'apartment' => 32,
            'postal_code' => 32,
            'comment' => 512,
        ];
        foreach ($limits as $key => $length) {
            if (!array_key_exists($key, $address) || !is_scalar($address[$key])) {
                continue;
            }
            $value = trim(self::cleanString($address[$key]));
            if ($value === '') {
                continue;
            }
            if ($key === 'country_code') {
                $value = strtoupper($value);
            }
            $out[$key] = mb_substr($value, 0, $length);
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $entity
     * @return array<string,mixed>
     */
    private function normalizeBillingEntity(array $entity): array
    {
        return [
            'company_name' => mb_substr(trim(self::cleanString($entity['company_name'] ?? '')), 0, 255),
            'tax_id' => mb_substr(trim(self::cleanString($entity['tax_id'] ?? '')), 0, 32),
            'kpp' => mb_substr(trim(self::cleanString($entity['kpp'] ?? '')), 0, 32),
            'legal_address' => is_array($entity['legal_address'] ?? null) ? $this->normalizeAddress($entity['legal_address']) : [],
            'bank_details' => is_array($entity['bank_details'] ?? null) ? self::normalizeFreeForm($entity['bank_details']) : [],
        ];
    }

    /**
     * File references are stored as metadata only: the module never fetches a
     * URL from a payload (SSRF, E-COM-01 §9.6).
     *
     * @return list<array<string,mixed>>
     */
    private function normalizeFiles(mixed $files): array
    {
        if (!is_array($files) || $files === []) {
            return [];
        }

        $out = [];
        foreach (array_slice(array_values($files), 0, self::MAX_FILES) as $file) {
            if (!is_array($file) || trim((string)($file['name'] ?? '')) === '') {
                continue;
            }
            $size = filter_var($file['size_bytes'] ?? 0, FILTER_VALIDATE_INT);
            $out[] = [
                'name' => mb_substr(self::cleanString($file['name']), 0, 255),
                'mime_type' => mb_substr(trim(self::cleanString($file['mime_type'] ?? '')), 0, 128),
                'size_bytes' => $size !== false ? max(0, min(26214400, (int)$size)) : 0,
                'url' => mb_substr(trim((string)($file['url'] ?? '')), 0, 1024),
                // Inline content is never decoded here; E-COM-11 checks the
                // store's allow_inline_files flag before touching it.
                'has_inline_content' => trim((string)($file['content_base64'] ?? '')) !== '',
            ];
        }

        return $out;
    }

    /**
     * @return array<string,bool>
     */
    private function normalizeConsent(mixed $consent): array
    {
        if (!is_array($consent)) {
            return [];
        }
        $out = [];
        $count = 0;
        foreach (['personal_data', 'marketing'] as $key) {
            if (array_key_exists($key, $consent)) {
                $out[$key] = self::boolValue($consent[$key]);
                $count++;
            }
            if ($count >= self::MAX_CONSENT_KEYS) {
                break;
            }
        }

        return $out;
    }

    /**
     * Deterministic id for stores that do not send external_id (E-COM-01 §6.2).
     */
    public function syntheticExternalId(string $type, array $payload, array $contact): string
    {
        $fingerprint = hash('sha256', json_encode([
            'type' => $type,
            'phone' => $contact['phone'] ?? null,
            'email' => $contact['email'] ?? null,
            'payload' => self::stableStringify($payload),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        return 'auto_' . substr($fingerprint, 0, 32);
    }

    /**
     * @param mixed $value
     */
    private static function stableStringify(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value);
        }

        return array_map(static fn(mixed $item): mixed => self::stableStringify($item), $value);
    }

    private static function cleanString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (!is_scalar($value)) {
            return '';
        }
        $string = (string)$value;
        $string = strip_tags($string);
        // Drop control characters that would break logs or the markdown body.
        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $string) ?? $string;

        return $string;
    }

    /**
     * @param array<string,mixed> $source
     */
    private function requiredString(array $source, string $key, int $maxLength, string $field, array &$errors): string
    {
        $value = trim(self::cleanString($source[$key] ?? ''));
        if ($value === '') {
            $errors[$field] = ['This field is required'];
        } elseif (mb_strlen($value) > $maxLength) {
            $errors[$field] = ['This field must not exceed ' . $maxLength . ' characters'];
        }

        return mb_substr($value, 0, $maxLength);
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function optionalString(array $source, string $key, int $maxLength): ?string
    {
        if (!array_key_exists($key, $source) || $source[$key] === null || $source[$key] === '') {
            return null;
        }
        $value = trim(self::cleanString($source[$key]));

        return $value === '' ? null : mb_substr($value, 0, $maxLength);
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,list<string>> $errors
     */
    private function optionalDateTime(array $source, string $key, string $field, array &$errors): ?string
    {
        if (!array_key_exists($key, $source) || $source[$key] === null || $source[$key] === '') {
            return null;
        }
        $normalized = self::normalizeDateTime($source[$key]);
        if ($normalized === null) {
            $errors[$field] = ['Must be an ISO 8601 date-time'];
        }

        return $normalized;
    }

    private static function normalizeDateTime(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $string = trim((string)$value);
        if ($string === '' || strlen($string) > 64) {
            return null;
        }
        $timestamp = strtotime($string);

        return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
    }

    private static function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int)$value !== 0;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    /**
     * @param mixed $value
     */
    private static function jsonDepth(mixed $value, int $depth = 1): int
    {
        if (!is_array($value) || $value === []) {
            return $depth;
        }
        $max = $depth;
        foreach ($value as $item) {
            if (is_array($item)) {
                $max = max($max, self::jsonDepth($item, $depth + 1));
            }
        }

        return $max;
    }

    /**
     * @param array<string,list<string>> $errors
     */
    private static function onlyContactError(array $errors): bool
    {
        if (count($errors) !== 1) {
            return false;
        }

        return array_key_exists('payload.customer', $errors);
    }

    /**
     * True when a money field was not an integer amount in minor units.
     *
     * @param array<string,list<string>> $errors
     */
    private static function hasMoneyIntegerError(array $errors): bool
    {
        foreach ($errors as $field => $messages) {
            if (str_ends_with($field, '.amount_minor')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,list<string>> $errors
     * @return ValidationResult
     */
    private static function failure(string $code, int $status, array $errors): array
    {
        return [
            'ok' => false,
            'code' => $code,
            'status' => $status,
            'errors' => $errors,
            'data' => [],
        ];
    }
}
