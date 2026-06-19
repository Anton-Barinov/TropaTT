<?php
declare(strict_types=1);

namespace Api\Controller\Mcp;

use Api\Controller\Common\BaseController;
use Api\Model\Knowledge\KnowledgeRepository;
use Api\System\Library\Http\RawJsonResponse;
use Api\System\Library\Service\AuthzService;
use Api\System\Library\Service\ProjectService;
use Api\System\Library\Service\SearchService;
use Api\System\Library\Service\TaskService;
use Throwable;

final class McpController extends BaseController
{
    private const PROTOCOL_VERSION = '2025-06-18';

    public function handle(): RawJsonResponse
    {
        $originError = $this->validateOrigin();
        if ($originError !== null) {
            return $this->response($this->errorPayload(null, -32003, $originError), 403);
        }

        $raw = trim($this->request()->rawBody);
        if ($raw === '') {
            return $this->response($this->errorPayload(null, -32600, 'Empty JSON-RPC request'), 400);
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return $this->response($this->errorPayload(null, -32700, 'Parse error'), 400);
        }

        $isBatch = array_is_list($payload);
        $messages = $isBatch ? $payload : [$payload];
        $responses = [];

        foreach ($messages as $message) {
            if (!is_array($message)) {
                $responses[] = $this->errorPayload(null, -32600, 'Invalid Request');
                continue;
            }

            $result = $this->handleMessage($message);
            if ($result !== null) {
                $responses[] = $result;
            }
        }

        if ($responses === []) {
            return $this->response(null, 202);
        }

        return $this->response($isBatch ? $responses : $responses[0]);
    }

