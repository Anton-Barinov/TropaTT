<?php

namespace Tropatt\Moguta;

/**
 * Two-way mapping between Moguta order statuses and CRM task stages.
 */
class StatusMapper
{
    /**
     * @return array
     */
    public static function decode($raw)
    {
        $mapping = array();

        foreach (preg_split('/[\r\n;]+/', (string)$raw) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '=') === false) {
                continue;
            }

            list($status, $stage) = array_map('trim', explode('=', $line, 2));
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
        $lines = array();
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

        return isset($mapping[$status]) ? (string)$mapping[$status] : null;
    }

    /**
     * @return string|null
     */
    public static function mogutaStatusFor(array $mapping, $crmStage)
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
