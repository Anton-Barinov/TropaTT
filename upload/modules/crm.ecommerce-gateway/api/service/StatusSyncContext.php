<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

/**
 * Echo Loop Prevention Context (E-COM-04 §2).
 *
 * Tracks whether the current task/entity status modification was initiated
 * by an incoming CMS webhook/API call ('cms') or by a CRM manager/workflow ('crm').
 *
 * When an incoming CMS webhook updates a task in the CRM, the listener observing
 * TASK_STATUS_CHANGED checks `isCmsInitiated()`. If true, it skips generating
 * an outbound webhook back to the same CMS, breaking the infinite echo loop.
 */
final class StatusSyncContext
{
    public const INITIATOR_CRM = 'crm';
    public const INITIATOR_CMS = 'cms';

    private static string $initiator = self::INITIATOR_CRM;

    public static function setInitiator(string $initiator): void
    {
        self::$initiator = ($initiator === self::INITIATOR_CMS) ? self::INITIATOR_CMS : self::INITIATOR_CRM;
    }

    public static function getInitiator(): string
    {
        return self::$initiator;
    }

    public static function isCmsInitiated(): bool
    {
        return self::$initiator === self::INITIATOR_CMS;
    }

    public static function reset(): void
    {
        self::$initiator = self::INITIATOR_CRM;
    }

    /**
     * Executes a callback within the CMS initiator context and guarantees cleanup.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function runAsCms(callable $callback): mixed
    {
        $previous = self::$initiator;
        self::$initiator = self::INITIATOR_CMS;
        try {
            return $callback();
        } finally {
            self::$initiator = $previous;
        }
    }
}
