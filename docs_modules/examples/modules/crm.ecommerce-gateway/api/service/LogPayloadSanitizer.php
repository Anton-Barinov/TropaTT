<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

/**
 * Prevent customer payloads from being copied into broadly visible sync logs.
 * Logs retain only payload size and field names for troubleshooting.
 */
final class LogPayloadSanitizer
{
    public static function sanitize(mixed $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        $raw = is_string($payload)
            ? $payload
            : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($raw)) {
            return '[payload omitted]';
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)
            && ($decoded['redacted'] ?? false) === true
            && is_numeric($decoded['payload_bytes'] ?? null)
            && is_array($decoded['fields'] ?? null)
        ) {
            $summary = [
                'redacted' => true,
                'payload_bytes' => max(0, (int)$decoded['payload_bytes']),
                'fields' => array_slice(array_values(array_filter(
                    $decoded['fields'],
                    static fn(mixed $field): bool => is_string($field) && preg_match('/^[A-Za-z_][A-Za-z0-9_.-]{0,255}$/', $field) === 1
                )), 0, 100),
            ];
            $encoded = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return is_string($encoded) ? $encoded : '[payload omitted]';
        }
        $fields = is_array($decoded) ? self::fieldNames($decoded) : [];
        $summary = [
            'redacted' => true,
            'payload_bytes' => strlen($raw),
        ];
        if ($fields !== []) {
            $summary['fields'] = array_slice($fields, 0, 100);
        }

        $encoded = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($encoded) ? $encoded : '[payload omitted]';
    }

    /** @param array<mixed> $value @return list<string> */
    private static function fieldNames(array $value, string $prefix = '', int $depth = 0): array
    {
        if ($depth >= 4) {
            return $prefix === '' ? [] : [$prefix . '…'];
        }
        $fields = [];
        foreach ($value as $key => $child) {
            if (!is_string($key) && !is_int($key)) {
                continue;
            }
            $rawName = (string)$key;
            $segment = preg_match('/^[A-Za-z_][A-Za-z0-9_-]{0,63}$/', $rawName) === 1 ? $rawName : 'custom_field';
            $name = $prefix === '' ? $segment : $prefix . '.' . $segment;
            $fields[] = $name;
            if (is_array($child)) {
                foreach (self::fieldNames($child, $name, $depth + 1) as $nested) {
                    $fields[] = $nested;
                }
            }
        }
        return array_values(array_unique($fields));
    }
}