    private function handleMessage(array $message): ?array
    {
        $id = $message['id'] ?? null;
        $method = is_string($message['method'] ?? null) ? (string)$message['method'] : '';
        $params = is_array($message['params'] ?? null) ? (array)$message['params'] : [];
        $isNotification = !array_key_exists('id', $message);

        if (($message['jsonrpc'] ?? '') !== '2.0' || $method === '') {
            return $this->errorPayload($id, -32600, 'Invalid Request');
        }

        try {
            $result = match ($method) {
                'initialize' => $this->initializeResult($params),
                'ping' => new \stdClass(),
                'tools/list' => ['tools' => $this->tools()],
                'tools/call' => $this->callTool($params),
                'notifications/initialized' => null,
                default => $this->methodNotFound($method),
            };
        } catch (Throwable $e) {
            return $this->errorPayload($id, -32603, $e->getMessage());
        }

        if ($isNotification || $result === null) {
            return null;
        }

        if (is_array($result) && isset($result['jsonrpc_error'])) {
            return $this->errorPayload($id, (int)$result['code'], (string)$result['message'], (array)($result['data'] ?? []));
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    private function initializeResult(array $params): array
    {
        $clientVersion = (string)($params['protocolVersion'] ?? $this->request()->header('MCP-Protocol-Version', self::PROTOCOL_VERSION));
        $protocolVersion = $clientVersion !== '' ? $clientVersion : self::PROTOCOL_VERSION;

        return [
            'protocolVersion' => $protocolVersion,
            'capabilities' => [
                'tools' => [
                    'listChanged' => false,
                ],
            ],
            'serverInfo' => [
                'name' => 'TropaTT CRM',
                'version' => '0.1.0',
            ],
        ];
    }

    private function tools(): array
    {
        $tools = [];

        if ($this->canAny(['task.manage', 'project.manage', 'knowledge.view'])) {
            $tools[] = $this->tool('crm_search', 'Search tasks, projects, counterparties, contacts and published knowledge pages visible to the current CRM user.', [
                'q' => ['type' => 'string', 'description' => 'Search query, at least 2 characters.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10],
            ], ['q']);
        }

        if ($this->can('task.manage')) {
            $tools[] = $this->tool('crm_list_tasks', 'List CRM tasks with optional filters.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'project_public_id' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'priority' => ['type' => 'string', 'enum' => ['low', 'normal', 'high', 'urgent']],
                'assigned_user_id' => ['type' => 'integer'],
                'updated_since' => ['type' => 'string', 'description' => 'ISO or SQL date-time.'],
            ]);
            $tools[] = $this->tool('crm_get_task', 'Get one CRM task by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_create_task', 'Create a CRM task. Uses current authenticated user as creator.', [
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'project_public_id' => ['type' => 'string'],
                'parent_task_public_id' => ['type' => 'string'],
                'priority' => ['type' => 'string', 'enum' => ['low', 'normal', 'high', 'urgent'], 'default' => 'normal'],
                'status' => ['type' => 'string', 'default' => 'new'],
                'due_at' => ['type' => 'string'],
                'start_at' => ['type' => 'string'],
                'end_at' => ['type' => 'string'],
                'assignee_user_id' => ['type' => 'integer'],
            ], ['title']);
            $tools[] = $this->tool('crm_update_task', 'Update an existing CRM task by public id.', [
                'public_id' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'priority' => ['type' => 'string', 'enum' => ['low', 'normal', 'high', 'urgent']],
                'status' => ['type' => 'string'],
                'due_at' => ['type' => 'string'],
                'start_at' => ['type' => 'string'],
                'end_at' => ['type' => 'string'],
                'assignee_user_id' => ['type' => 'integer'],
                'row_version' => ['type' => 'integer'],
            ], ['public_id']);
        }

        if ($this->can('project.manage')) {
            $tools[] = $this->tool('crm_list_projects', 'List CRM projects visible to the current CRM user.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'status' => ['type' => 'string'],
                'updated_since' => ['type' => 'string'],
            ]);
            $tools[] = $this->tool('crm_get_project', 'Get one CRM project by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
        }

        if ($this->can('knowledge.view')) {
            $tools[] = $this->tool('crm_list_knowledge_pages', 'List knowledge base pages visible to the current CRM user.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'space_public_id' => ['type' => 'string'],
                'status' => ['type' => 'string', 'default' => 'published'],
                'sort' => ['type' => 'string', 'enum' => ['title', 'created_at', 'updated_at', 'published_at', 'views_count']],
                'order' => ['type' => 'string', 'enum' => ['ASC', 'DESC']],
            ]);
            $tools[] = $this->tool('crm_get_knowledge_page', 'Get one knowledge base page by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
        }

        if ($this->can('knowledge.create')) {
            $tools[] = $this->tool('crm_create_knowledge_page', 'Create a knowledge base page in a space where the user has edit access.', [
                'title' => ['type' => 'string'],
                'content_html' => ['type' => 'string'],
                'space_public_id' => ['type' => 'string'],
                'parent_public_id' => ['type' => 'string'],
                'page_type' => ['type' => 'string', 'default' => 'article'],
                'status' => ['type' => 'string', 'enum' => ['draft', 'review', 'published'], 'default' => 'draft'],
            ], ['title']);
        }

        return $tools;
    }

    private function callTool(array $params): array
    {
        $name = is_string($params['name'] ?? null) ? (string)$params['name'] : '';
        $arguments = is_array($params['arguments'] ?? null) ? (array)$params['arguments'] : [];

        if ($name === '') {
            return $this->toolError('Tool name is required');
        }

        return match ($name) {
            'crm_search' => $this->withPermissionAny(['task.manage', 'project.manage', 'knowledge.view'], fn() => $this->toolResult($this->crmSearch($arguments))),
            'crm_list_tasks' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListTasks($arguments))),
            'crm_get_task' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetTask($arguments))),
            'crm_create_task' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmCreateTask($arguments))),
            'crm_update_task' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmUpdateTask($arguments))),
            'crm_list_projects' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmListProjects($arguments))),
            'crm_get_project' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmGetProject($arguments))),
            'crm_list_knowledge_pages' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmListKnowledgePages($arguments))),
            'crm_get_knowledge_page' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmGetKnowledgePage($arguments))),
            'crm_create_knowledge_page' => $this->withPermission('knowledge.create', fn() => $this->toolResult($this->crmCreateKnowledgePage($arguments))),
            default => $this->toolError('Unknown tool: ' . $name),
        };
    }

    private function crmSearch(array $arguments): array
    {
        $q = trim((string)($arguments['q'] ?? ''));
        if (mb_strlen($q) < 2) {
            return ['error' => 'Query must contain at least 2 characters.'];
        }

        /** @var SearchService $service */
        $service = $this->container->get('service.search');
        return $this->compactGlobalSearch($this->publicData($service->global($q, $this->actor(), $this->limit($arguments, 10, 50))));
    }

    private function crmListTasks(array $arguments): array
    {
        /** @var TaskService $service */
        $service = $this->container->get('service.task');
        return $this->publicData($service->list($this->filters($arguments, 20, 50), $this->actor()));
    }

    private function crmGetTask(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var TaskService $service */
        $service = $this->container->get('service.task');
        $task = $service->get($publicId, $this->actor());
        return $task ? ['task' => $this->publicData($task)] : ['error' => 'Task not found.'];
    }

    private function crmCreateTask(array $arguments): array
    {
        $title = trim((string)($arguments['title'] ?? ''));
        if ($title === '') {
            return ['error' => 'title is required.'];
        }

        /** @var TaskService $service */
        $service = $this->container->get('service.task');
        $task = $service->create($this->taskInput($arguments), $this->actor());
        return is_array($task) ? ['task' => $this->publicData($task)] : ['error' => $task];
    }

    private function crmUpdateTask(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var TaskService $service */
        $service = $this->container->get('service.task');
        $task = $service->update($publicId, $this->taskInput($arguments), (int)($this->actor()['id'] ?? 0), $this->actor());
        return is_array($task) ? ['task' => $this->publicData($task)] : ['error' => $task ?: 'Task not found.'];
    }

    private function crmListProjects(array $arguments): array
    {
        /** @var ProjectService $service */
        $service = $this->container->get('service.project');
        return $this->publicData($service->list($this->filters($arguments, 20, 50), $this->actor()));
    }

    private function crmGetProject(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var ProjectService $service */
        $service = $this->container->get('service.project');
        $project = $service->get($publicId, $this->actor());
        return $project ? ['project' => $this->publicData($project)] : ['error' => 'Project not found.'];
    }

    private function crmListKnowledgePages(array $arguments): array
    {
        return [
            'items' => $this->publicData($this->knowledge()->pages($this->filters($arguments, 20, 50), $this->actor())),
        ];
    }

    private function crmGetKnowledgePage(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        $page = $this->knowledge()->page($publicId, $this->actor());
        return $page ? ['page' => $this->publicData($page)] : ['error' => 'Knowledge page not found.'];
    }

    private function crmCreateKnowledgePage(array $arguments): array
    {
        $title = trim((string)($arguments['title'] ?? ''));
        if ($title === '') {
            return ['error' => 'title is required.'];
        }

        $page = $this->knowledge()->createPage($this->pick($arguments, [
            'title', 'content_html', 'space_public_id', 'parent_public_id', 'page_type', 'status',
        ]), (int)($this->actor()['id'] ?? 0), $this->actor());

        return ['page' => $this->publicData($page)];
    }

    private function tool(string $name, string $description, array $properties, array $required = []): array
    {
        return [
            'name' => $name,
            'description' => $description,
            'inputSchema' => [
                'type' => 'object',
                'properties' => $properties,
                'required' => $required,
                'additionalProperties' => false,
            ],
        ];
    }

    private function toolResult(array $payload): array
    {
        $isError = array_key_exists('error', $payload);
        return [
            'content' => [[
                'type' => 'text',
                'text' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            ]],
            'structuredContent' => $payload,
            'isError' => $isError,
        ];
    }

    private function toolError(string $message): array
    {
        return $this->toolResult(['error' => $message]);
    }

    private function withPermission(string $permission, callable $callback): array
    {
        return $this->can($permission) ? $callback() : $this->toolError('Insufficient permission: ' . $permission);
    }

    private function withPermissionAny(array $permissions, callable $callback): array
    {
        return $this->canAny($permissions) ? $callback() : $this->toolError('Insufficient permissions.');
    }

    private function can(string $permission): bool
    {
        /** @var AuthzService $authz */
        $authz = $this->container->get('service.authz');
        return $authz->hasPermissions($this->actor(), [$permission]);
    }

    private function canAny(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can((string)$permission)) {
                return true;
            }
        }

        return false;
    }

    private function actor(): array
    {
        $auth = $this->user();
        return is_array($auth['user'] ?? null) ? $auth['user'] : [];
    }

    private function knowledge(): KnowledgeRepository
    {
        return $this->container->get('repository.knowledge');
    }

    private function filters(array $arguments, int $defaultLimit, int $maxLimit): array
    {
        $filters = $this->pick($arguments, [
            'page', 'project_public_id', 'status', 'priority', 'assigned_user_id', 'updated_since',
            'space_public_id', 'sort', 'order',
        ]);
        $filters['limit'] = $this->limit($arguments, $defaultLimit, $maxLimit);

        return $filters;
    }

    private function limit(array $arguments, int $default, int $max): int
    {
        return min($max, max(1, (int)($arguments['limit'] ?? $default)));
    }

    private function taskInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'title', 'description', 'project_public_id', 'parent_task_public_id', 'priority', 'status',
            'due_at', 'start_at', 'end_at', 'assignee_user_id', 'row_version',
        ]) + ['source_type' => 'mcp'];
    }

    private function pick(array $source, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $source) && $source[$key] !== null && $source[$key] !== '') {
                $result[$key] = $source[$key];
            }
        }

        return $result;
    }

    private function publicData(mixed $payload): mixed
    {
        if (!is_array($payload)) {
            return $payload;
        }
        if (array_is_list($payload)) {
            return array_map(fn(mixed $item): mixed => $this->publicData($item), $payload);
        }

        $result = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && $this->isSensitiveOrInternalKey($key)) {
                continue;
            }
            $result[$key] = is_array($value) ? $this->publicData($value) : $value;
        }

        return $result;
    }

    private function isSensitiveOrInternalKey(string $key): bool
    {
        $normalized = strtolower($key);
        if (in_array($normalized, ['id', 'password', 'password_hash', 'token', 'token_hash', 'secret', 'secret_hash', 'key_hash'], true)) {
            return true;
        }
        if (str_contains($normalized, 'password') || str_contains($normalized, 'secret') || str_contains($normalized, 'token')) {
            return true;
        }
        if (str_ends_with($normalized, 'public_id')) {
            return false;
        }
        if (in_array($normalized, ['author_user_id', 'assigned_user_id', 'created_by_user_id', 'updated_by_user_id'], true)) {
            return false;
        }

        return str_ends_with($normalized, '_id');
    }

    private function compactGlobalSearch(array $payload): array
    {
        $results = is_array($payload['results'] ?? null) ? $payload['results'] : [];
        foreach ($results as $group => $items) {
            if (!is_array($items)) {
                continue;
            }
            $results[$group] = array_map(function (mixed $item): mixed {
                if (!is_array($item)) {
                    return $item;
                }

                return $this->pick($item, [
                    'entity_type',
                    'public_id',
                    'task_key',
                    'title',
                    'full_name',
                    'label',
                    'status',
                    'status_code',
                    'priority_code',
                    'page_type',
                    'space_title',
                    'project_public_id',
                    'project_title',
                    'counterparty_type',
                    'email',
                    'phone',
                    'due_at',
                    'updated_at',
                ]);
            }, $items);
        }
        $payload['results'] = $results;

        return $payload;
    }

    private function validateOrigin(): ?string
    {
        $origin = trim((string)$this->request()->header('Origin', ''));
        if ($origin === '') {
            return null;
        }

        $originHost = parse_url($origin, PHP_URL_HOST);
        $host = (string)($this->request()->server['HTTP_HOST'] ?? '');
        $host = preg_replace('/:\d+$/', '', $host) ?: $host;

        if (is_string($originHost) && strcasecmp($originHost, $host) === 0) {
            return null;
        }

        return 'Cross-origin MCP requests are not allowed for this installation.';
    }

    private function methodNotFound(string $method): array
    {
        return [
            'jsonrpc_error' => true,
            'code' => -32601,
            'message' => 'Method not found: ' . $method,
        ];
    }

    private function errorPayload(mixed $id, int $code, string $message, array $data = []): array
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];
        if ($data !== []) {
            $error['data'] = $data;
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => $error,
        ];
    }

    private function response(mixed $payload, int $status = 200): RawJsonResponse
    {
        return new RawJsonResponse($payload, $status, [
            'MCP-Protocol-Version' => self::PROTOCOL_VERSION,
        ]);
    }
}
