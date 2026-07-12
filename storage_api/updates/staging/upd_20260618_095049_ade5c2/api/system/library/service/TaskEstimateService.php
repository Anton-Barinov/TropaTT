<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Estimate\EstimateOptionRepository;
use Api\Model\Estimate\EstimateSetRepository;
use Api\Model\Estimate\TaskEstimateRepository;

use Api\System\Library\Support\Ulid;

final class TaskEstimateService
{
    private const VALID_ESTIMATE_TYPES = [
        'story_points', 'tshirt', 'hours', 'cost', 'complexity', 'risk', 'custom',
    ];

    private const VALID_SCOPE_TYPES = ['global', 'project'];

    private const RESERVED_PREFIX_CODES = ['task', 'sys', 'api'];

    private const ERROR_CODES = [
        'SET_NOT_FOUND' => 'ESTIMATE_SET_NOT_FOUND',
        'SET_FORBIDDEN' => 'ESTIMATE_SET_FORBIDDEN',
        'SET_NAME_REQUIRED' => 'ESTIMATE_SET_NAME_REQUIRED',
        'SET_NAME_TOO_LONG' => 'ESTIMATE_SET_NAME_TOO_LONG',
        'SET_INVALID_CODE' => 'ESTIMATE_SET_INVALID_CODE',
        'SET_CODE_ALREADY_EXISTS' => 'ESTIMATE_SET_CODE_ALREADY_EXISTS',
        'SET_INVALID_TYPE' => 'ESTIMATE_SET_INVALID_TYPE',
        'SET_INVALID_SCOPE' => 'ESTIMATE_SET_INVALID_SCOPE',
        'SET_PROJECT_REQUIRED' => 'ESTIMATE_SET_PROJECT_REQUIRED',
        'SET_PROJECT_NOT_FOUND' => 'ESTIMATE_SET_PROJECT_NOT_FOUND',
        'SET_GLOBAL_FORBIDDEN' => 'ESTIMATE_SET_GLOBAL_FORBIDDEN',
        'SET_CURRENCY_REQUIRED' => 'ESTIMATE_SET_CURRENCY_REQUIRED',
        'SET_INVALID_CURRENCY' => 'ESTIMATE_SET_INVALID_CURRENCY',
        'SET_LOCKED' => 'ESTIMATE_SET_LOCKED',
        'OPTION_NOT_FOUND' => 'ESTIMATE_OPTION_NOT_FOUND',
        'OPTION_LABEL_REQUIRED' => 'ESTIMATE_OPTION_LABEL_REQUIRED',
        'OPTION_LABEL_TOO_LONG' => 'ESTIMATE_OPTION_LABEL_TOO_LONG',
        'OPTION_INVALID_CODE' => 'ESTIMATE_OPTION_INVALID_CODE',
        'OPTION_CODE_ALREADY_EXISTS' => 'ESTIMATE_OPTION_CODE_ALREADY_EXISTS',
        'OPTION_NUMERIC_REQUIRED' => 'ESTIMATE_OPTION_NUMERIC_REQUIRED',
        'OPTION_INVALID_NUMERIC' => 'ESTIMATE_OPTION_INVALID_NUMERIC_VALUE',
        'OPTION_INVALID_COLOR' => 'ESTIMATE_OPTION_INVALID_COLOR',
        'TASK_NOT_FOUND' => 'TASK_ESTIMATE_TASK_NOT_FOUND',
        'TASK_FORBIDDEN' => 'TASK_ESTIMATE_FORBIDDEN',
        'TASK_SET_REQUIRED' => 'TASK_ESTIMATE_SET_REQUIRED',
        'TASK_OPTION_REQUIRED' => 'TASK_ESTIMATE_OPTION_REQUIRED',
        'TASK_OPTION_NOT_IN_SET' => 'TASK_ESTIMATE_OPTION_NOT_IN_SET',
        'TASK_NUMERIC_REQUIRED' => 'TASK_ESTIMATE_NUMERIC_REQUIRED',
        'TASK_INVALID_NUMERIC' => 'TASK_ESTIMATE_INVALID_NUMERIC_VALUE',
        'TASK_SET_NOT_AVAILABLE' => 'TASK_ESTIMATE_SET_NOT_AVAILABLE_FOR_TASK',
        'TASK_ESTIMATE_ALREADY_EXISTS' => 'TASK_ESTIMATE_ALREADY_EXISTS',
        'ROW_VERSION_CONFLICT' => 'ROW_VERSION_CONFLICT',
        'INVALID_SORT' => 'TASK_ESTIMATE_SET_REQUIRED_FOR_SORT',
    ];

