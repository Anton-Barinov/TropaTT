<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class RetentionService
{
    private const SCOPE = 'retention';

    /** @var array<string,mixed> */
    private array $defaults = [
        'enabled' => true,
        'request_logs_days' => 90,
        'security_logs_days' => 180,
        'audit_logs_days' => 365,
        'recycle_bin_days' => 30,
        'orphan_files_days' => 14,
        'backup_metadata_days' => 365,
    ];

    public function __construct(private readonly SettingService $settings)
    {
    }

    /** @return array<string,mixed> */
    public function getMetadata(): array
    {
        $result = $this->defaults;
        foreach (array_keys($this->defaults) as $key) {
            $row = $this->settings->get(self::SCOPE, $key);
            if ($row === null || !array_key_exists('value', $row)) {
                continue;
            }

            $result[$key] = $row['value'];
        }

        return $result;
    }

    /** @param array<string,mixed> $input */
    public function upsertMetadata(array $input): array
    {
        foreach (array_keys($this->defaults) as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }

            $this->settings->set(self::SCOPE, $key, $input[$key]);
        }

        return $this->getMetadata();
    }
}
