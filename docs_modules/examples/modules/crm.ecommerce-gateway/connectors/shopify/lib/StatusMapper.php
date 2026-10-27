<?php

namespace Tropatt\Shopify;

/**
 * CRM stage -> Shopify action.
 *
 * Shopify has no arbitrary order statuses, so a stage maps to one of the
 * supported actions: `cancel` (orderCancel), `fulfill` (fulfillmentCreate),
 * `close` (orderClose) or `noop`.
 */
class StatusMapper
{
    const ACTION_FULFILL = 'fulfill';
    const ACTION_CANCEL = 'cancel';
    const ACTION_CLOSE = 'close';
    const ACTION_NOOP = 'noop';

    /**
     * Actions the connector knows how to execute.
     *
     * @return array
     */
    public static function allowedActions()
    {
        return array(self::ACTION_FULFILL, self::ACTION_CANCEL, self::ACTION_CLOSE, self::ACTION_NOOP);
    }

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

            list($stage, $action) = array_map('trim', explode('=', $line, 2));
            if ($stage === '' || $action === '') {
                continue;
            }

            $mapping[$stage] = $action;
        }

        return $mapping;
    }

    /**
     * @return string
     */
    public static function encode(array $mapping)
    {
        $lines = array();
        foreach ($mapping as $stage => $action) {
            $lines[] = $stage . '=' . $action;
        }

        return implode("\n", $lines);
    }

    /**
     * @return string|null
     */
    public static function actionFor(array $mapping, $crmStage)
    {
        $crmStage = trim((string)$crmStage);
        if ($crmStage === '') {
            return null;
        }

        return isset($mapping[$crmStage]) ? (string)$mapping[$crmStage] : null;
    }

    /**
     * Normalise a raw action to a known one, or null when it is unsupported.
     *
     * @return string|null
     */
    public static function normalizeAction($action)
    {
        $action = strtolower(trim((string)$action));

        return in_array($action, self::allowedActions(), true) ? $action : null;
    }

    /**
     * Resolve the Shopify order GID from the external order id the CRM sends.
     *
     * The CRM stores whatever the connector reported on intake — the numeric
     * REST id (`555000111`) or an already-qualified GID
     * (`gid://shopify/Order/555000111`).
     *
     * @return string|null
     */
    public static function orderGid($externalOrderId)
    {
        $value = trim((string)$externalOrderId);

        if ($value === '') {
            return null;
        }

        if (stripos($value, 'gid://shopify/Order/') === 0) {
            return preg_replace('/[^0-9]/', '', substr($value, strlen('gid://shopify/Order/'))) === ''
                ? null
                : $value;
        }

        $digits = preg_replace('/[^0-9]/', '', $value);

        return $digits === '' ? null : 'gid://shopify/Order/' . $digits;
    }

    /**
     * @return string|null
     */
    public static function defaultMapping()
    {
        return "new=noop\nin_progress=noop\nreview=noop\ndone=fulfill\ncanceled=cancel";
    }
}