    private const VALID_COLORS = ['gray', 'blue', 'green', 'yellow', 'orange', 'red', 'purple', 'pink'];

    public function __construct(
        private readonly EstimateSetRepository $estimateSetRepository,
        private readonly EstimateOptionRepository $estimateOptionRepository,
        private readonly TaskEstimateRepository $taskEstimateRepository,
        private readonly TaskService $taskService,
        private readonly \PDO $db,
    ) {
    }

    // ========== Estimate Sets ==========

    public function listSets(array $filters, array $actor): array
    {
        $isRoot = (bool)($actor['is_root'] ?? false);
        $actorId = (int)($actor['id'] ?? 0);
        $result = $this->estimateSetRepository->list($filters, $actorId, $isRoot);

        return [
            'items' => $result['items'],
            'meta' => [
                'pagination' => [
                    'page' => $result['page'],
                    'limit' => $result['limit'],
                    'total' => $result['total'],
                    'pages' => (int)ceil($result['total'] / max(1, $result['limit'])),
                ],
            ],
        ];
    }

    public function createSet(array $input, array $actor): array|string
    {
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') {
            return self::ERROR_CODES['SET_NAME_REQUIRED'];
        }
        if (mb_strlen($name) > 255) {
            return self::ERROR_CODES['SET_NAME_TOO_LONG'];
        }

