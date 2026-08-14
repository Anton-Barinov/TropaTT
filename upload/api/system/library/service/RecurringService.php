<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Recurring\RecurringRepository;
use Api\System\Library\Language\LanguageManager;
use Api\System\Library\Language\TranslatableTrait;
use Api\System\Library\Support\Ulid;

final class RecurringService
{
    use TranslatableTrait;

    public function __construct(private readonly RecurringRepository $recurring, LanguageManager $lang)
    {
        $this->lang = $lang;
    }

    public function list(array $filters, int $actorId = 0): array
    {
        [$items, $total, $page, $limit] = $this->recurring->list($filters, $actorId);
        $items = array_map(fn(array $item): array => $this->normalizeRule($item, $actorId), $items);

        return [
            'items' => $items,
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
            ],
        ];
    }

    public function create(array $input, int $actorId = 0): array
    {
        if (!$this->canUseEntity((string)($input['entity_type'] ?? ''), (string)($input['entity_public_id'] ?? ''), $actorId)) {
            throw new \RuntimeException('RECURRING_ENTITY_FORBIDDEN');
        }
        $publicId = Ulid::generate('rrl');
        $now = gmdate('Y-m-d H:i:s');
        $this->recurring->create([
            'public_id' => $publicId,
            'title' => $this->normalizeTitle($input),
            'entity_type' => (string)$input['entity_type'],
            'entity_public_id' => trim((string)$input['entity_public_id']),
            'rrule' => trim((string)$input['rrule']),
            'is_active' => isset($input['is_active']) && (int)$input['is_active'] === 0 ? 0 : 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->get($publicId, $actorId) ?? ['public_id' => $publicId];
    }

    public function get(string $publicId, int $actorId = 0): ?array
    {
        $item = $this->recurring->findByPublicId($publicId, $actorId);
        if (!$item) {
            return null;
        }

        return $this->normalizeRule($item, $actorId);
    }

    public function update(string $publicId, array $input, int $actorId = 0): ?array
    {
        $existing = $this->recurring->findByPublicId($publicId, $actorId);
        if (!$existing) {
            return null;
        }

        $set = ['updated_at' => gmdate('Y-m-d H:i:s')];
        if (array_key_exists('entity_type', $input)) {
            $set['entity_type'] = (string)$input['entity_type'];
        }
        if (array_key_exists('title', $input)) {
            $set['title'] = $this->normalizeTitle($input);
        }
        if (array_key_exists('entity_public_id', $input)) {
            $entityType = (string)($input['entity_type'] ?? $existing['entity_type'] ?? '');
            $entityId = trim((string)$input['entity_public_id']);
            if (!$this->canUseEntity($entityType, $entityId, $actorId)) {
                throw new \RuntimeException('RECURRING_ENTITY_FORBIDDEN');
            }
            $set['entity_public_id'] = $entityId;
        }
        if (array_key_exists('entity_type', $input) && !array_key_exists('entity_public_id', $input)) {
            if (!$this->canUseEntity((string)$input['entity_type'], (string)($existing['entity_public_id'] ?? ''), $actorId)) {
                throw new \RuntimeException('RECURRING_ENTITY_FORBIDDEN');
            }
        }
        if (array_key_exists('rrule', $input)) {
            $set['rrule'] = trim((string)$input['rrule']);
        }
        if (array_key_exists('is_active', $input)) {
            $set['is_active'] = ((int)$input['is_active'] === 0) ? 0 : 1;
        }

        $this->recurring->updateByPublicId($publicId, $set, $actorId);
        return $this->get($publicId, $actorId);
    }

    public function pause(string $publicId, int $actorId = 0): ?array
    {
        if ($this->get($publicId, $actorId) === null) return null;
        $ok = $this->recurring->updateByPublicId($publicId, [
            'is_active' => 0,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], $actorId);
        if (!$ok) {
            return null;
        }

        return $this->get($publicId, $actorId);
    }

    public function resume(string $publicId, int $actorId = 0): ?array
    {
        if ($this->get($publicId, $actorId) === null) return null;
        $ok = $this->recurring->updateByPublicId($publicId, [
            'is_active' => 1,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], $actorId);
        if (!$ok) {
            return null;
        }

        return $this->get($publicId, $actorId);
    }

    public function delete(string $publicId, int $actorId = 0): bool
    {
        if ($this->get($publicId, $actorId) === null) return false;
        return $this->recurring->deleteByPublicId($publicId, $actorId);
    }

    public function isValidRrule(string $rrule): bool
    {
        $rrule = trim($rrule);
        if (!preg_match('/(^|;)FREQ=(DAILY|WEEKLY|MONTHLY|YEARLY)(;|$)/i', $rrule)) {
            return false;
        }

        try {
            $parser = new RruleParser($rrule);
            return in_array($parser->getFrequency(), ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true);
        } catch (\Throwable $e) {
            error_log('[RecurringService::isValidRrule] ' . $e->getMessage());
            return false;
        }
    }

    public function canUseEntity(string $entityType, string $entityPublicId, int $actorId = 0): bool
    {
        return $this->recurring->canUseEntity($entityType, $entityPublicId, $actorId);
    }

    private function normalizeRule(array $item, int $actorId = 0): array
    {
        $item['is_active'] = (int)($item['is_active'] ?? 1) === 1;
        $item['entity_title'] = $this->recurring->resolveEntityTitle(
            (string)($item['entity_type'] ?? ''),
            (string)($item['entity_public_id'] ?? '')
        );
        $item['title'] = trim((string)($item['title'] ?? ''));
        if ($item['title'] === '' || preg_match('/^' . preg_quote($this->t('recurring/messages.title_prefix'), '/') . ':\s*(task|project|reminder|calendar_event)\s+/i', $item['title'])) {
            $item['title'] = $this->normalizeTitle($item);
        }
        $item['next_run_at'] = $this->nextRunAt($item);

        return $item;
    }

    private function normalizeTitle(array $input): string
    {
        $title = trim((string)($input['title'] ?? ''));
        if ($title !== '') {
            return mb_substr($title, 0, 255);
        }

        $entityType = trim((string)($input['entity_type'] ?? $this->t('recurring/messages.entity_fallback')));
        $entityPublicId = trim((string)($input['entity_public_id'] ?? ''));
        $entityTitle = trim((string)($input['entity_title'] ?? ''));
        if ($entityTitle === '' && $entityPublicId !== '') {
            $entityTitle = (string)($this->recurring->resolveEntityTitle($entityType, $entityPublicId) ?? '');
        }
        if ($entityTitle !== '') {
            $entityLabel = $this->entityTypeLabel($entityType);
            if (mb_stripos($entityTitle, $entityLabel) === 0) {
                return mb_substr($entityTitle, 0, 255);
            }
            return mb_substr($entityLabel . ': ' . $entityTitle, 0, 255);
        }

        return mb_substr($this->t('recurring/messages.default_title'), 0, 255);
    }

    private function entityTypeLabel(string $entityType): string
    {
        return match (trim($entityType)) {
            'task' => $this->t('recurring/messages.entity_task'),
            'project' => $this->t('recurring/messages.entity_project'),
            'reminder' => $this->t('recurring/messages.entity_reminder'),
            'calendar_event' => $this->t('recurring/messages.entity_calendar_event'),
            default => $this->t('recurring/messages.entity_template'),
        };
    }

    private function nextRunAt(array $item): ?string
    {
        $rrule = trim((string)($item['rrule'] ?? ''));
        if ($rrule === '') {
            return null;
        }

        try {
            $from = $this->parseDate((string)($item['last_processed_at'] ?? ''))
                ?? $this->parseDate((string)($item['created_at'] ?? ''))
                ?? new \DateTimeImmutable('now');
            $next = (new RruleParser($rrule))->getNextDueDate($from);
            return $next ? $next->format('Y-m-d H:i:s') : null;
        } catch (\Throwable $e) {
            error_log('[RecurringService::nextRunAt] ' . $e->getMessage());
            return null;
        }
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? null : new \DateTimeImmutable('@' . $timestamp);
    }
}
