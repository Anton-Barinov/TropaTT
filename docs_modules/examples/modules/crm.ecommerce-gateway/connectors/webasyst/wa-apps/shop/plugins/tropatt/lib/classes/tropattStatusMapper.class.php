<?php

/**
 * Two-way mapping between Shop-Script workflow states and CRM task stages.
 */
class shopTropattStatusMapper
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

            list($state, $stage) = array_map('trim', explode('=', $line, 2));
            if ($state === '' || $stage === '') {
                continue;
            }

            $mapping[$state] = $stage;
        }

        return $mapping;
    }

    /**
     * @return string
     */
    public static function encode(array $mapping)
    {
        $lines = array();
        foreach ($mapping as $state => $stage) {
            $lines[] = $state . '=' . $stage;
        }

        return implode("\n", $lines);
    }

    /**
     * @return string|null
     */
    public static function crmStageFor(array $mapping, $state)
    {
        $state = (string)$state;

        return isset($mapping[$state]) ? (string)$mapping[$state] : null;
    }

    /**
     * @return string|null
     */
    public static function webasystActionFor(array $mapping, $crmStage)
    {
        $crmStage = trim((string)$crmStage);
        if ($crmStage === '') {
            return null;
        }

        foreach ($mapping as $state => $stage) {
            if (strcasecmp((string)$stage, $crmStage) === 0) {
                return (string)$state;
            }
        }

        return null;
    }
}
