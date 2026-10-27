<?php

namespace Tropatt\Crm\Model;

/**
 * Two-way mapping between Magento order statuses and CRM task stages.
 */
class StatusMapper
{
    /**
     * @return array
     */
    public static function decode($raw)
    {
        $mapping = [];

        foreach (preg_split('/[\r\n;]+/', (string)$raw) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '=') === false) {
                continue;
            }

            [$status, $stage] = array_map('trim', explode('=', $line, 2));
            if ($status === '' || $stage === '') {
                continue;
            }

            $mapping[$status] = $stage;
        }

        return $mapping;
    }

    /**
     * @return string
     */
    public static function encode(array $mapping)
    {
        $lines = [];
        foreach ($mapping as $status => $stage) {
            $lines[] = $status . '=' . $stage;
        }

        return implode("\n", $lines);
    }

    /**
     * @return string|null
     */
    public static function crmStageFor(array $mapping, $status)
    {
        $status = (string)$status;

        return $mapping[$status] ?? null;
    }

    /**
     * @return string|null
     */
    public static function magentoStatusFor(array $mapping, $crmStage)
    {
        $crmStage = trim((string)$crmStage);
        if ($crmStage === '') {
            return null;
        }

        foreach ($mapping as $status => $stage) {
            if (strcasecmp((string)$stage, $crmStage) === 0) {
                return (string)$status;
            }
        }

        return null;
    }
}
