<?php
declare(strict_types=1);

namespace Module\Crm\SlackIntegration\Service;

use Module\Crm\SlackIntegration\Repository\SlackRepository;
use PDO;

/**
 * Turns core module events into queued Slack notifications.
 *
 * Rules created by the user carry an `event_code` (the core event name or its
 * legacy underscore alias) and a message template. When a core event is
 * dispatched the provider forwards it here, the notifier matches enabled rules
 * and enqueues deliveries for the background worker.
 */
final class SlackNotifier
{
    /** @var array<string, string> Core dot event → legacy underscore alias. */
    private const EVENT_ALIASES = [
        'task.created' => 'task_created',
        'task.updated' => 'task_updated',
        'task.status_changed' => 'task_status_changed',
        'task.assignee_changed' => 'task_assignee_changed',
        'task.deleted' => 'task_deleted',
        'comment.added' => 'comment_added',
        'file.uploaded' => 'file_uploaded',
        'project.created' => 'project_created',
        'project.updated' => 'project_updated',
        'project.deleted' => 'project_deleted',
        'user.created' => 'user_created',
        'user.updated' => 'user_updated',
        'user.deleted' => 'user_deleted',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Enqueue a delivery for every enabled rule matching the event.
     *
     * @param array<string, mixed> $context
     * @return int Number of deliveries enqueued.
     */
    public function enqueueForEvent(string $event, array $context): int
    {
        $codes = [$event];
        if (isset(self::EVENT_ALIASES[$event])) {
            $codes[] = self::EVENT_ALIASES[$event];
        }

        $repo = new SlackRepository($this->pdo);
        $rules = $repo->listEnabledRulesByEvent($codes);
        if ($rules === []) {
            return 0;
        }

        // Resolve labels only when at least one rule matched (avoids idle queries).
        $context = $this->resolveLabels($context);

        $enqueued = 0;
        foreach ($rules as $rule) {
            $eventCode = (string)($rule['event_code'] ?? $event);
            $template = (string)($rule['text_template'] ?? '');
            $text = $template !== ''
                ? $this->interpolate($template, $context, $eventCode)
                : $this->defaultText($context, $eventCode);

            $repo->enqueueDelivery([
                'connection_id' => (int)($rule['connection_id'] ?? 0),
                'rule_id' => (int)($rule['id'] ?? 0),
                'event_code' => $eventCode,
                'payload_json' => ['text' => mb_substr($text, 0, 4000)],
            ]);
            $enqueued++;
        }

        return $enqueued;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function interpolate(string $template, array $context, string $eventCode): string
    {
        $values = [
            '{event}' => $eventCode,
            '{task}' => (string)($context['task_title'] ?? $context['task'] ?? ''),
            '{task_title}' => (string)($context['task_title'] ?? ''),
            '{task_id}' => (string)($context['task_public_id'] ?? $context['task_id'] ?? ''),
            '{project}' => (string)($context['project_title'] ?? $context['project'] ?? ''),
            '{project_title}' => (string)($context['project_title'] ?? ''),
            '{status}' => (string)($context['new_status'] ?? $context['task_status'] ?? $context['status_code'] ?? $context['status'] ?? ''),
            '{old_status}' => (string)($context['old_status'] ?? ''),
            '{user}' => (string)($context['actor_name'] ?? $context['actor_public_id'] ?? $context['user'] ?? ''),
            '{actor}' => (string)($context['actor_name'] ?? $context['actor_public_id'] ?? $context['actor'] ?? ''),
            '{assignee}' => (string)($context['assignee_name'] ?? $context['new_assignee_name'] ?? ''),
            '{comment}' => (string)($context['comment_body'] ?? $context['comment'] ?? ''),
            '{file}' => (string)($context['file_name'] ?? $context['file'] ?? ''),
        ];

        return strtr($template, $values);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function defaultText(array $context, string $eventCode): string
    {
        $task = (string)($context['task_title'] ?? '');
        $status = (string)($context['new_status'] ?? $context['status_code'] ?? '');
        $eventLabel = $eventCode !== '' ? $eventCode : 'event';
        $parts = [sprintf('[%s] %s', $eventLabel, $task !== '' ? $task : 'Событие TropaTT')];
        if ($status !== '') {
            $parts[] = 'Статус: ' . $status;
        }

        return implode("\n", $parts);
    }

    /**
     * Enrich the payload with human-readable labels so templates can reference
     * names/titles without any core change (payloads carry only ids).
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function resolveLabels(array $context): array
    {
        if (!isset($context['task_title']) && !empty($context['task_public_id'])) {
            $context['task_title'] = $this->scalar(
                'SELECT title FROM tasks WHERE public_id = ?',
                [(string)$context['task_public_id']]
            );
        }
        if (!isset($context['project_title']) && !empty($context['project_public_id'])) {
            $context['project_title'] = $this->scalar(
                'SELECT title FROM projects WHERE public_id = ?',
                [(string)$context['project_public_id']]
            );
        }
        if (!isset($context['comment_body']) && !empty($context['comment_public_id'])) {
            $context['comment_body'] = $this->scalar(
                'SELECT body FROM comments WHERE public_id = ?',
                [(string)$context['comment_public_id']]
            );
        }
        if (!isset($context['file_name']) && !empty($context['file_public_id'])) {
            $context['file_name'] = $this->scalar(
                'SELECT original_name FROM files WHERE public_id = ?',
                [(string)$context['file_public_id']]
            );
        }

        $userFields = [
            'actor_id' => 'actor_name',
            'author_id' => 'author_name',
            'assignee_id' => 'assignee_name',
            'new_assignee_id' => 'new_assignee_name',
            'old_assignee_id' => 'old_assignee_name',
        ];
        foreach ($userFields as $idField => $nameField) {
            $uid = (int)($context[$idField] ?? 0);
            if ($uid > 0 && !isset($context[$nameField])) {
                $context[$nameField] = $this->scalar(
                    'SELECT full_name FROM users WHERE id = ?',
                    [$uid]
                );
            }
        }

        return $context;
    }

    /**
     * @param array<int, mixed> $params
     */
    private function scalar(string $sql, array $params): string
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $value = $stmt->fetchColumn();

            return is_string($value) ? $value : '';
        } catch (\Throwable $e) {
            error_log('[SlackNotifier] label lookup failed: ' . $e->getMessage());

            return '';
        }
    }
}
