<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use Api\System\Library\Container;
use Api\System\Library\Http\Request;

/**
 * Builds the canonical domain-event envelope and fans it out to module hooks
 * and webhook subscriptions.
 *
 * Core controllers/services used to hand-roll flat payloads, so the same
 * mutation produced a different shape depending on whether it arrived through
 * the web UI, REST or MCP, and none of them carried workspace/time/trace
 * context. This publisher keeps the caller's payload (existing keys stay
 * intact for backwards compatibility) and adds the envelope described by the
 * core-extension specification:
 *
 *   event_id, event_version, occurred_at (UTC ISO-8601),
 *   organization_public_id, actor_public_id, source, correlation_id
 *
 * Delivery happens through ModuleHookDispatcher, i.e. after the caller's
 * mutation has committed: every mutator publishes at the end of its successful
 * path, never before, and a rolled-back mutation publishes nothing.
 */
final class DomainEventPublisher
{
    /** Envelope version handed to subscribers. Bump only for breaking changes. */
    public const EVENT_VERSION = 1;

    /** Entry points an event may be attributed to. */
    private const SOURCE_ALLOWLIST = ['web', 'api', 'mcp', 'cron', 'module'];

    /**
     * Keys that must never reach a module/webhook subscriber, even if a caller
     * puts them into the payload by accident: financial fields (stripped for
     * non-root actors everywhere else too), credentials and page bodies.
     */
    private const REDACT_KEYS = [
        'cost_rate', 'bill_rate', 'cost_amount', 'bill_amount',
        'cost_rate_snapshot', 'bill_rate_snapshot', 'payout_rate_snapshot',
        'password', 'password_hash', 'token', 'token_hash', 'secret',
        'backup_codes', 'api_key', 'authorization',
        'content_html', 'content_text', 'content_json', 'content',
    ];

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, mixed> $payload Entity-specific fields. Existing keys
     *                                     are preserved as-is.
     * @param array<string, mixed>|null $actor Authenticated actor (its
     *                                       organization/public id feed the envelope).
     * @param int|null $organizationId Numeric workspace when the actor does not
     *                                carry one.
     * @param string|null $source Explicit entry point; normally resolved automatically.
     */
    public function publish(
        string $event,
        array $payload,
        ?array $actor = null,
        ?int $organizationId = null,
        ?string $source = null
    ): void {
        $actor = is_array($actor) ? $actor : [];

        if ($organizationId === null) {
            $fromActor = (int)($actor['organization_id'] ?? 0);
            $organizationId = $fromActor > 0 ? $fromActor : null;
        }

        $organizationPublicId = trim((string)($actor['organization_public_id'] ?? ''));
        if ($organizationPublicId === '' && $organizationId !== null) {
            $organizationPublicId = $this->organizationPublicId($organizationId);
        }

        $actorPublicId = trim((string)($actor['public_id'] ?? ''));
        $correlationId = $this->correlationId();

        $envelope = [
            'event_id' => 'evt_' . bin2hex(random_bytes(16)),
            'event_version' => self::EVENT_VERSION,
            'occurred_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'organization_public_id' => $organizationPublicId,
            'actor_public_id' => $actorPublicId,
            'source' => $this->resolveSource($source),
            'correlation_id' => $correlationId,
        ];

        // Envelope wins on collision so subscribers always see canonical
        // tracing fields; every other payload key is passed through unchanged.
        $merged = array_merge(self::redact($payload), $envelope);

        ModuleHookDispatcher::dispatch($this->container, $event, $merged, $organizationId);
    }

    /**
     * Resolve the entry point that produced the event.
     *
     * Precedence: explicit argument → container override (set by the MCP entry
     * point and the cron runner) → `X-Client-Source` request header → browser
     * heuristic (state-changing browser calls carry a CSRF token, API clients
     * and integrations do not) → `api`.
     *
     * Unknown values never reach subscribers: anything outside the allowlist
     * falls back to `api`, so a caller cannot forge an arbitrary source.
     */
    private function resolveSource(?string $explicit): string
    {
        if ($explicit !== null && in_array($explicit, self::SOURCE_ALLOWLIST, true)) {
            return $explicit;
        }

        try {
            if ($this->container->has('event_source')) {
                $override = (string)$this->container->get('event_source');
                if (in_array($override, self::SOURCE_ALLOWLIST, true)) {
                    return $override;
                }
            }
        } catch (\Throwable) {
            // Container without the override — fall through.
        }

        $request = $this->request();
        if ($request instanceof Request) {
            $header = trim((string)($request->headers['X-Client-Source']
                ?? $request->headers['x-client-source'] ?? ''));
            if (in_array($header, self::SOURCE_ALLOWLIST, true)) {
                return $header;
            }

            $hasCsrf = ($request->headers['X-CSRF-Token'] ?? null) !== null
                || ($request->headers['X-Csrf-Token'] ?? null) !== null
                || ($request->headers['x-csrf-token'] ?? null) !== null;
            if ($hasCsrf) {
                return 'web';
            }
        }

        return 'api';
    }

    private function correlationId(): string
    {
        try {
            $request = $this->request();
            if ($request instanceof Request && $request->correlationId !== '') {
                return $request->correlationId;
            }
        } catch (\Throwable) {
            // No HTTP context (CLI/cron) — emit without a correlation id.
        }

        return '';
    }

    private function request(): mixed
    {
        try {
            return $this->container->has('request') ? $this->container->get('request') : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Numeric workspace → public id, resolved once per call against the DB. */
    private function organizationPublicId(int $organizationId): string
    {
        try {
            if (!$this->container->has('db.pdo')) {
                return '';
            }
            $pdo = $this->container->get('db.pdo');
            if (!$pdo instanceof \PDO) {
                return '';
            }
            $stmt = $pdo->prepare('SELECT public_id FROM organizations WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $organizationId]);
            $value = $stmt->fetchColumn();
            return $value === false || $value === null ? '' : (string)$value;
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Drop credentials, financial fields and page bodies from the outgoing
     * payload (recursively), so a subscriber can never observe them.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function redact(array $payload): array
    {
        foreach (self::REDACT_KEYS as $key) {
            unset($payload[$key]);
        }

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::redact($value);
            }
        }

        return $payload;
    }
}
