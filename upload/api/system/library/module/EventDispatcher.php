<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class Event
{
    private bool $propagationStopped = false;

    public function __construct(
        public readonly string $name,
        public readonly array $payload = [],
    ) {}

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}

final class EventDispatcher
{
    /** @var array<string, array<int, array{listener: callable, priority: int}>> */
    private array $listeners = [];

    public function dispatch(Event $event): void
    {
        if (!isset($this->listeners[$event->name])) {
            return;
        }

        $listeners = $this->listeners[$event->name];
        usort($listeners, fn($a, $b) => $b['priority'] <=> $a['priority']);

        foreach ($listeners as $entry) {
            if ($event->isPropagationStopped()) {
                break;
            }

            try {
                ($entry['listener'])($event);
            } catch (\Throwable $e) {
                error_log(sprintf(
                    '[EventDispatcher] Error in listener for "%s": %s',
                    $event->name,
                    $e->getMessage()
                ));
            }
        }
    }

    public function listen(string $eventName, callable $listener, int $priority = 0): void
    {
        if (!isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = [];
        }
        $this->listeners[$eventName][] = [
            'listener' => $listener,
            'priority' => $priority,
        ];
    }

    public function removeListeners(string $eventName): void
    {
        unset($this->listeners[$eventName]);
    }

    public function hasListeners(string $eventName): bool
    {
        return isset($this->listeners[$eventName]) && $this->listeners[$eventName] !== [];
    }

    public function clear(): void
    {
        $this->listeners = [];
    }
}
