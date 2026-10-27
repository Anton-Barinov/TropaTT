<?php

namespace Tropatt\Shopify;

/**
 * File logger with secret masking (enabled by the `debug` flag).
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

        return strlen($value) <= 8 ? '***' : substr($value, 0, 4) . '***' . substr($value, -4);
    }

    /**
     * @return void
     */
    public static function write($message, array $context = array())
    {
        if (!Config::debugEnabled()) {
            return;
        }

        foreach (array('store_secret', 'tilda_secret', 'tilda_store_api_key', 'signature') as $key) {
            if (isset($context[$key])) {
                $context[$key] = self::mask($context[$key]);
            }
        }

        $line = date('c') . ' ' . $message;
        if ($context !== array()) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $dir = dirname(Config::logFile());
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents(Config::logFile(), $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