        $code = !empty($input['code']) ? trim((string)$input['code']) : $this->slugify($name);
        $code = strtolower($code);
        if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/', $code)) {
            return self::ERROR_CODES['SET_INVALID_CODE'];
        }

        $scopeType = (string)($input['scope_type'] ?? 'project');
        if (!in_array($scopeType, self::VALID_SCOPE_TYPES, true)) {
            return self::ERROR_CODES['SET_INVALID_SCOPE'];
        }

        $estimateType = (string)($input['estimate_type'] ?? 'custom');
        if (!in_array($estimateType, self::VALID_ESTIMATE_TYPES, true)) {
            return self::ERROR_CODES['SET_INVALID_TYPE'];
        }

        $projectId = null;
        $projectPublicId = null;

        if ($scopeType === 'project') {
            if (empty($input['project_public_id'])) {
                return self::ERROR_CODES['SET_PROJECT_REQUIRED'];
            }
            $projectId = $this->estimateSetRepository->projectIdByPublicId((string)$input['project_public_id']);
            if ($projectId === null) {
                return self::ERROR_CODES['SET_PROJECT_NOT_FOUND'];
            }
            $projectPublicId = (string)$input['project_public_id'];
        } elseif ($scopeType === 'global') {
            if (!(bool)($actor['is_root'] ?? false)) {
                return self::ERROR_CODES['SET_GLOBAL_FORBIDDEN'];
            }
        }

        if ($estimateType === 'cost') {
            if (empty($input['currency_code'])) {
                return self::ERROR_CODES['SET_CURRENCY_REQUIRED'];
            }
            $currency = strtoupper(trim((string)$input['currency_code']));
            if (!preg_match('/^[A-Z]{3}$/', $currency)) {
                return self::ERROR_CODES['SET_INVALID_CURRENCY'];
            }
        }

        $activeKey = $scopeType === 'global'
            ? "global:{$code}"
            : "project:{$projectId}:{$code}";

        if ($this->estimateSetRepository->existsActiveKey($activeKey)) {
            return self::ERROR_CODES['SET_CODE_ALREADY_EXISTS'];
        }

        $publicId = Ulid::generate('est');
        $now = gmdate('Y-m-d H:i:s');

        $set = $this->estimateSetRepository->create([
            'public_id' => $publicId,
            'scope_type' => $scopeType,
            'project_id' => $projectId,
            'name' => $name,
            'code' => $code,
            'estimate_type' => $estimateType,
            'unit_label' => !empty($input['unit_label']) ? (string)$input['unit_label'] : null,
            'currency_code' => $input['currency_code'] ?? null,
            'description' => !empty($input['description']) ? (string)$input['description'] : null,
            'is_default' => (int)($input['is_default'] ?? 0),
            'is_active' => 1,
            'is_locked' => 0,
            'active_key' => $activeKey,
            'sort_order' => (int)($input['sort_order'] ?? 65535),
            'created_by_user_id' => (int)($actor['id'] ?? 0),
        ]);

        // If nested options provided, create them
        if (!empty($input['options']) && is_array($input['options'])) {
            $setId = (int)($set['id'] ?? 0);
            foreach ($input['options'] as $opt) {
                $this->createOptionInternal($setId, $opt, $actor);
            }
            $set = $this->estimateSetRepository->findByPublicId($publicId);
        }

        return $set;
    }

    public function getSet(string $setPublicId, array $actor): array|string|null
    {
        $set = $this->estimateSetRepository->findByPublicId($setPublicId);
        if (!$set || ($set['deleted_at'] ?? null) !== null) {
            return null;
        }
        return $set;
    }

    public function updateSet(string $setPublicId, array $input, array $actor): array|string|null
    {
        $set = $this->estimateSetRepository->findByPublicId($setPublicId);
        if (!$set || ($set['deleted_at'] ?? null) !== null) {
            return null;
        }

        if ((int)($set['is_locked'] ?? 0) === 1 && !(bool)($actor['is_root'] ?? false)) {
            return self::ERROR_CODES['SET_LOCKED'];
        }

        if (array_key_exists('row_version', $input)) {
            $expected = (int)$input['row_version'];
            $current = (int)($set['row_version'] ?? 0);
            if ($expected > 0 && $expected !== $current) {
                return 'ROW_VERSION_CONFLICT';
            }
        }

        $setUpdate = [];

        if (array_key_exists('name', $input)) {
            $name = trim((string)$input['name']);
            if ($name === '') {
                return self::ERROR_CODES['SET_NAME_REQUIRED'];
            }
            $setUpdate['name'] = mb_substr($name, 0, 255);
        }

        if (array_key_exists('description', $input)) {
            $setUpdate['description'] = !empty($input['description']) ? (string)$input['description'] : null;
        }

        if (array_key_exists('unit_label', $input)) {
            $setUpdate['unit_label'] = !empty($input['unit_label']) ? (string)$input['unit_label'] : null;
        }

        if (array_key_exists('is_default', $input)) {
            $setUpdate['is_default'] = (int)(bool)$input['is_default'];
        }

        if (array_key_exists('is_active', $input)) {
            $setUpdate['is_active'] = (int)(bool)$input['is_active'];
        }

        if (array_key_exists('sort_order', $input)) {
            $setUpdate['sort_order'] = (int)$input['sort_order'];
        }

        if ($setUpdate === []) {
            return $set;
        }

        $setUpdate['row_version'] = (int)($set['row_version'] ?? 0) + 1;
        $this->estimateSetRepository->updateByPublicId($setPublicId, $setUpdate);

        return $this->estimateSetRepository->findByPublicId($setPublicId);
    }

    public function archiveSet(string $setPublicId, array $actor): bool|string
    {
        $set = $this->estimateSetRepository->findByPublicId($setPublicId);
        if (!$set || ($set['deleted_at'] ?? null) !== null) {
            return false;
        }
        $now = gmdate('Y-m-d H:i:s');
        return $this->estimateSetRepository->archiveByPublicId($setPublicId, $now);
    }

    public function deleteSet(string $setPublicId, array $actor): bool|string
    {
        $set = $this->estimateSetRepository->findByPublicId($setPublicId);
        if (!$set || ($set['deleted_at'] ?? null) !== null) {
            return false;
        }
        $now = gmdate('Y-m-d H:i:s');
        return $this->estimateSetRepository->softDeleteByPublicId($setPublicId, $now);
    }

    // ========== Estimate Options ==========

    public function listOptions(string $setPublicId, array $filters, array $actor): array|string|null
    {
        $set = $this->estimateSetRepository->findByPublicId($setPublicId);
        if (!$set || ($set['deleted_at'] ?? null) !== null) {
            return null;
        }
        return $this->estimateOptionRepository->listBySetId((int)$set['id'], $filters);
    }

    public function createOption(string $setPublicId, array $input, array $actor): array|string|null
    {
        $set = $this->estimateSetRepository->findByPublicId($setPublicId);
        if (!$set || ($set['deleted_at'] ?? null) !== null) {
            return null;
        }
        return $this->createOptionInternal((int)$set['id'], $input, $actor);
    }

    private function createOptionInternal(int $setId, array $input, array $actor): array|string
    {
        $label = trim((string)($input['label'] ?? ''));
        if ($label === '') {
            return self::ERROR_CODES['OPTION_LABEL_REQUIRED'];
        }
        if (mb_strlen($label) > 255) {
            return self::ERROR_CODES['OPTION_LABEL_TOO_LONG'];
        }

        $code = !empty($input['code']) ? trim((string)$input['code']) : $this->slugify($label);
        if (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $code)) {
            return self::ERROR_CODES['OPTION_INVALID_CODE'];
        }

        $activeKey = "set:{$setId}:{$code}";
        if ($this->estimateOptionRepository->existsActiveKey($activeKey)) {
            return self::ERROR_CODES['OPTION_CODE_ALREADY_EXISTS'];
        }

        if (isset($input['numeric_value'])) {
            $numericValue = (float)$input['numeric_value'];
            if ($numericValue < 0 || $numericValue > 999999999.99) {
                return self::ERROR_CODES['OPTION_INVALID_NUMERIC'];
            }
        }

        $color = !empty($input['color']) ? (string)$input['color'] : null;
        if ($color !== null && !$this->isValidColor($color)) {
            return self::ERROR_CODES['OPTION_INVALID_COLOR'];
        }

        $publicId = Ulid::generate('eopt');

        return $this->estimateOptionRepository->create([
            'public_id' => $publicId,
            'estimate_set_id' => $setId,
            'label' => $label,
            'code' => $code,
            'numeric_value' => isset($input['numeric_value']) ? (float)$input['numeric_value'] : null,
            'color' => $color,
            'description' => !empty($input['description']) ? (string)$input['description'] : null,
            'is_default' => (int)($input['is_default'] ?? 0),
            'is_active' => 1,
            'active_key' => $activeKey,
            'sort_order' => (int)($input['sort_order'] ?? 65535),
            'created_by_user_id' => (int)($actor['id'] ?? 0),
        ]);
    }

    public function updateOption(string $optionPublicId, array $input, array $actor): array|string|null
    {
        $option = $this->estimateOptionRepository->findByPublicId($optionPublicId);
        if (!$option) {
            return null;
        }

        if (array_key_exists('row_version', $input)) {
            $expected = (int)$input['row_version'];
            $current = (int)($option['row_version'] ?? 0);
            if ($expected > 0 && $expected !== $current) {
                return 'ROW_VERSION_CONFLICT';
            }
        }

        $update = [];

        if (array_key_exists('label', $input)) {
            $label = trim((string)$input['label']);
            if ($label === '') {
                return self::ERROR_CODES['OPTION_LABEL_REQUIRED'];
            }
            $update['label'] = mb_substr($label, 0, 255);
        }

        if (array_key_exists('numeric_value', $input)) {
            if ($input['numeric_value'] === null || $input['numeric_value'] === '') {
                $update['numeric_value'] = null;
            } else {
                $nv = (float)$input['numeric_value'];
                if ($nv < 0 || $nv > 999999999.99) {
                    return self::ERROR_CODES['OPTION_INVALID_NUMERIC'];
                }
                $update['numeric_value'] = $nv;
            }
        }

        if (array_key_exists('color', $input)) {
            $color = !empty($input['color']) ? (string)$input['color'] : null;
            if ($color !== null && !$this->isValidColor($color)) {
                return self::ERROR_CODES['OPTION_INVALID_COLOR'];
            }
            $update['color'] = $color;
        }

        if (array_key_exists('description', $input)) {
            $update['description'] = !empty($input['description']) ? (string)$input['description'] : null;
        }

        if (array_key_exists('is_default', $input)) {
            $update['is_default'] = (int)(bool)$input['is_default'];
        }

        if (array_key_exists('is_active', $input)) {
            $update['is_active'] = (int)(bool)$input['is_active'];
        }

        if (array_key_exists('sort_order', $input)) {
            $update['sort_order'] = (int)$input['sort_order'];
        }

        if ($update === []) {
            return $option;
        }

        $update['row_version'] = (int)($option['row_version'] ?? 0) + 1;
        $this->estimateOptionRepository->updateByPublicId($optionPublicId, $update);

        return $this->estimateOptionRepository->findByPublicId($optionPublicId);
    }

    public function archiveOption(string $optionPublicId, array $actor): bool|string
    {
        $option = $this->estimateOptionRepository->findByPublicId($optionPublicId);
        if (!$option) {
            return false;
        }
        $now = gmdate('Y-m-d H:i:s');
        return $this->estimateOptionRepository->archiveByPublicId($optionPublicId, $now);
    }

    public function deleteOption(string $optionPublicId, array $actor): bool|string
    {
        $option = $this->estimateOptionRepository->findByPublicId($optionPublicId);
        if (!$option) {
            return false;
        }
        $now = gmdate('Y-m-d H:i:s');
        return $this->estimateOptionRepository->softDeleteByPublicId($optionPublicId, $now);
    }

    // ========== Task Estimates ==========

    public function listTaskEstimates(string $taskPublicId, array $actor): array|string|null
    {
        $task = $this->taskService->get($taskPublicId, $actor);
        if (!$task) {
            return null;
        }
        $taskId = (int)($task['id'] ?? 0);
        return $this->taskEstimateRepository->listByTaskId($taskId);
    }

    public function assignTaskEstimate(string $taskPublicId, array $input, array $actor): array|string|null
    {
        $task = $this->taskService->get($taskPublicId, $actor);
        if (!$task) {
            return null;
        }

        if (empty($input['estimate_set_public_id'])) {
            return self::ERROR_CODES['TASK_SET_REQUIRED'];
        }

        $set = $this->estimateSetRepository->findByPublicId((string)$input['estimate_set_public_id']);
        if (!$set || ($set['deleted_at'] ?? null) !== null) {
            return self::ERROR_CODES['SET_NOT_FOUND'];
        }

        if (!(int)($set['is_active'] ?? 0)) {
            return self::ERROR_CODES['SET_NOT_FOUND'];
        }

        $taskId = (int)($task['id'] ?? 0);

        $optionId = null;
        $numericValue = null;
        $textValue = null;
        $currencyCode = null;

        if (!empty($input['estimate_option_public_id'])) {
            $option = $this->estimateOptionRepository->findByPublicId((string)$input['estimate_option_public_id']);
            if (!$option) {
                return self::ERROR_CODES['OPTION_NOT_FOUND'];
            }
            if ((int)$option['estimate_set_id'] !== (int)$set['id']) {
                return self::ERROR_CODES['TASK_OPTION_NOT_IN_SET'];
            }
            $optionId = (int)$option['id'];
            $numericValue = $option['numeric_value'] !== null ? (float)$option['numeric_value'] : null;
            $textValue = (string)$option['label'];
        } elseif (isset($input['numeric_value'])) {
            $nv = (float)$input['numeric_value'];
            if ($nv < 0 || $nv > 999999999.99) {
                return self::ERROR_CODES['TASK_INVALID_NUMERIC'];
            }
            $numericValue = $nv;
            $textValue = (string)$nv;
        }

        $currencyCode = !empty($input['currency_code']) ? strtoupper(trim((string)$input['currency_code'])) : ($set['currency_code'] ?? null);

        $publicId = Ulid::generate('tes');

        $result = $this->taskEstimateRepository->upsertTaskEstimate([
            'public_id' => $publicId,
            'task_id' => $taskId,
            'task_public_id' => $taskPublicId,
            'estimate_set_id' => (int)$set['id'],
            'estimate_option_id' => $optionId,
            'numeric_value' => $numericValue,
            'text_value' => $textValue,
            'currency_code' => $currencyCode,
            'note' => !empty($input['note']) ? mb_substr((string)$input['note'], 0, 1000) : null,
            'assigned_by_user_id' => (int)($actor['id'] ?? 0),
        ]);

        return $result;
    }

    public function removeTaskEstimate(string $taskPublicId, string $estimateSetPublicId, array $actor): bool|string
    {
        $task = $this->taskService->get($taskPublicId, $actor);
        if (!$task) {
            return false;
        }

        $set = $this->estimateSetRepository->findByPublicId($estimateSetPublicId);
        if (!$set) {
            return false;
        }

        $taskId = (int)($task['id'] ?? 0);
        $setId = (int)$set['id'];

        $now = gmdate('Y-m-d H:i:s');
        return $this->taskEstimateRepository->removeByTaskAndSet($taskId, $setId, (int)($actor['id'] ?? 0), $now);
    }

    // ========== Summary ==========

    public function summaryByProject(string $projectPublicId, array $filters, array $actor): array|string|null
    {
        $projectId = $this->estimateSetRepository->projectIdByPublicId($projectPublicId);
        if ($projectId === null) {
            return null;
        }

        $sets = $this->taskEstimateRepository->summaryByProjectId($projectId, $filters);

        return [
            'project_public_id' => $projectPublicId,
            'sets' => $sets,
        ];
    }

    public function summaryByCycle(string $cyclePublicId, array $filters, array $actor): array|string|null
    {
        // Find cycle by public_id using PDO directly
        try {
            $stmt = $this->db->prepare("SELECT id FROM work_cycles WHERE public_id = :public_id AND deleted_at IS NULL");
            $stmt->execute(['public_id' => $cyclePublicId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }
            $cycleId = (int)$row['id'];
            $sets = $this->taskEstimateRepository->summaryByCycleId($cycleId, $filters);
            return ['cycle_public_id' => $cyclePublicId, 'sets' => $sets];
        } catch (\Throwable) {
            return ['cycle_public_id' => $cyclePublicId, 'sets' => []];
        }
    }

    public function summaryByModule(string $modulePublicId, array $filters, array $actor): array|string|null
    {
        try {
            $stmt = $this->db->prepare("SELECT id FROM project_modules WHERE public_id = :public_id AND deleted_at IS NULL");
            $stmt->execute(['public_id' => $modulePublicId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }
            $moduleId = (int)$row['id'];
            $sets = $this->taskEstimateRepository->summaryByModuleId($moduleId, $filters);
            return ['module_public_id' => $modulePublicId, 'sets' => $sets];
        } catch (\Throwable) {
            return ['module_public_id' => $modulePublicId, 'sets' => []];
        }
    }

    // ========== Helpers ==========

    private function slugify(string $value): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9_]+/', '_', $value);
        $slug = trim($slug, '_');
        if ($slug === '') {
            $slug = 'custom';
        }
        $slug = strtolower($slug);
        if (!preg_match('/^[a-z]/', $slug)) {
            $slug = 'c_' . $slug;
        }
        return mb_substr($slug, 0, 64);
    }

    private function isValidColor(string $color): bool
    {
        if (in_array($color, self::VALID_COLORS, true)) {
            return true;
        }
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return true;
        }
        return false;
    }


}
