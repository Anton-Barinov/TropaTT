<?php

namespace Tropatt\Crm;

/**
 * File logger with secret masking. Logging is off unless the debug option is on,
 * and the store secret never reaches the log.
 */
class ConnectorLogger
{
    /**
     * @return string
     */
    public static function mask($value)
    {
        $value = (string)$value;
        if ($value === '') {
            return '';
        }

        if (strlen($value) <= 8) {
            return '***';
        }

        return substr($value, 0, 4) . '***' . substr($value, -4);
    }

    /**
     * @return string
     */
    public static function logPath()
    {
        if (class_exists('\Bitrix\Main\Application')) {
            return \Bitrix\Main\Application::getDocumentRoot() . '/bitrix/modules/tropatt.crm.log';
        }

        return sys_get_temp_dir() . '/tropatt.crm.log';
    }

    /**
     * @return void
     */
    public static function write($message, array $context = array())
    {
        if (!Config::debugEnabled()) {
            return;
        }

        $line = date('c') . ' ' . $message;
        if ($context !== array()) {
            $line .= ' ' . json_encode(self::sanitize($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        @file_put_contents(self::logPath(), $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * @return array
     */
    public static function sanitize(array $context)
    {
        foreach (array('store_secret', 'secret', 'signature', 'X-TropaTT-Signature', 'password') as $key) {
            if (isset($context[$key])) {
                $context[$key] = self::mask($context[$key]);
            }
        }

        return $context;
    }
}
