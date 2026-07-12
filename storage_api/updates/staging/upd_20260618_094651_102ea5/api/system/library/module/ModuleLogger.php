<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleLogger
{
    private string $logDir;
    private string $moduleName;

    public function __construct(string $moduleName, string $storageBase)
    {
        $this->moduleName = $moduleName;
        $this->logDir = rtrim($storageBase, '/') . '/logs/modules';
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->log('EMERGENCY', $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log('ALERT', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log('NOTICE', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $ts = date('c');
        $contextStr = $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line = "[{$ts}] module.{$this->moduleName}.{$level}: {$message}{$contextStr}\n";

        $file = $this->logDir . '/' . $this->moduleName . '.log';
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);

        if (in_array($level, ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR'], true)) {
            error_log("[Module:{$this->moduleName}] {$level}: {$message}{$contextStr}");
        }
    }
}
