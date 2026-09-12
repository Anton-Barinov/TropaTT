<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

/**
 * Turns validated ingestion data into the intake item's human-facing content
 * (E-COM-01 §9.3, §9.6).
 *
 * Unmatched fields are never dropped: they are compiled into a Markdown table
 * so a manager sees the original form exactly as the customer filled it.
 * Everything is escaped for Markdown and HTML, and the class is pure (no
 * database, no container) so the rendering contract is unit testable.
 *
 * Human-facing strings are Russian today (the default installation locale);
 * E-COM-12 moves them into the module language files without changing this
 * contract.
 */
final class IntakeComposer
{
    public const MAX_DESCRIPTION = 65000;

    /**
     * @param array<string,mixed> $data normalized data from PayloadValidator
     * @param array<string,mixed> $store store row
     * @return array{
     *     title: string,
     *     description: string,
     *     extra: array<string,mixed>,
     *     task_title: string,
     *     source_type: string,
     *     source_ref: string|null,
     *     source_email: string|null
     * }
     */
    public function compose(string $type, array $data, array $store): array
    {
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];
        $contact = is_array($data['contact'] ?? null) ? $data['contact'] : [];
        $storeName = trim((string)($store['name'] ?? ''));

        $title = $this->title($type, $payload, $contact, $storeName);
        $description = $this->description($type, $data, $store);

