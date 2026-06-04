<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ScheduledTask
{
    public readonly string $name;
    public readonly string $description;
    public readonly string $schedule;
    public readonly array $handler;
    public readonly bool $enabled;
    public readonly int $timeout;
    public readonly bool $overlapAllowed;
    public readonly bool $notifyOnError;

    public function __construct(
        string $name,
        string $description,
        string $schedule,
        array $handler,
        bool $enabled = true,
        int $timeout = 300,
        bool $overlapAllowed = false,
        bool $notifyOnError = true,
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->schedule = $schedule;
        $this->handler = $handler;
        $this->enabled = $enabled;
        $this->timeout = $timeout;
        $this->overlapAllowed = $overlapAllowed;
        $this->notifyOnError = $notifyOnError;
    }
}
