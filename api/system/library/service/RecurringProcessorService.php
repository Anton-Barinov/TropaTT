<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Recurring\RecurringRepository;
use Api\Model\Task\TaskRepository;
use Api\Model\Project\ProjectRepository;
use Api\Model\Reminder\ReminderRepository;
use Api\Model\Calendar\CalendarEventRepository;
use Api\System\Library\Support\Ulid;

final class RecurringProcessorService
{
    // Typed class constants require PHP 8.3; CRM supports PHP 8.1+ on shared hosting.
    private const DEFAULT_LIMIT = 50;

    public function __construct(
        private readonly RecurringRepository $recurring,
        private readonly TaskRepository $tasks,
        private readonly ProjectRepository $projects,
        private readonly ReminderRepository $reminders,
        private readonly CalendarEventRepository $events,
    ) {
    }

    public function process(int $limit = self::DEFAULT_LIMIT): array
    {
        $now = new \DateTimeImmutable('now');
        $rules = $this->recurring->list([
            'is_active' => 1,
            'limit' => $limit,
        ]);

        $items = $rules[0] ?? [];
        $results = [];
        $created = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($items as $rule) {
            try {
                $result = $this->processRule($rule, $now);
                $results[] = [
                    'rule_id' => $rule['public_id'] ?? '',
                    'entity_type' => $rule['entity_type'] ?? '',
                    'status' => $result['status'],
                    'entity_public_id' => $result['entity_public_id'] ?? null,
                    'error' => $result['error'] ?? null,
                ];
                if ($result['status'] === 'created') {
                    $created++;
                } elseif ($result['status'] === 'skipped') {
                    $skipped++;
                } else {
                    $errors++;
                }
            } catch (\Throwable $e) {
                $results[] = [
                    'rule_id' => $rule['public_id'] ?? '',
                    'entity_type' => $rule['entity_type'] ?? '',
                    'status' => 'error',
                    'entity_public_id' => null,
                    'error' => $e->getMessage(),
                ];
                $errors++;
            }
        }

        return [
            'total' => count($items),
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
            'results' => $results,
        ];
    }

    private function processRule(array $rule, \DateTimeImmutable $now): array
    {
        $rulePublicId = (string)($rule['public_id'] ?? '');
        $entityType = (string)($rule['entity_type'] ?? '');
        $entityPublicId = (string)($rule['entity_public_id'] ?? '');
        $rruleStr = (string)($rule['rrule'] ?? '');
        $lastProcessedAt = $this->parseDateTime($rule['last_processed_at'] ?? null);

        if ($rruleStr === '' || $entityPublicId === '') {
            return ['status' => 'error', 'error' => 'Invalid rule: missing rrule or entity_public_id'];
        }

        $parser = new RruleParser($rruleStr);
        $lastCheck = $lastProcessedAt ?? $now->sub(new \DateInterval('P1D'));

        if (!$parser->isDue($lastCheck, $now)) {
            return ['status' => 'skipped', 'error' => null];
        }

        $nextOccurrence = $parser->getNextDueDate($lastCheck);
        if ($nextOccurrence === null || $nextOccurrence > $now) {
            return ['status' => 'skipped', 'error' => null];
        }

        $newPublicId = match ($entityType) {
            'task' => $this->cloneTask($entityPublicId),
            'project' => $this->cloneProject($entityPublicId),
            'reminder' => $this->cloneReminder($entityPublicId, $nextOccurrence),
            'calendar_event' => $this->cloneCalendarEvent($entityPublicId, $nextOccurrence),
            default => null,
        };

        if ($newPublicId === null) {
            return ['status' => 'error', 'error' => "Template {$entityType} not found: {$entityPublicId}"];
        }

        $nowStr = $now->format('Y-m-d H:i:s');
        $this->recurring->updateByPublicId($rulePublicId, [
            'last_processed_at' => $nowStr,
        ]);

        return [
            'status' => 'created',
            'entity_public_id' => $newPublicId,
            'error' => null,
        ];
    }

    private function cloneTask(string $publicId): ?string
    {
        $template = $this->tasks->findByPublicId($publicId);
        if (!$template) {
            return null;
        }

        $newPublicId = Ulid::generate('tsk');
        $now = gmdate('Y-m-d H:i:s');

        $this->tasks->create([
            'public_id' => $newPublicId,
            'project_id' => (int)($template['project_id'] ?? 0) ?: null,
            'parent_task_public_id' => null,
            'title' => (string)($template['title'] ?? ''),
            'description' => (string)($template['description'] ?? ''),
            'status_code' => 'new',
            'priority_code' => (string)($template['priority_code'] ?? 'normal'),
            'due_at' => null,
            'start_at' => null,
            'end_at' => null,
            'assignee_user_id' => (int)($template['assignee_user_id'] ?? 0) ?: null,
            'creator_user_id' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'row_version' => 1,
        ]);

        return $newPublicId;
    }

    private function cloneProject(string $publicId): ?string
    {
        $template = $this->projects->findByPublicId($publicId);
        if (!$template) {
            return null;
        }

        $newPublicId = Ulid::generate('prj');
        $now = gmdate('Y-m-d H:i:s');

        $this->projects->create([
            'public_id' => $newPublicId,
            'title' => (string)($template['title'] ?? ''),
            'description' => (string)($template['description'] ?? ''),
            'status_code' => 'active',
            'priority_code' => (string)($template['priority_code'] ?? 'normal'),
            'client_public_id' => (string)($template['client_public_id'] ?? ''),
            'manager_user_id' => (int)($template['manager_user_id'] ?? 0) ?: null,
            'team_public_id' => (string)($template['team_public_id'] ?? ''),
            'created_by_user_id' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'row_version' => 1,
        ]);

        return $newPublicId;
    }

    private function cloneReminder(string $publicId, \DateTimeImmutable $occurrence): ?string
    {
        $template = $this->reminders->findByPublicId($publicId);
        if (!$template) {
            return null;
        }

        $newPublicId = Ulid::generate('rmn');
        $now = gmdate('Y-m-d H:i:s');

        $this->reminders->create([
            'public_id' => $newPublicId,
            'user_id' => (int)($template['user_id'] ?? 0),
            'task_id' => (int)($template['task_id'] ?? 0) ?: null,
            'remind_at' => $occurrence->format('Y-m-d H:i:s'),
            'status' => 'new',
            'created_at' => $now,
        ]);

        return $newPublicId;
    }

    private function cloneCalendarEvent(string $publicId, \DateTimeImmutable $occurrence): ?string
    {
        $template = $this->events->findByPublicId($publicId, 0, true);
        if (!$template) {
            return null;
        }

        $newPublicId = Ulid::generate('evt');
        $startsAt = $occurrence->format('Y-m-d H:i:s');
        $endsAt = $occurrence->add(new \DateInterval('PT1H'))->format('Y-m-d H:i:s');
        $now = gmdate('Y-m-d H:i:s');

        $this->events->create([
            'public_id' => $newPublicId,
            'title' => (string)($template['title'] ?? ''),
            'description' => (string)($template['description'] ?? ''),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'owner_user_id' => (int)($template['owner_user_id'] ?? 0) ?: 0,
            'project_id' => (int)($template['project_id'] ?? 0) ?: null,
            'task_id' => (int)($template['task_id'] ?? 0) ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $newPublicId;
    }

    private function parseDateTime(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }

        return new \DateTimeImmutable('@' . $ts);
    }
}
