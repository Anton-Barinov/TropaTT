<?php

namespace Tropatt\Crm;

/**
 * Two-way mapping between Bitrix order statuses (STATUS_ID) and CRM task stages.
 *
 * The mapping is stored in the module options as one line per pair:
 *
 *   F=in_progress
 *   N=new
 */
class StatusMapper
{
    /**
     * Parse the option value into an array of Bitrix status id => CRM stage.
     *
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

            list($statusId, $stage) = array_map('trim', explode('=', $line, 2));
            if ($statusId === '' || $stage === '') {
                continue;
            }

            $mapping[$statusId] = $stage;
        }

        return $mapping;
    }

    /**
     * @return string
     */
    public static function encode(array $mapping)
    {
        $lines = array();
        foreach ($mapping as $statusId => $stage) {
            $lines[] = $statusId . '=' . $stage;
        }

        return implode("\n", $lines);
    }

    /**
     * CRM stage -> Bitrix status id.
     *
     * @return string|null
     */
    public static function bitrixStatusFor(array $mapping, $crmStage)
    {
        $crmStage = trim((string)$crmStage);
        if ($crmStage === '') {
            return null;
        }

        foreach ($mapping as $statusId => $stage) {
            if (strcasecmp((string)$stage, $crmStage) === 0) {
                return (string)$statusId;
            }
        }

        return null;
    }

    /**
     * Bitrix status id -> CRM stage.
     *
     * @return string|null
     */
    public static function crmStageFor(array $mapping, $statusId)
    {
        $statusId = (string)$statusId;

        return isset($mapping[$statusId]) ? (string)$mapping[$statusId] : null;
    }

    /**
     * Resolve the target Bitrix status for an incoming CRM webhook.
     *
     * @return string|null
     */
    public static function resolveIncoming(array $mapping, array $data)
    {
        if (!empty($data['external_status'])) {
            return (string)$data['external_status'];
        }

        $stage = isset($data['new_status']) ? (string)$data['new_status'] : '';

        return self::bitrixStatusFor($mapping, $stage);
    }
}
