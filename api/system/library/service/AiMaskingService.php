<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class AiMaskingService
{
    /** @return array<string,string> */
    public function fieldClassificationMap(): array
    {
        return [
            'public_id' => 'internal',
            'title' => 'internal',
            'status' => 'internal',
            'client_type' => 'internal',
            'email' => 'personal',
            'phone' => 'personal',
            'address_legal' => 'personal',
            'address_postal' => 'personal',
            'notes' => 'sensitive',
            'description' => 'sensitive',
            'prompt' => 'sensitive',
            'input_text' => 'sensitive',
            'tax_inn' => 'sensitive',
            'tax_kpp' => 'sensitive',
            'tax_ogrn' => 'sensitive',
            'tax_ogrnip' => 'sensitive',
            'bank_account' => 'sensitive',
            'bank_bik' => 'sensitive',
            'bank_corr_account' => 'sensitive',
            'bank_name' => 'sensitive',
            'password' => 'secret',
            'token' => 'secret',
            'secret' => 'secret',
            'authorization' => 'secret',
            'cookie' => 'secret',
            'api_key' => 'secret',
            'webhook_secret' => 'secret',
        ];
    }

    public function classifyField(string $field): string
    {
        $normalized = strtolower(trim($field));
        if ($normalized === '') {
            return 'internal';
        }

        foreach ($this->fieldClassificationMap() as $key => $classification) {
            if ($normalized === $key || str_ends_with($normalized, '_' . $key) || str_contains($normalized, $key)) {
                return $classification;
            }
        }

        return 'internal';
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    public function contextPolicyMetadata(array $context): array
    {
        $classes = [
            'public' => [],
            'internal' => [],
            'personal' => [],
            'sensitive' => [],
            'secret' => [],
        ];

        foreach ($context as $key => $_value) {
            if (!is_string($key) || str_starts_with($key, '_')) {
                continue;
            }
            $classification = $this->classifyField($key);
            $classes[$classification][] = $key;
        }

        foreach ($classes as $class => $fields) {
            $classes[$class] = array_values(array_unique($fields));
            sort($classes[$class]);
        }

        return [
            'field_classes' => $classes,
            'contains_personal' => $classes['personal'] !== [],
            'contains_sensitive' => $classes['sensitive'] !== [] || $classes['secret'] !== [],
            'secret_fields_blocked' => $classes['secret'],
        ];
    }

    public function maskSensitiveText(string $value): string
    {
        $result = trim($value);
        if ($result === '') {
            return '';
        }

        $patterns = [
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',
            '/\+?\d[\d\-\s()]{7,}\d/u',
            '/\b(?:\d[ -]*?){13,19}\b/u',
        ];

        foreach ($patterns as $pattern) {
            $result = (string)preg_replace($pattern, '[masked]', $result);
        }

        return $result;
    }
}
