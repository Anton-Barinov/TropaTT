<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

/**
 * Finite State Machine for E-Commerce Orders (E-COM-04 §1).
 *
 * Implements strict transition invariants to prevent race collisions
 * between customer actions on storefront (e.g. buyer cancelled) and CRM manager
 * actions (e.g. marked processing/paid).
 *
 * State Matrix:
 *   new        -> processing, cancelled
 *   processing -> shipped, cancelled, on_hold
 *   on_hold    -> processing, cancelled
 *   shipped    -> completed, returned
 *   returned   -> refunded, cancelled
 *   completed  -> refunded
 *   cancelled  -> terminal! Reverse requires explicit admin override.
 *   refunded   -> terminal!
 */
final class OrderStatusStateMachine
{
    public const STATUS_NEW = 'new';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_ON_HOLD = 'on_hold';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        self::STATUS_NEW => [
            self::STATUS_NEW,
            self::STATUS_PROCESSING,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_PROCESSING => [
            self::STATUS_PROCESSING,
            self::STATUS_SHIPPED,
            self::STATUS_CANCELLED,
            self::STATUS_ON_HOLD,
        ],
        self::STATUS_ON_HOLD => [
            self::STATUS_ON_HOLD,
            self::STATUS_PROCESSING,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_SHIPPED => [
            self::STATUS_SHIPPED,
            self::STATUS_COMPLETED,
            self::STATUS_RETURNED,
        ],
        self::STATUS_RETURNED => [
            self::STATUS_RETURNED,
            self::STATUS_REFUNDED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_COMPLETED => [
            self::STATUS_COMPLETED,
            self::STATUS_REFUNDED,
        ],
        self::STATUS_REFUNDED => [
            self::STATUS_REFUNDED,
        ],
        self::STATUS_CANCELLED => [
            self::STATUS_CANCELLED,
        ],
    ];

    /** @var array<string, string> */
    private const ALIASES = [
        'todo' => self::STATUS_NEW,
        'draft' => self::STATUS_NEW,
        'pending' => self::STATUS_NEW,
        'in_progress' => self::STATUS_PROCESSING,
        'review' => self::STATUS_PROCESSING,
        'hold' => self::STATUS_ON_HOLD,
        'done' => self::STATUS_COMPLETED,
        'complete' => self::STATUS_COMPLETED,
        'success' => self::STATUS_COMPLETED,
        'canceled' => self::STATUS_CANCELLED,
        'failed' => self::STATUS_CANCELLED,
        'declined' => self::STATUS_CANCELLED,
        'refund' => self::STATUS_REFUNDED,
        'return' => self::STATUS_RETURNED,
    ];

    public static function normalize(string $status): string
    {
        $cleaned = strtolower(trim($status));
        return self::ALIASES[$cleaned] ?? $cleaned;
    }

    public static function isTerminal(string $status): bool
    {
        $normalized = self::normalize($status);
        return $normalized === self::STATUS_CANCELLED || $normalized === self::STATUS_REFUNDED;
    }

    /**
     * @return list<string>
     */
    public static function getAllowedNextStatuses(string $from): array
    {
        $normalized = self::normalize($from);
        return self::TRANSITIONS[$normalized] ?? [];
    }

    public static function canTransition(string $from, string $to, bool $isAdminOverride = false): bool
    {
        $result = self::validateTransition($from, $to, $isAdminOverride);
        return $result['allowed'];
    }

    /**
     * @return array{allowed: bool, reason: string, is_terminal_violation: bool, from: string, to: string}
     */
    public static function validateTransition(string $from, string $to, bool $isAdminOverride = false): array
    {
        $normFrom = self::normalize($from);
        $normTo = self::normalize($to);

        // Transition to the identical normalized status is always a no-op / allowed.
        if ($normFrom === $normTo) {
            return [
                'allowed' => true,
                'reason' => 'Identical status',
                'is_terminal_violation' => false,
                'from' => $normFrom,
                'to' => $normTo,
            ];
        }

        // Check terminal source states
        if (self::isTerminal($normFrom)) {
            if ($isAdminOverride) {
                return [
                    'allowed' => true,
                    'reason' => 'Admin override on terminal state',
                    'is_terminal_violation' => false,
                    'from' => $normFrom,
                    'to' => $normTo,
                ];
            }

            return [
                'allowed' => false,
                'reason' => sprintf('Status "%s" is terminal and cannot transition to "%s" without administrator override', $normFrom, $normTo),
                'is_terminal_violation' => true,
                'from' => $normFrom,
                'to' => $normTo,
            ];
        }

        $allowed = self::TRANSITIONS[$normFrom] ?? null;
        if ($allowed !== null && in_array($normTo, $allowed, true)) {
            return [
                'allowed' => true,
                'reason' => 'Allowed transition',
                'is_terminal_violation' => false,
                'from' => $normFrom,
                'to' => $normTo,
            ];
        }

        if ($isAdminOverride) {
            return [
                'allowed' => true,
                'reason' => 'Admin override allowed non-standard transition',
                'is_terminal_violation' => false,
                'from' => $normFrom,
                'to' => $normTo,
            ];
        }

        return [
            'allowed' => false,
            'reason' => sprintf('Invalid transition from "%s" to "%s"', $normFrom, $normTo),
            'is_terminal_violation' => false,
            'from' => $normFrom,
            'to' => $normTo,
        ];
    }
}
