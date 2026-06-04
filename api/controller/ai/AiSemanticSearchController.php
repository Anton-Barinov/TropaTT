<?php
declare(strict_types=1);

namespace Api\Controller\Ai;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\AiSemanticIndexService;
use Api\System\Library\Service\FeatureFlagService;

final class AiSemanticSearchController extends BaseController
{
    public function search(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var FeatureFlagService $flags */
        $flags = $this->container->get('service.feature_flag');
        if (!$flags->isEnabled('ai.enabled') || !$flags->isEnabled('ai.search')) {
            return $this->error('AI_FEATURE_DISABLED', $this->t('ai/messages.action_failed'), 409, [
                'ai' => ['AI_FEATURE_DISABLED'],
            ]);
        }

        $input = $this->request()->allInput();
        $query = trim((string)($input['query'] ?? $input['q'] ?? ''));
        if ($query === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'query' => [$this->t('common/messages.field_required')],
            ]);
        }

        $limit = max(1, min(50, (int)($input['limit'] ?? 10)));
        $includeArchived = $this->toBool($input['include_archived'] ?? false);

        /** @var AiSemanticIndexService $semanticIndex */
        $semanticIndex = $this->container->get('service.ai_semantic_index');
        $result = $semanticIndex->search($query, $limit * 3);
        $items = [];
        foreach ((array)($result['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $presented = $this->presentResult($item, (array)$auth['user'], $includeArchived);
            if ($presented === null) {
                continue;
            }
            $items[] = $presented;
            if (count($items) >= $limit) {
                break;
            }
        }

        return $this->success('AI_SEMANTIC_SEARCH_RESULTS', $this->t('ai/messages.action_result'), [
            'items' => $items,
            'query' => $query,
        ]);
    }

    /** @param array<string,mixed> $item @param array<string,mixed> $actor */
    private function presentResult(array $item, array $actor, bool $includeArchived): ?array
    {
        $meta = is_array($item['meta'] ?? null) ? (array)$item['meta'] : [];
        $entityType = $this->normalizeEntityType((string)($meta['entity_type'] ?? ''));
        $entityPublicId = trim((string)($meta['entity_public_id'] ?? ''));
        if ($entityType === '' || $entityPublicId === '' || !$this->canAccessEntity($entityType, $entityPublicId, $actor, $includeArchived)) {
            return null;
        }

        return [
            'entity_type' => $entityType,
            'entity_public_id' => $entityPublicId,
            'score' => (float)($item['score'] ?? 0.0),
            'snippet' => (string)($item['snippet'] ?? ''),
        ];
    }

    /** @param array<string,mixed> $actor */
    private function canAccessEntity(string $entityType, string $entityPublicId, array $actor, bool $includeArchived): bool
    {
        if ($entityType === 'task') {
            $task = $this->container->get('service.task')->get($entityPublicId, $actor);
            return is_array($task) && ($includeArchived || trim((string)($task['archived_at'] ?? '')) === '');
        }
        if ($entityType === 'project') {
            $project = $this->container->get('service.project')->get($entityPublicId, $actor);
            return is_array($project) && ($includeArchived || trim((string)($project['archived_at'] ?? '')) === '');
        }

        return match ($entityType) {
            'client' => $this->container->get('service.client')->get($entityPublicId, $actor) !== null,
            'company' => $this->container->get('service.company')->get($entityPublicId, $actor) !== null,
            'contact' => $this->container->get('service.contact')->get($entityPublicId, $actor) !== null,
            'comment' => $this->container->get('service.entity_access')->canAccess('comment', $entityPublicId, $actor),
            'file' => (bool)($this->container->get('service.file')->canDownloadInternal($entityPublicId, $actor)['ok'] ?? false),
            default => false,
        };
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function normalizeEntityType(string $entityType): string
    {
        $normalized = strtolower(trim($entityType));
        return match ($normalized) {
            'tasks' => 'task',
            'projects' => 'project',
            'clients' => 'client',
            'companies' => 'company',
            'contacts' => 'contact',
            'comments' => 'comment',
            'files' => 'file',
            default => $normalized,
        };
    }
}
