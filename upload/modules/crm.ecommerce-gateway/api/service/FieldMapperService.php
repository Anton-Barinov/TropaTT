<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

use PDO;

/**
 * Flexible Field Mapper: transforms custom form fields into CRM custom fields
 * with structured Markdown-table fallback (E-COM-11 §2).
 */
final class FieldMapperService
{
    public function __construct(private readonly ?PDO $pdo = null)
    {
    }

    /**
     * Maps form fields against store custom field mappings.
     *
     * @param int $storeId Store ID
     * @param array<string,mixed> $formData Raw form key-value pairs
     * @param array<string,string> $mappingRules Optional explicit rules ['form_field' => 'crm_custom_field_key']
     * @return array{
     *     mapped_custom_fields: array<string,mixed>,
     *     unmapped_fields: array<string,mixed>,
     *     markdown_table: string
     * }
     */
    public function map(int $storeId, array $formData, array $mappingRules = []): array
    {
        if ($mappingRules === [] && $this->pdo !== null && $storeId > 0) {
            $mappingRules = $this->loadStoreFieldMappings($storeId);
        }

        $mappedCustomFields = [];
        $unmappedFields = [];

        foreach ($formData as $key => $value) {
            $cleanKey = trim((string)$key);
            if ($cleanKey === '') {
                continue;
            }

            if (isset($mappingRules[$cleanKey]) && $mappingRules[$cleanKey] !== '') {
                $targetCrmKey = $mappingRules[$cleanKey];
                $mappedCustomFields[$targetCrmKey] = $value;
            } else {
                $unmappedFields[$cleanKey] = $value;
            }
        }

        $markdownTable = $this->renderMarkdownTable($unmappedFields);

        return [
            'mapped_custom_fields' => $mappedCustomFields,
            'unmapped_fields' => $unmappedFields,
            'markdown_table' => $markdownTable,
        ];
    }

    /**
     * Formats unmapped form fields into a clean Markdown table.
     *
     * @param array<string,mixed> $fields
     */
    public function renderMarkdownTable(array $fields): string
    {
        if ($fields === []) {
            return '';
        }

        $lines = [
            '| Поле формы | Значение |',
            '| --- | --- |',
        ];

        foreach ($fields as $key => $value) {
            $label = htmlspecialchars((string)$key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $valStr = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value;
            $valEscaped = htmlspecialchars((string)$valStr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $valSingleLine = trim(preg_replace('/\s*\R\s*/u', ' ', $valEscaped) ?? $valEscaped);

            $lines[] = '| ' . str_replace('|', '\\|', $label) . ' | ' . str_replace('|', '\\|', $valSingleLine) . ' |';
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string,string>
     */
    private function loadStoreFieldMappings(int $storeId): array
    {
        if ($this->pdo === null) {
            return [];
        }

        try {
            $stmt = $this->pdo->prepare('SELECT settings_json FROM ecommerce_stores WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $storeId]);
            $raw = $stmt->fetchColumn();
            if ($raw && is_string($raw)) {
                $settings = json_decode($raw, true);
                if (is_array($settings) && isset($settings['field_mappings']) && is_array($settings['field_mappings'])) {
                    return array_map('strval', $settings['field_mappings']);
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal
        }

        return [];
    }
}