        return [
            'title' => mb_substr($title, 0, 255),
            'description' => mb_substr($description, 0, self::MAX_DESCRIPTION),
            'extra' => $this->extra($type, $data, $store),
            'task_title' => mb_substr($this->taskTitle($type, $payload, $title), 0, 255),
            'source_type' => $type === 'callback' ? 'webhook' : 'api',
            'source_ref' => trim((string)($data['external_id'] ?? '')) !== '' ? (string)$data['external_id'] : null,
            'source_email' => trim((string)($contact['email'] ?? '')) !== '' ? (string)$contact['email'] : null,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $contact
     */
    public function title(string $type, array $payload, array $contact, string $storeName): string
    {
        $name = trim((string)($contact['full_name'] ?? ''));
        $suffix = $name !== '' ? ' — ' . $name : '';

        return match ($type) {
            'order' => 'Заказ ' . trim((string)($payload['order_number'] ?? '')) . $suffix,
            'quick_order' => 'Покупка в 1 клик: ' . trim((string)($payload['product']['name'] ?? '')) . $suffix,
            'callback' => trim('Обратный звонок' . (trim((string)($payload['topic'] ?? '')) !== '' ? ': ' . (string)$payload['topic'] : '') . $suffix),
            'feedback' => trim((trim((string)($payload['subject'] ?? '')) !== '' ? (string)$payload['subject'] : 'Обращение из формы') . $suffix),
            'form' => trim($this->formTitle($payload) . $suffix),
            default => 'Заявка' . $suffix,
        };
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function formTitle(array $payload): string
    {
        $title = trim((string)($payload['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }
        $formName = trim((string)($payload['form_name'] ?? ''));
        if ($formName !== '') {
            return 'Заявка: ' . $formName;
        }

        return 'Заявка из формы ' . trim((string)($payload['form_id'] ?? ''));
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function taskTitle(string $type, array $payload, string $fallback): string
    {
        return match ($type) {
            'quick_order' => 'Перезвонить по заказу в 1 клик: ' . trim((string)($payload['product']['name'] ?? '')),
            'callback' => 'Обратный звонок' . (trim((string)($payload['topic'] ?? '')) !== '' ? ': ' . (string)$payload['topic'] : ''),
            'order' => 'Обработать заказ ' . trim((string)($payload['order_number'] ?? '')),
            default => $fallback,
        };
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $store
     */
    public function description(string $type, array $data, array $store): string
    {
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];
        $lines = [];

        $storeName = trim((string)($store['name'] ?? ''));
        $storeKey = trim((string)($store['api_key'] ?? ''));
        $header = '### Заявка из интернет-магазина';
        if ($storeName !== '') {
            $header .= ' — ' . $storeName;
        }
        $lines[] = $header;
        $lines[] = '';
        $lines[] = '| Поле | Значение |';
        $lines[] = '| --- | --- |';
        $lines[] = self::markdownRow('Тип', self::typeLabel($type));
        $lines[] = self::markdownRow('Внешний ID', (string)($data['external_id'] ?? ''));
        if (!empty($data['external_id_synthetic'])) {
            $lines[] = self::markdownRow('Внешний ID', 'сформирован CRM (витрина не передала)');
        }
        if ($storeKey !== '') {
            $lines[] = self::markdownRow('Витрина', $storeKey);
        }
        if (trim((string)($data['occurred_at'] ?? '')) !== '') {
            $lines[] = self::markdownRow('Дата на витрине', (string)$data['occurred_at']);
        }
        if (trim((string)($data['channel'] ?? '')) !== '') {
            $lines[] = self::markdownRow('Канал', (string)$data['channel']);
        }
        $lines[] = '';

        $lines = array_merge($lines, $this->payloadSection($type, $payload));
        $lines = array_merge($lines, $this->contactSection($data['contact'] ?? []));
        $lines = array_merge($lines, $this->contextSection($data));
        $lines = array_merge($lines, $this->filesSection($type, $payload));

        return implode("\n", $lines);
    }

    /**
     * @param array<string,mixed> $payload
     * @return list<string>
     */
    private function payloadSection(string $type, array $payload): array
    {
        return match ($type) {
            'order' => $this->orderSection($payload),
            'quick_order' => $this->quickOrderSection($payload),
            'callback' => $this->callbackSection($payload),
            'feedback' => $this->feedbackSection($payload),
            'form' => $this->formSection($payload),
            default => [],
        };
    }

    /**
     * @param array<string,mixed> $payload
     * @return list<string>
     */
    private function orderSection(array $payload): array
    {
        $lines = ['#### Состав заказа', ''];
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        if ($items === []) {
            $lines[] = '_Позиции не переданы._';
        } else {
            $lines[] = '| Товар | SKU | Кол-во | Цена | Сумма |';
            $lines[] = '| --- | --- | --- | --- | --- |';
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $lines[] = '| ' . self::markdownCell((string)($item['name'] ?? ''))
                    . ' | ' . self::markdownCell((string)($item['sku'] ?? ''))
                    . ' | ' . (int)($item['quantity'] ?? 0)
                    . ' | ' . self::markdownCell(self::money($item['price'] ?? null))
                    . ' | ' . self::markdownCell(self::money($item['line_total'] ?? null))
                    . ' |';
            }
        }
        $lines[] = '';
        $lines[] = '| Итог | Сумма |';
        $lines[] = '| --- | --- |';
        foreach (['subtotal' => 'Подытог', 'discount_total' => 'Скидка', 'delivery_total' => 'Доставка', 'tax_total' => 'Налог', 'total' => 'Итого'] as $key => $label) {
            if (($payload[$key] ?? null) !== null) {
                $lines[] = '| ' . self::markdownCell($label) . ' | ' . self::markdownCell(self::money($payload[$key])) . ' |';
            }
        }
        $lines[] = '';

        $details = [];
        $details['Статус на витрине'] = trim((string)($payload['order_status'] ?? ''));
        $details['Оплачен'] = !empty($payload['paid']) ? 'да' : 'нет';
        $details['Способ оплаты'] = trim((string)($payload['payment_method'] ?? ''));
        $details['Доставка'] = trim((string)($payload['delivery_method'] ?? ''));
        $details['Пункт выдачи'] = trim((string)($payload['pickup_point'] ?? ''));
        $lines = array_merge($lines, self::detailTable($details));

        $address = is_array($payload['delivery_address'] ?? null) ? $payload['delivery_address'] : [];
        if ($address !== []) {
            $lines[] = '#### Адрес доставки';
            $lines[] = '';
            $lines[] = self::markdownCell(self::addressLine($address));
            $lines[] = '';
        }

        $billing = is_array($payload['billing_entity'] ?? null) ? $payload['billing_entity'] : [];
        if (($billing['company_name'] ?? '') !== '' || ($billing['tax_id'] ?? '') !== '') {
            $lines[] = '#### Плательщик (B2B)';
            $lines[] = '';
            $lines = array_merge($lines, self::detailTable([
                'Компания' => trim((string)($billing['company_name'] ?? '')),
                'ИНН' => trim((string)($billing['tax_id'] ?? '')),
                'КПП' => trim((string)($billing['kpp'] ?? '')),
            ]));
        }

        if (trim((string)($payload['comment'] ?? '')) !== '') {
            $lines[] = '#### Комментарий покупателя';
            $lines[] = '';
            $lines[] = self::markdownCell((string)$payload['comment']);
            $lines[] = '';
        }

        $lines = array_merge($lines, self::freeFormTable('Дополнительные свойства заказа', $payload['attributes'] ?? []));

        return $lines;
    }

    /**
     * @param array<string,mixed> $payload
     * @return list<string>
     */
    private function quickOrderSection(array $payload): array
    {
        $product = is_array($payload['product'] ?? null) ? $payload['product'] : [];
        $lines = ['#### Товар', ''];
        $rows = [
            'Название' => trim((string)($product['name'] ?? '')),
            'SKU' => trim((string)($product['sku'] ?? '')),
            'Ссылка' => trim((string)($product['url'] ?? '')),
            'Цена' => self::money($product['price'] ?? null),
            'Количество' => (string)(int)($payload['quantity'] ?? 1),
        ];
        $lines = array_merge($lines, self::detailTable($rows));
        if (trim((string)($payload['comment'] ?? '')) !== '') {
            $lines[] = '#### Комментарий';
            $lines[] = '';
            $lines[] = self::markdownCell((string)$payload['comment']);
            $lines[] = '';
        }

        return $lines;
    }

    /**
     * @param array<string,mixed> $payload
     * @return list<string>
     */
    private function callbackSection(array $payload): array
    {
        $preferred = is_array($payload['preferred_time'] ?? null) ? $payload['preferred_time'] : [];
        $product = is_array($payload['product'] ?? null) ? $payload['product'] : [];
        $rows = [
            'Тема' => trim((string)($payload['topic'] ?? '')),
            'Товар' => trim((string)($product['name'] ?? '')),
            'SKU' => trim((string)($product['sku'] ?? '')),
            'Страница' => trim((string)($payload['page_url'] ?? '')),
            'Звонок с' => trim((string)($preferred['from'] ?? '')),
            'Звонок до' => trim((string)($preferred['to'] ?? '')),
            'Как можно скорее' => !empty($preferred['asap']) ? 'да' : '',
        ];
        $lines = self::detailTable($rows);
        if (trim((string)($payload['comment'] ?? '')) !== '') {
            $lines[] = '#### Комментарий';
            $lines[] = '';
            $lines[] = self::markdownCell((string)$payload['comment']);
            $lines[] = '';
        }

        return $lines;
    }

    /**
     * @param array<string,mixed> $payload
     * @return list<string>
     */
    private function feedbackSection(array $payload): array
    {
        $product = is_array($payload['product'] ?? null) ? $payload['product'] : [];
        $lines = [];
        if (trim((string)($payload['subject'] ?? '')) !== '') {
            $lines[] = '#### Тема';
            $lines[] = '';
            $lines[] = self::markdownCell((string)$payload['subject']);
            $lines[] = '';
        }
        $lines[] = '#### Сообщение';
        $lines[] = '';
        $lines[] = self::markdownCell((string)($payload['message'] ?? ''));
        $lines[] = '';
        $lines = array_merge($lines, self::detailTable([
            'Товар' => trim((string)($product['name'] ?? '')),
            'SKU' => trim((string)($product['sku'] ?? '')),
            'Ссылка на товар' => trim((string)($product['url'] ?? '')),
            'Номер заказа' => trim((string)($payload['order_number'] ?? '')),
        ]));

        return $lines;
    }

    /**
     * @param array<string,mixed> $payload
     * @return list<string>
     */
    private function formSection(array $payload): array
    {
        $lines = [];
        if (trim((string)($payload['form_name'] ?? '')) !== '') {
            $lines[] = '**Форма:** ' . self::escape((string)$payload['form_name']);
            $lines[] = '';
        }
        $lines[] = '**form_id:** `' . self::escape((string)($payload['form_id'] ?? '')) . '`';
        $lines[] = '';

        return array_merge($lines, self::freeFormTable('Поля формы', $payload['form_data'] ?? []));
    }

    /**
     * @param mixed $contact
     * @return list<string>
     */
    private function contactSection(mixed $contact): array
    {
        if (!is_array($contact)) {
            return [];
        }
        $messenger = is_array($contact['messenger'] ?? null) ? $contact['messenger'] : [];
        $rows = [
            'Имя' => trim((string)($contact['full_name'] ?? '')),
            'Компания' => trim((string)($contact['company_name'] ?? '')),
            'Телефон' => trim((string)($contact['phone'] ?? '')),
            'Email' => trim((string)($contact['email'] ?? '')),
            'Мессенджер' => trim((string)($messenger['channel'] ?? '')) !== ''
                ? trim((string)$messenger['channel'] . ' ' . (string)($messenger['handle'] ?? ''))
                : '',
            'ИНН' => trim((string)($contact['tax_id'] ?? '')),
        ];
        $table = self::detailTable($rows);
        if ($table === []) {
            return [];
        }

        return array_merge(['#### Контакт', ''], $table);
    }

    /**
     * @param array<string,mixed> $data
     * @return list<string>
     */
    private function contextSection(array $data): array
    {
        $lines = [];
        $lines = array_merge($lines, self::freeFormTable('UTM-метки', $data['utm'] ?? []));
        $lines = array_merge($lines, self::freeFormTable('Страница', $data['page'] ?? []));
        $lines = array_merge($lines, self::freeFormTable('Поля формы (общие)', $data['form_data'] ?? []));
        $lines = array_merge($lines, self::freeFormTable('Дополнительно', $data['meta'] ?? []));

        $consent = is_array($data['payload']['consent'] ?? null) ? $data['payload']['consent'] : [];
        if ($consent !== []) {
            $rows = [];
            foreach ($consent as $key => $value) {
                $rows[self::consentLabel((string)$key)] = !empty($value) ? 'да' : 'нет';
            }
            $lines = array_merge($lines, self::detailTable($rows));
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function filesSection(string $type, array $payload): array
    {
        $files = is_array($payload['files'] ?? null) ? $payload['files'] : [];
        if ($files === []) {
            return [];
        }

        $lines = ['#### Вложения', ''];
        foreach ($files as $file) {
            if (!is_array($file)) {
                continue;
            }
            $name = trim((string)($file['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $line = '- ' . self::escape($name);
            if (trim((string)($file['url'] ?? '')) !== '') {
                $line .= ' — ' . self::escape((string)$file['url']);
            }
            if (!empty($file['has_inline_content'])) {
                $line .= ' _(передан inline; загрузка — E-COM-11)_';
            }
            $lines[] = $line;
        }
        $lines[] = '';

        return $lines;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $store
     * @return array<string,mixed>
     */
    public function extra(string $type, array $data, array $store): array
    {
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];
        $extra = [
            'gateway' => [
                'store_key' => (string)($store['api_key'] ?? ''),
                'store_name' => (string)($store['name'] ?? ''),
                'cms_type' => (string)($store['cms_type'] ?? 'custom'),
                'type' => $type,
                'protocol_version' => '1.0',
                'received_at' => gmdate('c'),
            ],
            'external_id' => $data['external_id'] ?? null,
            'external_id_synthetic' => (bool)($data['external_id_synthetic'] ?? false),
            'occurred_at' => $data['occurred_at'] ?? null,
            'channel' => $data['channel'] ?? null,
            'contact' => $data['contact'] ?? [],
            'utm' => $data['utm'] ?? [],
            'page' => $data['page'] ?? [],
            'form_data' => $data['form_data'] ?? [],
            'meta' => $data['meta'] ?? [],
            'consent' => $payload['consent'] ?? [],
            'files' => $payload['files'] ?? [],
            'payload' => $payload,
        ];

        return $this->fitExtra($extra);
    }

    /**
     * Keeps extra_json under the intake column limit by dropping the most
     * voluminous optional blocks first; the essential ids always survive.
     *
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function fitExtra(array $extra): array
    {
        $droppable = ['payload', 'form_data', 'meta', 'page', 'utm', 'files', 'contact'];
        foreach ($droppable as $key) {
            $encoded = json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($encoded) && strlen($encoded) <= 60000) {
                return $extra;
            }
            unset($extra[$key]);
            $extra['truncated'] = true;
        }

        return $extra;
    }

    /**
     * @param array<string,string> $rows
     * @return list<string>
     */
    private static function detailTable(array $rows): array
    {
        $filled = array_filter($rows, static fn(string $value): bool => trim($value) !== '');
        if ($filled === []) {
            return [];
        }

        $lines = ['| Поле | Значение |', '| --- | --- |'];
        foreach ($filled as $label => $value) {
            $lines[] = '| ' . self::markdownCell($label) . ' | ' . self::markdownCell($value) . ' |';
        }
        $lines[] = '';

        return $lines;
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function freeFormTable(string $title, mixed $values): array
    {
        if (!is_array($values) || $values === []) {
            return [];
        }

        $lines = ['#### ' . $title, '', '| Поле | Значение |', '| --- | --- |'];
        foreach ($values as $key => $value) {
            $lines[] = '| ' . self::markdownCell((string)$key) . ' | ' . self::markdownCell(self::valueToString($value)) . ' |';
        }
        $lines[] = '';

        return $lines;
    }

    /**
     * @param mixed $value
     */
    private static function valueToString(mixed $value): string
    {
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                if (is_scalar($item)) {
                    $parts[] = (string)$item;
                }
            }

            return implode(', ', $parts);
        }
        if (is_bool($value)) {
            return $value ? 'да' : 'нет';
        }

        return is_scalar($value) ? (string)$value : '';
    }

    /**
     * @param mixed $money
     */
    private static function money(mixed $money): string
    {
        if (!is_array($money) || !array_key_exists('amount_minor', $money)) {
            return '';
        }
        $amount = (int)$money['amount_minor'];
        $minorUnit = (int)($money['currency_minor_unit'] ?? 2);
        $divisor = $minorUnit > 0 ? 10 ** $minorUnit : 1;
        $formatted = $minorUnit > 0
            ? number_format($amount / $divisor, $minorUnit, '.', ' ')
            : number_format($amount, 0, '.', ' ');

        return $formatted . ' ' . strtoupper((string)($money['currency'] ?? ''));
    }

    /**
     * @param array<string,mixed> $address
     */
    private static function addressLine(array $address): string
    {
        $parts = [];
        foreach (['country_code', 'region', 'city', 'street', 'house', 'apartment', 'postal_code', 'comment'] as $key) {
            $value = trim((string)($address[$key] ?? ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return implode(', ', $parts);
    }

    private static function typeLabel(string $type): string
    {
        return match ($type) {
            'order' => 'Заказ',
            'quick_order' => 'Покупка в один клик',
            'callback' => 'Обратный звонок',
            'feedback' => 'Обратная связь',
            'form' => 'Форма / лид',
            default => $type,
        };
    }

    private static function consentLabel(string $key): string
    {
        return match ($key) {
            'personal_data' => 'Согласие на обработку персональных данных',
            'marketing' => 'Согласие на маркетинг',
            default => $key,
        };
    }

    private static function markdownRow(string $label, string $value): string
    {
        return '| ' . self::markdownCell($label) . ' | ' . self::markdownCell($value) . ' |';
    }

    private static function markdownCell(string $value): string
    {
        $escaped = self::escape($value);
        // Newlines would break the table layout; keep the row on one line.
        return trim(preg_replace('/\s*\R\s*/', ' ', $escaped) ?? $escaped);
    }

    public static function escape(string $value): string
    {
        // strip_tags on input plus escaping here means the description can be
        // rendered by the core markdown path without introducing HTML.
        return htmlspecialchars(str_replace(['\\', '|'], ['\\\\', '\\|'], $value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
