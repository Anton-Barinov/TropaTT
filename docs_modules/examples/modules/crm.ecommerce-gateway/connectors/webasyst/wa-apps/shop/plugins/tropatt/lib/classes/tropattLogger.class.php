<?php

/**
 * Plugin logger with secret masking (enabled by the `debug` setting).
 */
class shopTropattLogger
{
    /**
     * @return string
     */
    public static function mask($value)
    {
        $value = (string)$value;

        return $value === '' ? '' : (strlen($value) <= 8 ? '***' : substr($value, 0, 4) . '***' . substr($value, -4));
    }

    /**
     * @return void
     */
    public static function write(array $config, $message, array $context = array())
    {
        if (empty($config['debug'])) {
            return;
        }

        foreach (array('store_secret', 'webhook_secret', 'signature') as $key) {
            if (isset($context[$key])) {
                $context[$key] = self::mask($context[$key]);
            }
        }

        $line = date('c') . ' ' . $message;
        if ($context !== array()) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $file = (string)($config['log_file'] ?? '');
        if ($file === '') {
            return;
        }

        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
