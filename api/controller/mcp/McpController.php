<?php
declare(strict_types=1);

namespace Api\Controller\Mcp;

use Api\Controller\Common\BaseController;
use Api\Controller\Admin\CacheController;
use Api\Controller\Admin\RoleMatrixController;
use Api\Controller\Admin\OpsController;
use Api\Controller\Activity\ActivityController;
use Api\Controller\Chat\ChatController;
use Api\Controller\Auth\MenuController;
use Api\Controller\Module\ModuleController;
use Api\Controller\Knowledge\KnowledgeController;
use Api\Controller\Knowledge\KnowledgePageVersionController;
use Api\Controller\Project\ProjectController;
use Api\Controller\Security\SessionController;
use Api\Model\Tag\TagRepository;
use Api\Controller\System\CoreUpdateController;
use Api\Controller\System\CoreVersionController;
use Api\Model\Knowledge\KnowledgeRepository;
use Api\System\Library\Http\RawJsonResponse;
use Api\System\Library\Service\ApiClientService;
use Api\System\Library\Service\ApprovalService;
use Api\System\Library\Service\AuthzService;
use Api\System\Library\Service\CalendarService;
use Api\System\Library\Service\ChecklistService;
use Api\System\Library\Service\ClientService;
use Api\System\Library\Service\AnalyticsService;
use Api\System\Library\Service\AiActionService;
use Api\System\Library\Service\AiActionTypeService;
use Api\System\Library\Service\AiAvailabilityService;
use Api\System\Library\Service\AiJobService;
use Api\System\Library\Service\AiPreferenceService;
use Api\System\Library\Service\AiPromptSchemaService;
use Api\System\Library\Service\AiRetentionPolicyService;
use Api\System\Library\Service\AiSemanticIndexService;
use Api\System\Library\Service\AiSettingsService;
use Api\System\Library\Service\AiSuggestionService;
use Api\System\Library\Service\AiIntentSettingService;
use Api\System\Library\Service\AiUsageService;
use Api\System\Library\Service\AiProviderService;
use Api\System\Library\Service\DashboardService;
use Api\System\Library\Service\CompanyService;
use Api\System\Library\Service\CommentService;
use Api\System\Library\Service\ContactService;
use Api\System\Library\Service\CounterpartyService;
use Api\System\Library\Service\CustomFieldService;
use Api\System\Library\Service\DepartmentService;
use Api\System\Library\Service\DependencyService;
use Api\System\Library\Service\FavoriteService;
use Api\System\Library\Service\FileService;
use Api\System\Library\Service\FeatureFlagService;
use Api\System\Library\Service\ExportService;
use Api\System\Library\Service\IdeaService;
use Api\System\Library\Service\ImportService;
use Api\System\Library\Service\IntakeItemService;
use Api\System\Library\Service\LogsService;
use Api\System\Library\Service\MentionService;
use Api\System\Library\Service\MilestoneService;
use Api\System\Library\Service\NotificationService;
use Api\System\Library\Service\NotificationPushService;
use Api\System\Library\Service\PermissionService;
use Api\System\Library\Service\ProjectModuleService;
use Api\System\Library\Service\ProjectService;
use Api\System\Library\Service\ReactionService;
use Api\System\Library\Service\RecycleBinService;
use Api\System\Library\Service\RecurringService;
use Api\System\Library\Service\ReminderService;
use Api\System\Library\Service\RoleService;
use Api\System\Library\Service\SavedViewService;
use Api\System\Library\Service\SearchService;
use Api\System\Library\Service\SessionService;
use Api\System\Library\Service\SettingService;
use Api\System\Library\Service\SlaService;
use Api\System\Library\Service\StatusService;
use Api\System\Library\Service\StickyNoteService;
use Api\System\Library\Service\SubscriptionService;
use Api\System\Library\Service\TagService;
use Api\System\Library\Service\TaskEstimateService;
use Api\System\Library\Service\TaskService;
use Api\System\Library\Service\TeamService;
use Api\System\Library\Service\TemplateService;
use Api\System\Library\Service\UserService;
use Api\System\Library\Service\UserProfileService;
use Api\System\Library\Service\WebhookService;
use Api\System\Library\Service\WorkCycleService;
use Api\System\Library\Service\WorklogService;
use Api\System\Library\Service\WorkflowService;
use Api\System\Library\Update\CoreUpdateClient;
use Api\System\Library\Update\CoreUpdateConfig;
use Api\System\Library\Update\CoreUpdateHistoryRepository;
use Api\System\Library\Update\CoreUpdateLogRepository;
use Api\System\Library\Update\CoreUpdatePlanner;
use Api\System\Library\Update\CoreUpdateSessionService;
use Api\System\Library\Update\CoreVersion;
use PDO;
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
                'resources/list' => ['resources' => $this->resources()],
                'resources/read' => $this->readResource($params),
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
                'resources' => [
                    'listChanged' => false,
                ],
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

    private function resources(): array
    {
        return [
            $this->resource(
                'tropatt://server/about',
                'about',
                'TropaTT MCP Overview',
                'How this CRM exposes safe agent access through MCP.',
                'text/markdown',
                1.0
            ),
            $this->resource(
                'tropatt://server/tools',
                'tools',
                'Available MCP Tools',
                'Tool list visible to the current authenticated CRM user.',
                'application/json',
                0.95
            ),
            $this->resource(
                'tropatt://server/api-map',
                'api-map',
                'CRM API Capability Map',
                'High-level map of CRM API domains that agents can reason about.',
                'text/markdown',
                0.8
            ),
            $this->resource(
                'tropatt://user/current',
                'current-user',
                'Current CRM User',
                'Sanitized profile and permissions for the authenticated user.',
                'application/json',
                0.9
            ),
        ];
    }

    private function readResource(array $params): array
    {
        $uri = trim((string)($params['uri'] ?? ''));
        if ($uri === '') {
            return [
                'jsonrpc_error' => true,
                'code' => -32602,
                'message' => 'Resource uri is required.',
            ];
        }

        $content = match ($uri) {
            'tropatt://server/about' => $this->textResource($uri, 'text/markdown', $this->mcpAboutMarkdown()),
            'tropatt://server/tools' => $this->textResource($uri, 'application/json', json_encode(['tools' => $this->tools()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}'),
            'tropatt://server/api-map' => $this->textResource($uri, 'text/markdown', $this->apiMapMarkdown()),
            'tropatt://user/current' => $this->textResource($uri, 'application/json', json_encode($this->crmGetCurrentUser(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}'),
            default => null,
        };

        if ($content === null) {
            return [
                'jsonrpc_error' => true,
                'code' => -32002,
                'message' => 'Resource not found',
                'data' => ['uri' => $uri],
            ];
        }

        return ['contents' => [$content]];
    }

    private function resource(string $uri, string $name, string $title, string $description, string $mimeType, float $priority): array
    {
        return [
            'uri' => $uri,
            'name' => $name,
            'title' => $title,
            'description' => $description,
            'mimeType' => $mimeType,
            'annotations' => [
                'audience' => ['assistant'],
                'priority' => $priority,
            ],
        ];
    }

    private function textResource(string $uri, string $mimeType, string $text): array
    {
        return [
            'uri' => $uri,
            'mimeType' => $mimeType,
            'text' => $text,
        ];
    }

    private function mcpAboutMarkdown(): string
    {
        return <<<'MD'
# TropaTT MCP Server

TropaTT exposes a JSON-RPC MCP endpoint for authenticated CRM users and agent clients.

## Endpoint

Use the current installation host:

`POST /api/index.php?route=api/v1/mcp`

Do not hardcode the public demo host. Every installation can have its own domain.

## Authentication

Send the same bearer access token used by the REST API:

`Authorization: Bearer <access_token>`

The server uses the current CRM user, existing RBAC permissions and entity access checks. MCP tools never expose passwords, token hashes, secrets, local paths or internal numeric IDs unless the field is intentionally public.

## Recommended Agent Workflow

1. Call `initialize`.
2. Call `resources/read` for `tropatt://server/tools` and `tropatt://user/current`.
3. Use read tools first to inspect tasks, projects, ideas, chats, calendar and knowledge.
4. Use write tools only after the user intent is clear.
5. Prefer public identifiers such as `task_public_id`, `project_public_id`, `idea_public_id`, `chat_public_id`.
MD;
    }

    private function apiMapMarkdown(): string
    {
        return <<<'MD'
# TropaTT API Capability Map

The REST API remains the full integration surface. MCP is a safe agent-facing layer on top of selected CRM operations.

## Main Domains

- Auth and profile: login, logout, current user, preferences and sessions.
- Users, teams and RBAC: users, roles, permissions, teams, departments and invitations.
- CRM records: organizations, clients, counterparties and contacts.
- Work management: projects, tasks, subtasks, checklists, dependencies, estimates, worklogs, cycles, recurring tasks and approvals.
- Planning views: dashboard, calendar, Kanban, Gantt, saved views and custom fields.
- Collaboration: comments, mentions, notifications, chats, files, reactions, favorites and subscriptions.
- Knowledge base: spaces, pages, comments, permissions, versions, locks, tags, files and export/import.
- Ideas and AI: ideas, AI analysis pipeline, AI suggestions, providers, jobs and semantic search.
- Automation and admin: workflow rules, webhooks, modules, audit logs, settings, feature flags, retention and recycle bin.

## Agent Safety Notes

- Use MCP tools for common agent tasks.
- Use direct REST API only when a needed endpoint is not exposed through MCP yet.
- Always keep user data on the installation host selected by the customer.
- Never assume the demo domain is the production domain.
MD;
    }

    private function tools(): array
    {
        $tools = [
            $this->tool('crm_get_current_user', 'Get the authenticated CRM user profile and permission codes visible to MCP.', []),
            $this->tool('crm_get_profile', 'Get the authenticated CRM user profile and preferences.', []),
            $this->tool('crm_update_profile', 'Update the authenticated CRM user profile.', [
                'full_name' => ['type' => 'string'],
                'email' => ['type' => 'string'],
                'locale' => ['type' => 'string'],
                'timezone' => ['type' => 'string'],
            ]),
            $this->tool('crm_get_profile_preferences', 'Get the authenticated CRM user preferences.', []),
            $this->tool('crm_update_profile_preferences', 'Update the authenticated CRM user preferences.', [
                'preferences' => ['type' => 'object', 'additionalProperties' => true],
            ], ['preferences']),
            $this->tool('crm_change_profile_password', 'Change the authenticated CRM user password and revoke other sessions.', [
                'current_password' => ['type' => 'string'],
                'new_password' => ['type' => 'string'],
            ], ['current_password', 'new_password']),
            $this->tool('crm_list_security_sessions', 'List active and historical sessions for the current CRM user.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            ]),
            $this->tool('crm_revoke_security_session', 'Revoke one session for the current CRM user.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']),
            $this->tool('crm_revoke_other_security_sessions', 'Revoke every other current-user session except the active one.', []),
            $this->tool('crm_revoke_device_sessions', 'Revoke all sessions for a matching device fingerprint.', [
                'device_fingerprint' => ['type' => 'string'],
            ], ['device_fingerprint']),
            $this->tool('crm_get_menu', 'Get the current CRM navigation items after permission and preference filtering.', []),
            $this->tool('crm_get_menu_preferences', 'Get the current user menu preferences and team template.', []),
            $this->tool('crm_save_menu_preferences', 'Save the current user menu preferences.', [
                'items' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
            ], ['items']),
        ];

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
            $tools[] = $this->tool('crm_add_task_comment', 'Add a comment to a task visible to the current CRM user.', [
                'task_public_id' => ['type' => 'string'],
                'body' => ['type' => 'string', 'description' => 'Comment body, up to 8000 characters.'],
                'visibility' => ['type' => 'string', 'enum' => ['internal', 'public'], 'default' => 'internal'],
            ], ['task_public_id', 'body']);
            $tools[] = $this->tool('crm_list_cycles', 'List work cycles/sprints visible to the current CRM user.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'project_public_id' => ['type' => 'string'],
                'status' => ['type' => 'string', 'enum' => ['planned', 'active', 'completed', 'archived']],
            ]);
            $tools[] = $this->tool('crm_get_cycle', 'Get one work cycle/sprint by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_create_cycle', 'Create a work cycle/sprint for an accessible project.', [
                'title' => ['type' => 'string'],
                'project_public_id' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'goal' => ['type' => 'string'],
                'status' => ['type' => 'string', 'enum' => ['planned', 'active'], 'default' => 'planned'],
                'start_at' => ['type' => 'string'],
                'end_at' => ['type' => 'string'],
                'timezone' => ['type' => 'string', 'default' => 'UTC'],
                'owner_user_public_id' => ['type' => 'string'],
            ], ['title', 'project_public_id']);
            $tools[] = $this->tool('crm_update_cycle', 'Update safe fields on a work cycle/sprint.', [
                'public_id' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'goal' => ['type' => 'string'],
                'start_at' => ['type' => 'string'],
                'end_at' => ['type' => 'string'],
                'timezone' => ['type' => 'string'],
                'owner_user_public_id' => ['type' => 'string'],
                'row_version' => ['type' => 'integer'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_list_cycle_tasks', 'List tasks assigned to a visible work cycle/sprint.', [
                'cycle_public_id' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'status' => ['type' => 'string'],
            ], ['cycle_public_id']);
            $tools[] = $this->tool('crm_add_tasks_to_cycle', 'Add existing CRM tasks to a visible planned or active work cycle/sprint.', [
                'cycle_public_id' => ['type' => 'string'],
                'task_public_ids' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Task public ids to add. Max 100 per request.'],
                'task_keys' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional human task keys to resolve and add.'],
            ], ['cycle_public_id']);
        }

        if ($this->can('user.view')) {
            $tools[] = $this->tool('crm_list_users', 'List CRM users for assignment and collaboration lookup.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'q' => ['type' => 'string', 'description' => 'Search by login, full name or email.'],
                'is_active' => ['type' => 'integer', 'enum' => [0, 1]],
            ]);
            $tools[] = $this->tool('crm_get_user', 'Get one CRM user by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_create_user', 'Create a CRM user.', [
                'login' => ['type' => 'string'],
                'password' => ['type' => 'string'],
                'email' => ['type' => 'string'],
                'full_name' => ['type' => 'string'],
                'locale' => ['type' => 'string'],
                'is_root' => ['type' => 'integer', 'enum' => [0, 1]],
                'role_public_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
                'is_active' => ['type' => 'integer', 'enum' => [0, 1]],
                'cost_rate' => ['type' => 'number'],
                'bill_rate' => ['type' => 'number'],
                'token' => ['type' => 'string'],
            ], ['login', 'password']);
            $tools[] = $this->tool('crm_update_user', 'Update a CRM user by public id.', [
                'public_id' => ['type' => 'string'],
                'email' => ['type' => 'string'],
                'full_name' => ['type' => 'string'],
                'locale' => ['type' => 'string'],
                'is_active' => ['type' => 'integer', 'enum' => [0, 1]],
                'is_root' => ['type' => 'integer', 'enum' => [0, 1]],
                'role_public_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
                'password' => ['type' => 'string'],
                'token' => ['type' => 'string'],
                'cost_rate' => ['type' => 'number'],
                'bill_rate' => ['type' => 'number'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_delete_user', 'Soft-delete a CRM user by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_get_user_token_info', 'Check whether a user has an API token set.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_rotate_user_token', 'Rotate or set a user API token and return the plain token once.', [
                'public_id' => ['type' => 'string'],
                'token' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_revoke_user_token', 'Revoke the API token for a CRM user.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_get_user_activity', 'Get request, security and audit activity for a CRM user.', [
                'public_id' => ['type' => 'string'],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
            ], ['public_id']);
        }

        $tools[] = $this->tool('crm_get_profile', 'Get the current user profile and preferences without secrets.', []);
        $tools[] = $this->tool('crm_list_security_sessions', 'List active and historical sessions for the current CRM user.', [
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
        ]);

        if ($this->can('role.view') || $this->can('role.manage') || (bool)($this->actor()['is_root'] ?? false)) {
            $tools[] = $this->tool('crm_list_roles', 'List CRM roles.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'q' => ['type' => 'string'],
            ]);
            $tools[] = $this->tool('crm_list_permissions', 'List CRM permission registry.', []);
            $tools[] = $this->tool('crm_get_role_permissions', 'Get permission codes assigned to a role.', [
                'role_public_id' => ['type' => 'string'],
            ], ['role_public_id']);
            $tools[] = $this->tool('crm_get_admin_role_matrix', 'Get the role and permission matrix.', []);
            $tools[] = $this->tool('crm_update_admin_role_matrix', 'Update the role and permission matrix.', [
                'roles' => ['type' => 'array', 'items' => ['type' => 'object']],
            ], ['roles']);
        }

        if ($this->can('settings.manage')) {
            $tools[] = $this->tool('crm_list_settings', 'List CRM settings. Secret-looking values are redacted from MCP output.', [
                'scope' => ['type' => 'string', 'default' => 'system'],
                'q' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            ]);
            $tools[] = $this->tool('crm_get_setting', 'Get one CRM setting by scope and name. Secret-looking values are redacted.', [
                'scope' => ['type' => 'string', 'default' => 'system'],
                'name' => ['type' => 'string'],
            ], ['name']);
            $tools[] = $this->tool('crm_list_feature_flags', 'List CRM feature flags.', [
                'q' => ['type' => 'string'],
                'is_enabled' => ['type' => 'integer', 'enum' => [0, 1]],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            ]);
            $tools[] = $this->tool('crm_update_feature_flag', 'Enable, disable or update payload for a CRM feature flag.', [
                'public_id' => ['type' => 'string'],
                'is_enabled' => ['type' => 'boolean'],
                'payload' => ['type' => 'object', 'additionalProperties' => true],
            ], ['public_id']);
            $tools[] = $this->tool('crm_list_modules', 'List installed and discoverable CRM modules.', []);
            $tools[] = $this->tool('crm_get_module', 'Get one CRM module manifest and registry state.', [
                'name' => ['type' => 'string'],
            ], ['name']);
            $tools[] = $this->tool('crm_install_module', 'Install a CRM module by name.', [
                'name' => ['type' => 'string'],
            ], ['name']);
            $tools[] = $this->tool('crm_activate_module', 'Activate an installed CRM module by name.', [
                'name' => ['type' => 'string'],
            ], ['name']);
            $tools[] = $this->tool('crm_deactivate_module', 'Deactivate an installed CRM module by name.', [
                'name' => ['type' => 'string'],
            ], ['name']);
            $tools[] = $this->tool('crm_uninstall_module', 'Uninstall a CRM module by name.', [
                'name' => ['type' => 'string'],
            ], ['name']);
            $tools[] = $this->tool('crm_get_module_config', 'Get a CRM module configuration snapshot.', [
                'name' => ['type' => 'string'],
            ], ['name']);
            $tools[] = $this->tool('crm_update_module_config', 'Update a CRM module configuration.', [
                'name' => ['type' => 'string'],
                'config' => ['type' => 'object', 'additionalProperties' => true],
            ], ['name', 'config']);
            $tools[] = $this->tool('crm_get_module_health', 'Get a CRM module health snapshot.', [
                'name' => ['type' => 'string'],
            ], ['name']);
            $tools[] = $this->tool('crm_get_module_migrations', 'List migration state for a CRM module.', [
                'name' => ['type' => 'string'],
            ], ['name']);
            $tools[] = $this->tool('crm_get_module_errors', 'List recent errors for a CRM module.', [
                'name' => ['type' => 'string'],
            ], ['name']);
            $tools[] = $this->tool('crm_clear_module_errors', 'Clear recent errors for a CRM module.', [
                'name' => ['type' => 'string'],
            ], ['name']);
            $tools[] = $this->tool('crm_install_module_from_url', 'Install a CRM module from a URL.', [
                'url' => ['type' => 'string'],
            ], ['url']);
            $tools[] = $this->tool('crm_install_module_from_file', 'Install a CRM module from a base64 encoded archive.', [
                'file_name' => ['type' => 'string'],
                'file_data' => ['type' => 'string'],
            ], ['file_data']);
            $tools[] = $this->tool('crm_get_cache_stats', 'Get API file cache stats and current cache settings.', []);
            $tools[] = $this->tool('crm_clear_cache', 'Clear API file cache.', []);
            $tools[] = $this->tool('crm_get_ops_system', 'Get system ops snapshot for the CRM installation.', []);
            $tools[] = $this->tool('crm_get_ops_metrics', 'Get system metrics snapshot for the CRM installation.', []);
            $tools[] = $this->tool('crm_run_ops_jobs', 'Run queued import, export, push and webhook jobs.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 10],
            ]);
            $tools[] = $this->tool('crm_get_core_version', 'Get the current core version for this installation.', []);
            $tools[] = $this->tool('crm_get_core_update_status', 'Get core update status and last known update state.', []);
            $tools[] = $this->tool('crm_check_core_update', 'Check whether a core update is available.', []);
            $tools[] = $this->tool('crm_run_core_update_preflight', 'Run a dry-run core update preflight.', [
                'payload' => ['type' => 'object', 'additionalProperties' => true],
            ]);
            $tools[] = $this->tool('crm_get_core_update_changes', 'Get a diff summary for a core update range.', [
                'from' => ['type' => 'string'],
                'to' => ['type' => 'string'],
            ]);
            $tools[] = $this->tool('crm_get_core_update_session', 'Create a core update session for the current user.', []);
            $tools[] = $this->tool('crm_get_core_update_history', 'Get core update history.', []);
            $tools[] = $this->tool('crm_get_core_update_log', 'Get a core update job log.', [
                'job_id' => ['type' => 'string'],
            ], ['job_id']);
        }

        if ($this->can('logs.view') || (bool)($this->actor()['is_root'] ?? false)) {
            $tools[] = $this->tool('crm_list_audit_log', 'List audit log entries visible to the current CRM user.', [
                'actor_public_id' => ['type' => 'string'],
                'entity_type' => ['type' => 'string'],
                'entity_public_id' => ['type' => 'string'],
                'action' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            ]);
            $tools[] = $this->tool('crm_list_security_log', 'List security log entries. Root or logs permission required.', [
                'event_type' => ['type' => 'string'],
                'actor_public_id' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            ]);
        }

        if ($this->can('api_client.view') || $this->can('api_client.manage') || (bool)($this->actor()['is_root'] ?? false)) {
            $tools[] = $this->tool('crm_list_api_clients', 'List API clients without key material.', [
                'q' => ['type' => 'string'],
                'is_active' => ['type' => 'integer', 'enum' => [0, 1]],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            ]);
            $tools[] = $this->tool('crm_get_api_client', 'Get API client metadata without key material.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_list_api_client_keys', 'List API client keys metadata only. Key hashes and plain keys are never returned.', [
                'client_public_id' => ['type' => 'string'],
            ], ['client_public_id']);
        }

        if ($this->can('webhook.manage') || (bool)($this->actor()['is_root'] ?? false)) {
            $tools[] = $this->tool('crm_list_webhooks', 'List webhook subscriptions without secrets.', [
                'q' => ['type' => 'string'],
                'is_active' => ['type' => 'integer', 'enum' => [0, 1]],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            ]);
            $tools[] = $this->tool('crm_list_webhook_deliveries', 'List webhook delivery attempts.', [
                'webhook_public_id' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            ]);
        }

        $tools[] = $this->tool('crm_get_dashboard_summary', 'Get dashboard summary counters and recent workload.', []);
        $tools[] = $this->tool('crm_get_ai_settings', 'Get global AI settings and provider configuration summary.', []);
        $tools[] = $this->tool('crm_update_ai_settings', 'Update global AI settings and provider configuration.', [
            'default_provider_public_id' => ['type' => 'string'],
            'default_model' => ['type' => 'string'],
            'runtime_mode' => ['type' => 'string'],
            'max_input_chars' => ['type' => 'integer'],
            'request_timeout_ms' => ['type' => 'integer'],
            'strict_json_mode' => ['type' => 'boolean'],
            'audit_redaction_enabled' => ['type' => 'boolean'],
            'allow_personal_recommendations_opt_out' => ['type' => 'boolean'],
        ]);
        $tools[] = $this->tool('crm_get_ai_preferences', 'Get current user AI preferences.', []);
        $tools[] = $this->tool('crm_update_ai_preferences', 'Update current user AI preferences.', [
            'preferences' => ['type' => 'object', 'additionalProperties' => true],
        ], ['preferences']);
        $tools[] = $this->tool('crm_get_ai_availability', 'Check whether AI capabilities are available for the current user.', [
            'requested_intents' => ['type' => 'array', 'items' => ['type' => 'string']],
        ]);
        $tools[] = $this->tool('crm_list_ai_action_types', 'List AI action types and enabled status.', []);
        $tools[] = $this->tool('crm_execute_ai_action', 'Execute a safe AI action by action type.', [
            'action_type' => ['type' => 'string'],
            'input' => ['type' => 'object', 'additionalProperties' => true],
        ], ['action_type']);
        $tools[] = $this->tool('crm_list_ai_providers', 'List AI providers without secrets.', [
            'q' => ['type' => 'string'],
            'is_active' => ['type' => 'integer', 'enum' => [0, 1]],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
        ]);
        $tools[] = $this->tool('crm_get_ai_provider', 'Get AI provider metadata without secrets.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_list_ai_models', 'List AI models for the default provider or a given provider.', [
            'provider_public_id' => ['type' => 'string'],
        ]);
        $tools[] = $this->tool('crm_list_ai_intents', 'List AI intent settings.', [
            'q' => ['type' => 'string'],
            'is_enabled' => ['type' => 'integer', 'enum' => [0, 1]],
            'feature_flag' => ['type' => 'string'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
        ]);
        $tools[] = $this->tool('crm_update_ai_intent', 'Update an AI intent setting.', [
            'intent_code' => ['type' => 'string'],
            'is_enabled' => ['type' => 'boolean'],
            'required_permission' => ['type' => 'string'],
            'feature_flag' => ['type' => 'string'],
        ], ['intent_code']);
        $tools[] = $this->tool('crm_list_ai_prompts', 'List AI prompt templates.', [
            'intent_code' => ['type' => 'string'],
            'locale' => ['type' => 'string'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
        ]);
        $tools[] = $this->tool('crm_create_ai_prompt', 'Create an AI prompt template.', [
            'intent_code' => ['type' => 'string'],
            'locale' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'prompt' => ['type' => 'string'],
            'is_active' => ['type' => 'boolean'],
        ], ['intent_code', 'locale', 'title', 'prompt']);
        $tools[] = $this->tool('crm_update_ai_prompt', 'Update an AI prompt template.', [
            'public_id' => ['type' => 'string'],
            'intent_code' => ['type' => 'string'],
            'locale' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'prompt' => ['type' => 'string'],
            'is_active' => ['type' => 'boolean'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_list_ai_json_schemas', 'List AI JSON schemas.', [
            'intent_code' => ['type' => 'string'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
        ]);
        $tools[] = $this->tool('crm_create_ai_json_schema', 'Create an AI JSON schema.', [
            'intent_code' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'schema_json' => ['type' => 'string'],
            'is_active' => ['type' => 'boolean'],
        ], ['intent_code', 'title', 'schema_json']);
        $tools[] = $this->tool('crm_update_ai_json_schema', 'Update an AI JSON schema.', [
            'public_id' => ['type' => 'string'],
            'intent_code' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'schema_json' => ['type' => 'string'],
            'is_active' => ['type' => 'boolean'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_list_ai_usage', 'List AI usage logs.', [
            'intent_code' => ['type' => 'string'],
            'action_type' => ['type' => 'string'],
            'provider_public_id' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'error_code' => ['type' => 'string'],
            'user_id' => ['type' => 'integer'],
            'is_sensitive_context' => ['type' => 'boolean'],
            'date_from' => ['type' => 'string'],
            'date_to' => ['type' => 'string'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
        ]);
        $tools[] = $this->tool('crm_list_ai_audit', 'List AI audit log entries.', [
            'intent_code' => ['type' => 'string'],
            'entity_public_id' => ['type' => 'string'],
            'action' => ['type' => 'string'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
        ]);
        $tools[] = $this->tool('crm_list_ai_jobs', 'List AI cron jobs.', [
            'job_type' => ['type' => 'string'],
            'action_type' => ['type' => 'string'],
            'intent_code' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'scope_type' => ['type' => 'string'],
            'scope_public_id' => ['type' => 'string'],
            'error_code' => ['type' => 'string'],
            'requested_by_user_id' => ['type' => 'integer'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
        ]);
        $tools[] = $this->tool('crm_get_ai_job', 'Get one AI cron job by public id.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_retry_ai_job', 'Retry an AI job.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_dry_run_ai_job', 'Dry-run an AI job by job code.', [
            'job_code' => ['type' => 'string'],
            'input' => ['type' => 'object', 'additionalProperties' => true],
        ], ['job_code']);
        $tools[] = $this->tool('crm_run_once_ai_job', 'Run an AI job once by job code.', [
            'job_code' => ['type' => 'string'],
            'input' => ['type' => 'object', 'additionalProperties' => true],
        ], ['job_code']);
        $tools[] = $this->tool('crm_search_ai_semantic', 'Run semantic AI search.', [
            'query' => ['type' => 'string'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10],
        ], ['query']);
        $tools[] = $this->tool('crm_list_ai_retention_policies', 'List AI retention policies.', []);
        $tools[] = $this->tool('crm_list_ai_suggestions', 'List AI suggestions.', [
            'intent_code' => ['type' => 'string'],
            'entity_type' => ['type' => 'string'],
            'entity_public_id' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'created_by_user_id' => ['type' => 'integer'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
        ]);
        $tools[] = $this->tool('crm_get_ai_suggestion', 'Get one AI suggestion by public id.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_dismiss_ai_suggestion', 'Dismiss an AI suggestion.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_preview_apply_ai_suggestion', 'Preview apply an AI suggestion.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_confirm_ai_suggestion', 'Confirm an AI suggestion.', [
            'public_id' => ['type' => 'string'],
            'input' => ['type' => 'object', 'additionalProperties' => true],
        ], ['public_id']);
        $tools[] = $this->tool('crm_create_ai_dashboard_digest', 'Create an AI dashboard digest.', [
            'input' => ['type' => 'object', 'additionalProperties' => true],
        ]);
        $tools[] = $this->tool('crm_create_ai_my_day_plan', 'Create an AI plan for today.', [
            'input' => ['type' => 'object', 'additionalProperties' => true],
        ]);
        $tools[] = $this->tool('crm_create_ai_my_week_plan', 'Create an AI plan for the week.', [
            'input' => ['type' => 'object', 'additionalProperties' => true],
        ]);
        $tools[] = $this->tool('crm_create_ai_task_summary', 'Create an AI summary for a task.', [
            'task_public_id' => ['type' => 'string'],
            'input' => ['type' => 'object', 'additionalProperties' => true],
        ], ['task_public_id']);
        $tools[] = $this->tool('crm_create_ai_task_next_action', 'Create an AI next action for a task.', [
            'task_public_id' => ['type' => 'string'],
            'input' => ['type' => 'object', 'additionalProperties' => true],
        ], ['task_public_id']);
        $tools[] = $this->tool('crm_create_ai_task_decomposition', 'Create an AI decomposition for a task.', [
            'task_public_id' => ['type' => 'string'],
            'input' => ['type' => 'object', 'additionalProperties' => true],
        ], ['task_public_id']);
        $tools[] = $this->tool('crm_create_ai_task_checklist', 'Create an AI checklist for a task.', [
            'task_public_id' => ['type' => 'string'],
            'input' => ['type' => 'object', 'additionalProperties' => true],
        ], ['task_public_id']);
        $tools[] = $this->tool('crm_create_ai_task_quality', 'Create an AI quality review for a task.', [
            'task_public_id' => ['type' => 'string'],
            'input' => ['type' => 'object', 'additionalProperties' => true],
        ], ['task_public_id']);
        $tools[] = $this->tool('crm_create_ai_project_summary', 'Create an AI summary for a project.', [
            'project_public_id' => ['type' => 'string'],
            'input' => ['type' => 'object', 'additionalProperties' => true],
        ], ['project_public_id']);
        $tools[] = $this->tool('crm_create_ai_project_risks', 'Create an AI risk review for a project.', [
            'project_public_id' => ['type' => 'string'],
            'input' => ['type' => 'object', 'additionalProperties' => true],
        ], ['project_public_id']);
        $tools[] = $this->tool('crm_create_ai_analytics_kpi_explanation', 'Create an AI KPI explanation.', [
            'input' => ['type' => 'object', 'additionalProperties' => true],
        ]);
        $tools[] = $this->tool('crm_create_ai_analytics_risks_explanation', 'Create an AI analytics risk explanation.', [
            'input' => ['type' => 'object', 'additionalProperties' => true],
        ]);
        $tools[] = $this->tool('crm_create_ai_analytics_team_workload_summary', 'Create an AI team workload summary.', [
            'input' => ['type' => 'object', 'additionalProperties' => true],
        ]);
        $tools[] = $this->tool('crm_get_analytics_summary', 'Get aggregated analytics summary for the current CRM user.', []);
        $tools[] = $this->tool('crm_list_analytics_projects', 'List project analytics breakdown.', [
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
        ]);
        $tools[] = $this->tool('crm_list_analytics_users', 'List user workload analytics.', [
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
        ]);

        $tools[] = $this->tool('crm_list_intake_items', 'List intake items visible to the current CRM user.', [
            'status' => ['type' => 'string'],
            'source_type' => ['type' => 'string'],
            'project_public_id' => ['type' => 'string'],
            'client_public_id' => ['type' => 'string'],
            'contact_public_id' => ['type' => 'string'],
            'assignee_user_id' => ['type' => 'integer'],
            'q' => ['type' => 'string'],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
        ]);
        $tools[] = $this->tool('crm_get_intake_item', 'Get one intake item by public id.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_create_intake_item', 'Create an intake item.', [
            'title' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'project_public_id' => ['type' => 'string'],
            'client_public_id' => ['type' => 'string'],
            'contact_public_id' => ['type' => 'string'],
            'source_type' => ['type' => 'string'],
            'source_ref' => ['type' => 'string'],
            'source_email' => ['type' => 'string'],
            'external_source' => ['type' => 'string'],
            'external_id' => ['type' => 'string'],
            'extra' => ['type' => 'object', 'additionalProperties' => true],
            'due_at' => ['type' => 'string'],
            'priority_code' => ['type' => 'string'],
            'assignee_user_id' => ['type' => 'integer'],
        ], ['title']);
        $tools[] = $this->tool('crm_update_intake_item', 'Update an intake item.', ['public_id' => ['type' => 'string']] + $this->intakeSchema(), ['public_id']);
        $tools[] = $this->tool('crm_delete_intake_item', 'Soft-delete an intake item.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_accept_intake_item', 'Accept an intake item and create a task.', [
            'public_id' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'project_public_id' => ['type' => 'string'],
            'priority' => ['type' => 'string'],
            'due_at' => ['type' => 'string'],
            'assignee_user_id' => ['type' => 'integer'],
            'status' => ['type' => 'string'],
            'row_version' => ['type' => 'integer'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_reject_intake_item', 'Reject an intake item with a reason.', [
            'public_id' => ['type' => 'string'],
            'reason' => ['type' => 'string'],
            'row_version' => ['type' => 'integer'],
        ], ['public_id', 'reason']);
        $tools[] = $this->tool('crm_snooze_intake_item', 'Snooze an intake item until a later time.', [
            'public_id' => ['type' => 'string'],
            'snoozed_until' => ['type' => 'string'],
            'row_version' => ['type' => 'integer'],
        ], ['public_id', 'snoozed_until']);

        $tools[] = $this->tool('crm_list_project_modules', 'List project modules.', [
            'project_public_id' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
        ]);
        $tools[] = $this->tool('crm_get_project_module', 'Get one project module by public id.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_create_project_module', 'Create a project module.', $this->projectModuleSchema(), ['project_public_id', 'title']);
        $tools[] = $this->tool('crm_update_project_module', 'Update a project module.', ['public_id' => ['type' => 'string']] + $this->projectModuleSchema(), ['public_id']);
        $tools[] = $this->tool('crm_archive_project_module', 'Archive a project module.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_delete_project_module', 'Soft-delete a project module.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_list_project_module_tasks', 'List tasks linked to a project module.', [
            'module_public_id' => ['type' => 'string'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
        ], ['module_public_id']);
        $tools[] = $this->tool('crm_list_project_module_members', 'List project module members.', [
            'module_public_id' => ['type' => 'string'],
        ], ['module_public_id']);
        $tools[] = $this->tool('crm_list_project_module_links', 'List project module links.', [
            'module_public_id' => ['type' => 'string'],
        ], ['module_public_id']);
        $tools[] = $this->tool('crm_add_tasks_to_project_module', 'Add tasks to a project module.', [
            'module_public_id' => ['type' => 'string'],
            'task_public_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
            'task_keys' => ['type' => 'array', 'items' => ['type' => 'string']],
        ], ['module_public_id']);
        $tools[] = $this->tool('crm_add_members_to_project_module', 'Add users to a project module.', [
            'module_public_id' => ['type' => 'string'],
            'members' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                'user_public_id' => ['type' => 'string'],
                'role_code' => ['type' => 'string'],
            ]]],
        ], ['module_public_id', 'members']);
        $tools[] = $this->tool('crm_remove_project_module_task', 'Remove a task from a project module.', [
            'module_public_id' => ['type' => 'string'],
            'task_public_id' => ['type' => 'string'],
        ], ['module_public_id', 'task_public_id']);
        $tools[] = $this->tool('crm_remove_project_module_member', 'Remove a user from a project module.', [
            'module_public_id' => ['type' => 'string'],
            'user_public_id' => ['type' => 'string'],
        ], ['module_public_id', 'user_public_id']);
        $tools[] = $this->tool('crm_add_project_module_link', 'Add a link to a project module.', [
            'module_public_id' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'url' => ['type' => 'string'],
            'link_type' => ['type' => 'string'],
            'sort_order' => ['type' => 'integer'],
        ], ['module_public_id', 'title', 'url']);
        $tools[] = $this->tool('crm_update_project_module_link', 'Update a project module link.', [
            'link_public_id' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'url' => ['type' => 'string'],
            'link_type' => ['type' => 'string'],
            'sort_order' => ['type' => 'integer'],
        ], ['link_public_id']);
        $tools[] = $this->tool('crm_delete_project_module_link', 'Delete a project module link.', [
            'link_public_id' => ['type' => 'string'],
        ], ['link_public_id']);

        $tools[] = $this->tool('crm_list_recycle_bin', 'List recycle bin entries visible to the current CRM user.', [
            'entity_type' => ['type' => 'string'],
            'entity_public_id' => ['type' => 'string'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
        ]);
        $tools[] = $this->tool('crm_restore_recycle_bin_item', 'Restore a recycle bin entry.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_purge_recycle_bin_item', 'Permanently purge a recycle bin entry.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);

        $tools[] = $this->tool('crm_list_import_jobs', 'List import jobs visible to the current CRM user.', [
            'type' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
        ]);
        $tools[] = $this->tool('crm_get_import_job', 'Get one import job by public id.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_create_import_job', 'Create an import job.', [
            'type' => ['type' => 'string'],
            'rows' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
            'content_base64' => ['type' => 'string'],
            'delimiter' => ['type' => 'string'],
            'has_header' => ['type' => 'boolean'],
            'columns' => ['type' => 'array', 'items' => ['type' => 'string']],
            'async' => ['type' => 'boolean'],
        ], ['type']);
        $tools[] = $this->tool('crm_cancel_import_job', 'Cancel an import job.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_retry_import_job', 'Retry an import job.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);

        $tools[] = $this->tool('crm_list_export_jobs', 'List export jobs visible to the current CRM user.', [
            'type' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
        ]);
        $tools[] = $this->tool('crm_get_export_job', 'Get one export job by public id.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_create_export_job', 'Create an export job.', [
            'type' => ['type' => 'string'],
            'filters' => ['type' => 'object', 'additionalProperties' => true],
            'async' => ['type' => 'boolean'],
        ], ['type']);
        $tools[] = $this->tool('crm_cancel_export_job', 'Cancel an export job.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_retry_export_job', 'Retry an export job.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_download_export_job', 'Get a safe download URL for an export file.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);

        $tools[] = $this->tool('crm_list_teams', 'List teams visible to the current CRM user.', [
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            'team_type' => ['type' => 'string'],
        ]);
        $tools[] = $this->tool('crm_get_team', 'Get one visible team by public id.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);

        if ($this->can('team.manage')) {
            $tools[] = $this->tool('crm_create_team', 'Create a team.', $this->teamSchema(), ['title']);
            $tools[] = $this->tool('crm_update_team', 'Update safe team fields by public id.', ['public_id' => ['type' => 'string']] + $this->teamSchema(), ['public_id']);
        }

        if ($this->can('department.manage')) {
            $tools[] = $this->tool('crm_list_departments', 'List departments visible to the current CRM user.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            ]);
            $tools[] = $this->tool('crm_get_department', 'Get one department by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_create_department', 'Create a department.', [
                'title' => ['type' => 'string'],
                'code' => ['type' => 'string'],
                'manager_user_id' => ['type' => 'integer'],
            ], ['title']);
            $tools[] = $this->tool('crm_update_department', 'Update safe department fields by public id.', [
                'public_id' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'code' => ['type' => 'string'],
                'manager_user_id' => ['type' => 'integer'],
            ], ['public_id']);
        }

        if ($this->can('counterparty.manage')) {
            $tools[] = $this->tool('crm_list_counterparties', 'List counterparties visible to the current CRM user.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'search' => ['type' => 'string'],
                'counterparty_type' => ['type' => 'string', 'enum' => ['organization', 'individual', 'sole_proprietor', 'legal_entity']],
                'status' => ['type' => 'string'],
            ]);
            $tools[] = $this->tool('crm_get_counterparty', 'Get one counterparty by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_create_counterparty', 'Create a counterparty visible to the authenticated CRM user.', $this->counterpartySchema(), ['title']);
            $tools[] = $this->tool('crm_update_counterparty', 'Update safe counterparty fields by public id.', ['public_id' => ['type' => 'string']] + $this->counterpartySchema(), ['public_id']);
        }

        if ($this->can('company.manage')) {
            $tools[] = $this->tool('crm_list_companies', 'List organization companies visible to the current CRM user.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'search' => ['type' => 'string'],
                'status' => ['type' => 'string'],
            ]);
            $tools[] = $this->tool('crm_get_company', 'Get one company by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_create_company', 'Create an organization company.', [
                'title' => ['type' => 'string'],
                'status' => ['type' => 'string', 'default' => 'active'],
            ], ['title']);
            $tools[] = $this->tool('crm_update_company', 'Update a company by public id.', [
                'public_id' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'status' => ['type' => 'string'],
            ], ['public_id']);
        }

        if ($this->can('client.manage')) {
            $tools[] = $this->tool('crm_list_clients', 'List clients visible to the current CRM user.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'search' => ['type' => 'string'],
                'client_type' => ['type' => 'string', 'enum' => ['individual', 'sole_proprietor', 'legal_entity']],
                'status' => ['type' => 'string'],
            ]);
            $tools[] = $this->tool('crm_get_client', 'Get one client by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_create_client', 'Create a client.', $this->clientSchema(), ['title']);
            $tools[] = $this->tool('crm_update_client', 'Update safe client fields by public id.', ['public_id' => ['type' => 'string']] + $this->clientSchema(), ['public_id']);
        }

        if ($this->can('contact.manage')) {
            $tools[] = $this->tool('crm_list_contacts', 'List contacts visible to the current CRM user.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'search' => ['type' => 'string'],
                'counterparty_public_id' => ['type' => 'string'],
                'company_public_id' => ['type' => 'string'],
                'client_public_id' => ['type' => 'string'],
            ]);
            $tools[] = $this->tool('crm_get_contact', 'Get one contact by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_create_contact', 'Create a contact linked to an optional counterparty, company or client.', $this->contactSchema(), ['full_name']);
            $tools[] = $this->tool('crm_update_contact', 'Update safe contact fields by public id.', ['public_id' => ['type' => 'string']] + $this->contactSchema(), ['public_id']);
        }

        if ($this->can('approval.manage')) {
            $tools[] = $this->tool('crm_list_approvals', 'List approval requests visible to the current CRM user.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'status' => ['type' => 'string', 'enum' => ['pending', 'approved', 'rejected']],
                'entity_type' => ['type' => 'string'],
                'entity_public_id' => ['type' => 'string'],
            ]);
            $tools[] = $this->tool('crm_get_approval', 'Get one approval request by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_create_approval', 'Create an approval request for an entity and reviewer list.', [
                'entity_type' => ['type' => 'string'],
                'entity_public_id' => ['type' => 'string'],
                'reviewer_public_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
                'title' => ['type' => 'string'],
                'comment' => ['type' => 'string'],
            ], ['entity_type', 'entity_public_id', 'reviewer_public_ids']);
            $tools[] = $this->tool('crm_approve_request', 'Approve an approval request where the current user is a pending reviewer.', [
                'public_id' => ['type' => 'string'],
                'comment' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_reject_request', 'Reject an approval request where the current user is a pending reviewer.', [
                'public_id' => ['type' => 'string'],
                'comment' => ['type' => 'string'],
            ], ['public_id']);
        }

        if ($this->can('task.manage')) {
            $tools[] = $this->tool('crm_list_recurring_rules', 'List recurring task/project/reminder/calendar rules.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'entity_type' => ['type' => 'string', 'enum' => ['task', 'project', 'reminder', 'calendar_event']],
                'is_active' => ['type' => 'integer', 'enum' => [0, 1]],
            ]);
            $tools[] = $this->tool('crm_get_recurring_rule', 'Get one recurring rule by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_create_recurring_rule', 'Create a recurring rule using an RRULE string.', $this->recurringSchema(), ['entity_type', 'entity_public_id', 'rrule']);
            $tools[] = $this->tool('crm_update_recurring_rule', 'Update a recurring rule by public id.', ['public_id' => ['type' => 'string']] + $this->recurringSchema(), ['public_id']);
            $tools[] = $this->tool('crm_pause_recurring_rule', 'Pause a recurring rule.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_resume_recurring_rule', 'Resume a recurring rule.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
        }

        if ($this->can('settings.manage')) {
            $tools[] = $this->tool('crm_list_workflow_rules', 'List automation workflow rules visible to the current CRM user.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'trigger_code' => ['type' => 'string'],
                'action_code' => ['type' => 'string'],
                'is_enabled' => ['type' => 'integer', 'enum' => [0, 1]],
            ]);
            $tools[] = $this->tool('crm_get_workflow_rule', 'Get one workflow rule by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_create_workflow_rule', 'Create an automation workflow rule.', $this->workflowSchema(), ['title', 'trigger_code', 'action_code']);
            $tools[] = $this->tool('crm_update_workflow_rule', 'Update an automation workflow rule by public id.', ['public_id' => ['type' => 'string']] + $this->workflowSchema(), ['public_id']);
            $tools[] = $this->tool('crm_list_workflow_runs', 'List workflow execution logs visible to the current CRM user.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'rule_public_id' => ['type' => 'string'],
                'status' => ['type' => 'string', 'enum' => ['success', 'failed', 'error']],
            ]);
            $tools[] = $this->tool('crm_run_workflow_rule_test', 'Run the CRM workflow test harness for a rule. This can execute the rule action in test context.', [
                'public_id' => ['type' => 'string'],
                'context' => ['type' => 'object', 'additionalProperties' => true],
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
            $tools[] = $this->tool('crm_get_project_summary', 'Get summary, milestones, risks and workload for one project.', [
                'project_public_id' => ['type' => 'string'],
            ], ['project_public_id']);
            $tools[] = $this->tool('crm_get_project_timeline', 'Get timeline data for one project.', [
                'project_public_id' => ['type' => 'string'],
                'date_from' => ['type' => 'string'],
                'date_to' => ['type' => 'string'],
                'view_mode' => ['type' => 'string', 'enum' => ['days', 'weeks', 'months']],
            ], ['project_public_id']);
            $tools[] = $this->tool('crm_get_project_milestones_summary', 'Get milestones summary for one project.', [
                'project_public_id' => ['type' => 'string'],
            ], ['project_public_id']);
            $tools[] = $this->tool('crm_get_project_risks', 'Get project risk summary.', [
                'project_public_id' => ['type' => 'string'],
            ], ['project_public_id']);
            $tools[] = $this->tool('crm_get_project_workload', 'Get project workload summary.', [
                'project_public_id' => ['type' => 'string'],
            ], ['project_public_id']);
        }

        if ($this->can('knowledge.view')) {
            $tools[] = $this->tool('crm_get_knowledge_overview', 'Get the knowledge base overview.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                'space_public_id' => ['type' => 'string'],
            ]);
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
            $tools[] = $this->tool('crm_list_knowledge_spaces', 'List knowledge spaces visible to the current CRM user.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            ]);
            $tools[] = $this->tool('crm_list_knowledge_spaces_tree', 'List knowledge spaces as a tree.', [
                'include_archived' => ['type' => 'boolean'],
            ]);
            $tools[] = $this->tool('crm_get_knowledge_space', 'Get one knowledge space by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_get_knowledge_tree', 'Get the page tree for one knowledge space.', [
                'space_public_id' => ['type' => 'string'],
                'depth' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'default' => 10],
            ], ['space_public_id']);
            $tools[] = $this->tool('crm_search_knowledge', 'Search the knowledge base.', [
                'q' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                'space_public_id' => ['type' => 'string'],
                'status' => ['type' => 'string'],
            ], ['q']);
            $tools[] = $this->tool('crm_list_knowledge_recent', 'List recent knowledge pages.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
            ]);
            $tools[] = $this->tool('crm_list_knowledge_popular', 'List popular knowledge pages.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
            ]);
            $tools[] = $this->tool('crm_list_knowledge_review_queue', 'List knowledge pages waiting for review.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
            ]);
            $tools[] = $this->tool('crm_list_knowledge_outdated', 'List outdated knowledge pages.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
            ]);
            $tools[] = $this->tool('crm_list_knowledge_favorites', 'List favorite knowledge pages.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
            ]);
            $tools[] = $this->tool('crm_list_knowledge_page_versions', 'List versions for one knowledge page.', [
                'public_id' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 30],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            ], ['public_id']);
            $tools[] = $this->tool('crm_get_knowledge_page_version', 'Get one knowledge page version.', [
                'public_id' => ['type' => 'string'],
                'version_public_id' => ['type' => 'string'],
            ], ['public_id', 'version_public_id']);
            $tools[] = $this->tool('crm_diff_knowledge_page_version', 'Get a diff for one knowledge page version.', [
                'public_id' => ['type' => 'string'],
                'version_public_id' => ['type' => 'string'],
            ], ['public_id', 'version_public_id']);
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
        if ($this->can('knowledge.comment')) {
            $tools[] = $this->tool('crm_list_knowledge_comments', 'List comments for a knowledge page.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_add_knowledge_comment', 'Add a comment to a knowledge page.', [
                'public_id' => ['type' => 'string'],
                'body' => ['type' => 'string'],
                'parent_public_id' => ['type' => 'string'],
            ], ['public_id', 'body']);
            $tools[] = $this->tool('crm_delete_knowledge_comment', 'Delete a knowledge page comment.', [
                'comment_public_id' => ['type' => 'string'],
            ], ['comment_public_id']);
            $tools[] = $this->tool('crm_resolve_knowledge_comment', 'Resolve a knowledge page comment thread.', [
                'comment_public_id' => ['type' => 'string'],
            ], ['comment_public_id']);
            $tools[] = $this->tool('crm_reopen_knowledge_comment', 'Reopen a knowledge page comment thread.', [
                'comment_public_id' => ['type' => 'string'],
            ], ['comment_public_id']);
            $tools[] = $this->tool('crm_list_knowledge_page_links', 'List links attached to a knowledge page.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_delete_knowledge_page_link', 'Delete a link from a knowledge page.', [
                'link_public_id' => ['type' => 'string'],
            ], ['link_public_id']);
            $tools[] = $this->tool('crm_list_knowledge_page_tags', 'List tags attached to a knowledge page.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_attach_knowledge_page_tag', 'Attach an existing tag to a knowledge page.', [
                'public_id' => ['type' => 'string'],
                'tag_public_id' => ['type' => 'string'],
            ], ['public_id', 'tag_public_id']);
            $tools[] = $this->tool('crm_detach_knowledge_page_tag', 'Detach a tag from a knowledge page.', [
                'public_id' => ['type' => 'string'],
                'tag_public_id' => ['type' => 'string'],
            ], ['public_id', 'tag_public_id']);
        }
        if ($this->can('knowledge.edit')) {
            $tools[] = $this->tool('crm_list_knowledge_files', 'List files attached to a knowledge page.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_delete_knowledge_file', 'Delete a file attached to a knowledge page.', [
                'file_public_id' => ['type' => 'string'],
            ], ['file_public_id']);
            $tools[] = $this->tool('crm_delete_knowledge_draft', 'Delete your draft copy for a knowledge page.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
        }
        if ($this->can('knowledge.publish')) {
            $tools[] = $this->tool('crm_publish_knowledge_page', 'Publish a knowledge page.', [
                'public_id' => ['type' => 'string'],
                'change_summary' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_archive_knowledge_page', 'Archive a knowledge page.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_restore_knowledge_page', 'Restore a knowledge page from draft or archive.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_request_knowledge_review', 'Request review for a knowledge page.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_approve_knowledge_review', 'Approve review for a knowledge page.', [
                'public_id' => ['type' => 'string'],
                'change_summary' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_reject_knowledge_review', 'Reject review for a knowledge page.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_duplicate_knowledge_page', 'Duplicate a knowledge page.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_move_knowledge_page', 'Move a knowledge page inside the knowledge structure.', [
                'public_id' => ['type' => 'string'],
                'space_public_id' => ['type' => 'string'],
                'parent_public_id' => ['type' => 'string'],
                'sort_order' => ['type' => 'integer'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_lock_knowledge_page', 'Lock a knowledge page for editing.', [
                'public_id' => ['type' => 'string'],
                'reason' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_unlock_knowledge_page', 'Unlock a knowledge page.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_delete_knowledge_page', 'Delete a knowledge page.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
        }
        if ($this->can('knowledge.permission_manage')) {
            $tools[] = $this->tool('crm_get_knowledge_space_permissions', 'List knowledge space permissions.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_add_knowledge_space_permission', 'Add a knowledge space permission.', [
                'public_id' => ['type' => 'string'],
                'subject_type' => ['type' => 'string'],
                'subject_id' => ['type' => 'integer'],
                'subject_public_id' => ['type' => 'string'],
                'access_level' => ['type' => 'string'],
            ], ['public_id', 'subject_type']);
            $tools[] = $this->tool('crm_remove_knowledge_space_permission', 'Remove a knowledge space permission.', [
                'permission_id' => ['type' => 'integer'],
            ], ['permission_id']);
            $tools[] = $this->tool('crm_get_knowledge_page_permissions', 'List knowledge page permissions.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_add_knowledge_page_permission', 'Add a knowledge page permission.', [
                'public_id' => ['type' => 'string'],
                'subject_type' => ['type' => 'string'],
                'subject_id' => ['type' => 'integer'],
                'subject_public_id' => ['type' => 'string'],
                'access_level' => ['type' => 'string'],
            ], ['public_id', 'subject_type']);
            $tools[] = $this->tool('crm_remove_knowledge_page_permission', 'Remove a knowledge page permission.', [
                'permission_id' => ['type' => 'integer'],
            ], ['permission_id']);
        }
        if ($this->can('knowledge.admin')) {
            $tools[] = $this->tool('crm_get_admin_knowledge_settings', 'Get admin knowledge settings.', []);
            $tools[] = $this->tool('crm_update_admin_knowledge_settings', 'Update admin knowledge settings.', [
                'settings' => ['type' => 'object', 'additionalProperties' => true],
            ], ['settings']);
            $tools[] = $this->tool('crm_reindex_knowledge', 'Rebuild knowledge search index.', []);
            $tools[] = $this->tool('crm_rebuild_knowledge_permissions', 'Rebuild knowledge permissions versioning.', []);
            $tools[] = $this->tool('crm_cleanup_knowledge_drafts', 'Cleanup old knowledge drafts.', []);
        }

        if ($this->can('task.manage')) {
            $tools[] = $this->tool('crm_list_calendar_events', 'List calendar events visible to the current CRM user.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'starts_from' => ['type' => 'string', 'description' => 'Start date/time lower bound.'],
                'starts_to' => ['type' => 'string', 'description' => 'Start date/time upper bound.'],
                'project_public_id' => ['type' => 'string'],
                'task_public_id' => ['type' => 'string'],
            ]);
            $tools[] = $this->tool('crm_get_calendar_agenda', 'Get the current user day or week agenda.', [
                'period' => ['type' => 'string', 'enum' => ['day', 'week'], 'default' => 'day'],
                'date' => ['type' => 'string', 'description' => 'Date in YYYY-MM-DD format. Defaults to today.'],
            ]);
            $tools[] = $this->tool('crm_create_calendar_event', 'Create a calendar event for the current CRM user.', [
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'starts_at' => ['type' => 'string'],
                'ends_at' => ['type' => 'string'],
                'project_public_id' => ['type' => 'string'],
                'task_public_id' => ['type' => 'string'],
            ], ['title', 'starts_at']);
            $tools[] = $this->tool('crm_list_milestones', 'List milestones for an accessible project.', [
                'project_public_id' => ['type' => 'string'],
            ], ['project_public_id']);
            $tools[] = $this->tool('crm_get_milestone', 'Get one milestone by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_create_milestone', 'Create a milestone in an accessible project.', $this->milestoneSchema(), ['project_public_id', 'title']);
            $tools[] = $this->tool('crm_update_milestone', 'Update a milestone.', ['public_id' => ['type' => 'string']] + $this->milestoneSchema(), ['public_id']);
            $tools[] = $this->tool('crm_list_reminders', 'List current-user reminders.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'status' => ['type' => 'string', 'enum' => ['new', 'pending', 'done', 'cancelled']],
                'task_public_id' => ['type' => 'string'],
            ]);
            $tools[] = $this->tool('crm_get_reminder', 'Get one current-user reminder by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_create_reminder', 'Create a reminder for the current user.', $this->reminderSchema(), ['remind_at']);
            $tools[] = $this->tool('crm_update_reminder', 'Update a current-user reminder.', ['public_id' => ['type' => 'string']] + $this->reminderSchema(), ['public_id']);
            $tools[] = $this->tool('crm_delete_reminder', 'Delete a current-user reminder.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_list_saved_views', 'List saved views available to the current user.', $this->savedViewListSchema());
            $tools[] = $this->tool('crm_get_saved_view', 'Get one saved view by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_create_saved_view', 'Create a saved view.', $this->savedViewSchema(), ['title']);
            $tools[] = $this->tool('crm_update_saved_view', 'Update a saved view.', ['public_id' => ['type' => 'string']] + $this->savedViewSchema(), ['public_id']);
            $tools[] = $this->tool('crm_archive_saved_view', 'Archive a saved view.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_duplicate_saved_view', 'Duplicate a saved view.', [
                'public_id' => ['type' => 'string'],
                'title' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_pin_saved_view', 'Update current-user pin preference for a saved view.', [
                'public_id' => ['type' => 'string'],
                'is_pinned' => ['type' => 'boolean'],
                'sort_order' => ['type' => 'integer'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_get_saved_view_task_filters', 'Resolve task filters for a saved view.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_list_sticky_notes', 'List sticky notes visible to the current user.', $this->stickyNoteListSchema());
            $tools[] = $this->tool('crm_get_sticky_note', 'Get one sticky note by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_create_sticky_note', 'Create a sticky note.', $this->stickyNoteSchema(), ['body']);
            $tools[] = $this->tool('crm_update_sticky_note', 'Update a sticky note.', ['public_id' => ['type' => 'string']] + $this->stickyNoteSchema(), ['public_id']);
            $tools[] = $this->tool('crm_archive_sticky_note', 'Archive a sticky note.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_unarchive_sticky_note', 'Unarchive a sticky note.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_list_estimate_sets', 'List estimate sets available to the current user.', $this->estimateSetListSchema());
            $tools[] = $this->tool('crm_get_estimate_set', 'Get one estimate set by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_create_estimate_set', 'Create an estimate set.', $this->estimateSetSchema(), ['name']);
            $tools[] = $this->tool('crm_update_estimate_set', 'Update an estimate set.', ['public_id' => ['type' => 'string']] + $this->estimateSetSchema(), ['public_id']);
            $tools[] = $this->tool('crm_list_estimate_options', 'List options for an estimate set.', [
                'estimate_set_public_id' => ['type' => 'string'],
                'active_only' => ['type' => 'boolean'],
            ], ['estimate_set_public_id']);
            $tools[] = $this->tool('crm_create_estimate_option', 'Create an option inside an estimate set.', ['estimate_set_public_id' => ['type' => 'string']] + $this->estimateOptionSchema(), ['estimate_set_public_id', 'label']);
            $tools[] = $this->tool('crm_update_estimate_option', 'Update an estimate option.', ['public_id' => ['type' => 'string']] + $this->estimateOptionSchema(), ['public_id']);
            $tools[] = $this->tool('crm_list_task_estimates', 'List estimates assigned to a visible task.', [
                'task_public_id' => ['type' => 'string'],
            ], ['task_public_id']);
            $tools[] = $this->tool('crm_assign_task_estimate', 'Assign or update a task estimate.', [
                'task_public_id' => ['type' => 'string'],
                'estimate_set_public_id' => ['type' => 'string'],
                'estimate_option_public_id' => ['type' => 'string'],
                'numeric_value' => ['type' => 'number'],
                'currency_code' => ['type' => 'string'],
                'note' => ['type' => 'string'],
            ], ['task_public_id', 'estimate_set_public_id']);
            $tools[] = $this->tool('crm_remove_task_estimate', 'Remove an estimate set assignment from a task.', [
                'task_public_id' => ['type' => 'string'],
                'estimate_set_public_id' => ['type' => 'string'],
            ], ['task_public_id', 'estimate_set_public_id']);
            $tools[] = $this->tool('crm_get_project_estimate_summary', 'Get estimate summary for a project.', [
                'project_public_id' => ['type' => 'string'],
                'estimate_set_public_id' => ['type' => 'string'],
            ], ['project_public_id']);
            $tools[] = $this->tool('crm_get_cycle_estimate_summary', 'Get estimate summary for a work cycle/sprint.', [
                'cycle_public_id' => ['type' => 'string'],
                'estimate_set_public_id' => ['type' => 'string'],
            ], ['cycle_public_id']);
            $tools[] = $this->tool('crm_get_module_estimate_summary', 'Get estimate summary for a project module.', [
                'module_public_id' => ['type' => 'string'],
                'estimate_set_public_id' => ['type' => 'string'],
            ], ['module_public_id']);
            $tools[] = $this->tool('crm_list_custom_fields', 'List custom fields.', $this->customFieldListSchema());
            $tools[] = $this->tool('crm_get_custom_field', 'Get one custom field by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_create_custom_field', 'Create a custom field definition.', $this->customFieldSchema(), ['scope', 'code', 'title', 'type']);
            $tools[] = $this->tool('crm_update_custom_field', 'Update a custom field definition.', ['public_id' => ['type' => 'string']] + $this->customFieldSchema(), ['public_id']);
            $tools[] = $this->tool('crm_get_custom_field_values', 'Get custom field values for an entity.', [
                'entity_type' => ['type' => 'string'],
                'entity_public_id' => ['type' => 'string'],
            ], ['entity_type', 'entity_public_id']);
            $tools[] = $this->tool('crm_set_custom_field_values', 'Set custom field values for an entity.', [
                'entity_type' => ['type' => 'string'],
                'entity_public_id' => ['type' => 'string'],
                'values' => ['type' => 'object', 'additionalProperties' => true],
            ], ['entity_type', 'entity_public_id', 'values']);
            $tools[] = $this->tool('crm_list_sla_policies', 'List SLA policies.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'search' => ['type' => 'string'],
            ]);
            $tools[] = $this->tool('crm_get_sla_policy', 'Get one SLA policy by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_create_sla_policy', 'Create an SLA policy.', $this->slaPolicySchema(), ['title', 'response_minutes', 'resolve_minutes']);
            $tools[] = $this->tool('crm_update_sla_policy', 'Update an SLA policy.', ['public_id' => ['type' => 'string']] + $this->slaPolicySchema(), ['public_id']);
            $tools[] = $this->tool('crm_get_sla_report', 'Get SLA report summary.', []);
            $tools[] = $this->tool('crm_assign_sla_to_task', 'Assign an SLA policy to a task.', [
                'task_public_id' => ['type' => 'string'],
                'sla_policy_public_id' => ['type' => 'string'],
            ], ['task_public_id', 'sla_policy_public_id']);
            $tools[] = $this->tool('crm_list_templates', 'List task or project templates.', $this->templateListSchema(), ['kind']);
            $tools[] = $this->tool('crm_get_template', 'Get one task or project template.', [
                'kind' => ['type' => 'string', 'enum' => ['task', 'project']],
                'public_id' => ['type' => 'string'],
            ], ['kind', 'public_id']);
            $tools[] = $this->tool('crm_create_template', 'Create a task or project template.', $this->templateSchema(), ['kind', 'title']);
            $tools[] = $this->tool('crm_update_template', 'Update a task or project template.', ['public_id' => ['type' => 'string']] + $this->templateSchema(), ['kind', 'public_id']);
            $tools[] = $this->tool('crm_apply_template', 'Apply a task or project template and create the target entity.', [
                'kind' => ['type' => 'string', 'enum' => ['task', 'project']],
                'public_id' => ['type' => 'string'],
            ], ['kind', 'public_id']);
            $tools[] = $this->tool('crm_list_files', 'List files linked to a visible task, project or knowledge page.', [
                'entity_type' => ['type' => 'string', 'enum' => ['task', 'project', 'knowledge_page']],
                'entity_public_id' => ['type' => 'string'],
            ], ['entity_type', 'entity_public_id']);
            $tools[] = $this->tool('crm_get_file', 'Get file metadata by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_upload_file_base64', 'Upload a small base64-encoded file to a visible task, project or knowledge page.', [
                'entity_type' => ['type' => 'string', 'enum' => ['task', 'project', 'knowledge_page']],
                'entity_public_id' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'mime_type' => ['type' => 'string'],
                'content_base64' => ['type' => 'string'],
            ], ['entity_type', 'entity_public_id', 'name', 'content_base64']);
            $tools[] = $this->tool('crm_get_file_download_info', 'Get a safe API download URL for a file if the current user can access it.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_delete_file', 'Soft-delete a file by public id when CRM rules allow it.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_list_statuses', 'List task/project status dictionary entries.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'scope' => ['type' => 'string'],
                'is_active' => ['type' => 'integer', 'enum' => [0, 1]],
            ]);
            $tools[] = $this->tool('crm_get_status', 'Get one status dictionary entry by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_create_status', 'Create a status dictionary entry.', $this->statusSchema(), ['scope', 'code', 'title']);
            $tools[] = $this->tool('crm_update_status', 'Update a status dictionary entry by public id.', ['public_id' => ['type' => 'string']] + $this->statusSchema(), ['public_id']);
            $tools[] = $this->tool('crm_list_tags', 'List tags.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'search' => ['type' => 'string'],
            ]);
            $tools[] = $this->tool('crm_get_tag', 'Get one tag by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_create_tag', 'Create a tag.', $this->tagSchema(), ['title']);
            $tools[] = $this->tool('crm_update_tag', 'Update a tag by public id.', ['public_id' => ['type' => 'string']] + $this->tagSchema(), ['public_id']);
            $tools[] = $this->tool('crm_list_task_tags', 'List tags attached to a task.', [
                'task_public_id' => ['type' => 'string'],
            ], ['task_public_id']);
            $tools[] = $this->tool('crm_attach_task_tag', 'Attach a tag to a visible task.', [
                'task_public_id' => ['type' => 'string'],
                'tag_public_id' => ['type' => 'string'],
            ], ['task_public_id', 'tag_public_id']);
            $tools[] = $this->tool('crm_detach_task_tag', 'Detach a tag from a visible task.', [
                'task_public_id' => ['type' => 'string'],
                'tag_public_id' => ['type' => 'string'],
            ], ['task_public_id', 'tag_public_id']);
            $tools[] = $this->tool('crm_list_task_checklists', 'List checklists for a visible task.', [
                'task_public_id' => ['type' => 'string'],
            ], ['task_public_id']);
            $tools[] = $this->tool('crm_create_task_checklist', 'Create a checklist on a visible task.', [
                'task_public_id' => ['type' => 'string'],
                'title' => ['type' => 'string'],
            ], ['task_public_id', 'title']);
            $tools[] = $this->tool('crm_update_checklist', 'Update a checklist title.', [
                'public_id' => ['type' => 'string'],
                'title' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_list_checklist_items', 'List checklist items.', [
                'checklist_public_id' => ['type' => 'string'],
            ], ['checklist_public_id']);
            $tools[] = $this->tool('crm_create_checklist_item', 'Create a checklist item.', [
                'checklist_public_id' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'is_done' => ['type' => 'boolean'],
                'sort_order' => ['type' => 'integer'],
            ], ['checklist_public_id', 'title']);
            $tools[] = $this->tool('crm_update_checklist_item', 'Update a checklist item.', [
                'public_id' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'is_done' => ['type' => 'boolean'],
                'sort_order' => ['type' => 'integer'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_list_dependencies', 'List task dependencies visible to the current CRM user.', [
                'task_public_id' => ['type' => 'string'],
                'depends_on_task_public_id' => ['type' => 'string'],
            ]);
            $tools[] = $this->tool('crm_create_dependency', 'Create a dependency between visible tasks.', [
                'task_public_id' => ['type' => 'string'],
                'depends_on_task_public_id' => ['type' => 'string'],
                'dependency_type' => ['type' => 'string', 'enum' => ['FS', 'SS', 'FF', 'SF', 'BLOCKS'], 'default' => 'FS'],
            ], ['task_public_id', 'depends_on_task_public_id']);
            $tools[] = $this->tool('crm_list_worklogs', 'List worklog entries visible to the current CRM user.', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'task_public_id' => ['type' => 'string'],
                'user_public_id' => ['type' => 'string'],
                'date_from' => ['type' => 'string'],
                'date_to' => ['type' => 'string'],
            ]);
            $tools[] = $this->tool('crm_get_worklog', 'Get one worklog entry by public id.', [
                'public_id' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_create_worklog', 'Create a worklog entry.', [
                'minutes_spent' => ['type' => 'integer', 'minimum' => 1],
                'task_public_id' => ['type' => 'string'],
                'note' => ['type' => 'string'],
                'logged_at' => ['type' => 'string'],
                'user_public_id' => ['type' => 'string'],
            ], ['minutes_spent']);
            $tools[] = $this->tool('crm_update_worklog', 'Update a worklog entry.', [
                'public_id' => ['type' => 'string'],
                'minutes_spent' => ['type' => 'integer', 'minimum' => 1],
                'task_public_id' => ['type' => 'string'],
                'note' => ['type' => 'string'],
                'logged_at' => ['type' => 'string'],
            ], ['public_id']);
            $tools[] = $this->tool('crm_get_worklog_summary', 'Get worklog summary grouped by day.', [
                'date_from' => ['type' => 'string'],
                'date_to' => ['type' => 'string'],
                'team_public_id' => ['type' => 'string'],
            ]);
        }

        $tools[] = $this->tool('crm_list_ideas', 'List visible CRM ideas.', [
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
            'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
            'status' => ['type' => 'string'],
            'category' => ['type' => 'string'],
            'sort' => ['type' => 'string', 'enum' => ['votes', 'newest', 'oldest', 'comments'], 'default' => 'votes'],
            'period' => ['type' => 'string', 'enum' => ['today', 'week', 'month']],
        ]);
        $tools[] = $this->tool('crm_get_idea', 'Get one visible CRM idea by public id.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_create_idea', 'Create a new CRM idea as the authenticated user.', [
            'title' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'category' => ['type' => 'string'],
            'region' => ['type' => 'string'],
            'visibility' => ['type' => 'string', 'enum' => ['public', 'private'], 'default' => 'public'],
            'target_date' => ['type' => 'string'],
        ], ['title']);
        $tools[] = $this->tool('crm_add_idea_comment', 'Add a comment to a visible CRM idea.', [
            'idea_public_id' => ['type' => 'string'],
            'body' => ['type' => 'string'],
        ], ['idea_public_id', 'body']);

        $tools[] = $this->tool('crm_list_chats', 'List chats where the current CRM user is a participant.', [
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
            'archived' => ['type' => 'boolean', 'default' => false],
        ]);
        $tools[] = $this->tool('crm_get_chat', 'Get one chat where the current CRM user is a participant.', [
            'chat_public_id' => ['type' => 'string'],
        ], ['chat_public_id']);
        $tools[] = $this->tool('crm_create_chat', 'Create a direct, team, project or group chat.', [
            'type' => ['type' => 'string', 'enum' => ['direct', 'project', 'team', 'group'], 'default' => 'direct'],
            'user_id' => ['type' => 'integer'],
            'project_id' => ['type' => 'integer'],
            'team_id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'participant_public_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
        ]);
        $tools[] = $this->tool('crm_get_chat_participants', 'Get chat participants visible to the current CRM user.', [
            'chat_public_id' => ['type' => 'string'],
        ], ['chat_public_id']);
        $tools[] = $this->tool('crm_list_chat_messages', 'List messages from a chat where the current CRM user is a participant.', [
            'chat_public_id' => ['type' => 'string'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 30],
            'before_id' => ['type' => 'integer'],
            'after_id' => ['type' => 'integer'],
        ], ['chat_public_id']);
        $tools[] = $this->tool('crm_send_chat_message', 'Send a text message to a chat where the current CRM user is a participant.', [
            'chat_public_id' => ['type' => 'string'],
            'text' => ['type' => 'string', 'description' => 'Message text, up to 4000 characters.'],
            'reply_to_message_public_id' => ['type' => 'string'],
        ], ['chat_public_id', 'text']);
        $tools[] = $this->tool('crm_edit_chat_message', 'Edit one of your chat messages within the allowed time window.', [
            'chat_public_id' => ['type' => 'string'],
            'message_public_id' => ['type' => 'string'],
            'text' => ['type' => 'string'],
        ], ['chat_public_id', 'message_public_id', 'text']);
        $tools[] = $this->tool('crm_delete_chat_message', 'Soft-delete one of your chat messages within the allowed time window.', [
            'chat_public_id' => ['type' => 'string'],
            'message_public_id' => ['type' => 'string'],
        ], ['chat_public_id', 'message_public_id']);
        $tools[] = $this->tool('crm_upload_chat_attachment', 'Upload a chat attachment as base64-encoded content.', [
            'chat_public_id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'mime_type' => ['type' => 'string'],
            'content_base64' => ['type' => 'string'],
            'text' => ['type' => 'string'],
        ], ['chat_public_id', 'name', 'content_base64']);
        $tools[] = $this->tool('crm_list_chat_attachments', 'List attachments from a chat.', [
            'chat_public_id' => ['type' => 'string'],
        ], ['chat_public_id']);
        $tools[] = $this->tool('crm_get_chat_settings', 'Get your current settings for a chat.', [
            'chat_public_id' => ['type' => 'string'],
        ], ['chat_public_id']);
        $tools[] = $this->tool('crm_update_chat_settings', 'Update your current settings for a chat.', [
            'chat_public_id' => ['type' => 'string'],
            'is_favorite' => ['type' => 'boolean'],
            'is_muted' => ['type' => 'boolean'],
            'muted_until' => ['type' => 'string'],
        ], ['chat_public_id']);
        $tools[] = $this->tool('crm_mark_chat_read', 'Mark one chat as read.', [
            'chat_public_id' => ['type' => 'string'],
        ], ['chat_public_id']);
        $tools[] = $this->tool('crm_get_chat_unread_count', 'Get the unread chat count for the current user.', []);
        $tools[] = $this->tool('crm_archive_chat', 'Archive one chat for its creator.', [
            'chat_public_id' => ['type' => 'string'],
        ], ['chat_public_id']);
        $tools[] = $this->tool('crm_restore_chat', 'Restore one archived chat for its creator.', [
            'chat_public_id' => ['type' => 'string'],
        ], ['chat_public_id']);
        $tools[] = $this->tool('crm_list_notifications', 'List notifications for the current CRM user.', [
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            'category' => ['type' => 'string'],
            'is_read' => ['type' => 'integer', 'enum' => [0, 1]],
        ]);
        $tools[] = $this->tool('crm_get_notification_counters', 'Get notification counters for the current CRM user.', []);
        $tools[] = $this->tool('crm_create_notification', 'Create a notification for the current user, or for a target user when permitted by CRM rules.', [
            'title' => ['type' => 'string'],
            'body' => ['type' => 'string'],
            'category' => ['type' => 'string'],
            'entity_type' => ['type' => 'string'],
            'entity_public_id' => ['type' => 'string'],
            'action_code' => ['type' => 'string'],
            'link' => ['type' => 'string'],
            'user_public_id' => ['type' => 'string'],
        ], ['title']);
        $tools[] = $this->tool('crm_list_push_subscriptions', 'List push subscriptions for the current CRM user.', [
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
        ]);
        $tools[] = $this->tool('crm_create_push_subscription', 'Create or update a push subscription for the current CRM user.', [
            'endpoint' => ['type' => 'string'],
            'p256dh' => ['type' => 'string'],
            'auth' => ['type' => 'string'],
            'device_label' => ['type' => 'string'],
            'user_agent' => ['type' => 'string'],
        ], ['endpoint', 'p256dh', 'auth']);
        $tools[] = $this->tool('crm_delete_push_subscription', 'Delete one push subscription by public id.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_send_push_test', 'Send a test push notification to the current user.', []);
        $tools[] = $this->tool('crm_mark_notification_read', 'Mark one current-user notification as read.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_mark_notification_unread', 'Mark one current-user notification as unread.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_mark_all_notifications_read', 'Mark all current-user notifications as read, optionally by category.', [
            'category' => ['type' => 'string'],
        ]);
        $tools[] = $this->tool('crm_list_favorites', 'List favorites visible to the current CRM user.', $this->collaborationListSchema());
        $tools[] = $this->tool('crm_create_favorite', 'Add a visible task, project or comment to current-user favorites.', $this->entityActionSchema(), ['entity_type', 'entity_public_id']);
        $tools[] = $this->tool('crm_delete_favorite', 'Remove a favorite by public id.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_list_subscriptions', 'List current-user subscriptions.', $this->collaborationListSchema());
        $tools[] = $this->tool('crm_create_subscription', 'Subscribe the current user to a visible task, project or comment.', $this->entityActionSchema(), ['entity_type', 'entity_public_id']);
        $tools[] = $this->tool('crm_delete_subscription', 'Remove a subscription by public id.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_list_reactions', 'List reactions visible to the current CRM user.', $this->collaborationListSchema() + [
            'reaction' => ['type' => 'string', 'enum' => ['like', 'love', 'laugh', 'wow', 'sad', 'angry', 'up']],
        ]);
        $tools[] = $this->tool('crm_add_reaction', 'Add or update the current user reaction on a visible task, project or comment.', $this->entityActionSchema() + [
            'reaction' => ['type' => 'string', 'enum' => ['like', 'love', 'laugh', 'wow', 'sad', 'angry', 'up']],
        ], ['entity_type', 'entity_public_id', 'reaction']);
        $tools[] = $this->tool('crm_remove_reaction', 'Remove a reaction by public id.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_list_mentions', 'List mentions visible to the current CRM user.', $this->collaborationListSchema() + [
            'mentioned_user_public_id' => ['type' => 'string'],
        ]);
        $tools[] = $this->tool('crm_add_mention', 'Mention a user on a visible task, project or comment.', $this->entityActionSchema() + [
            'mentioned_user_public_id' => ['type' => 'string'],
        ], ['entity_type', 'entity_public_id', 'mentioned_user_public_id']);
        $tools[] = $this->tool('crm_delete_mention', 'Delete a mention by public id when CRM rules allow it.', [
            'public_id' => ['type' => 'string'],
        ], ['public_id']);
        $tools[] = $this->tool('crm_get_activity_feed', 'Get the activity feed visible to the current CRM user.', [
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            'entity_type' => ['type' => 'string'],
            'entity_public_id' => ['type' => 'string'],
            'channel' => ['type' => 'string'],
        ]);
        $tools[] = $this->tool('crm_get_activity_history', 'Get activity history for a visible entity.', [
            'entity_type' => ['type' => 'string'],
            'public_id' => ['type' => 'string'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
        ], ['entity_type', 'public_id']);

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
            'crm_get_current_user' => $this->toolResult($this->crmGetCurrentUser()),
            'crm_get_profile' => $this->toolResult($this->crmGetProfile()),
            'crm_update_profile' => $this->toolResult($this->crmUpdateProfile($arguments)),
            'crm_get_profile_preferences' => $this->toolResult($this->crmGetProfilePreferences()),
            'crm_update_profile_preferences' => $this->toolResult($this->crmUpdateProfilePreferences($arguments)),
            'crm_change_profile_password' => $this->toolResult($this->crmChangeProfilePassword($arguments)),
            'crm_list_security_sessions' => $this->toolResult($this->crmListSecuritySessions($arguments)),
            'crm_revoke_security_session' => $this->toolResult($this->crmRevokeSecuritySession($arguments)),
            'crm_revoke_other_security_sessions' => $this->toolResult($this->crmRevokeOtherSecuritySessions()),
            'crm_revoke_device_sessions' => $this->toolResult($this->crmRevokeDeviceSessions($arguments)),
            'crm_get_menu' => $this->toolResult($this->crmGetMenu()),
            'crm_get_menu_preferences' => $this->toolResult($this->crmGetMenuPreferences()),
            'crm_save_menu_preferences' => $this->toolResult($this->crmSaveMenuPreferences($arguments)),
            'crm_list_roles' => $this->withPermissionAny(['role.view', 'role.manage'], fn() => $this->toolResult($this->crmListRoles($arguments))),
            'crm_list_permissions' => $this->withPermissionAny(['role.view', 'role.manage'], fn() => $this->toolResult($this->crmListPermissions())),
            'crm_get_role_permissions' => $this->withPermissionAny(['role.view', 'role.manage'], fn() => $this->toolResult($this->crmGetRolePermissions($arguments))),
            'crm_list_settings' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmListSettings($arguments))),
            'crm_get_setting' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmGetSetting($arguments))),
            'crm_list_feature_flags' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmListFeatureFlags($arguments))),
            'crm_update_feature_flag' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmUpdateFeatureFlag($arguments))),
            'crm_list_modules' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmListModules())),
            'crm_get_module' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmGetModule($arguments))),
            'crm_install_module' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmInstallModule($arguments))),
            'crm_activate_module' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmActivateModule($arguments))),
            'crm_deactivate_module' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmDeactivateModule($arguments))),
            'crm_uninstall_module' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmUninstallModule($arguments))),
            'crm_get_module_config' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmGetModuleConfig($arguments))),
            'crm_update_module_config' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmUpdateModuleConfig($arguments))),
            'crm_get_module_health' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmGetModuleHealth($arguments))),
            'crm_get_module_migrations' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmGetModuleMigrations($arguments))),
            'crm_get_module_errors' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmGetModuleErrors($arguments))),
            'crm_clear_module_errors' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmClearModuleErrors($arguments))),
            'crm_install_module_from_url' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmInstallModuleFromUrl($arguments))),
            'crm_install_module_from_file' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmInstallModuleFromFile($arguments))),
            'crm_get_cache_stats' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmGetCacheStats())),
            'crm_clear_cache' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmClearCache())),
            'crm_get_ops_system' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmGetOpsSystem())),
            'crm_get_ops_metrics' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmGetOpsMetrics())),
            'crm_run_ops_jobs' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmRunOpsJobs($arguments))),
            'crm_get_core_version' => $this->withPermissionAny(['settings.manage'], fn() => $this->toolResult($this->crmGetCoreVersion())),
            'crm_get_core_update_status' => $this->withPermissionAny(['settings.manage'], fn() => $this->toolResult($this->crmGetCoreUpdateStatus())),
            'crm_check_core_update' => $this->withPermissionAny(['settings.manage'], fn() => $this->toolResult($this->crmCheckCoreUpdate())),
            'crm_run_core_update_preflight' => $this->withPermissionAny(['settings.manage'], fn() => $this->toolResult($this->crmRunCoreUpdatePreflight($arguments))),
            'crm_get_core_update_changes' => $this->withPermissionAny(['settings.manage'], fn() => $this->toolResult($this->crmGetCoreUpdateChanges($arguments))),
            'crm_get_core_update_session' => $this->withPermissionAny(['settings.manage'], fn() => $this->toolResult($this->crmGetCoreUpdateSession())),
            'crm_get_core_update_history' => $this->withPermissionAny(['settings.manage'], fn() => $this->toolResult($this->crmGetCoreUpdateHistory())),
            'crm_get_core_update_log' => $this->withPermissionAny(['settings.manage'], fn() => $this->toolResult($this->crmGetCoreUpdateLog($arguments))),
            'crm_list_audit_log' => $this->withPermissionAny(['logs.view', 'settings.manage'], fn() => $this->toolResult($this->crmListAuditLog($arguments))),
            'crm_list_security_log' => $this->withPermissionAny(['logs.view', 'settings.manage'], fn() => $this->toolResult($this->crmListSecurityLog($arguments))),
            'crm_list_api_clients' => $this->withPermissionAny(['api_client.view', 'api_client.manage'], fn() => $this->toolResult($this->crmListApiClients($arguments))),
            'crm_get_api_client' => $this->withPermissionAny(['api_client.view', 'api_client.manage'], fn() => $this->toolResult($this->crmGetApiClient($arguments))),
            'crm_list_api_client_keys' => $this->withPermissionAny(['api_client.view', 'api_client.manage'], fn() => $this->toolResult($this->crmListApiClientKeys($arguments))),
            'crm_list_webhooks' => $this->withPermission('webhook.manage', fn() => $this->toolResult($this->crmListWebhooks($arguments))),
            'crm_list_webhook_deliveries' => $this->withPermission('webhook.manage', fn() => $this->toolResult($this->crmListWebhookDeliveries($arguments))),
            'crm_get_dashboard_summary' => $this->toolResult($this->crmGetDashboardSummary()),
            'crm_get_analytics_summary' => $this->withPermissionAny(['analytics.view', 'task.manage'], fn() => $this->toolResult($this->crmGetAnalyticsSummary())),
            'crm_list_analytics_projects' => $this->withPermissionAny(['analytics.view', 'task.manage'], fn() => $this->toolResult($this->crmListAnalyticsProjects($arguments))),
            'crm_list_analytics_users' => $this->withPermissionAny(['analytics.view', 'task.manage'], fn() => $this->toolResult($this->crmListAnalyticsUsers($arguments))),
            'crm_list_intake_items' => $this->withPermissionAny(['intake.view', 'intake.manage'], fn() => $this->toolResult($this->crmListIntakeItems($arguments))),
            'crm_get_intake_item' => $this->withPermissionAny(['intake.view', 'intake.manage'], fn() => $this->toolResult($this->crmGetIntakeItem($arguments))),
            'crm_create_intake_item' => $this->withPermissionAny(['intake.create', 'intake.manage'], fn() => $this->toolResult($this->crmCreateIntakeItem($arguments))),
            'crm_update_intake_item' => $this->withPermissionAny(['intake.manage'], fn() => $this->toolResult($this->crmUpdateIntakeItem($arguments))),
            'crm_delete_intake_item' => $this->withPermissionAny(['intake.delete', 'intake.manage'], fn() => $this->toolResult($this->crmDeleteIntakeItem($arguments))),
            'crm_accept_intake_item' => $this->withPermissionAny(['intake.accept', 'intake.manage'], fn() => $this->toolResult($this->crmAcceptIntakeItem($arguments))),
            'crm_reject_intake_item' => $this->withPermissionAny(['intake.manage'], fn() => $this->toolResult($this->crmRejectIntakeItem($arguments))),
            'crm_snooze_intake_item' => $this->withPermissionAny(['intake.manage'], fn() => $this->toolResult($this->crmSnoozeIntakeItem($arguments))),
            'crm_list_project_modules' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmListProjectModules($arguments))),
            'crm_get_project_module' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmGetProjectModule($arguments))),
            'crm_create_project_module' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmCreateProjectModule($arguments))),
            'crm_update_project_module' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmUpdateProjectModule($arguments))),
            'crm_archive_project_module' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmArchiveProjectModule($arguments))),
            'crm_delete_project_module' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmDeleteProjectModule($arguments))),
            'crm_list_project_module_tasks' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmListProjectModuleTasks($arguments))),
            'crm_list_project_module_members' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmListProjectModuleMembers($arguments))),
            'crm_list_project_module_links' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmListProjectModuleLinks($arguments))),
            'crm_add_tasks_to_project_module' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmAddTasksToProjectModule($arguments))),
            'crm_add_members_to_project_module' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmAddMembersToProjectModule($arguments))),
            'crm_remove_project_module_task' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmRemoveProjectModuleTask($arguments))),
            'crm_remove_project_module_member' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmRemoveProjectModuleMember($arguments))),
            'crm_add_project_module_link' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmAddProjectModuleLink($arguments))),
            'crm_update_project_module_link' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmUpdateProjectModuleLink($arguments))),
            'crm_delete_project_module_link' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmDeleteProjectModuleLink($arguments))),
            'crm_list_recycle_bin' => $this->withPermission('recycle_bin.manage', fn() => $this->toolResult($this->crmListRecycleBin($arguments))),
            'crm_restore_recycle_bin_item' => $this->withPermission('recycle_bin.manage', fn() => $this->toolResult($this->crmRestoreRecycleBinItem($arguments))),
            'crm_purge_recycle_bin_item' => $this->withPermission('recycle_bin.manage', fn() => $this->toolResult($this->crmPurgeRecycleBinItem($arguments))),
            'crm_list_import_jobs' => $this->withPermission('import.manage', fn() => $this->toolResult($this->crmListImportJobs($arguments))),
            'crm_get_import_job' => $this->withPermission('import.manage', fn() => $this->toolResult($this->crmGetImportJob($arguments))),
            'crm_create_import_job' => $this->withPermission('import.manage', fn() => $this->toolResult($this->crmCreateImportJob($arguments))),
            'crm_cancel_import_job' => $this->withPermission('import.manage', fn() => $this->toolResult($this->crmCancelImportJob($arguments))),
            'crm_retry_import_job' => $this->withPermission('import.manage', fn() => $this->toolResult($this->crmRetryImportJob($arguments))),
            'crm_list_export_jobs' => $this->withPermission('export.manage', fn() => $this->toolResult($this->crmListExportJobs($arguments))),
            'crm_get_export_job' => $this->withPermission('export.manage', fn() => $this->toolResult($this->crmGetExportJob($arguments))),
            'crm_create_export_job' => $this->withPermission('export.manage', fn() => $this->toolResult($this->crmCreateExportJob($arguments))),
            'crm_cancel_export_job' => $this->withPermission('export.manage', fn() => $this->toolResult($this->crmCancelExportJob($arguments))),
            'crm_retry_export_job' => $this->withPermission('export.manage', fn() => $this->toolResult($this->crmRetryExportJob($arguments))),
            'crm_download_export_job' => $this->withPermission('export.manage', fn() => $this->toolResult($this->crmDownloadExportJob($arguments))),
            'crm_get_ai_settings' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmGetAiSettings())),
            'crm_update_ai_settings' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmUpdateAiSettings($arguments))),
            'crm_get_ai_preferences' => $this->toolResult($this->crmGetAiPreferences()),
            'crm_update_ai_preferences' => $this->toolResult($this->crmUpdateAiPreferences($arguments)),
            'crm_get_ai_availability' => $this->toolResult($this->crmGetAiAvailability($arguments)),
            'crm_list_ai_action_types' => $this->toolResult($this->crmListAiActionTypes()),
            'crm_execute_ai_action' => $this->toolResult($this->crmExecuteAiAction($arguments)),
            'crm_list_ai_providers' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmListAiProviders($arguments))),
            'crm_get_ai_provider' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmGetAiProvider($arguments))),
            'crm_list_ai_models' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmListAiModels($arguments))),
            'crm_list_ai_intents' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmListAiIntents($arguments))),
            'crm_update_ai_intent' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmUpdateAiIntent($arguments))),
            'crm_list_ai_prompts' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmListAiPrompts($arguments))),
            'crm_create_ai_prompt' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmCreateAiPrompt($arguments))),
            'crm_update_ai_prompt' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmUpdateAiPrompt($arguments))),
            'crm_list_ai_json_schemas' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmListAiJsonSchemas($arguments))),
            'crm_create_ai_json_schema' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmCreateAiJsonSchema($arguments))),
            'crm_update_ai_json_schema' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmUpdateAiJsonSchema($arguments))),
            'crm_list_ai_usage' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmListAiUsage($arguments))),
            'crm_list_ai_audit' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmListAiAudit($arguments))),
            'crm_list_ai_jobs' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmListAiJobs($arguments))),
            'crm_get_ai_job' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmGetAiJob($arguments))),
            'crm_retry_ai_job' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmRetryAiJob($arguments))),
            'crm_dry_run_ai_job' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmDryRunAiJob($arguments))),
            'crm_run_once_ai_job' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmRunOnceAiJob($arguments))),
            'crm_search_ai_semantic' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmSearchAiSemantic($arguments))),
            'crm_list_ai_retention_policies' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmListAiRetentionPolicies())),
            'crm_list_ai_suggestions' => $this->toolResult($this->crmListAiSuggestions($arguments)),
            'crm_get_ai_suggestion' => $this->toolResult($this->crmGetAiSuggestion($arguments)),
            'crm_dismiss_ai_suggestion' => $this->toolResult($this->crmDismissAiSuggestion($arguments)),
            'crm_preview_apply_ai_suggestion' => $this->toolResult($this->crmPreviewApplyAiSuggestion($arguments)),
            'crm_confirm_ai_suggestion' => $this->toolResult($this->crmConfirmAiSuggestion($arguments)),
            'crm_create_ai_dashboard_digest' => $this->toolResult($this->crmCreateAiDashboardDigest($arguments)),
            'crm_create_ai_my_day_plan' => $this->toolResult($this->crmCreateAiMyDayPlan($arguments)),
            'crm_create_ai_my_week_plan' => $this->toolResult($this->crmCreateAiMyWeekPlan($arguments)),
            'crm_create_ai_task_summary' => $this->toolResult($this->crmCreateAiTaskSummary($arguments)),
            'crm_create_ai_task_next_action' => $this->toolResult($this->crmCreateAiTaskNextAction($arguments)),
            'crm_create_ai_task_decomposition' => $this->toolResult($this->crmCreateAiTaskDecomposition($arguments)),
            'crm_create_ai_task_checklist' => $this->toolResult($this->crmCreateAiTaskChecklist($arguments)),
            'crm_create_ai_task_quality' => $this->toolResult($this->crmCreateAiTaskQuality($arguments)),
            'crm_create_ai_project_summary' => $this->toolResult($this->crmCreateAiProjectSummary($arguments)),
            'crm_create_ai_project_risks' => $this->toolResult($this->crmCreateAiProjectRisks($arguments)),
            'crm_create_ai_analytics_kpi_explanation' => $this->toolResult($this->crmCreateAiAnalyticsKpiExplanation($arguments)),
            'crm_create_ai_analytics_risks_explanation' => $this->toolResult($this->crmCreateAiAnalyticsRisksExplanation($arguments)),
            'crm_create_ai_analytics_team_workload_summary' => $this->toolResult($this->crmCreateAiAnalyticsTeamWorkloadSummary($arguments)),
            'crm_search' => $this->withPermissionAny(['task.manage', 'project.manage', 'knowledge.view'], fn() => $this->toolResult($this->crmSearch($arguments))),
            'crm_list_tasks' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListTasks($arguments))),
            'crm_get_task' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetTask($arguments))),
            'crm_create_task' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmCreateTask($arguments))),
            'crm_update_task' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmUpdateTask($arguments))),
            'crm_add_task_comment' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmAddTaskComment($arguments))),
            'crm_list_cycles' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListCycles($arguments))),
            'crm_get_cycle' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetCycle($arguments))),
            'crm_create_cycle' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmCreateCycle($arguments))),
            'crm_update_cycle' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmUpdateCycle($arguments))),
            'crm_list_cycle_tasks' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListCycleTasks($arguments))),
            'crm_add_tasks_to_cycle' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmAddTasksToCycle($arguments))),
            'crm_list_users' => $this->withPermission('user.view', fn() => $this->toolResult($this->crmListUsers($arguments))),
            'crm_get_user' => $this->withPermission('user.view', fn() => $this->toolResult($this->crmGetUser($arguments))),
            'crm_create_user' => $this->withPermission('user.manage', fn() => $this->toolResult($this->crmCreateUser($arguments))),
            'crm_update_user' => $this->withPermission('user.manage', fn() => $this->toolResult($this->crmUpdateUser($arguments))),
            'crm_delete_user' => $this->withPermission('user.manage', fn() => $this->toolResult($this->crmDeleteUser($arguments))),
            'crm_get_user_token_info' => $this->withPermission('user.manage', fn() => $this->toolResult($this->crmGetUserTokenInfo($arguments))),
            'crm_rotate_user_token' => $this->withPermission('user.manage', fn() => $this->toolResult($this->crmRotateUserToken($arguments))),
            'crm_revoke_user_token' => $this->withPermission('user.manage', fn() => $this->toolResult($this->crmRevokeUserToken($arguments))),
            'crm_get_user_activity' => $this->withPermission('user.manage', fn() => $this->toolResult($this->crmGetUserActivity($arguments))),
            'crm_list_teams' => $this->toolResult($this->crmListTeams($arguments)),
            'crm_get_team' => $this->toolResult($this->crmGetTeam($arguments)),
            'crm_create_team' => $this->withPermission('team.manage', fn() => $this->toolResult($this->crmCreateTeam($arguments))),
            'crm_update_team' => $this->withPermission('team.manage', fn() => $this->toolResult($this->crmUpdateTeam($arguments))),
            'crm_list_departments' => $this->withPermission('department.manage', fn() => $this->toolResult($this->crmListDepartments($arguments))),
            'crm_get_department' => $this->withPermission('department.manage', fn() => $this->toolResult($this->crmGetDepartment($arguments))),
            'crm_create_department' => $this->withPermission('department.manage', fn() => $this->toolResult($this->crmCreateDepartment($arguments))),
            'crm_update_department' => $this->withPermission('department.manage', fn() => $this->toolResult($this->crmUpdateDepartment($arguments))),
            'crm_list_counterparties' => $this->withPermission('counterparty.manage', fn() => $this->toolResult($this->crmListCounterparties($arguments))),
            'crm_get_counterparty' => $this->withPermission('counterparty.manage', fn() => $this->toolResult($this->crmGetCounterparty($arguments))),
            'crm_create_counterparty' => $this->withPermission('counterparty.manage', fn() => $this->toolResult($this->crmCreateCounterparty($arguments))),
            'crm_update_counterparty' => $this->withPermission('counterparty.manage', fn() => $this->toolResult($this->crmUpdateCounterparty($arguments))),
            'crm_list_companies' => $this->withPermission('company.manage', fn() => $this->toolResult($this->crmListCompanies($arguments))),
            'crm_get_company' => $this->withPermission('company.manage', fn() => $this->toolResult($this->crmGetCompany($arguments))),
            'crm_create_company' => $this->withPermission('company.manage', fn() => $this->toolResult($this->crmCreateCompany($arguments))),
            'crm_update_company' => $this->withPermission('company.manage', fn() => $this->toolResult($this->crmUpdateCompany($arguments))),
            'crm_list_clients' => $this->withPermission('client.manage', fn() => $this->toolResult($this->crmListClients($arguments))),
            'crm_get_client' => $this->withPermission('client.manage', fn() => $this->toolResult($this->crmGetClient($arguments))),
            'crm_create_client' => $this->withPermission('client.manage', fn() => $this->toolResult($this->crmCreateClient($arguments))),
            'crm_update_client' => $this->withPermission('client.manage', fn() => $this->toolResult($this->crmUpdateClient($arguments))),
            'crm_list_contacts' => $this->withPermission('contact.manage', fn() => $this->toolResult($this->crmListContacts($arguments))),
            'crm_get_contact' => $this->withPermission('contact.manage', fn() => $this->toolResult($this->crmGetContact($arguments))),
            'crm_create_contact' => $this->withPermission('contact.manage', fn() => $this->toolResult($this->crmCreateContact($arguments))),
            'crm_update_contact' => $this->withPermission('contact.manage', fn() => $this->toolResult($this->crmUpdateContact($arguments))),
            'crm_list_approvals' => $this->withPermission('approval.manage', fn() => $this->toolResult($this->crmListApprovals($arguments))),
            'crm_get_approval' => $this->withPermission('approval.manage', fn() => $this->toolResult($this->crmGetApproval($arguments))),
            'crm_create_approval' => $this->withPermission('approval.manage', fn() => $this->toolResult($this->crmCreateApproval($arguments))),
            'crm_approve_request' => $this->withPermission('approval.manage', fn() => $this->toolResult($this->crmReviewApproval($arguments, 'approve'))),
            'crm_reject_request' => $this->withPermission('approval.manage', fn() => $this->toolResult($this->crmReviewApproval($arguments, 'reject'))),
            'crm_list_recurring_rules' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListRecurringRules($arguments))),
            'crm_get_recurring_rule' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetRecurringRule($arguments))),
            'crm_create_recurring_rule' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmCreateRecurringRule($arguments))),
            'crm_update_recurring_rule' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmUpdateRecurringRule($arguments))),
            'crm_pause_recurring_rule' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmSetRecurringRuleState($arguments, false))),
            'crm_resume_recurring_rule' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmSetRecurringRuleState($arguments, true))),
            'crm_list_workflow_rules' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmListWorkflowRules($arguments))),
            'crm_get_workflow_rule' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmGetWorkflowRule($arguments))),
            'crm_create_workflow_rule' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmCreateWorkflowRule($arguments))),
            'crm_update_workflow_rule' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmUpdateWorkflowRule($arguments))),
            'crm_list_workflow_runs' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmListWorkflowRuns($arguments))),
            'crm_run_workflow_rule_test' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmRunWorkflowRuleTest($arguments))),
            'crm_list_projects' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmListProjects($arguments))),
            'crm_get_project' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmGetProject($arguments))),
            'crm_list_knowledge_pages' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmListKnowledgePages($arguments))),
            'crm_get_knowledge_page' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmGetKnowledgePage($arguments))),
            'crm_create_knowledge_page' => $this->withPermission('knowledge.create', fn() => $this->toolResult($this->crmCreateKnowledgePage($arguments))),
            'crm_list_calendar_events' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListCalendarEvents($arguments))),
            'crm_get_calendar_agenda' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetCalendarAgenda($arguments))),
            'crm_create_calendar_event' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmCreateCalendarEvent($arguments))),
            'crm_list_milestones' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListMilestones($arguments))),
            'crm_get_milestone' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetMilestone($arguments))),
            'crm_create_milestone' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmCreateMilestone($arguments))),
            'crm_update_milestone' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmUpdateMilestone($arguments))),
            'crm_list_reminders' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListReminders($arguments))),
            'crm_get_reminder' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetReminder($arguments))),
            'crm_create_reminder' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmCreateReminder($arguments))),
            'crm_update_reminder' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmUpdateReminder($arguments))),
            'crm_delete_reminder' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmDeleteReminder($arguments))),
            'crm_list_saved_views' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListSavedViews($arguments))),
            'crm_get_saved_view' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetSavedView($arguments))),
            'crm_create_saved_view' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmCreateSavedView($arguments))),
            'crm_update_saved_view' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmUpdateSavedView($arguments))),
            'crm_archive_saved_view' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmArchiveSavedView($arguments))),
            'crm_duplicate_saved_view' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmDuplicateSavedView($arguments))),
            'crm_pin_saved_view' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmPinSavedView($arguments))),
            'crm_get_saved_view_task_filters' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetSavedViewTaskFilters($arguments))),
            'crm_list_sticky_notes' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListStickyNotes($arguments))),
            'crm_get_sticky_note' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetStickyNote($arguments))),
            'crm_create_sticky_note' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmCreateStickyNote($arguments))),
            'crm_update_sticky_note' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmUpdateStickyNote($arguments))),
            'crm_archive_sticky_note' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmSetStickyNoteArchived($arguments, true))),
            'crm_unarchive_sticky_note' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmSetStickyNoteArchived($arguments, false))),
            'crm_list_estimate_sets' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListEstimateSets($arguments))),
            'crm_get_estimate_set' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetEstimateSet($arguments))),
            'crm_create_estimate_set' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmCreateEstimateSet($arguments))),
            'crm_update_estimate_set' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmUpdateEstimateSet($arguments))),
            'crm_list_estimate_options' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListEstimateOptions($arguments))),
            'crm_create_estimate_option' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmCreateEstimateOption($arguments))),
            'crm_update_estimate_option' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmUpdateEstimateOption($arguments))),
            'crm_list_task_estimates' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListTaskEstimates($arguments))),
            'crm_assign_task_estimate' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmAssignTaskEstimate($arguments))),
            'crm_remove_task_estimate' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmRemoveTaskEstimate($arguments))),
            'crm_get_project_estimate_summary' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetProjectEstimateSummary($arguments))),
            'crm_get_cycle_estimate_summary' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetCycleEstimateSummary($arguments))),
            'crm_get_module_estimate_summary' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetModuleEstimateSummary($arguments))),
            'crm_list_custom_fields' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListCustomFields($arguments))),
            'crm_get_custom_field' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetCustomField($arguments))),
            'crm_create_custom_field' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmCreateCustomField($arguments))),
            'crm_update_custom_field' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmUpdateCustomField($arguments))),
            'crm_get_custom_field_values' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetCustomFieldValues($arguments))),
            'crm_set_custom_field_values' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmSetCustomFieldValues($arguments))),
            'crm_list_sla_policies' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListSlaPolicies($arguments))),
            'crm_get_sla_policy' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetSlaPolicy($arguments))),
            'crm_create_sla_policy' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmCreateSlaPolicy($arguments))),
            'crm_update_sla_policy' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmUpdateSlaPolicy($arguments))),
            'crm_get_sla_report' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetSlaReport())),
            'crm_assign_sla_to_task' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmAssignSlaToTask($arguments))),
            'crm_list_templates' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListTemplates($arguments))),
            'crm_get_template' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetTemplate($arguments))),
            'crm_create_template' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmCreateTemplate($arguments))),
            'crm_update_template' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmUpdateTemplate($arguments))),
            'crm_apply_template' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmApplyTemplate($arguments))),
            'crm_list_files' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListFiles($arguments))),
            'crm_get_file' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetFile($arguments))),
            'crm_upload_file_base64' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmUploadFileBase64($arguments))),
            'crm_get_file_download_info' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetFileDownloadInfo($arguments))),
            'crm_delete_file' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmDeleteFile($arguments))),
            'crm_list_statuses' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListStatuses($arguments))),
            'crm_get_status' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetStatus($arguments))),
            'crm_create_status' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmCreateStatus($arguments))),
            'crm_update_status' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmUpdateStatus($arguments))),
            'crm_list_tags' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListTags($arguments))),
            'crm_get_tag' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetTag($arguments))),
            'crm_create_tag' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmCreateTag($arguments))),
            'crm_update_tag' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmUpdateTag($arguments))),
            'crm_list_task_tags' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListTaskTags($arguments))),
            'crm_attach_task_tag' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmAttachTaskTag($arguments))),
            'crm_detach_task_tag' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmDetachTaskTag($arguments))),
            'crm_list_task_checklists' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListTaskChecklists($arguments))),
            'crm_create_task_checklist' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmCreateTaskChecklist($arguments))),
            'crm_update_checklist' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmUpdateChecklist($arguments))),
            'crm_list_checklist_items' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListChecklistItems($arguments))),
            'crm_create_checklist_item' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmCreateChecklistItem($arguments))),
            'crm_update_checklist_item' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmUpdateChecklistItem($arguments))),
            'crm_list_dependencies' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListDependencies($arguments))),
            'crm_create_dependency' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmCreateDependency($arguments))),
            'crm_list_worklogs' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListWorklogs($arguments))),
            'crm_get_worklog' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetWorklog($arguments))),
            'crm_create_worklog' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmCreateWorklog($arguments))),
            'crm_update_worklog' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmUpdateWorklog($arguments))),
            'crm_get_worklog_summary' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetWorklogSummary($arguments))),
            'crm_list_ideas' => $this->toolResult($this->crmListIdeas($arguments)),
            'crm_get_idea' => $this->toolResult($this->crmGetIdea($arguments)),
            'crm_create_idea' => $this->toolResult($this->crmCreateIdea($arguments)),
            'crm_add_idea_comment' => $this->toolResult($this->crmAddIdeaComment($arguments)),
            'crm_list_chats' => $this->toolResult($this->crmListChats($arguments)),
            'crm_list_chat_messages' => $this->toolResult($this->crmListChatMessages($arguments)),
            'crm_send_chat_message' => $this->toolResult($this->crmSendChatMessage($arguments)),
            'crm_list_notifications' => $this->toolResult($this->crmListNotifications($arguments)),
            'crm_get_notification_counters' => $this->toolResult($this->crmGetNotificationCounters()),
            'crm_create_notification' => $this->toolResult($this->crmCreateNotification($arguments)),
            'crm_mark_notification_read' => $this->toolResult($this->crmSetNotificationReadState($arguments, true)),
            'crm_mark_notification_unread' => $this->toolResult($this->crmSetNotificationReadState($arguments, false)),
            'crm_mark_all_notifications_read' => $this->toolResult($this->crmMarkAllNotificationsRead($arguments)),
            'crm_list_favorites' => $this->toolResult($this->crmListFavorites($arguments)),
            'crm_create_favorite' => $this->toolResult($this->crmCreateFavorite($arguments)),
            'crm_delete_favorite' => $this->toolResult($this->crmDeleteFavorite($arguments)),
            'crm_list_subscriptions' => $this->toolResult($this->crmListSubscriptions($arguments)),
            'crm_create_subscription' => $this->toolResult($this->crmCreateSubscription($arguments)),
            'crm_delete_subscription' => $this->toolResult($this->crmDeleteSubscription($arguments)),
            'crm_list_reactions' => $this->toolResult($this->crmListReactions($arguments)),
            'crm_add_reaction' => $this->toolResult($this->crmAddReaction($arguments)),
            'crm_remove_reaction' => $this->toolResult($this->crmRemoveReaction($arguments)),
            'crm_list_mentions' => $this->toolResult($this->crmListMentions($arguments)),
            'crm_add_mention' => $this->toolResult($this->crmAddMention($arguments)),
            'crm_delete_mention' => $this->toolResult($this->crmDeleteMention($arguments)),
            'crm_get_project_summary' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmGetProjectSummary($arguments))),
            'crm_get_project_timeline' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmGetProjectTimeline($arguments))),
            'crm_get_project_milestones_summary' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmGetProjectMilestonesSummary($arguments))),
            'crm_get_project_risks' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmGetProjectRisks($arguments))),
            'crm_get_project_workload' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmGetProjectWorkload($arguments))),
            'crm_get_knowledge_overview' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmGetKnowledgeOverview($arguments))),
            'crm_list_knowledge_spaces_tree' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmListKnowledgeSpacesTree($arguments))),
            'crm_get_knowledge_tree' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmGetKnowledgeTree($arguments))),
            'crm_search_knowledge' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmSearchKnowledge($arguments))),
            'crm_list_knowledge_recent' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmListKnowledgeRecent($arguments))),
            'crm_list_knowledge_popular' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmListKnowledgePopular($arguments))),
            'crm_list_knowledge_review_queue' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmListKnowledgeReviewQueue($arguments))),
            'crm_list_knowledge_outdated' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmListKnowledgeOutdated($arguments))),
            'crm_list_knowledge_favorites' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmListKnowledgeFavorites($arguments))),
            'crm_list_knowledge_page_links' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmListKnowledgePageLinks($arguments))),
            'crm_delete_knowledge_page_link' => $this->withPermission('knowledge.edit', fn() => $this->toolResult($this->crmDeleteKnowledgePageLink($arguments))),
            'crm_list_knowledge_page_tags' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmListKnowledgePageTags($arguments))),
            'crm_attach_knowledge_page_tag' => $this->withPermission('knowledge.edit', fn() => $this->toolResult($this->crmAttachKnowledgePageTag($arguments))),
            'crm_detach_knowledge_page_tag' => $this->withPermission('knowledge.edit', fn() => $this->toolResult($this->crmDetachKnowledgePageTag($arguments))),
            'crm_delete_knowledge_draft' => $this->withPermission('knowledge.edit', fn() => $this->toolResult($this->crmDeleteKnowledgeDraft($arguments))),
            'crm_delete_knowledge_page' => $this->withPermission('knowledge.manage', fn() => $this->toolResult($this->crmDeleteKnowledgePage($arguments))),
            'crm_get_activity_feed' => $this->toolResult($this->crmGetActivityFeed($arguments)),
            'crm_get_activity_history' => $this->toolResult($this->crmGetActivityHistory($arguments)),
            'crm_list_knowledge_spaces' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmListKnowledgeSpaces($arguments))),
            'crm_get_knowledge_space' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmGetKnowledgeSpace($arguments))),
            'crm_list_knowledge_page_versions' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmListKnowledgePageVersions($arguments))),
            'crm_get_knowledge_page_version' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmGetKnowledgePageVersion($arguments))),
            'crm_diff_knowledge_page_version' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmDiffKnowledgePageVersion($arguments))),
            'crm_list_knowledge_comments' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmListKnowledgeComments($arguments))),
            'crm_add_knowledge_comment' => $this->withPermission('knowledge.comment', fn() => $this->toolResult($this->crmAddKnowledgeComment($arguments))),
            'crm_delete_knowledge_comment' => $this->withPermission('knowledge.comment', fn() => $this->toolResult($this->crmDeleteKnowledgeComment($arguments))),
            'crm_resolve_knowledge_comment' => $this->withPermission('knowledge.comment', fn() => $this->toolResult($this->crmResolveKnowledgeComment($arguments))),
            'crm_reopen_knowledge_comment' => $this->withPermission('knowledge.comment', fn() => $this->toolResult($this->crmReopenKnowledgeComment($arguments))),
            'crm_list_knowledge_files' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmListKnowledgeFiles($arguments))),
            'crm_delete_knowledge_file' => $this->withPermission('knowledge.delete', fn() => $this->toolResult($this->crmDeleteKnowledgeFile($arguments))),
            'crm_publish_knowledge_page' => $this->withPermission('knowledge.publish', fn() => $this->toolResult($this->crmPublishKnowledgePage($arguments))),
            'crm_archive_knowledge_page' => $this->withPermission('knowledge.publish', fn() => $this->toolResult($this->crmArchiveKnowledgePage($arguments))),
            'crm_restore_knowledge_page' => $this->withPermission('knowledge.publish', fn() => $this->toolResult($this->crmRestoreKnowledgePage($arguments))),
            'crm_request_knowledge_review' => $this->withPermission('knowledge.review', fn() => $this->toolResult($this->crmRequestKnowledgeReview($arguments))),
            'crm_approve_knowledge_review' => $this->withPermission('knowledge.review', fn() => $this->toolResult($this->crmApproveKnowledgeReview($arguments))),
            'crm_reject_knowledge_review' => $this->withPermission('knowledge.review', fn() => $this->toolResult($this->crmRejectKnowledgeReview($arguments))),
            'crm_duplicate_knowledge_page' => $this->withPermission('knowledge.create', fn() => $this->toolResult($this->crmDuplicateKnowledgePage($arguments))),
            'crm_move_knowledge_page' => $this->withPermission('knowledge.manage', fn() => $this->toolResult($this->crmMoveKnowledgePage($arguments))),
            'crm_lock_knowledge_page' => $this->withPermission('knowledge.manage', fn() => $this->toolResult($this->crmLockKnowledgePage($arguments))),
            'crm_unlock_knowledge_page' => $this->withPermission('knowledge.manage', fn() => $this->toolResult($this->crmUnlockKnowledgePage($arguments))),
            'crm_get_knowledge_space_permissions' => $this->withPermission('knowledge.manage', fn() => $this->toolResult($this->crmGetKnowledgeSpacePermissions($arguments))),
            'crm_add_knowledge_space_permission' => $this->withPermission('knowledge.manage', fn() => $this->toolResult($this->crmAddKnowledgeSpacePermission($arguments))),
            'crm_remove_knowledge_space_permission' => $this->withPermission('knowledge.manage', fn() => $this->toolResult($this->crmRemoveKnowledgeSpacePermission($arguments))),
            'crm_get_knowledge_page_permissions' => $this->withPermission('knowledge.manage', fn() => $this->toolResult($this->crmGetKnowledgePagePermissions($arguments))),
            'crm_add_knowledge_page_permission' => $this->withPermission('knowledge.manage', fn() => $this->toolResult($this->crmAddKnowledgePagePermission($arguments))),
            'crm_remove_knowledge_page_permission' => $this->withPermission('knowledge.manage', fn() => $this->toolResult($this->crmRemoveKnowledgePagePermission($arguments))),
            'crm_get_admin_knowledge_settings' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmGetAdminKnowledgeSettings())),
            'crm_update_admin_knowledge_settings' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmUpdateAdminKnowledgeSettings($arguments))),
            'crm_reindex_knowledge' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmReindexKnowledge())),
            'crm_rebuild_knowledge_permissions' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmRebuildKnowledgePermissions())),
            'crm_cleanup_knowledge_drafts' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmCleanupKnowledgeDrafts())),
            'crm_get_chat' => $this->toolResult($this->crmGetChat($arguments)),
            'crm_create_chat' => $this->toolResult($this->crmCreateChat($arguments)),
            'crm_get_chat_participants' => $this->toolResult($this->crmGetChatParticipants($arguments)),
            'crm_edit_chat_message' => $this->toolResult($this->crmEditChatMessage($arguments)),
            'crm_delete_chat_message' => $this->toolResult($this->crmDeleteChatMessage($arguments)),
            'crm_upload_chat_attachment' => $this->toolResult($this->crmUploadChatAttachment($arguments)),
            'crm_list_chat_attachments' => $this->toolResult($this->crmListChatAttachments($arguments)),
            'crm_get_chat_settings' => $this->toolResult($this->crmGetChatSettings($arguments)),
            'crm_update_chat_settings' => $this->toolResult($this->crmUpdateChatSettings($arguments)),
            'crm_mark_chat_read' => $this->toolResult($this->crmMarkChatRead($arguments)),
            'crm_get_chat_unread_count' => $this->toolResult($this->crmGetChatUnreadCount()),
            'crm_archive_chat' => $this->toolResult($this->crmArchiveChat($arguments)),
            'crm_restore_chat' => $this->toolResult($this->crmRestoreChat($arguments)),
            'crm_list_push_subscriptions' => $this->toolResult($this->crmListPushSubscriptions($arguments)),
            'crm_create_push_subscription' => $this->toolResult($this->crmCreatePushSubscription($arguments)),
            'crm_delete_push_subscription' => $this->toolResult($this->crmDeletePushSubscription($arguments)),
            'crm_send_push_test' => $this->toolResult($this->crmSendPushTest()),
            'crm_get_admin_role_matrix' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmGetAdminRoleMatrix())),
            'crm_update_admin_role_matrix' => $this->withPermission('settings.manage', fn() => $this->toolResult($this->crmUpdateAdminRoleMatrix($arguments))),
            default => $this->toolError('Unknown tool: ' . $name),
        };
    }

    private function crmGetCurrentUser(): array
    {
        return ['user' => $this->publicData($this->actor())];
    }

    private function crmGetProfile(): array
    {
        /** @var UserProfileService $service */
        $service = $this->container->get('service.user_profile');
        return [
            'user' => $this->publicData($service->me($this->actor()) ?? $this->actor()),
            'preferences' => $this->publicData($service->preferences($this->actor())),
        ];
    }

    private function crmGetDashboardSummary(): array
    {
        /** @var DashboardService $service */
        $service = $this->container->get('service.dashboard');
        return $this->publicData($service->summary($this->actor()));
    }

    private function crmGetAiSettings(): array
    {
        /** @var AiSettingsService $service */
        $service = $this->container->get('service.ai_settings');
        return $this->publicData($service->getSettings());
    }

    private function crmUpdateProfile(array $arguments): array
    {
        /** @var UserProfileService $service */
        $service = $this->container->get('service.user_profile');
        $result = $service->updateMe($this->actor(), $this->pick($arguments, ['full_name', 'email', 'locale', 'timezone']));
        return $this->publicData($result);
    }

    private function crmGetProfilePreferences(): array
    {
        /** @var UserProfileService $service */
        $service = $this->container->get('service.user_profile');
        return $this->publicData(['preferences' => $service->preferences($this->actor())]);
    }

    private function crmUpdateProfilePreferences(array $arguments): array
    {
        $preferences = $arguments['preferences'] ?? [];
        if (!is_array($preferences)) {
            return ['error' => 'preferences must be an object.'];
        }

        /** @var UserProfileService $service */
        $service = $this->container->get('service.user_profile');
        $updated = $service->setPreferences($this->actor(), $preferences);
        return $this->publicData(['preferences' => $updated]);
    }

    private function crmChangeProfilePassword(array $arguments): array
    {
        $current = trim((string)($arguments['current_password'] ?? ''));
        $new = trim((string)($arguments['new_password'] ?? ''));
        if ($current === '' || $new === '') {
            return ['error' => 'current_password and new_password are required.'];
        }
        if (strlen($new) < 8) {
            return ['error' => 'new_password must be at least 8 characters.'];
        }

        /** @var UserProfileService $service */
        $service = $this->container->get('service.user_profile');
        return $this->publicData($service->changePassword($this->actor(), $current, $new, (string)($this->user()['session_public_id'] ?? '')));
    }

    private function crmRevokeSecuritySession(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var SessionService $service */
        $service = $this->container->get('service.session');
        return $this->publicData($service->revoke($this->actor(), $publicId));
    }

    private function crmRevokeOtherSecuritySessions(): array
    {
        /** @var SessionService $service */
        $service = $this->container->get('service.session');
        return $this->publicData(['revoked_count' => $service->revokeOthers($this->actor(), (string)($this->user()['session_public_id'] ?? ''))]);
    }

    private function crmRevokeDeviceSessions(array $arguments): array
    {
        $fingerprint = trim((string)($arguments['device_fingerprint'] ?? ''));
        if ($fingerprint === '') {
            return ['error' => 'device_fingerprint is required.'];
        }

        /** @var SessionService $service */
        $service = $this->container->get('service.session');
        return $this->publicData([
            'device_fingerprint' => $fingerprint,
            'revoked_count' => $service->revokeDevice($this->actor(), $fingerprint, (string)($this->user()['session_public_id'] ?? '')),
        ]);
    }

    private function crmGetMenu(): array
    {
        $controller = new MenuController($this->container);
        return $this->payloadData($controller->list());
    }

    private function crmGetMenuPreferences(): array
    {
        $controller = new MenuController($this->container);
        return $this->payloadData($controller->getPreferences());
    }

    private function crmSaveMenuPreferences(array $arguments): array
    {
        $items = $arguments['items'] ?? null;
        if (!is_array($items)) {
            return ['error' => 'items must be an array.'];
        }

        $validated = $this->validateMenuPreferencesItems($items);
        $scope = 'user:' . (string)($this->actor()['public_id'] ?? '');
        /** @var SettingService $settingService */
        $settingService = $this->container->get('service.setting');
        $settingService->set($scope, 'menu_preferences', $validated);

        return $this->publicData(['preferences' => $validated]);
    }

    private function crmGetUser(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var UserService $service */
        $service = $this->container->get('service.user');
        $user = $service->get($publicId);
        return $user ? ['user' => $this->publicData($user)] : ['error' => 'User not found.'];
    }

    private function crmCreateUser(array $arguments): array
    {
        $login = trim((string)($arguments['login'] ?? ''));
        $password = (string)($arguments['password'] ?? '');
        if ($login === '' || $password === '') {
            return ['error' => 'login and password are required.'];
        }

        /** @var UserService $service */
        $service = $this->container->get('service.user');
        $result = $service->create($this->pick($arguments, [
            'login', 'password', 'email', 'full_name', 'locale', 'is_root',
            'role_public_ids', 'is_active', 'cost_rate', 'bill_rate', 'token',
        ]), $this->actor());

        return $this->publicData($result);
    }

    private function crmUpdateUser(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var UserService $service */
        $service = $this->container->get('service.user');
        $result = $service->update($publicId, $this->pick($arguments, [
            'email', 'full_name', 'locale', 'is_active', 'is_root', 'role_public_ids',
            'password', 'token', 'cost_rate', 'bill_rate',
        ]), $this->actor());

        return $this->publicData($result);
    }

    private function crmDeleteUser(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var UserService $service */
        $service = $this->container->get('service.user');
        return $this->publicData($service->delete($publicId, $this->actor()));
    }

    private function crmGetUserTokenInfo(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var UserService $service */
        $service = $this->container->get('service.user');
        $result = $service->tokenInfo($publicId, $this->actor());
        return [
            'ok' => (bool)($result['ok'] ?? false),
            'api_key_present' => (bool)($result['token']['has_token_factor'] ?? false),
        ];
    }

    private function crmRotateUserToken(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var UserService $service */
        $service = $this->container->get('service.user');
        $result = $service->rotateToken($publicId, $this->pick($arguments, ['token']), $this->actor());
        return [
            'ok' => (bool)($result['ok'] ?? false),
            'api_key' => (string)($result['plain_token'] ?? ''),
        ];
    }

    private function crmRevokeUserToken(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var UserService $service */
        $service = $this->container->get('service.user');
        return $this->publicData($service->revokeToken($publicId, $this->actor()));
    }

    private function crmGetUserActivity(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var UserService $service */
        $service = $this->container->get('service.user');
        $filters = [
            'page' => max(1, (int)($arguments['page'] ?? 1)),
            'limit' => min(100, max(1, (int)($arguments['limit'] ?? 20))),
        ];
        return $this->publicData($service->activity($publicId, $filters, $this->actor()));
    }

    private function crmInstallModule(array $arguments): array
    {
        $name = trim((string)($arguments['name'] ?? ''));
        if ($name === '') {
            return ['error' => 'name is required.'];
        }

        return $this->payloadData((new ModuleController($this->container))->install(['name' => $name]));
    }

    private function crmActivateModule(array $arguments): array
    {
        $name = trim((string)($arguments['name'] ?? ''));
        if ($name === '') {
            return ['error' => 'name is required.'];
        }

        return $this->payloadData((new ModuleController($this->container))->activate(['name' => $name]));
    }

    private function crmDeactivateModule(array $arguments): array
    {
        $name = trim((string)($arguments['name'] ?? ''));
        if ($name === '') {
            return ['error' => 'name is required.'];
        }

        return $this->payloadData((new ModuleController($this->container))->deactivate(['name' => $name]));
    }

    private function crmUninstallModule(array $arguments): array
    {
        $name = trim((string)($arguments['name'] ?? ''));
        if ($name === '') {
            return ['error' => 'name is required.'];
        }

        return $this->payloadData((new ModuleController($this->container))->uninstall(['name' => $name]));
    }

    private function crmGetModuleConfig(array $arguments): array
    {
        $name = trim((string)($arguments['name'] ?? ''));
        if ($name === '') {
            return ['error' => 'name is required.'];
        }

        return $this->payloadData((new ModuleController($this->container))->config(['name' => $name]));
    }

    private function crmUpdateModuleConfig(array $arguments): array
    {
        $name = trim((string)($arguments['name'] ?? ''));
        if ($name === '') {
            return ['error' => 'name is required.'];
        }

        $config = $arguments['config'] ?? [];
        if (!is_array($config)) {
            return ['error' => 'config must be an object.'];
        }

        return $this->payloadData((new ModuleController($this->container))->updateConfig(['name' => $name, 'config' => $config]));
    }

    private function crmGetModuleHealth(array $arguments): array
    {
        $name = trim((string)($arguments['name'] ?? ''));
        if ($name === '') {
            return ['error' => 'name is required.'];
        }

        return $this->payloadData((new ModuleController($this->container))->health(['name' => $name]));
    }

    private function crmGetModuleMigrations(array $arguments): array
    {
        $name = trim((string)($arguments['name'] ?? ''));
        if ($name === '') {
            return ['error' => 'name is required.'];
        }

        return $this->payloadData((new ModuleController($this->container))->migrations(['name' => $name]));
    }

    private function crmGetModuleErrors(array $arguments): array
    {
        $name = trim((string)($arguments['name'] ?? ''));
        if ($name === '') {
            return ['error' => 'name is required.'];
        }

        return $this->payloadData((new ModuleController($this->container))->errors(['name' => $name]));
    }

    private function crmClearModuleErrors(array $arguments): array
    {
        $name = trim((string)($arguments['name'] ?? ''));
        if ($name === '') {
            return ['error' => 'name is required.'];
        }

        return $this->payloadData((new ModuleController($this->container))->clearErrors(['name' => $name]));
    }

    private function crmInstallModuleFromUrl(array $arguments): array
    {
        $url = trim((string)($arguments['url'] ?? ''));
        if ($url === '') {
            return ['error' => 'url is required.'];
        }

        return $this->payloadData((new ModuleController($this->container))->installFromUrl(['url' => $url]));
    }

    private function crmInstallModuleFromFile(array $arguments): array
    {
        $fileData = trim((string)($arguments['file_data'] ?? ''));
        if ($fileData === '') {
            return ['error' => 'file_data is required.'];
        }

        $params = [
            'file_name' => trim((string)($arguments['file_name'] ?? 'module.zip')),
            'file_data' => $fileData,
        ];
        return $this->payloadData((new ModuleController($this->container))->installFromFile($params));
    }

    private function crmGetCacheStats(): array
    {
        return $this->payloadData((new CacheController($this->container))->stats());
    }

    private function crmClearCache(): array
    {
        return $this->payloadData((new CacheController($this->container))->clear());
    }

    private function crmGetOpsSystem(): array
    {
        return $this->payloadData((new OpsController($this->container))->system());
    }

    private function crmGetOpsMetrics(): array
    {
        return $this->payloadData((new OpsController($this->container))->metrics());
    }

    private function crmRunOpsJobs(array $arguments): array
    {
        $limit = max(1, min(100, (int)($arguments['limit'] ?? 10)));

        /** @var ImportService $imports */
        $imports = $this->container->get('service.import');
        /** @var ExportService $exports */
        $exports = $this->container->get('service.export');
        /** @var NotificationPushService $push */
        $push = $this->container->get('service.notification_push');
        /** @var WebhookService $webhooks */
        $webhooks = $this->container->get('service.webhook');

        return $this->publicData([
            'import' => $imports->runQueued($limit),
            'export' => $exports->runQueued($limit),
            'push' => $push->runQueued($limit),
            'webhook' => $webhooks->runQueued($limit),
            'limit' => $limit,
            'generated_at' => gmdate('c'),
        ]);
    }

    private function crmGetCoreVersion(): array
    {
        return $this->payloadData((new CoreVersionController($this->container))->show());
    }

    private function crmGetCoreUpdateStatus(): array
    {
        return $this->payloadData((new CoreUpdateController($this->container))->status());
    }

    private function crmCheckCoreUpdate(): array
    {
        return $this->payloadData((new CoreUpdateController($this->container))->check());
    }

    private function crmRunCoreUpdatePreflight(array $arguments): array
    {
        if (!$this->coreUpdateAllowed()) {
            return ['error' => 'Forbidden'];
        }

        $config = CoreUpdateConfig::load();
        $payload = is_array($arguments['payload'] ?? null) ? $arguments['payload'] : [];
        $payload['dry_run'] = true;
        $result = $this->coreUpdateCallUpdater('preflight', $payload, $config);
        $normalized = $this->coreUpdateNormalizeUpdaterResult($result);
        if (($normalized['success'] ?? false) !== true || empty($normalized['job_id'])) {
            $message = (string)($normalized['message'] ?? $normalized['code'] ?? 'Updater preflight failed.');
            return ['error' => 'CORE_UPDATE_PREFLIGHT_FAILED', 'message' => $message, 'updater' => $normalized];
        }

        return $this->publicData($normalized);
    }

    private function crmGetCoreUpdateChanges(array $arguments): array
    {
        $config = CoreUpdateConfig::load();
        $client = new CoreUpdateClient($config);
        $from = array_key_exists('from', $arguments) ? trim((string)$arguments['from']) : null;
        $to = trim((string)($arguments['to'] ?? ''));

        if ($to === '') {
            $check = (new CoreUpdatePlanner($client, new CoreVersion((string)$config['storage_dir'], dirname(__DIR__, 3))))->check();
            $plan = is_array($check['plan'] ?? null) ? $check['plan'] : [];
            $current = is_array($check['current'] ?? null) ? $check['current'] : [];
            $to = (string)($plan['target_build'] ?? '');
            $from = $current['core_build'] ?? ($plan['current_build'] ?? $from);
            if ($to === '' || (($plan['update_available'] ?? null) === false && (string)$from === $to)) {
                return $this->publicData($this->coreUpdateEmptyChanges(is_string($from) ? $from : null, $to));
            }
        }

        return $this->publicData($client->changes(is_string($from) ? $from : null, $to));
    }

    private function crmGetCoreUpdateSession(): array
    {
        $config = CoreUpdateConfig::load();
        $userId = (int)($this->actor()['id'] ?? 0);
        return $this->publicData((new CoreUpdateSessionService((string)$config['storage_dir']))->create($userId));
    }

    private function crmGetCoreUpdateHistory(): array
    {
        $config = CoreUpdateConfig::load();
        return $this->publicData(['items' => (new CoreUpdateHistoryRepository((string)$config['storage_dir']))->list()]);
    }

    private function crmGetCoreUpdateLog(array $arguments): array
    {
        $jobId = trim((string)($arguments['job_id'] ?? ''));
        if ($jobId === '') {
            return ['error' => 'job_id is required.'];
        }

        $config = CoreUpdateConfig::load();
        return $this->publicData(['job_id' => $jobId, 'lines' => (new CoreUpdateLogRepository((string)$config['storage_dir']))->read($jobId)]);
    }

    private function coreUpdateAllowed(): bool
    {
        $user = $this->user()['user'] ?? null;
        if (!is_array($user)) {
            return false;
        }
        if ((bool)($user['is_root'] ?? false)) {
            return true;
        }
        $permissions = is_array($user['permission_codes'] ?? null) ? $user['permission_codes'] : [];
        if (in_array('*', $permissions, true) || in_array('system.update', $permissions, true) || in_array('settings.manage', $permissions, true)) {
            return true;
        }
        $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
        $normalizedRoles = array_values(array_unique(array_filter(array_map(
            static function (mixed $role): string {
                if (is_array($role)) {
                    $role = $role['code'] ?? $role['public_id'] ?? $role['name'] ?? '';
                }
                return strtolower(str_replace('-', '_', trim(is_scalar($role) ? (string)$role : '')));
            },
            $roles
        ), static fn(string $role): bool => $role !== '')));
        if (array_intersect($normalizedRoles, ['admin', 'administrator', 'super_admin', 'super_administrator', 'root']) !== []) {
            return true;
        }

        return strtolower(trim((string)($user['login'] ?? ''))) === 'admin';
    }

    private function coreUpdateCallUpdater(string $action, array $payload, array $config): array
    {
        $baseUrl = $this->coreUpdateLocalUpdaterBaseUrl($config);
        $url = $baseUrl . '/updater/index.php?action=' . rawurlencode($action);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $response = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => (int)($config['timeouts']['apply_step'] ?? 60),
            ],
        ]));
        $decoded = json_decode((string)$response, true);
        return is_array($decoded) ? $decoded : ['success' => false, 'error' => 'invalid_updater_response'];
    }

    private function coreUpdateLocalUpdaterBaseUrl(array $config): string
    {
        $configured = trim((string)($config['local_updater_url'] ?? ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $host = trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'crm.ru'));
        $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
        $scheme = $isHttps ? 'https' : 'http';
        return $scheme . '://' . $host;
    }

    private function coreUpdateNormalizeUpdaterResult(array $result): array
    {
        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        return [
            'success' => (bool)($result['success'] ?? false),
            'code' => $result['code'] ?? $result['error'] ?? null,
            'message' => $result['message'] ?? $result['error'] ?? null,
            'job_id' => $data['job_id'] ?? null,
            'preflight' => $data['preflight'] ?? null,
            'updater' => $result,
        ];
    }

    private function crmUpdateAiSettings(array $arguments): array
    {
        /** @var AiSettingsService $service */
        $service = $this->container->get('service.ai_settings');
        $result = $service->updateSettings($this->pick($arguments, [
            'default_provider_public_id', 'default_model', 'runtime_mode', 'max_input_chars',
            'request_timeout_ms', 'strict_json_mode', 'audit_redaction_enabled', 'allow_personal_recommendations_opt_out',
        ]));
        return $this->publicData($result);
    }

    private function crmGetAiPreferences(): array
    {
        /** @var AiPreferenceService $service */
        $service = $this->container->get('service.ai_preference');
        return $this->publicData($service->getPreferences($this->actor()));
    }

    private function crmUpdateAiPreferences(array $arguments): array
    {
        /** @var AiPreferenceService $service */
        $service = $this->container->get('service.ai_preference');
        $result = $service->updatePreferences($this->actor(), (array)($arguments['preferences'] ?? []));
        return $this->publicData($result);
    }

    private function crmGetAiAvailability(array $arguments): array
    {
        /** @var AiAvailabilityService $service */
        $service = $this->container->get('service.ai_availability');
        $requested = is_array($arguments['requested_intents'] ?? null) ? array_values(array_filter(array_map('strval', (array)$arguments['requested_intents']))) : [];
        return $this->publicData($service->getAvailability($this->actor(), $requested));
    }

    private function crmListAiActionTypes(): array
    {
        /** @var AiActionTypeService $service */
        $service = $this->container->get('service.ai_action_type');
        return [
            'allowlist' => $service->allowlist(),
            'enabled_allowlist' => $service->enabledAllowlist(),
        ];
    }

    private function crmExecuteAiAction(array $arguments): array
    {
        $actionType = trim((string)($arguments['action_type'] ?? ''));
        if ($actionType === '') {
            return ['error' => 'action_type is required.'];
        }

        /** @var AiActionService $service */
        $service = $this->container->get('service.ai_action');
        return $this->publicData($service->execute($actionType, (array)($arguments['input'] ?? []), $this->actor()));
    }

    private function crmListAiProviders(array $arguments): array
    {
        /** @var AiProviderService $service */
        $service = $this->container->get('service.ai_provider');
        return $this->publicData($service->list($this->aiProviderFilters($arguments)));
    }

    private function crmGetAiProvider(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        /** @var AiProviderService $service */
        $service = $this->container->get('service.ai_provider');
        return $this->publicData($service->get($publicId));
    }

    private function crmListAiModels(array $arguments): array
    {
        $providerPublicId = trim((string)($arguments['provider_public_id'] ?? ''));
        /** @var AiProviderService $service */
        $service = $this->container->get('service.ai_provider');
        return $this->publicData($service->listModels($providerPublicId !== '' ? $providerPublicId : null));
    }

    private function crmListAiIntents(array $arguments): array
    {
        /** @var AiIntentSettingService $service */
        $service = $this->container->get('service.ai_intent_settings');
        return $this->publicData($service->list($this->aiIntentFilters($arguments)));
    }

    private function crmUpdateAiIntent(array $arguments): array
    {
        $intentCode = trim((string)($arguments['intent_code'] ?? ''));
        if ($intentCode === '') {
            return ['error' => 'intent_code is required.'];
        }
        /** @var AiIntentSettingService $service */
        $service = $this->container->get('service.ai_intent_settings');
        return $this->publicData($service->update($intentCode, $this->pick($arguments, ['is_enabled', 'required_permission', 'feature_flag']), $this->actor()));
    }

    private function crmListAiPrompts(array $arguments): array
    {
        /** @var AiPromptSchemaService $service */
        $service = $this->container->get('service.ai_prompt_schema');
        return $this->publicData($service->listPrompts($this->aiSchemaFilters($arguments)));
    }

    private function crmCreateAiPrompt(array $arguments): array
    {
        /** @var AiPromptSchemaService $service */
        $service = $this->container->get('service.ai_prompt_schema');
        return $this->publicData($service->createPrompt($this->pick($arguments, ['intent_code', 'locale', 'title', 'prompt', 'is_active']), $this->actor()));
    }

    private function crmUpdateAiPrompt(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        /** @var AiPromptSchemaService $service */
        $service = $this->container->get('service.ai_prompt_schema');
        return $this->publicData($service->updatePrompt($publicId, $this->pick($arguments, ['intent_code', 'locale', 'title', 'prompt', 'is_active']), $this->actor()));
    }

    private function crmListAiJsonSchemas(array $arguments): array
    {
        /** @var AiPromptSchemaService $service */
        $service = $this->container->get('service.ai_prompt_schema');
        return $this->publicData($service->listSchemas($this->aiSchemaFilters($arguments)));
    }

    private function crmCreateAiJsonSchema(array $arguments): array
    {
        /** @var AiPromptSchemaService $service */
        $service = $this->container->get('service.ai_prompt_schema');
        return $this->publicData($service->createSchema($this->pick($arguments, ['intent_code', 'title', 'schema_json', 'is_active']), $this->actor()));
    }

    private function crmUpdateAiJsonSchema(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        /** @var AiPromptSchemaService $service */
        $service = $this->container->get('service.ai_prompt_schema');
        return $this->publicData($service->updateSchema($publicId, $this->pick($arguments, ['intent_code', 'title', 'schema_json', 'is_active']), $this->actor()));
    }

    private function crmListAiUsage(array $arguments): array
    {
        /** @var AiUsageService $service */
        $service = $this->container->get('service.ai_usage');
        return $this->publicData($service->usageList($this->aiUsageFilters($arguments)));
    }

    private function crmListAiAudit(array $arguments): array
    {
        /** @var AiUsageService $service */
        $service = $this->container->get('service.ai_usage');
        return $this->publicData($service->auditList($this->aiUsageFilters($arguments)));
    }

    private function crmListAiJobs(array $arguments): array
    {
        /** @var AiJobService $service */
        $service = $this->container->get('service.ai_job');
        return $this->publicData($service->list($this->aiJobFilters($arguments), $this->actor()));
    }

    private function crmGetAiJob(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        /** @var AiJobService $service */
        $service = $this->container->get('service.ai_job');
        $job = $service->get($publicId, $this->actor());
        return $job ? $this->publicData($job) : ['error' => 'AI job not found.'];
    }

    private function crmRetryAiJob(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        /** @var AiJobService $service */
        $service = $this->container->get('service.ai_job');
        return $this->publicData($service->retry($publicId, $this->actor()));
    }

    private function crmDryRunAiJob(array $arguments): array
    {
        $jobCode = trim((string)($arguments['job_code'] ?? ''));
        if ($jobCode === '') {
            return ['error' => 'job_code is required.'];
        }
        /** @var AiJobService $service */
        $service = $this->container->get('service.ai_job');
        return $this->publicData($service->dryRun($jobCode, (array)($arguments['input'] ?? []), $this->actor()));
    }

    private function crmRunOnceAiJob(array $arguments): array
    {
        $jobCode = trim((string)($arguments['job_code'] ?? ''));
        if ($jobCode === '') {
            return ['error' => 'job_code is required.'];
        }
        /** @var AiJobService $service */
        $service = $this->container->get('service.ai_job');
        return $this->publicData($service->runOnce($jobCode, (array)($arguments['input'] ?? []), $this->actor()));
    }

    private function crmSearchAiSemantic(array $arguments): array
    {
        $query = trim((string)($arguments['query'] ?? ''));
        if ($query === '') {
            return ['error' => 'query is required.'];
        }
        /** @var AiSemanticIndexService $service */
        $service = $this->container->get('service.ai_semantic_index');
        return $this->publicData($service->search($query, $this->limit($arguments, 10, 50)));
    }

    private function crmListAiRetentionPolicies(): array
    {
        /** @var AiRetentionPolicyService $service */
        $service = $this->container->get('service.ai_retention');
        return $this->publicData(['policies' => $service->getPolicies()]);
    }

    private function crmListAiSuggestions(array $arguments): array
    {
        /** @var AiSuggestionService $service */
        $service = $this->container->get('service.ai_suggestion');
        return $this->publicData($service->list($this->aiSuggestionFilters($arguments), $this->actor()));
    }

    private function crmGetAiSuggestion(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        /** @var AiSuggestionService $service */
        $service = $this->container->get('service.ai_suggestion');
        $item = $service->get($publicId, $this->actor());
        return $item ? $this->publicData($item) : ['error' => 'AI suggestion not found.'];
    }

    private function crmDismissAiSuggestion(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        /** @var AiSuggestionService $service */
        $service = $this->container->get('service.ai_suggestion');
        return $this->publicData($service->dismiss($publicId, $this->actor()));
    }

    private function crmPreviewApplyAiSuggestion(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        /** @var AiSuggestionService $service */
        $service = $this->container->get('service.ai_suggestion');
        return $this->publicData($service->previewApply($publicId, $this->actor()));
    }

    private function crmConfirmAiSuggestion(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        /** @var AiSuggestionService $service */
        $service = $this->container->get('service.ai_suggestion');
        return $this->publicData($service->confirm($publicId, (array)($arguments['input'] ?? []), $this->actor()));
    }

    private function crmCreateAiDashboardDigest(array $arguments): array
    {
        /** @var AiSuggestionService $service */
        $service = $this->container->get('service.ai_suggestion');
        return $this->publicData($service->createDashboardDigest((array)($arguments['input'] ?? []), $this->actor()));
    }

    private function crmCreateAiMyDayPlan(array $arguments): array
    {
        /** @var AiSuggestionService $service */
        $service = $this->container->get('service.ai_suggestion');
        return $this->publicData($service->createMyDayPlan((array)($arguments['input'] ?? []), $this->actor()));
    }

    private function crmCreateAiMyWeekPlan(array $arguments): array
    {
        /** @var AiSuggestionService $service */
        $service = $this->container->get('service.ai_suggestion');
        return $this->publicData($service->createMyWeekPlan((array)($arguments['input'] ?? []), $this->actor()));
    }

    private function crmCreateAiTaskSummary(array $arguments): array
    {
        $taskPublicId = trim((string)($arguments['task_public_id'] ?? ''));
        if ($taskPublicId === '') {
            return ['error' => 'task_public_id is required.'];
        }
        /** @var AiSuggestionService $service */
        $service = $this->container->get('service.ai_suggestion');
        return $this->publicData($service->createTaskSummary($taskPublicId, (array)($arguments['input'] ?? []), $this->actor()));
    }

    private function crmCreateAiTaskNextAction(array $arguments): array
    {
        $taskPublicId = trim((string)($arguments['task_public_id'] ?? ''));
        if ($taskPublicId === '') {
            return ['error' => 'task_public_id is required.'];
        }
        /** @var AiSuggestionService $service */
        $service = $this->container->get('service.ai_suggestion');
        return $this->publicData($service->createTaskNextAction($taskPublicId, (array)($arguments['input'] ?? []), $this->actor()));
    }

    private function crmCreateAiTaskDecomposition(array $arguments): array
    {
        $taskPublicId = trim((string)($arguments['task_public_id'] ?? ''));
        if ($taskPublicId === '') {
            return ['error' => 'task_public_id is required.'];
        }
        /** @var AiSuggestionService $service */
        $service = $this->container->get('service.ai_suggestion');
        return $this->publicData($service->createTaskDecomposition($taskPublicId, (array)($arguments['input'] ?? []), $this->actor()));
    }

    private function crmCreateAiTaskChecklist(array $arguments): array
    {
        $taskPublicId = trim((string)($arguments['task_public_id'] ?? ''));
        if ($taskPublicId === '') {
            return ['error' => 'task_public_id is required.'];
        }
        /** @var AiSuggestionService $service */
        $service = $this->container->get('service.ai_suggestion');
        return $this->publicData($service->createTaskChecklist($taskPublicId, (array)($arguments['input'] ?? []), $this->actor()));
    }

    private function crmCreateAiTaskQuality(array $arguments): array
    {
        $taskPublicId = trim((string)($arguments['task_public_id'] ?? ''));
        if ($taskPublicId === '') {
            return ['error' => 'task_public_id is required.'];
        }
        /** @var AiSuggestionService $service */
        $service = $this->container->get('service.ai_suggestion');
        return $this->publicData($service->createTaskQuality($taskPublicId, (array)($arguments['input'] ?? []), $this->actor()));
    }

    private function crmCreateAiProjectSummary(array $arguments): array
    {
        $projectPublicId = trim((string)($arguments['project_public_id'] ?? ''));
        if ($projectPublicId === '') {
            return ['error' => 'project_public_id is required.'];
        }
        /** @var AiSuggestionService $service */
        $service = $this->container->get('service.ai_suggestion');
        return $this->publicData($service->createProjectSummary($projectPublicId, (array)($arguments['input'] ?? []), $this->actor()));
    }

    private function crmCreateAiProjectRisks(array $arguments): array
    {
        $projectPublicId = trim((string)($arguments['project_public_id'] ?? ''));
        if ($projectPublicId === '') {
            return ['error' => 'project_public_id is required.'];
        }
        /** @var AiSuggestionService $service */
        $service = $this->container->get('service.ai_suggestion');
        return $this->publicData($service->createProjectRisks($projectPublicId, (array)($arguments['input'] ?? []), $this->actor()));
    }

    private function crmCreateAiAnalyticsKpiExplanation(array $arguments): array
    {
        /** @var AiSuggestionService $service */
        $service = $this->container->get('service.ai_suggestion');
        return $this->publicData($service->createAnalyticsKpiExplanation((array)($arguments['input'] ?? []), $this->actor()));
    }

    private function crmCreateAiAnalyticsRisksExplanation(array $arguments): array
    {
        /** @var AiSuggestionService $service */
        $service = $this->container->get('service.ai_suggestion');
        return $this->publicData($service->createAnalyticsRisksExplanation((array)($arguments['input'] ?? []), $this->actor()));
    }

    private function crmCreateAiAnalyticsTeamWorkloadSummary(array $arguments): array
    {
        /** @var AiSuggestionService $service */
        $service = $this->container->get('service.ai_suggestion');
        return $this->publicData($service->createAnalyticsTeamWorkloadSummary((array)($arguments['input'] ?? []), $this->actor()));
    }

    private function crmGetAnalyticsSummary(): array
    {
        /** @var AnalyticsService $service */
        $service = $this->container->get('service.analytics');
        return $this->publicData($service->summary($this->actor()));
    }

    private function crmListAnalyticsProjects(array $arguments): array
    {
        /** @var AnalyticsService $service */
        $service = $this->container->get('service.analytics');
        return $this->publicData($service->projects($this->actor(), $this->analyticsListFilters($arguments)));
    }

    private function crmListAnalyticsUsers(array $arguments): array
    {
        /** @var AnalyticsService $service */
        $service = $this->container->get('service.analytics');
        return $this->publicData($service->users($this->actor(), $this->analyticsListFilters($arguments)));
    }

    private function crmListIntakeItems(array $arguments): array
    {
        /** @var IntakeItemService $service */
        $service = $this->container->get('service.intake_item');
        return $this->publicData($service->list($this->intakeFilters($arguments), $this->actor()));
    }

    private function crmGetIntakeItem(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var IntakeItemService $service */
        $service = $this->container->get('service.intake_item');
        $item = $service->get($publicId, $this->actor());
        return $item ? ['item' => $this->publicData($item)] : ['error' => 'Intake item not found.'];
    }

    private function crmCreateIntakeItem(array $arguments): array
    {
        /** @var IntakeItemService $service */
        $service = $this->container->get('service.intake_item');
        $result = $service->create($this->pick($arguments, [
            'title', 'description', 'project_public_id', 'client_public_id', 'contact_public_id',
            'source_type', 'source_ref', 'source_email', 'external_source', 'external_id',
            'extra', 'due_at', 'priority_code', 'assignee_user_id',
        ]), $this->actor());
        return is_array($result) ? ['item' => $this->publicData($result)] : ['error' => (string)$result];
    }

    private function crmUpdateIntakeItem(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var IntakeItemService $service */
        $service = $this->container->get('service.intake_item');
        $result = $service->update($publicId, $this->pick($arguments, [
            'title', 'description', 'project_public_id', 'client_public_id', 'contact_public_id',
            'priority_code', 'source_type', 'source_ref', 'source_email', 'external_source',
            'external_id', 'extra', 'due_at', 'assignee_user_id', 'row_version',
        ]), $this->actor());

        if ($result === null) {
            return ['error' => 'Intake item not found.'];
        }
        return is_array($result) ? ['item' => $this->publicData($result)] : ['error' => (string)$result];
    }

    private function crmDeleteIntakeItem(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var IntakeItemService $service */
        $service = $this->container->get('service.intake_item');
        $ok = $service->delete($publicId, $this->actor());
        return $ok === true ? ['ok' => true, 'public_id' => $publicId] : ['error' => (string)$ok ?: 'Intake item not found.'];
    }

    private function crmAcceptIntakeItem(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var IntakeItemService $service */
        $service = $this->container->get('service.intake_item');
        $result = $service->accept($publicId, $this->pick($arguments, [
            'title', 'description', 'project_public_id', 'priority', 'due_at', 'assignee_user_id', 'status', 'row_version',
        ]), $this->actor());
        if ($result === null) {
            return ['error' => 'Intake item not found.'];
        }
        return is_array($result) ? ['result' => $this->publicData($result)] : ['error' => (string)$result];
    }

    private function crmRejectIntakeItem(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var IntakeItemService $service */
        $service = $this->container->get('service.intake_item');
        $result = $service->reject($publicId, $this->pick($arguments, ['reason', 'row_version']), $this->actor());
        if ($result === null) {
            return ['error' => 'Intake item not found.'];
        }
        return is_array($result) ? ['item' => $this->publicData($result)] : ['error' => (string)$result];
    }

    private function crmSnoozeIntakeItem(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var IntakeItemService $service */
        $service = $this->container->get('service.intake_item');
        $result = $service->snooze($publicId, $this->pick($arguments, ['snoozed_until', 'row_version']), $this->actor());
        if ($result === null) {
            return ['error' => 'Intake item not found.'];
        }
        return is_array($result) ? ['item' => $this->publicData($result)] : ['error' => (string)$result];
    }

    private function crmListProjectModules(array $arguments): array
    {
        /** @var ProjectModuleService $service */
        $service = $this->container->get('service.project_module');
        return $this->publicData($service->list($this->projectModuleFilters($arguments), $this->actor()));
    }

    private function crmGetProjectModule(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var ProjectModuleService $service */
        $service = $this->container->get('service.project_module');
        $module = $service->get($publicId, $this->actor());
        return is_array($module) ? ['module' => $this->publicData($module)] : ['error' => (string)$module ?: 'Project module not found.'];
    }

    private function crmCreateProjectModule(array $arguments): array
    {
        /** @var ProjectModuleService $service */
        $service = $this->container->get('service.project_module');
        $result = $service->create($this->pick($arguments, $this->projectModuleInputKeys()), $this->actor());
        return is_array($result) ? ['module' => $this->publicData($result)] : ['error' => (string)$result];
    }

    private function crmUpdateProjectModule(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var ProjectModuleService $service */
        $service = $this->container->get('service.project_module');
        $result = $service->update($publicId, $this->pick($arguments, $this->projectModuleInputKeys(true)), $this->actor());
        if ($result === null) {
            return ['error' => 'Project module not found.'];
        }
        return is_array($result) ? ['module' => $this->publicData($result)] : ['error' => (string)$result];
    }

    private function crmArchiveProjectModule(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var ProjectModuleService $service */
        $service = $this->container->get('service.project_module');
        $ok = $service->archive($publicId, $this->actor());
        return $ok === true ? ['ok' => true, 'public_id' => $publicId] : ['error' => (string)$ok ?: 'Project module not found.'];
    }

    private function crmDeleteProjectModule(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var ProjectModuleService $service */
        $service = $this->container->get('service.project_module');
        $ok = $service->delete($publicId, $this->actor());
        return $ok === true ? ['ok' => true, 'public_id' => $publicId] : ['error' => (string)$ok ?: 'Project module not found.'];
    }

    private function crmListProjectModuleTasks(array $arguments): array
    {
        $publicId = trim((string)($arguments['module_public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'module_public_id is required.'];
        }

        /** @var ProjectModuleService $service */
        $service = $this->container->get('service.project_module');
        $result = $service->tasks($publicId, $this->filters($arguments, 20, 100), $this->actor());
        return $result === null ? ['error' => 'Project module not found.'] : $this->publicData($result);
    }

    private function crmListProjectModuleMembers(array $arguments): array
    {
        $publicId = trim((string)($arguments['module_public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'module_public_id is required.'];
        }

        /** @var ProjectModuleService $service */
        $service = $this->container->get('service.project_module');
        $result = $service->members($publicId, $this->actor());
        return $result === null ? ['error' => 'Project module not found.'] : $this->publicData($result);
    }

    private function crmListProjectModuleLinks(array $arguments): array
    {
        $publicId = trim((string)($arguments['module_public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'module_public_id is required.'];
        }

        /** @var ProjectModuleService $service */
        $service = $this->container->get('service.project_module');
        $result = $service->links($publicId, $this->actor());
        return $result === null ? ['error' => 'Project module not found.'] : $this->publicData($result);
    }

    private function crmAddTasksToProjectModule(array $arguments): array
    {
        $publicId = trim((string)($arguments['module_public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'module_public_id is required.'];
        }

        /** @var ProjectModuleService $service */
        $service = $this->container->get('service.project_module');
        $result = $service->addTasks($publicId, $this->pick($arguments, ['task_public_ids', 'task_keys']), $this->actor());
        return is_array($result) ? $this->publicData($result) : ['error' => (string)$result];
    }

    private function crmAddMembersToProjectModule(array $arguments): array
    {
        $publicId = trim((string)($arguments['module_public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'module_public_id is required.'];
        }

        /** @var ProjectModuleService $service */
        $service = $this->container->get('service.project_module');
        $result = $service->addMembers($publicId, ['members' => $arguments['members'] ?? []], $this->actor());
        return is_array($result) ? $this->publicData($result) : ['error' => (string)$result];
    }

    private function crmRemoveProjectModuleTask(array $arguments): array
    {
        $modulePublicId = trim((string)($arguments['module_public_id'] ?? ''));
        $taskPublicId = trim((string)($arguments['task_public_id'] ?? ''));
        if ($modulePublicId === '' || $taskPublicId === '') {
            return ['error' => 'module_public_id and task_public_id are required.'];
        }

        /** @var ProjectModuleService $service */
        $service = $this->container->get('service.project_module');
        $ok = $service->removeTask($modulePublicId, $taskPublicId, $this->actor());
        return $ok === true ? ['ok' => true] : ['error' => (string)$ok ?: 'Task not found.'];
    }

    private function crmRemoveProjectModuleMember(array $arguments): array
    {
        $modulePublicId = trim((string)($arguments['module_public_id'] ?? ''));
        $userPublicId = trim((string)($arguments['user_public_id'] ?? ''));
        if ($modulePublicId === '' || $userPublicId === '') {
            return ['error' => 'module_public_id and user_public_id are required.'];
        }

        /** @var ProjectModuleService $service */
        $service = $this->container->get('service.project_module');
        $ok = $service->removeMember($modulePublicId, $userPublicId, $this->actor());
        return $ok === true ? ['ok' => true] : ['error' => (string)$ok ?: 'Member not found.'];
    }

    private function crmAddProjectModuleLink(array $arguments): array
    {
        $modulePublicId = trim((string)($arguments['module_public_id'] ?? ''));
        if ($modulePublicId === '') {
            return ['error' => 'module_public_id is required.'];
        }

        /** @var ProjectModuleService $service */
        $service = $this->container->get('service.project_module');
        $result = $service->addLink($modulePublicId, $this->pick($arguments, ['title', 'url', 'link_type', 'sort_order']), $this->actor());
        return is_array($result) ? ['link' => $this->publicData($result)] : ['error' => (string)$result];
    }

    private function crmUpdateProjectModuleLink(array $arguments): array
    {
        $linkPublicId = trim((string)($arguments['link_public_id'] ?? ''));
        if ($linkPublicId === '') {
            return ['error' => 'link_public_id is required.'];
        }

        /** @var ProjectModuleService $service */
        $service = $this->container->get('service.project_module');
        $result = $service->updateLink($linkPublicId, $this->pick($arguments, ['title', 'url', 'link_type', 'sort_order']), $this->actor());
        return is_array($result) ? ['link' => $this->publicData($result)] : ['error' => (string)$result];
    }

    private function crmDeleteProjectModuleLink(array $arguments): array
    {
        $linkPublicId = trim((string)($arguments['link_public_id'] ?? ''));
        if ($linkPublicId === '') {
            return ['error' => 'link_public_id is required.'];
        }

        /** @var ProjectModuleService $service */
        $service = $this->container->get('service.project_module');
        $ok = $service->deleteLink($linkPublicId, $this->actor());
        return $ok === true ? ['ok' => true] : ['error' => (string)$ok ?: 'Link not found.'];
    }

    private function crmListRecycleBin(array $arguments): array
    {
        /** @var RecycleBinService $service */
        $service = $this->container->get('service.recycle_bin');
        return $this->publicData($service->list($this->recycleBinFilters($arguments)));
    }

    private function crmRestoreRecycleBinItem(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var RecycleBinService $service */
        $service = $this->container->get('service.recycle_bin');
        $result = $service->restore($publicId, $this->actor());
        return is_array($result) ? $this->publicData($result) : ['error' => (string)$result];
    }

    private function crmPurgeRecycleBinItem(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var RecycleBinService $service */
        $service = $this->container->get('service.recycle_bin');
        $result = $service->purge($publicId, $this->actor());
        return is_array($result) ? $this->publicData($result) : ['error' => (string)$result];
    }

    private function crmListImportJobs(array $arguments): array
    {
        /** @var ImportService $service */
        $service = $this->container->get('service.import');
        return $this->publicData($service->list($this->jobFilters($arguments), $this->actor()));
    }

    private function crmGetImportJob(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var ImportService $service */
        $service = $this->container->get('service.import');
        $job = $service->get($publicId, $this->actor());
        return $job ? $this->publicData($job) : ['error' => 'Import job not found.'];
    }

    private function crmCreateImportJob(array $arguments): array
    {
        /** @var ImportService $service */
        $service = $this->container->get('service.import');
        $result = $service->create($this->pick($arguments, ['type', 'rows', 'content_base64', 'delimiter', 'has_header', 'columns', 'async']), $this->actor());
        return is_array($result) ? $this->publicData($result) : ['error' => (string)$result];
    }

    private function crmCancelImportJob(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        /** @var ImportService $service */
        $service = $this->container->get('service.import');
        $result = $service->cancel($publicId, $this->actor());
        return is_array($result) ? $this->publicData($result) : ['error' => (string)$result];
    }

    private function crmRetryImportJob(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        /** @var ImportService $service */
        $service = $this->container->get('service.import');
        $result = $service->retry($publicId, $this->actor());
        return is_array($result) ? $this->publicData($result) : ['error' => (string)$result];
    }

    private function crmListExportJobs(array $arguments): array
    {
        /** @var ExportService $service */
        $service = $this->container->get('service.export');
        return $this->publicData($service->list($this->jobFilters($arguments), $this->actor()));
    }

    private function crmGetExportJob(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        /** @var ExportService $service */
        $service = $this->container->get('service.export');
        $job = $service->get($publicId, $this->actor());
        return $job ? $this->publicData($job) : ['error' => 'Export job not found.'];
    }

    private function crmCreateExportJob(array $arguments): array
    {
        /** @var ExportService $service */
        $service = $this->container->get('service.export');
        $result = $service->create($this->pick($arguments, ['type', 'filters', 'async']), $this->actor());
        return is_array($result) ? $this->publicData($result) : ['error' => (string)$result];
    }

    private function crmCancelExportJob(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        /** @var ExportService $service */
        $service = $this->container->get('service.export');
        $result = $service->cancel($publicId, $this->actor());
        return is_array($result) ? $this->publicData($result) : ['error' => (string)$result];
    }

    private function crmRetryExportJob(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        /** @var ExportService $service */
        $service = $this->container->get('service.export');
        $result = $service->retry($publicId, $this->actor());
        return is_array($result) ? $this->publicData($result) : ['error' => (string)$result];
    }

    private function crmDownloadExportJob(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        /** @var ExportService $service */
        $service = $this->container->get('service.export');
        $download = $service->download($publicId, $this->actor());
        if (!is_array($download) || isset($download['error'])) {
            return ['error' => (string)($download['error'] ?? 'Export file not found.')];
        }

        return [
            'ok' => true,
            'public_id' => $publicId,
            'name' => (string)($download['name'] ?? ''),
            'mime' => (string)($download['mime'] ?? ''),
            'size' => (int)($download['size'] ?? 0),
            'download_url' => '/api/index.php?route=api/v1/export/jobs/' . rawurlencode($publicId) . '/download',
        ];
    }

    private function crmListSecuritySessions(array $arguments): array
    {
        /** @var SessionService $service */
        $service = $this->container->get('service.session');
        return $this->publicData($service->list($this->actor(), $this->filters($arguments, 20, 100)));
    }

    private function crmListRoles(array $arguments): array
    {
        /** @var RoleService $service */
        $service = $this->container->get('service.role');
        return $this->publicData($service->list($this->filters($arguments, 50, 100)));
    }

    private function crmListPermissions(): array
    {
        /** @var PermissionService $service */
        $service = $this->container->get('service.permission');
        return $this->publicData($service->list());
    }

    private function crmGetRolePermissions(array $arguments): array
    {
        $publicId = trim((string)($arguments['role_public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'role_public_id is required.'];
        }

        /** @var PermissionService $service */
        $service = $this->container->get('service.permission');
        return $this->publicData($service->listByRole($publicId));
    }

    private function crmGetAdminRoleMatrix(): array
    {
        return $this->payloadData((new RoleMatrixController($this->container))->get());
    }

    private function crmUpdateAdminRoleMatrix(array $arguments): array
    {
        $roles = $arguments['roles'] ?? null;
        if (!is_array($roles)) {
            return ['error' => 'roles must be an array.'];
        }

        /** @var AdminRoleMatrixService $service */
        $service = $this->container->get('service.admin_role_matrix');
        return $this->publicData($service->setMatrix($roles, $this->actor()));
    }

    private function crmListSettings(array $arguments): array
    {
        /** @var SettingService $service */
        $service = $this->container->get('service.setting');
        return $this->redactSettings($this->publicData($service->list($this->filters($arguments, 50, 100))));
    }

    private function crmGetSetting(array $arguments): array
    {
        $name = trim((string)($arguments['name'] ?? ''));
        if ($name === '') {
            return ['error' => 'name is required.'];
        }
        $scope = trim((string)($arguments['scope'] ?? 'system'));
        if ($scope === '') {
            $scope = 'system';
        }

        /** @var SettingService $service */
        $service = $this->container->get('service.setting');
        $item = $service->get($scope, $name);
        return $item ? ['setting' => $this->redactSettingItem($this->publicData($item))] : ['error' => 'Setting not found.'];
    }

    private function crmListFeatureFlags(array $arguments): array
    {
        /** @var FeatureFlagService $service */
        $service = $this->container->get('service.feature_flag');
        return $this->publicData($service->list($this->filters($arguments, 50, 100)));
    }

    private function crmUpdateFeatureFlag(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var FeatureFlagService $service */
        $service = $this->container->get('service.feature_flag');
        return $this->publicData($service->update($publicId, $this->pick($arguments, ['is_enabled', 'payload']), $this->actor()));
    }

    private function crmListAuditLog(array $arguments): array
    {
        /** @var LogsService $service */
        $service = $this->container->get('service.logs');
        return $this->publicData($service->auditList($this->filters($arguments, 50, 100)));
    }

    private function crmListSecurityLog(array $arguments): array
    {
        /** @var LogsService $service */
        $service = $this->container->get('service.logs');
        return $this->publicData($service->securityList($this->filters($arguments, 50, 100)));
    }

    private function crmListApiClients(array $arguments): array
    {
        /** @var ApiClientService $service */
        $service = $this->container->get('service.api_client');
        return $this->publicData($service->listClients($this->filters($arguments, 50, 100)));
    }

    private function crmGetApiClient(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var ApiClientService $service */
        $service = $this->container->get('service.api_client');
        $client = $service->getClient($publicId);
        return $client ? ['client' => $this->publicData($client)] : ['error' => 'API client not found.'];
    }

    private function crmListApiClientKeys(array $arguments): array
    {
        $publicId = trim((string)($arguments['client_public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'client_public_id is required.'];
        }

        /** @var ApiClientService $service */
        $service = $this->container->get('service.api_client');
        return $this->publicData($service->listKeys($publicId));
    }

    private function crmListWebhooks(array $arguments): array
    {
        /** @var WebhookService $service */
        $service = $this->container->get('service.webhook');
        return $this->publicData($service->listSubscriptions($this->filters($arguments, 50, 100)));
    }

    private function crmListWebhookDeliveries(array $arguments): array
    {
        $filters = $this->filters($arguments, 50, 100);
        if (!empty($arguments['webhook_public_id'])) {
            $filters['webhook_public_id'] = trim((string)$arguments['webhook_public_id']);
        }

        /** @var WebhookService $service */
        $service = $this->container->get('service.webhook');
        return $this->publicData($service->listDeliveries($filters));
    }

    private function crmListModules(): array
    {
        $pluginManager = $this->container->get('plugin.manager');
        $moduleConfig = $this->container->get('module.config');
        $pluginManager->discover();
        $items = [];
        foreach ($pluginManager->getDiscovered() as $name => $manifest) {
            $registry = $moduleConfig->getRegistry((string)$name);
            $items[] = [
                'name' => (string)$name,
                'version' => (string)($manifest->version ?? ''),
                'vendor' => (string)($manifest->vendor ?? ''),
                'title' => (string)($manifest->title ?? ''),
                'description' => (string)($manifest->description ?? ''),
                'is_loaded' => $pluginManager->isLoaded((string)$name),
                'is_active' => $registry ? (bool)($registry['is_active'] ?? false) : false,
                'status' => $registry ? ((bool)($registry['is_active'] ?? false) ? 'active' : 'installed') : 'not_installed',
                'installed_at' => $registry['installed_at'] ?? null,
                'activated_at' => $registry['activated_at'] ?? null,
            ];
        }

        return ['items' => $this->publicData($items)];
    }

    private function crmGetModule(array $arguments): array
    {
        $name = trim((string)($arguments['name'] ?? ''));
        if ($name === '') {
            return ['error' => 'name is required.'];
        }

        $pluginManager = $this->container->get('plugin.manager');
        $moduleConfig = $this->container->get('module.config');
        $manifest = $pluginManager->getManifest($name);
        if ($manifest === null) {
            return ['error' => 'Module not found.'];
        }
        $registry = $moduleConfig->getRegistry($name);

        return ['module' => $this->publicData([
            'name' => (string)$manifest->name,
            'version' => (string)$manifest->version,
            'vendor' => (string)$manifest->vendor,
            'title' => (string)$manifest->title,
            'description' => (string)$manifest->description,
            'core_version' => (string)$manifest->coreVersion,
            'dependencies' => $manifest->dependencies,
            'require_permissions' => $manifest->requirePermissions,
            'api_routes' => $manifest->apiRoutes,
            'web_routes' => $manifest->webRoutes,
            'is_loaded' => $pluginManager->isLoaded($name),
            'is_active' => $registry ? (bool)($registry['is_active'] ?? false) : false,
            'installed_at' => $registry['installed_at'] ?? null,
            'activated_at' => $registry['activated_at'] ?? null,
        ])];
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

    private function crmAddTaskComment(array $arguments): array
    {
        $taskPublicId = trim((string)($arguments['task_public_id'] ?? ''));
        $body = trim((string)($arguments['body'] ?? ''));
        if ($taskPublicId === '' || $body === '') {
            return ['error' => 'task_public_id and body are required.'];
        }
        if (mb_strlen($body) > 8000) {
            return ['error' => 'Comment body is too long.'];
        }

        /** @var TaskService $taskService */
        $taskService = $this->container->get('service.task');
        if (!$taskService->get($taskPublicId, $this->actor())) {
            return ['error' => 'Task not found.'];
        }

        /** @var CommentService $service */
        $service = $this->container->get('service.comment');
        $ok = $service->createByTask($taskPublicId, [
            'body' => $body,
            'visibility' => (string)($arguments['visibility'] ?? 'internal'),
        ], (int)($this->actor()['id'] ?? 0));

        return $ok ? ['ok' => true, 'task_public_id' => $taskPublicId] : ['error' => 'Comment was not created.'];
    }

    private function crmListCycles(array $arguments): array
    {
        /** @var WorkCycleService $service */
        $service = $this->container->get('service.work_cycle');
        return $this->publicData($service->list($this->cycleFilters($arguments), $this->actor()));
    }

    private function crmGetCycle(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var WorkCycleService $service */
        $service = $this->container->get('service.work_cycle');
        $cycle = $service->get($publicId, $this->actor());
        return is_array($cycle) ? ['cycle' => $this->publicData($cycle)] : ['error' => (string)($cycle ?: 'Cycle not found.')];
    }

    private function crmCreateCycle(array $arguments): array
    {
        $title = trim((string)($arguments['title'] ?? ''));
        $projectPublicId = trim((string)($arguments['project_public_id'] ?? ''));
        if ($title === '' || $projectPublicId === '') {
            return ['error' => 'title and project_public_id are required.'];
        }

        /** @var WorkCycleService $service */
        $service = $this->container->get('service.work_cycle');
        $cycle = $service->create($this->cycleInput($arguments), $this->actor());
        return is_array($cycle) ? ['cycle' => $this->publicData($cycle)] : ['error' => (string)$cycle];
    }

    private function crmUpdateCycle(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var WorkCycleService $service */
        $service = $this->container->get('service.work_cycle');
        $cycle = $service->update($publicId, $this->cycleInput($arguments), $this->actor());
        return is_array($cycle) ? ['cycle' => $this->publicData($cycle)] : ['error' => (string)($cycle ?: 'Cycle not found.')];
    }

    private function crmListCycleTasks(array $arguments): array
    {
        $cyclePublicId = trim((string)($arguments['cycle_public_id'] ?? ''));
        if ($cyclePublicId === '') {
            return ['error' => 'cycle_public_id is required.'];
        }

        /** @var WorkCycleService $service */
        $service = $this->container->get('service.work_cycle');
        $tasks = $service->tasks($cyclePublicId, $this->filters($arguments, 50, 100), $this->actor());
        return is_array($tasks) ? $this->publicData($tasks) : ['error' => (string)($tasks ?: 'Cycle not found.')];
    }

    private function crmAddTasksToCycle(array $arguments): array
    {
        $cyclePublicId = trim((string)($arguments['cycle_public_id'] ?? ''));
        if ($cyclePublicId === '') {
            return ['error' => 'cycle_public_id is required.'];
        }

        $taskPublicIds = is_array($arguments['task_public_ids'] ?? null) ? (array)$arguments['task_public_ids'] : [];
        $taskKeys = is_array($arguments['task_keys'] ?? null) ? (array)$arguments['task_keys'] : [];
        if ($taskPublicIds === [] && $taskKeys === []) {
            return ['error' => 'task_public_ids or task_keys are required.'];
        }

        /** @var WorkCycleService $service */
        $service = $this->container->get('service.work_cycle');
        $result = $service->addTasks($cyclePublicId, [
            'task_public_ids' => $taskPublicIds,
            'task_keys' => $taskKeys,
            'source_type' => 'mcp',
        ], $this->actor());

        return is_array($result) ? $this->publicData($result) : ['error' => (string)($result ?: 'Tasks were not added.')];
    }

    private function crmListUsers(array $arguments): array
    {
        /** @var UserService $service */
        $service = $this->container->get('service.user');
        return $this->publicData($service->list($this->userFilters($arguments)));
    }

    private function crmListTeams(array $arguments): array
    {
        /** @var TeamService $service */
        $service = $this->container->get('service.team');
        return $this->publicData($service->list($this->teamFilters($arguments), $this->actor()));
    }

    private function crmGetTeam(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var TeamService $service */
        $service = $this->container->get('service.team');
        $team = $service->get($publicId, $this->actor());
        return $team ? ['team' => $this->publicData($team)] : ['error' => 'Team not found.'];
    }

    private function crmCreateTeam(array $arguments): array
    {
        if (trim((string)($arguments['title'] ?? '')) === '') {
            return ['error' => 'title is required.'];
        }

        /** @var TeamService $service */
        $service = $this->container->get('service.team');
        return ['team' => $this->publicData($service->create($this->teamInput($arguments), $this->actor()))];
    }

    private function crmUpdateTeam(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var TeamService $service */
        $service = $this->container->get('service.team');
        $team = $service->update($publicId, $this->teamInput($arguments), $this->actor());
        return $team ? ['team' => $this->publicData($team)] : ['error' => 'Team not found.'];
    }

    private function crmListDepartments(array $arguments): array
    {
        /** @var DepartmentService $service */
        $service = $this->container->get('service.department');
        return $this->publicData($service->list($this->teamFilters($arguments), $this->actor()));
    }

    private function crmGetDepartment(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var DepartmentService $service */
        $service = $this->container->get('service.department');
        $department = $service->get($publicId, $this->actor());
        return $department ? ['department' => $this->publicData($department)] : ['error' => 'Department not found.'];
    }

    private function crmCreateDepartment(array $arguments): array
    {
        if (trim((string)($arguments['title'] ?? '')) === '') {
            return ['error' => 'title is required.'];
        }

        /** @var DepartmentService $service */
        $service = $this->container->get('service.department');
        return ['department' => $this->publicData($service->create($this->departmentInput($arguments), $this->actor()))];
    }

    private function crmUpdateDepartment(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var DepartmentService $service */
        $service = $this->container->get('service.department');
        $department = $service->update($publicId, $this->departmentInput($arguments), $this->actor());
        return $department ? ['department' => $this->publicData($department)] : ['error' => 'Department not found.'];
    }

    private function crmListCounterparties(array $arguments): array
    {
        /** @var CounterpartyService $service */
        $service = $this->container->get('service.counterparty');
        return $this->publicData($service->list($this->crmEntityFilters($arguments), $this->actor()));
    }

    private function crmGetCounterparty(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var CounterpartyService $service */
        $service = $this->container->get('service.counterparty');
        $item = $service->get($publicId, $this->actor());
        return $item ? ['counterparty' => $this->publicData($item)] : ['error' => 'Counterparty not found.'];
    }

    private function crmCreateCounterparty(array $arguments): array
    {
        if (trim((string)($arguments['title'] ?? '')) === '') {
            return ['error' => 'title is required.'];
        }

        /** @var CounterpartyService $service */
        $service = $this->container->get('service.counterparty');
        return ['counterparty' => $this->publicData($service->create($this->counterpartyInput($arguments), $this->actor()))];
    }

    private function crmUpdateCounterparty(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var CounterpartyService $service */
        $service = $this->container->get('service.counterparty');
        try {
            return ['counterparty' => $this->publicData($service->update($publicId, $this->counterpartyInput($arguments), $this->actor()))];
        } catch (Throwable $e) {
            return ['error' => $e->getMessage() ?: 'Counterparty was not updated.'];
        }
    }

    private function crmListCompanies(array $arguments): array
    {
        /** @var CompanyService $service */
        $service = $this->container->get('service.company');
        return $this->publicData($service->list($this->crmEntityFilters($arguments), $this->actor()));
    }

    private function crmGetCompany(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var CompanyService $service */
        $service = $this->container->get('service.company');
        $item = $service->get($publicId, $this->actor());
        return $item ? ['company' => $this->publicData($item)] : ['error' => 'Company not found.'];
    }

    private function crmCreateCompany(array $arguments): array
    {
        if (trim((string)($arguments['title'] ?? '')) === '') {
            return ['error' => 'title is required.'];
        }

        /** @var CompanyService $service */
        $service = $this->container->get('service.company');
        return ['company' => $this->publicData($service->create($this->pick($arguments, ['title', 'status']), $this->actor()))];
    }

    private function crmUpdateCompany(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var CompanyService $service */
        $service = $this->container->get('service.company');
        $item = $service->update($publicId, $this->pick($arguments, ['title', 'status']), $this->actor());
        return $item ? ['company' => $this->publicData($item)] : ['error' => 'Company not found.'];
    }

    private function crmListClients(array $arguments): array
    {
        /** @var ClientService $service */
        $service = $this->container->get('service.client');
        return $this->publicData($service->list($this->crmEntityFilters($arguments), $this->actor()));
    }

    private function crmGetClient(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var ClientService $service */
        $service = $this->container->get('service.client');
        $item = $service->get($publicId, $this->actor());
        return $item ? ['client' => $this->publicData($item)] : ['error' => 'Client not found.'];
    }

    private function crmCreateClient(array $arguments): array
    {
        if (trim((string)($arguments['title'] ?? '')) === '') {
            return ['error' => 'title is required.'];
        }

        /** @var ClientService $service */
        $service = $this->container->get('service.client');
        return ['client' => $this->publicData($service->create($this->clientInput($arguments), $this->actor()))];
    }

    private function crmUpdateClient(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var ClientService $service */
        $service = $this->container->get('service.client');
        $item = $service->update($publicId, $this->clientInput($arguments), $this->actor());
        return $item ? ['client' => $this->publicData($item)] : ['error' => 'Client not found.'];
    }

    private function crmListContacts(array $arguments): array
    {
        /** @var ContactService $service */
        $service = $this->container->get('service.contact');
        return $this->publicData($service->list($this->contactFilters($arguments), $this->actor()));
    }

    private function crmGetContact(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var ContactService $service */
        $service = $this->container->get('service.contact');
        $item = $service->get($publicId, $this->actor());
        return $item ? ['contact' => $this->publicData($item)] : ['error' => 'Contact not found.'];
    }

    private function crmCreateContact(array $arguments): array
    {
        if (trim((string)($arguments['full_name'] ?? '')) === '') {
            return ['error' => 'full_name is required.'];
        }

        /** @var ContactService $service */
        $service = $this->container->get('service.contact');
        try {
            return ['contact' => $this->publicData($service->create($this->contactInput($arguments), $this->actor()))];
        } catch (Throwable $e) {
            return ['error' => $e->getMessage() ?: 'Contact was not created.'];
        }
    }

    private function crmUpdateContact(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var ContactService $service */
        $service = $this->container->get('service.contact');
        try {
            $item = $service->update($publicId, $this->contactInput($arguments), $this->actor());
            return $item ? ['contact' => $this->publicData($item)] : ['error' => 'Contact not found.'];
        } catch (Throwable $e) {
            return ['error' => $e->getMessage() ?: 'Contact was not updated.'];
        }
    }

    private function crmListApprovals(array $arguments): array
    {
        /** @var ApprovalService $service */
        $service = $this->container->get('service.approval');
        return $this->publicData($service->list($this->approvalFilters($arguments), $this->actor()));
    }

    private function crmGetApproval(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var ApprovalService $service */
        $service = $this->container->get('service.approval');
        $result = $service->get($publicId, $this->actor());
        return !empty($result['ok']) ? ['approval' => $this->publicData($result['approval'] ?? [])] : ['error' => (string)($result['code'] ?? 'Approval not found.')];
    }

    private function crmCreateApproval(array $arguments): array
    {
        if (trim((string)($arguments['entity_type'] ?? '')) === '' || trim((string)($arguments['entity_public_id'] ?? '')) === '') {
            return ['error' => 'entity_type and entity_public_id are required.'];
        }
        if (!is_array($arguments['reviewer_public_ids'] ?? null) || (array)$arguments['reviewer_public_ids'] === []) {
            return ['error' => 'reviewer_public_ids are required.'];
        }

        /** @var ApprovalService $service */
        $service = $this->container->get('service.approval');
        $result = $service->create($this->approvalInput($arguments), $this->actor());
        return !empty($result['ok']) ? ['approval' => $this->publicData($result['approval'] ?? [])] : ['error' => (string)($result['code'] ?? 'Approval was not created.')];
    }

    private function crmReviewApproval(array $arguments, string $action): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var ApprovalService $service */
        $service = $this->container->get('service.approval');
        $result = $action === 'approve'
            ? $service->approve($publicId, $this->pick($arguments, ['comment']), $this->actor())
            : $service->reject($publicId, $this->pick($arguments, ['comment']), $this->actor());

        return !empty($result['ok']) ? ['approval' => $this->publicData($result['approval'] ?? [])] : ['error' => (string)($result['code'] ?? 'Approval was not reviewed.')];
    }

    private function crmListRecurringRules(array $arguments): array
    {
        /** @var RecurringService $service */
        $service = $this->container->get('service.recurring');
        return $this->publicData($service->list($this->recurringFilters($arguments)));
    }

    private function crmGetRecurringRule(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var RecurringService $service */
        $service = $this->container->get('service.recurring');
        $rule = $service->get($publicId);
        return $rule ? ['rule' => $this->publicData($rule)] : ['error' => 'Recurring rule not found.'];
    }

    private function crmCreateRecurringRule(array $arguments): array
    {
        foreach (['entity_type', 'entity_public_id', 'rrule'] as $field) {
            if (trim((string)($arguments[$field] ?? '')) === '') {
                return ['error' => $field . ' is required.'];
            }
        }

        /** @var RecurringService $service */
        $service = $this->container->get('service.recurring');
        if (!$service->isValidRrule((string)$arguments['rrule'])) {
            return ['error' => 'Invalid RRULE.'];
        }

        return ['rule' => $this->publicData($service->create($this->recurringInput($arguments)))];
    }

    private function crmUpdateRecurringRule(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var RecurringService $service */
        $service = $this->container->get('service.recurring');
        if (array_key_exists('rrule', $arguments) && !$service->isValidRrule((string)$arguments['rrule'])) {
            return ['error' => 'Invalid RRULE.'];
        }

        $rule = $service->update($publicId, $this->recurringInput($arguments));
        return $rule ? ['rule' => $this->publicData($rule)] : ['error' => 'Recurring rule not found.'];
    }

    private function crmSetRecurringRuleState(array $arguments, bool $active): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var RecurringService $service */
        $service = $this->container->get('service.recurring');
        $rule = $active ? $service->resume($publicId) : $service->pause($publicId);
        return $rule ? ['rule' => $this->publicData($rule)] : ['error' => 'Recurring rule not found.'];
    }

    private function crmListWorkflowRules(array $arguments): array
    {
        /** @var WorkflowService $service */
        $service = $this->container->get('service.workflow');
        return $this->publicData($service->listRules($this->workflowFilters($arguments), $this->actor()));
    }

    private function crmGetWorkflowRule(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var WorkflowService $service */
        $service = $this->container->get('service.workflow');
        $rule = $service->getRule($publicId, $this->actor());
        return $rule ? ['rule' => $this->publicData($rule)] : ['error' => 'Workflow rule not found.'];
    }

    private function crmCreateWorkflowRule(array $arguments): array
    {
        foreach (['title', 'trigger_code', 'action_code'] as $field) {
            if (trim((string)($arguments[$field] ?? '')) === '') {
                return ['error' => $field . ' is required.'];
            }
        }

        /** @var WorkflowService $service */
        $service = $this->container->get('service.workflow');
        return ['rule' => $this->publicData($service->createRule($this->workflowInput($arguments), $this->actor()))];
    }

    private function crmUpdateWorkflowRule(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var WorkflowService $service */
        $service = $this->container->get('service.workflow');
        $rule = $service->updateRule($publicId, $this->workflowInput($arguments), $this->actor());
        return $rule ? ['rule' => $this->publicData($rule)] : ['error' => 'Workflow rule not found.'];
    }

    private function crmListWorkflowRuns(array $arguments): array
    {
        /** @var WorkflowService $service */
        $service = $this->container->get('service.workflow');
        return $this->publicData($service->listRuns($this->workflowRunFilters($arguments), $this->actor()));
    }

    private function crmRunWorkflowRuleTest(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var WorkflowService $service */
        $service = $this->container->get('service.workflow');
        $run = $service->runTest($publicId, is_array($arguments['context'] ?? null) ? (array)$arguments['context'] : [], $this->actor());
        return is_array($run) ? ['run' => $this->publicData($run)] : ['error' => (string)$run];
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

    private function crmGetProjectSummary(array $arguments): array
    {
        $publicId = $this->argumentPublicId($arguments, ['public_id', 'project_public_id']);
        if ($publicId === '') {
            return ['error' => 'public_id or project_public_id is required.'];
        }

        /** @var ProjectSummaryService $service */
        $service = $this->container->get('service.project_summary');
        $result = $service->summary($publicId, $this->actor());
        return $result === 'PROJECT_NOT_FOUND' ? ['error' => 'Project not found.'] : $this->publicData($result);
    }

    private function crmGetProjectTimeline(array $arguments): array
    {
        $publicId = $this->argumentPublicId($arguments, ['public_id', 'project_public_id']);
        if ($publicId === '') {
            return ['error' => 'public_id or project_public_id is required.'];
        }

        /** @var GanttService $service */
        $service = $this->container->get('service.gantt');
        $filters = $this->pick($arguments, ['date_from', 'date_to', 'view_mode']);
        $result = $service->timeline($publicId, $filters, $this->actor());
        return $result === 'PROJECT_NOT_FOUND' ? ['error' => 'Project not found.'] : $this->publicData(['timeline' => $result]);
    }

    private function crmGetProjectMilestonesSummary(array $arguments): array
    {
        $publicId = $this->argumentPublicId($arguments, ['public_id', 'project_public_id']);
        if ($publicId === '') {
            return ['error' => 'public_id or project_public_id is required.'];
        }

        /** @var ProjectSummaryService $service */
        $service = $this->container->get('service.project_summary');
        $result = $service->milestones($publicId, $this->actor());
        return $result === 'PROJECT_NOT_FOUND' ? ['error' => 'Project not found.'] : $this->publicData(['milestones' => $result]);
    }

    private function crmGetProjectRisks(array $arguments): array
    {
        $publicId = $this->argumentPublicId($arguments, ['public_id', 'project_public_id']);
        if ($publicId === '') {
            return ['error' => 'public_id or project_public_id is required.'];
        }

        /** @var ProjectSummaryService $service */
        $service = $this->container->get('service.project_summary');
        $result = $service->risks($publicId, $this->actor());
        return $result === 'PROJECT_NOT_FOUND' ? ['error' => 'Project not found.'] : $this->publicData(['risks' => $result]);
    }

    private function crmGetProjectWorkload(array $arguments): array
    {
        $publicId = $this->argumentPublicId($arguments, ['public_id', 'project_public_id']);
        if ($publicId === '') {
            return ['error' => 'public_id or project_public_id is required.'];
        }

        /** @var ProjectSummaryService $service */
        $service = $this->container->get('service.project_summary');
        $result = $service->workload($publicId, $this->actor());
        return $result === 'PROJECT_NOT_FOUND' ? ['error' => 'Project not found.'] : $this->publicData(['workload' => $result]);
    }

    private function crmGetActivityFeed(array $arguments): array
    {
        /** @var ActivityService $service */
        $service = $this->container->get('service.activity');
        $filters = $this->activityFilters($arguments);
        return $this->publicData($service->feed($filters, $this->actor()));
    }

    private function crmGetActivityHistory(array $arguments): array
    {
        $entityType = trim((string)($arguments['entity_type'] ?? ''));
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($entityType === '' || $publicId === '') {
            return ['error' => 'entity_type and public_id are required.'];
        }

        /** @var ActivityService $service */
        $service = $this->container->get('service.activity');
        $filters = $this->activityFilters($arguments);
        return $this->publicData($service->entityHistory($entityType, $publicId, $filters, $this->actor()));
    }

    private function crmListKnowledgePages(array $arguments): array
    {
        return [
            'items' => $this->publicData($this->knowledge()->pages($this->filters($arguments, 20, 50), $this->actor())),
        ];
    }

    private function crmGetKnowledgeOverview(array $arguments): array
    {
        $filters = $this->filters($arguments, 20, 100);
        return $this->publicData($this->knowledge()->overview($filters, $this->actor()));
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

    private function crmListKnowledgeSpaces(array $arguments): array
    {
        $filters = $this->filters($arguments, 20, 100);
        return ['items' => $this->publicData($this->knowledge()->spaces($filters, $this->actor()))];
    }

    private function crmListKnowledgeSpacesTree(array $arguments): array
    {
        $filters = [
            'include_archived' => !empty($arguments['include_archived']),
        ];
        return ['items' => $this->publicData($this->knowledge()->spacesTree($filters, $this->actor()))];
    }

    private function crmGetKnowledgeSpace(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        $space = $this->knowledge()->space($publicId, $this->actor());
        return $space ? ['space' => $this->publicData($space)] : ['error' => 'Knowledge space not found.'];
    }

    private function crmGetKnowledgeTree(array $arguments): array
    {
        $spacePublicId = $this->argumentPublicId($arguments, ['space_public_id', 'public_id']);
        if ($spacePublicId === '') {
            return ['error' => 'space_public_id or public_id is required.'];
        }
        $depth = max(1, min(20, (int)($arguments['depth'] ?? 10)));
        return ['items' => $this->publicData($this->knowledge()->tree($spacePublicId, $depth, $this->actor()))];
    }

    private function crmSearchKnowledge(array $arguments): array
    {
        $q = trim((string)($arguments['q'] ?? ''));
        if ($q === '') {
            return ['error' => 'q is required.'];
        }
        $filters = $this->filters($arguments, 20, 100);
        unset($filters['status']);
        return ['items' => $this->publicData($this->knowledge()->search($q, $filters, $this->actor()))];
    }

    private function crmListKnowledgeRecent(array $arguments): array
    {
        return ['items' => $this->publicData($this->knowledge()->pages(['limit' => $this->limit($arguments, 20, 100), 'sort' => 'updated_at', 'order' => 'DESC'], $this->actor()))];
    }

    private function crmListKnowledgePopular(array $arguments): array
    {
        return ['items' => $this->publicData($this->knowledge()->popular($this->limit($arguments, 20, 100), $this->actor()))];
    }

    private function crmListKnowledgeReviewQueue(array $arguments): array
    {
        return ['items' => $this->publicData($this->knowledge()->pages(['status' => 'review', 'limit' => $this->limit($arguments, 20, 100)], $this->actor()))];
    }

    private function crmListKnowledgeOutdated(array $arguments): array
    {
        return ['items' => $this->publicData($this->knowledge()->outdated($this->limit($arguments, 20, 100), $this->actor()))];
    }

    private function crmListKnowledgeFavorites(array $arguments): array
    {
        $limit = $this->limit($arguments, 20, 100);
        $offset = max(0, (int)($arguments['offset'] ?? 0));
        return ['items' => $this->publicData($this->knowledge()->favorites((int)($this->actor()['id'] ?? 0), $limit, $offset, $this->actor()))];
    }

    private function crmListKnowledgePageVersions(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        $filters = [
            'limit' => min(100, max(1, (int)($arguments['limit'] ?? 30))),
            'page' => max(1, (int)($arguments['page'] ?? 1)),
        ];
        /** @var KnowledgePageVersionService $service */
        $service = $this->container->get('service.knowledge_page_version');
        $result = $service->listVersions($publicId, $filters, $this->actor());
        return $result === 'KNOWLEDGE_PAGE_NOT_FOUND' ? ['error' => 'Knowledge page not found.'] : $this->publicData($result);
    }

    private function crmGetKnowledgePageVersion(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        $versionPublicId = trim((string)($arguments['version_public_id'] ?? ''));
        if ($publicId === '' || $versionPublicId === '') {
            return ['error' => 'public_id and version_public_id are required.'];
        }

        /** @var KnowledgePageVersionService $service */
        $service = $this->container->get('service.knowledge_page_version');
        $result = $service->getVersion($publicId, $versionPublicId, $this->actor());
        if ($result === 'KNOWLEDGE_PAGE_NOT_FOUND') {
            return ['error' => 'Knowledge page not found.'];
        }
        if ($result === 'KNOWLEDGE_PAGE_VERSION_NOT_FOUND') {
            return ['error' => 'Knowledge page version not found.'];
        }
        return $this->publicData(['version' => $result]);
    }

    private function crmDiffKnowledgePageVersion(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        $versionPublicId = trim((string)($arguments['version_public_id'] ?? ''));
        if ($publicId === '' || $versionPublicId === '') {
            return ['error' => 'public_id and version_public_id are required.'];
        }

        /** @var KnowledgePageVersionService $service */
        $service = $this->container->get('service.knowledge_page_version');
        $result = $service->diffVersion($publicId, $versionPublicId, $this->actor());
        if ($result === 'KNOWLEDGE_PAGE_NOT_FOUND') {
            return ['error' => 'Knowledge page not found.'];
        }
        if ($result === 'KNOWLEDGE_PAGE_VERSION_NOT_FOUND') {
            return ['error' => 'Knowledge page version not found.'];
        }
        return $this->publicData($result);
    }

    private function crmListKnowledgeComments(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        if (!$this->knowledge()->page($publicId, $this->actor())) {
            return ['error' => 'Knowledge page not found.'];
        }

        return ['items' => $this->publicData($this->knowledge()->comments($publicId))];
    }

    private function crmListKnowledgePageLinks(array $arguments): array
    {
        $publicId = $this->argumentPublicId($arguments, ['public_id']);
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        if (!$this->knowledge()->page($publicId, $this->actor())) {
            return ['error' => 'Knowledge page not found.'];
        }
        return ['items' => $this->publicData($this->knowledge()->links($publicId))];
    }

    private function crmDeleteKnowledgePageLink(array $arguments): array
    {
        $linkPublicId = trim((string)($arguments['link_public_id'] ?? ''));
        if ($linkPublicId === '') {
            return ['error' => 'link_public_id is required.'];
        }
        try {
            $this->knowledge()->unlinkEntity($linkPublicId);
        } catch (\RuntimeException $e) {
            return ['error' => 'Knowledge link not found.'];
        }
        return ['deleted' => true];
    }

    private function crmListKnowledgePageTags(array $arguments): array
    {
        $publicId = $this->argumentPublicId($arguments, ['public_id']);
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        if (!$this->knowledge()->page($publicId, $this->actor())) {
            return ['error' => 'Knowledge page not found.'];
        }
        return ['items' => $this->publicData($this->tagRepo()->listByEntity('knowledge_page', $publicId))];
    }

    private function crmAttachKnowledgePageTag(array $arguments): array
    {
        $publicId = $this->argumentPublicId($arguments, ['public_id']);
        $tagPublicId = trim((string)($arguments['tag_public_id'] ?? ''));
        if ($publicId === '' || $tagPublicId === '') {
            return ['error' => 'public_id and tag_public_id are required.'];
        }
        if (!$this->knowledge()->page($publicId, $this->actor(), 'edit')) {
            return ['error' => 'Knowledge page not found.'];
        }
        $tag = $this->tagRepo()->findByPublicId($tagPublicId);
        if (!$tag) {
            return ['error' => 'Tag not found.'];
        }
        $this->tagRepo()->assignToEntity('knowledge_page', $publicId, (int)$tag['id']);
        return ['attached' => true];
    }

    private function crmDetachKnowledgePageTag(array $arguments): array
    {
        $publicId = $this->argumentPublicId($arguments, ['public_id']);
        $tagPublicId = trim((string)($arguments['tag_public_id'] ?? ''));
        if ($publicId === '' || $tagPublicId === '') {
            return ['error' => 'public_id and tag_public_id are required.'];
        }
        if (!$this->knowledge()->page($publicId, $this->actor(), 'edit')) {
            return ['error' => 'Knowledge page not found.'];
        }
        $tag = $this->tagRepo()->findByPublicId($tagPublicId);
        if (!$tag) {
            return ['error' => 'Tag not found.'];
        }
        return ['detached' => $this->tagRepo()->detachFromEntity('knowledge_page', $publicId, (int)$tag['id'])];
    }

    private function crmAddKnowledgeComment(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        $body = trim((string)($arguments['body'] ?? ''));
        if ($publicId === '' || $body === '') {
            return ['error' => 'public_id and body are required.'];
        }
        $page = $this->knowledge()->page($publicId, $this->actor());
        if (!$page) {
            return ['error' => 'Knowledge page not found.'];
        }

        $comment = $this->knowledge()->addComment(
            $publicId,
            $body,
            (int)($this->actor()['id'] ?? 0),
            trim((string)($arguments['parent_public_id'] ?? '')) ?: null
        );
        if (!$comment) {
            return ['error' => 'Knowledge page not found.'];
        }

        return ['comment' => $this->publicData($comment)];
    }

    private function crmDeleteKnowledgeComment(array $arguments): array
    {
        $commentPublicId = trim((string)($arguments['comment_public_id'] ?? ''));
        if ($commentPublicId === '') {
            return ['error' => 'comment_public_id is required.'];
        }
        $ok = $this->knowledge()->deleteComment($commentPublicId, (int)($this->actor()['id'] ?? 0));
        return $ok ? ['deleted' => true] : ['error' => 'Knowledge comment not found.'];
    }

    private function crmResolveKnowledgeComment(array $arguments): array
    {
        $commentPublicId = trim((string)($arguments['comment_public_id'] ?? ''));
        if ($commentPublicId === '') {
            return ['error' => 'comment_public_id is required.'];
        }
        $ok = $this->knowledge()->resolveComment($commentPublicId);
        return $ok ? ['resolved' => true] : ['error' => 'Knowledge comment not found.'];
    }

    private function crmReopenKnowledgeComment(array $arguments): array
    {
        $commentPublicId = trim((string)($arguments['comment_public_id'] ?? ''));
        if ($commentPublicId === '') {
            return ['error' => 'comment_public_id is required.'];
        }
        $ok = $this->knowledge()->reopenComment($commentPublicId);
        return $ok ? ['reopened' => true] : ['error' => 'Knowledge comment not found.'];
    }

    private function crmListKnowledgeFiles(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var FileService $service */
        $service = $this->container->get('service.file');
        $items = $service->listByEntity('knowledge_page', $publicId, $this->user()['user'] ?? []);
        return $items === null ? ['error' => 'Knowledge page not found.'] : ['items' => $this->publicData($items)];
    }

    private function crmDeleteKnowledgeFile(array $arguments): array
    {
        $filePublicId = trim((string)($arguments['file_public_id'] ?? ''));
        if ($filePublicId === '') {
            return ['error' => 'file_public_id is required.'];
        }

        /** @var FileService $service */
        $service = $this->container->get('service.file');
        $ok = $service->delete($filePublicId, $this->user()['user'] ?? []);
        return $ok ? ['deleted' => true] : ['error' => 'File not found.'];
    }

    private function crmDeleteKnowledgePage(array $arguments): array
    {
        $publicId = $this->argumentPublicId($arguments, ['public_id']);
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        if (!$this->knowledge()->page($publicId, $this->actor(), 'manage')) {
            return ['error' => 'Knowledge page not found.'];
        }
        return $this->knowledge()->deletePage($publicId)
            ? ['deleted' => true]
            : ['error' => 'Knowledge page not found.'];
    }

    private function crmDeleteKnowledgeDraft(array $arguments): array
    {
        $publicId = $this->argumentPublicId($arguments, ['public_id']);
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        $ok = $this->knowledge()->deleteDraft($publicId, (int)($this->actor()['id'] ?? 0));
        return $ok ? ['deleted' => true] : ['error' => 'Knowledge draft not found.'];
    }

    private function crmPublishKnowledgePage(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        $page = $this->knowledge()->publish($publicId, (int)($this->actor()['id'] ?? 0), trim((string)($arguments['change_summary'] ?? '')));
        return $page ? ['page' => $this->publicData($page)] : ['error' => 'Knowledge page not found.'];
    }

    private function crmArchiveKnowledgePage(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        $page = $this->knowledge()->setStatus($publicId, 'archived', (int)($this->actor()['id'] ?? 0));
        return $page ? ['page' => $this->publicData($page)] : ['error' => 'Knowledge page not found.'];
    }

    private function crmRestoreKnowledgePage(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        $page = $this->knowledge()->setStatus($publicId, 'draft', (int)($this->actor()['id'] ?? 0));
        return $page ? ['page' => $this->publicData($page)] : ['error' => 'Knowledge page not found.'];
    }

    private function crmRequestKnowledgeReview(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        $page = $this->knowledge()->setStatus($publicId, 'review', (int)($this->actor()['id'] ?? 0));
        return $page ? ['page' => $this->publicData($page)] : ['error' => 'Knowledge page not found.'];
    }

    private function crmApproveKnowledgeReview(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        $page = $this->knowledge()->publish($publicId, (int)($this->actor()['id'] ?? 0), trim((string)($arguments['change_summary'] ?? '')));
        return $page ? ['page' => $this->publicData($page)] : ['error' => 'Knowledge page not found.'];
    }

    private function crmRejectKnowledgeReview(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        $page = $this->knowledge()->setStatus($publicId, 'draft', (int)($this->actor()['id'] ?? 0));
        return $page ? ['page' => $this->publicData($page)] : ['error' => 'Knowledge page not found.'];
    }

    private function crmDuplicateKnowledgePage(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        $page = $this->knowledge()->duplicate($publicId, (int)($this->actor()['id'] ?? 0), $this->actor());
        return $page ? ['page' => $this->publicData($page)] : ['error' => 'Knowledge page not found.'];
    }

    private function crmMoveKnowledgePage(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        $page = $this->knowledge()->updatePage($publicId, [
            'space_public_id' => $arguments['space_public_id'] ?? null,
            'parent_public_id' => $arguments['parent_public_id'] ?? null,
            'sort_order' => $arguments['sort_order'] ?? null,
        ], (int)($this->actor()['id'] ?? 0), $this->actor());
        return $page && $page !== 'ROW_VERSION_CONFLICT' ? ['page' => $this->publicData($page)] : ['error' => 'Knowledge page not found.'];
    }

    private function crmLockKnowledgePage(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        /** @var KnowledgePageVersionService $service */
        $service = $this->container->get('service.knowledge_page_version');
        $result = $service->lockPage($publicId, $this->pick($arguments, ['reason']), $this->actor());
        return $result === 'KNOWLEDGE_PAGE_NOT_FOUND'
            ? ['error' => 'Knowledge page not found.']
            : ($result === 'KNOWLEDGE_PAGE_ALREADY_LOCKED' ? ['error' => 'Knowledge page already locked.'] : $this->publicData(['page' => $result]));
    }

    private function crmUnlockKnowledgePage(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        /** @var KnowledgePageVersionService $service */
        $service = $this->container->get('service.knowledge_page_version');
        $result = $service->unlockPage($publicId, [], $this->actor());
        return $result === 'KNOWLEDGE_PAGE_NOT_FOUND' ? ['error' => 'Knowledge page not found.'] : $this->publicData(['page' => $result]);
    }

    private function crmGetKnowledgeSpacePermissions(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        $space = $this->knowledge()->space($publicId, $this->actor());
        if (!$space) {
            return ['error' => 'Knowledge space not found.'];
        }
        return ['items' => $this->publicData($this->knowledge()->spacePermissions($publicId))];
    }

    private function crmAddKnowledgeSpacePermission(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        $subjectType = trim((string)($arguments['subject_type'] ?? ''));
        if ($publicId === '' || $subjectType === '') {
            return ['error' => 'public_id and subject_type are required.'];
        }
        $result = $this->knowledge()->addSpacePermission(
            $publicId,
            $subjectType,
            (int)($arguments['subject_id'] ?? 0),
            trim((string)($arguments['access_level'] ?? 'view')),
            (int)($this->actor()['id'] ?? 0),
            trim((string)($arguments['subject_public_id'] ?? ''))
        );
        return $result ? ['permission' => $this->publicData($result)] : ['error' => 'Knowledge space not found.'];
    }

    private function crmRemoveKnowledgeSpacePermission(array $arguments): array
    {
        $permissionId = (int)($arguments['permission_id'] ?? 0);
        if ($permissionId <= 0) {
            return ['error' => 'permission_id is required.'];
        }
        $this->knowledge()->removeSpacePermission($permissionId);
        return ['removed' => true];
    }

    private function crmGetKnowledgePagePermissions(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        $page = $this->knowledge()->page($publicId, $this->actor());
        if (!$page) {
            return ['error' => 'Knowledge page not found.'];
        }
        return ['items' => $this->publicData($this->knowledge()->pagePermissions($publicId))];
    }

    private function crmAddKnowledgePagePermission(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        $subjectType = trim((string)($arguments['subject_type'] ?? ''));
        if ($publicId === '' || $subjectType === '') {
            return ['error' => 'public_id and subject_type are required.'];
        }
        $result = $this->knowledge()->addPagePermission(
            $publicId,
            $subjectType,
            (int)($arguments['subject_id'] ?? 0),
            trim((string)($arguments['access_level'] ?? 'view')),
            (int)($this->actor()['id'] ?? 0),
            trim((string)($arguments['subject_public_id'] ?? ''))
        );
        return $result ? ['permission' => $this->publicData($result)] : ['error' => 'Knowledge page not found.'];
    }

    private function crmRemoveKnowledgePagePermission(array $arguments): array
    {
        $permissionId = (int)($arguments['permission_id'] ?? 0);
        if ($permissionId <= 0) {
            return ['error' => 'permission_id is required.'];
        }
        $this->knowledge()->removePagePermission($permissionId);
        return ['removed' => true];
    }

    private function crmGetAdminKnowledgeSettings(): array
    {
        return $this->payloadData((new KnowledgeController($this->container))->adminGetSettings());
    }

    private function crmUpdateAdminKnowledgeSettings(array $arguments): array
    {
        $settings = $arguments['settings'] ?? null;
        if (!is_array($settings)) {
            return ['error' => 'settings must be an object.'];
        }
        /** @var SettingService $service */
        $service = $this->container->get('service.setting');
        foreach ($settings as $name => $value) {
            $service->set('knowledge', (string)$name, $value);
        }
        return ['updated' => $this->publicData($settings)];
    }

    private function crmReindexKnowledge(): array
    {
        return $this->payloadData((new KnowledgeController($this->container))->adminReindex());
    }

    private function crmRebuildKnowledgePermissions(): array
    {
        return $this->payloadData((new KnowledgeController($this->container))->adminRebuildPermissions());
    }

    private function crmCleanupKnowledgeDrafts(): array
    {
        return $this->payloadData((new KnowledgeController($this->container))->adminCleanupDrafts());
    }

    private function crmListCalendarEvents(array $arguments): array
    {
        /** @var CalendarService $service */
        $service = $this->container->get('service.calendar');
        return $this->publicData($service->listEvents($this->calendarFilters($arguments), $this->actor()));
    }

    private function crmGetCalendarAgenda(array $arguments): array
    {
        /** @var CalendarService $service */
        $service = $this->container->get('service.calendar');
        $period = (string)($arguments['period'] ?? 'day');
        $date = trim((string)($arguments['date'] ?? '')) ?: null;

        return [
            'period' => $period === 'week' ? 'week' : 'day',
            'agenda' => $this->publicData($period === 'week' ? $service->myWeek($this->actor(), $date) : $service->myDay($this->actor(), $date)),
        ];
    }

    private function crmCreateCalendarEvent(array $arguments): array
    {
        $title = trim((string)($arguments['title'] ?? ''));
        $startsAt = trim((string)($arguments['starts_at'] ?? ''));
        if ($title === '' || $startsAt === '') {
            return ['error' => 'title and starts_at are required.'];
        }
        if (strtotime($startsAt) === false) {
            return ['error' => 'starts_at must be a valid date/time.'];
        }
        if (!empty($arguments['ends_at']) && strtotime((string)$arguments['ends_at']) === false) {
            return ['error' => 'ends_at must be a valid date/time.'];
        }

        /** @var CalendarService $service */
        $service = $this->container->get('service.calendar');
        $event = $service->createEvent($this->pick($arguments, [
            'title', 'description', 'starts_at', 'ends_at', 'project_public_id', 'task_public_id',
        ]), $this->actor());

        return is_array($event) ? ['event' => $this->publicData($event)] : ['error' => (string)$event];
    }

    private function crmListMilestones(array $arguments): array
    {
        $projectPublicId = trim((string)($arguments['project_public_id'] ?? ''));
        if ($projectPublicId === '') {
            return ['error' => 'project_public_id is required.'];
        }

        /** @var MilestoneService $service */
        $service = $this->container->get('service.milestone');
        $items = $service->list($projectPublicId, $this->actor());
        return is_array($items) ? ['items' => $this->publicData($items)] : ['error' => (string)$items];
    }

    private function crmGetMilestone(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var MilestoneService $service */
        $service = $this->container->get('service.milestone');
        $item = $service->get($publicId, $this->actor());
        return $item ? ['milestone' => $this->publicData($item)] : ['error' => 'Milestone not found.'];
    }

    private function crmCreateMilestone(array $arguments): array
    {
        if (trim((string)($arguments['project_public_id'] ?? '')) === '' || trim((string)($arguments['title'] ?? '')) === '') {
            return ['error' => 'project_public_id and title are required.'];
        }

        /** @var MilestoneService $service */
        $service = $this->container->get('service.milestone');
        $item = $service->create($this->milestoneInput($arguments), $this->actor());
        return is_array($item) ? ['milestone' => $this->publicData($item)] : ['error' => (string)$item];
    }

    private function crmUpdateMilestone(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var MilestoneService $service */
        $service = $this->container->get('service.milestone');
        $item = $service->update($publicId, $this->milestoneInput($arguments), $this->actor());
        if ($item === null) {
            return ['error' => 'Milestone not found.'];
        }
        return is_array($item) ? ['milestone' => $this->publicData($item)] : ['error' => (string)$item];
    }

    private function crmListReminders(array $arguments): array
    {
        /** @var ReminderService $service */
        $service = $this->container->get('service.reminder');
        return $this->publicData($service->list($this->reminderFilters($arguments), $this->actor()));
    }

    private function crmGetReminder(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var ReminderService $service */
        $service = $this->container->get('service.reminder');
        $item = $service->get($publicId, $this->actor());
        return $item ? ['reminder' => $this->publicData($item)] : ['error' => 'Reminder not found.'];
    }

    private function crmCreateReminder(array $arguments): array
    {
        $remindAt = trim((string)($arguments['remind_at'] ?? ''));
        if ($remindAt === '') {
            return ['error' => 'remind_at is required.'];
        }
        if (strtotime($remindAt) === false) {
            return ['error' => 'remind_at must be a valid date/time.'];
        }

        /** @var ReminderService $service */
        $service = $this->container->get('service.reminder');
        $item = $service->create($this->reminderInput($arguments), $this->actor());
        return is_array($item) ? ['reminder' => $this->publicData($item)] : ['error' => (string)$item];
    }

    private function crmUpdateReminder(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        if (!empty($arguments['remind_at']) && strtotime((string)$arguments['remind_at']) === false) {
            return ['error' => 'remind_at must be a valid date/time.'];
        }

        /** @var ReminderService $service */
        $service = $this->container->get('service.reminder');
        $item = $service->update($publicId, $this->reminderInput($arguments), $this->actor());
        if ($item === null || $item === false) {
            return ['error' => 'Reminder not found.'];
        }
        return is_array($item) ? ['reminder' => $this->publicData($item)] : ['error' => (string)$item];
    }

    private function crmDeleteReminder(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var ReminderService $service */
        $service = $this->container->get('service.reminder');
        return $service->delete($publicId, $this->actor())
            ? ['ok' => true, 'public_id' => $publicId]
            : ['error' => 'Reminder not found.'];
    }

    private function crmListSavedViews(array $arguments): array
    {
        /** @var SavedViewService $service */
        $service = $this->container->get('service.saved_view');
        return $this->publicData($service->list($this->savedViewFilters($arguments), $this->actor()));
    }

    private function crmGetSavedView(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var SavedViewService $service */
        $service = $this->container->get('service.saved_view');
        $item = $service->get($publicId, $this->actor());
        if ($item === null) {
            return ['error' => 'Saved view not found.'];
        }
        return is_array($item) ? ['saved_view' => $this->publicData($item)] : ['error' => (string)$item];
    }

    private function crmCreateSavedView(array $arguments): array
    {
        if (trim((string)($arguments['title'] ?? '')) === '') {
            return ['error' => 'title is required.'];
        }

        /** @var SavedViewService $service */
        $service = $this->container->get('service.saved_view');
        $item = $service->create($this->savedViewInput($arguments), $this->actor());
        return is_array($item) ? ['saved_view' => $this->publicData($item)] : ['error' => (string)$item];
    }

    private function crmUpdateSavedView(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var SavedViewService $service */
        $service = $this->container->get('service.saved_view');
        $item = $service->update($publicId, $this->savedViewInput($arguments), $this->actor());
        if ($item === null) {
            return ['error' => 'Saved view not found.'];
        }
        return is_array($item) ? ['saved_view' => $this->publicData($item)] : ['error' => (string)$item];
    }

    private function crmArchiveSavedView(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var SavedViewService $service */
        $service = $this->container->get('service.saved_view');
        $ok = $service->archive($publicId, $this->actor());
        if ($ok === false) {
            return ['error' => 'Saved view not found.'];
        }
        return is_string($ok) ? ['error' => $ok] : ['ok' => true, 'public_id' => $publicId];
    }

    private function crmDuplicateSavedView(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var SavedViewService $service */
        $service = $this->container->get('service.saved_view');
        $item = $service->duplicate($publicId, $this->pick($arguments, ['title']), $this->actor());
        if ($item === null) {
            return ['error' => 'Saved view not found.'];
        }
        return is_array($item) ? ['saved_view' => $this->publicData($item)] : ['error' => (string)$item];
    }

    private function crmPinSavedView(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var SavedViewService $service */
        $service = $this->container->get('service.saved_view');
        $item = $service->pin($publicId, $this->pick($arguments, ['is_pinned', 'sort_order']), $this->actor());
        if ($item === null) {
            return ['error' => 'Saved view not found.'];
        }
        return is_array($item) ? ['preference' => $this->publicData($item)] : ['error' => (string)$item];
    }

    private function crmGetSavedViewTaskFilters(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var SavedViewService $service */
        $service = $this->container->get('service.saved_view');
        $result = $service->getTaskFilters($publicId, $this->actor());
        if ($result === null) {
            return ['error' => 'Saved view not found.'];
        }
        return is_array($result) ? $this->publicData($result) : ['error' => (string)$result];
    }

    private function crmListStickyNotes(array $arguments): array
    {
        /** @var StickyNoteService $service */
        $service = $this->container->get('service.sticky_note');
        return $this->publicData($service->list($this->stickyNoteFilters($arguments), (int)($this->actor()['id'] ?? 0), (bool)($this->actor()['is_root'] ?? false)));
    }

    private function crmGetStickyNote(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var StickyNoteService $service */
        $service = $this->container->get('service.sticky_note');
        $item = $service->get($publicId, (int)($this->actor()['id'] ?? 0), (bool)($this->actor()['is_root'] ?? false));
        return isset($item['error']) ? ['error' => (string)$item['error']] : ['sticky_note' => $this->publicData($item)];
    }

    private function crmCreateStickyNote(array $arguments): array
    {
        if (trim((string)($arguments['body'] ?? '')) === '') {
            return ['error' => 'body is required.'];
        }

        /** @var StickyNoteService $service */
        $service = $this->container->get('service.sticky_note');
        $item = $service->create($this->stickyNoteInput($arguments), (int)($this->actor()['id'] ?? 0));
        return isset($item['error']) ? ['error' => (string)$item['error'], 'details' => $item['errors'] ?? null] : ['sticky_note' => $this->publicData($item)];
    }

    private function crmUpdateStickyNote(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var StickyNoteService $service */
        $service = $this->container->get('service.sticky_note');
        $item = $service->update($publicId, $this->stickyNoteInput($arguments), (int)($this->actor()['id'] ?? 0), (bool)($this->actor()['is_root'] ?? false));
        return isset($item['error']) ? ['error' => (string)$item['error'], 'details' => $item['errors'] ?? null] : ['sticky_note' => $this->publicData($item)];
    }

    private function crmSetStickyNoteArchived(array $arguments, bool $archived): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var StickyNoteService $service */
        $service = $this->container->get('service.sticky_note');
        $result = $archived
            ? $service->archive($publicId, (int)($this->actor()['id'] ?? 0), (bool)($this->actor()['is_root'] ?? false))
            : $service->unarchive($publicId, (int)($this->actor()['id'] ?? 0), (bool)($this->actor()['is_root'] ?? false));

        return isset($result['error']) ? ['error' => (string)$result['error']] : $this->publicData($result);
    }

    private function crmListEstimateSets(array $arguments): array
    {
        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        return $this->publicData($service->listSets($this->estimateSetFilters($arguments), $this->actor()));
    }

    private function crmGetEstimateSet(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $item = $service->getSet($publicId, $this->actor());
        if ($item === null || $item === false) {
            return ['error' => 'Estimate set not found.'];
        }
        return is_array($item) ? ['estimate_set' => $this->publicData($item)] : ['error' => (string)$item];
    }

    private function crmCreateEstimateSet(array $arguments): array
    {
        if (trim((string)($arguments['name'] ?? '')) === '') {
            return ['error' => 'name is required.'];
        }

        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $item = $service->createSet($this->estimateSetInput($arguments), $this->actor());
        return is_array($item) ? ['estimate_set' => $this->publicData($item)] : ['error' => (string)$item];
    }

    private function crmUpdateEstimateSet(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $item = $service->updateSet($publicId, $this->estimateSetInput($arguments), $this->actor());
        if ($item === null || $item === false) {
            return ['error' => 'Estimate set not found.'];
        }
        return is_array($item) ? ['estimate_set' => $this->publicData($item)] : ['error' => (string)$item];
    }

    private function crmListEstimateOptions(array $arguments): array
    {
        $setPublicId = trim((string)($arguments['estimate_set_public_id'] ?? ''));
        if ($setPublicId === '') {
            return ['error' => 'estimate_set_public_id is required.'];
        }

        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $items = $service->listOptions($setPublicId, $this->pick($arguments, ['active_only']), $this->actor());
        if ($items === null || $items === false) {
            return ['error' => 'Estimate set not found.'];
        }
        return is_array($items) ? ['items' => $this->publicData($items)] : ['error' => (string)$items];
    }

    private function crmCreateEstimateOption(array $arguments): array
    {
        $setPublicId = trim((string)($arguments['estimate_set_public_id'] ?? ''));
        if ($setPublicId === '' || trim((string)($arguments['label'] ?? '')) === '') {
            return ['error' => 'estimate_set_public_id and label are required.'];
        }

        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $item = $service->createOption($setPublicId, $this->estimateOptionInput($arguments), $this->actor());
        if ($item === null || $item === false) {
            return ['error' => 'Estimate set not found.'];
        }
        return is_array($item) ? ['estimate_option' => $this->publicData($item)] : ['error' => (string)$item];
    }

    private function crmUpdateEstimateOption(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $item = $service->updateOption($publicId, $this->estimateOptionInput($arguments), $this->actor());
        if ($item === null || $item === false) {
            return ['error' => 'Estimate option not found.'];
        }
        return is_array($item) ? ['estimate_option' => $this->publicData($item)] : ['error' => (string)$item];
    }

    private function crmListTaskEstimates(array $arguments): array
    {
        $taskPublicId = trim((string)($arguments['task_public_id'] ?? ''));
        if ($taskPublicId === '') {
            return ['error' => 'task_public_id is required.'];
        }

        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $items = $service->listTaskEstimates($taskPublicId, $this->actor());
        if ($items === null || $items === false) {
            return ['error' => 'Task not found.'];
        }
        return is_array($items) ? ['items' => $this->publicData($items)] : ['error' => (string)$items];
    }

    private function crmAssignTaskEstimate(array $arguments): array
    {
        $taskPublicId = trim((string)($arguments['task_public_id'] ?? ''));
        if ($taskPublicId === '' || trim((string)($arguments['estimate_set_public_id'] ?? '')) === '') {
            return ['error' => 'task_public_id and estimate_set_public_id are required.'];
        }

        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $item = $service->assignTaskEstimate($taskPublicId, $this->taskEstimateInput($arguments), $this->actor());
        if ($item === null || $item === false) {
            return ['error' => 'Task not found.'];
        }
        return is_array($item) ? ['task_estimate' => $this->publicData($item)] : ['error' => (string)$item];
    }

    private function crmRemoveTaskEstimate(array $arguments): array
    {
        $taskPublicId = trim((string)($arguments['task_public_id'] ?? ''));
        $setPublicId = trim((string)($arguments['estimate_set_public_id'] ?? ''));
        if ($taskPublicId === '' || $setPublicId === '') {
            return ['error' => 'task_public_id and estimate_set_public_id are required.'];
        }

        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $ok = $service->removeTaskEstimate($taskPublicId, $setPublicId, $this->actor());
        return $ok ? ['ok' => true, 'task_public_id' => $taskPublicId, 'estimate_set_public_id' => $setPublicId] : ['error' => 'Task estimate not found.'];
    }

    private function crmGetProjectEstimateSummary(array $arguments): array
    {
        return $this->estimateSummary($arguments, 'project_public_id', 'summaryByProject');
    }

    private function crmGetCycleEstimateSummary(array $arguments): array
    {
        return $this->estimateSummary($arguments, 'cycle_public_id', 'summaryByCycle');
    }

    private function crmGetModuleEstimateSummary(array $arguments): array
    {
        return $this->estimateSummary($arguments, 'module_public_id', 'summaryByModule');
    }

    private function estimateSummary(array $arguments, string $publicIdKey, string $method): array
    {
        $publicId = trim((string)($arguments[$publicIdKey] ?? ''));
        if ($publicId === '') {
            return ['error' => $publicIdKey . ' is required.'];
        }

        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $summary = $service->{$method}($publicId, $this->pick($arguments, ['estimate_set_public_id']), $this->actor());
        if ($summary === null || $summary === false) {
            return ['error' => 'Estimate summary source not found.'];
        }
        return is_array($summary) ? ['summary' => $this->publicData($summary)] : ['error' => (string)$summary];
    }

    private function crmListCustomFields(array $arguments): array
    {
        /** @var CustomFieldService $service */
        $service = $this->container->get('service.custom_field');
        return $this->publicData($service->list($this->customFieldFilters($arguments)));
    }

    private function crmGetCustomField(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var CustomFieldService $service */
        $service = $this->container->get('service.custom_field');
        $item = $service->get($publicId);
        return $item ? ['field' => $this->publicData($item)] : ['error' => 'Custom field not found.'];
    }

    private function crmCreateCustomField(array $arguments): array
    {
        foreach (['scope', 'code', 'title', 'type'] as $field) {
            if (trim((string)($arguments[$field] ?? '')) === '') {
                return ['error' => $field . ' is required.'];
            }
        }

        /** @var CustomFieldService $service */
        $service = $this->container->get('service.custom_field');
        $item = $service->create($this->customFieldInput($arguments));
        return is_array($item) ? ['field' => $this->publicData($item)] : ['error' => (string)$item];
    }

    private function crmUpdateCustomField(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var CustomFieldService $service */
        $service = $this->container->get('service.custom_field');
        $item = $service->update($publicId, $this->customFieldInput($arguments));
        if ($item === null || $item === false) {
            return ['error' => 'Custom field not found.'];
        }
        return is_array($item) ? ['field' => $this->publicData($item)] : ['error' => (string)$item];
    }

    private function crmGetCustomFieldValues(array $arguments): array
    {
        $entityType = trim((string)($arguments['entity_type'] ?? ''));
        $entityPublicId = trim((string)($arguments['entity_public_id'] ?? ''));
        if ($entityType === '' || $entityPublicId === '') {
            return ['error' => 'entity_type and entity_public_id are required.'];
        }

        /** @var CustomFieldService $service */
        $service = $this->container->get('service.custom_field');
        return ['items' => $this->publicData($service->values($entityType, $entityPublicId))];
    }

    private function crmSetCustomFieldValues(array $arguments): array
    {
        $entityType = trim((string)($arguments['entity_type'] ?? ''));
        $entityPublicId = trim((string)($arguments['entity_public_id'] ?? ''));
        $values = $arguments['values'] ?? null;
        if ($entityType === '' || $entityPublicId === '' || !is_array($values) || $values === []) {
            return ['error' => 'entity_type, entity_public_id and non-empty values object are required.'];
        }

        /** @var CustomFieldService $service */
        $service = $this->container->get('service.custom_field');
        $result = $service->setValues($entityType, $entityPublicId, $values);
        return is_array($result) ? $this->publicData($result) : ['error' => (string)$result];
    }

    private function crmListSlaPolicies(array $arguments): array
    {
        /** @var SlaService $service */
        $service = $this->container->get('service.sla');
        return $this->publicData($service->list($this->slaFilters($arguments)));
    }

    private function crmGetSlaPolicy(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var SlaService $service */
        $service = $this->container->get('service.sla');
        $item = $service->get($publicId);
        return $item ? ['policy' => $this->publicData($item)] : ['error' => 'SLA policy not found.'];
    }

    private function crmCreateSlaPolicy(array $arguments): array
    {
        foreach (['title', 'response_minutes', 'resolve_minutes'] as $field) {
            if (trim((string)($arguments[$field] ?? '')) === '') {
                return ['error' => $field . ' is required.'];
            }
        }

        /** @var SlaService $service */
        $service = $this->container->get('service.sla');
        return ['policy' => $this->publicData($service->create($this->slaPolicyInput($arguments)))];
    }

    private function crmUpdateSlaPolicy(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var SlaService $service */
        $service = $this->container->get('service.sla');
        $item = $service->update($publicId, $this->slaPolicyInput($arguments));
        return $item ? ['policy' => $this->publicData($item)] : ['error' => 'SLA policy not found.'];
    }

    private function crmGetSlaReport(): array
    {
        /** @var SlaService $service */
        $service = $this->container->get('service.sla');
        return ['report' => $this->publicData($service->report())];
    }

    private function crmAssignSlaToTask(array $arguments): array
    {
        $taskPublicId = trim((string)($arguments['task_public_id'] ?? ''));
        $slaPublicId = trim((string)($arguments['sla_policy_public_id'] ?? ''));
        if ($taskPublicId === '' || $slaPublicId === '') {
            return ['error' => 'task_public_id and sla_policy_public_id are required.'];
        }

        /** @var SlaService $service */
        $service = $this->container->get('service.sla');
        $result = $service->assignToTask($taskPublicId, $slaPublicId);
        return $result ? ['task' => $this->publicData($result)] : ['error' => 'Task or SLA policy not found.'];
    }

    private function crmListTemplates(array $arguments): array
    {
        $kind = $this->templateKind($arguments);
        if ($kind === null) {
            return ['error' => 'kind must be task or project.'];
        }

        /** @var TemplateService $service */
        $service = $this->container->get('service.template');
        return $this->publicData($service->list($kind, $this->templateFilters($arguments), $this->actor()));
    }

    private function crmGetTemplate(array $arguments): array
    {
        $kind = $this->templateKind($arguments);
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($kind === null || $publicId === '') {
            return ['error' => 'kind and public_id are required.'];
        }

        /** @var TemplateService $service */
        $service = $this->container->get('service.template');
        $item = $service->get($kind, $publicId, $this->actor());
        return $item ? ['template' => $this->publicData($item)] : ['error' => 'Template not found.'];
    }

    private function crmCreateTemplate(array $arguments): array
    {
        $kind = $this->templateKind($arguments);
        if ($kind === null || trim((string)($arguments['title'] ?? '')) === '') {
            return ['error' => 'kind and title are required.'];
        }

        /** @var TemplateService $service */
        $service = $this->container->get('service.template');
        return ['template' => $this->publicData($service->create($kind, $this->templateInput($arguments), $this->actor()))];
    }

    private function crmUpdateTemplate(array $arguments): array
    {
        $kind = $this->templateKind($arguments);
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($kind === null || $publicId === '') {
            return ['error' => 'kind and public_id are required.'];
        }

        /** @var TemplateService $service */
        $service = $this->container->get('service.template');
        $item = $service->update($kind, $publicId, $this->templateInput($arguments), $this->actor());
        return $item ? ['template' => $this->publicData($item)] : ['error' => 'Template not found.'];
    }

    private function crmApplyTemplate(array $arguments): array
    {
        $kind = $this->templateKind($arguments);
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($kind === null || $publicId === '') {
            return ['error' => 'kind and public_id are required.'];
        }

        /** @var TemplateService $service */
        $service = $this->container->get('service.template');
        $result = $service->apply($kind, $publicId, $this->actor());
        return $result ? ['entity' => $this->publicData($result)] : ['error' => 'Template not found.'];
    }

    private function templateKind(array $arguments): ?string
    {
        $kind = strtolower(trim((string)($arguments['kind'] ?? '')));
        return in_array($kind, ['task', 'project'], true) ? $kind : null;
    }

    private function crmListFiles(array $arguments): array
    {
        $entityType = trim((string)($arguments['entity_type'] ?? ''));
        $entityPublicId = trim((string)($arguments['entity_public_id'] ?? ''));
        if (!$this->isAllowedFileEntityType($entityType) || $entityPublicId === '') {
            return ['error' => 'entity_type must be task, project or knowledge_page and entity_public_id is required.'];
        }

        /** @var FileService $service */
        $service = $this->container->get('service.file');
        $items = $service->listByEntity($entityType, $entityPublicId, $this->actor());
        return is_array($items) ? ['items' => $this->publicData($items)] : ['error' => 'Linked entity not found or access denied.'];
    }

    private function crmGetFile(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var FileService $service */
        $service = $this->container->get('service.file');
        $file = $service->get($publicId, $this->actor());
        return $file && (int)($file['is_deleted'] ?? 0) !== 1
            ? ['file' => $this->publicData($file)]
            : ['error' => 'File not found.'];
    }

    private function crmUploadFileBase64(array $arguments): array
    {
        $entityType = trim((string)($arguments['entity_type'] ?? ''));
        $entityPublicId = trim((string)($arguments['entity_public_id'] ?? ''));
        $name = trim((string)($arguments['name'] ?? ''));
        $contentBase64 = trim((string)($arguments['content_base64'] ?? ''));
        if (!$this->isAllowedFileEntityType($entityType) || $entityPublicId === '' || $name === '' || $contentBase64 === '') {
            return ['error' => 'entity_type, entity_public_id, name and content_base64 are required.'];
        }
        if (strlen($contentBase64) > 7_000_000) {
            return ['error' => 'content_base64 is too large for MCP JSON upload. Use the REST multipart upload endpoint instead.'];
        }

        /** @var FileService $service */
        $service = $this->container->get('service.file');
        try {
            $file = $service->create($this->pick($arguments, [
                'entity_type', 'entity_public_id', 'name', 'mime_type', 'content_base64',
            ]), [], (int)($this->actor()['id'] ?? 0), $this->actor());
            return ['file' => $this->publicData($file)];
        } catch (Throwable $e) {
            return ['error' => $e->getMessage() ?: 'File upload failed.'];
        }
    }

    private function crmGetFileDownloadInfo(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var FileService $service */
        $service = $this->container->get('service.file');
        $result = $service->canDownloadInternal($publicId, $this->actor());
        if (!(bool)($result['ok'] ?? false)) {
            return ['error' => (string)($result['error'] ?? 'FILE_NOT_FOUND')];
        }

        return [
            'ok' => true,
            'public_id' => $publicId,
            'name' => (string)($result['name'] ?? ''),
            'mime_type' => (string)($result['mime_type'] ?? ''),
            'size_bytes' => (int)($result['size_bytes'] ?? 0),
            'download_url' => '/api/index.php?route=api/v1/files/' . rawurlencode($publicId) . '/download',
        ];
    }

    private function crmDeleteFile(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var FileService $service */
        $service = $this->container->get('service.file');
        return $service->delete($publicId, $this->actor())
            ? ['ok' => true, 'public_id' => $publicId]
            : ['error' => 'File not found.'];
    }

    private function isAllowedFileEntityType(string $entityType): bool
    {
        return in_array($entityType, ['task', 'project', 'knowledge_page'], true);
    }

    private function crmListStatuses(array $arguments): array
    {
        /** @var StatusService $service */
        $service = $this->container->get('service.status');
        return $this->publicData($service->list($this->statusFilters($arguments)));
    }

    private function crmGetStatus(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var StatusService $service */
        $service = $this->container->get('service.status');
        $status = $service->get($publicId);
        return $status ? ['status' => $this->publicData($status)] : ['error' => 'Status not found.'];
    }

    private function crmCreateStatus(array $arguments): array
    {
        foreach (['scope', 'code', 'title'] as $field) {
            if (trim((string)($arguments[$field] ?? '')) === '') {
                return ['error' => $field . ' is required.'];
            }
        }

        /** @var StatusService $service */
        $service = $this->container->get('service.status');
        $status = $service->create($this->statusInput($arguments));
        return is_array($status) ? ['status' => $this->publicData($status)] : ['error' => (string)$status];
    }

    private function crmUpdateStatus(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var StatusService $service */
        $service = $this->container->get('service.status');
        $status = $service->update($publicId, $this->statusInput($arguments));
        return is_array($status) ? ['status' => $this->publicData($status)] : ['error' => (string)($status ?: 'Status not found.')];
    }

    private function crmListTags(array $arguments): array
    {
        /** @var TagService $service */
        $service = $this->container->get('service.tag');
        return $this->publicData($service->list($this->tagFilters($arguments)));
    }

    private function crmGetTag(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var TagService $service */
        $service = $this->container->get('service.tag');
        $tag = $service->get($publicId);
        return $tag ? ['tag' => $this->publicData($tag)] : ['error' => 'Tag not found.'];
    }

    private function crmCreateTag(array $arguments): array
    {
        $title = trim((string)($arguments['title'] ?? $arguments['name'] ?? ''));
        if ($title === '') {
            return ['error' => 'title is required.'];
        }

        /** @var TagService $service */
        $service = $this->container->get('service.tag');
        $input = $this->tagInput($arguments) + ['title' => $title];
        if (trim((string)($input['code'] ?? '')) === '') {
            $input['code'] = $this->slugCode($title);
        }
        $tag = $service->create($input);
        return is_array($tag) ? ['tag' => $this->publicData($tag)] : ['error' => (string)$tag];
    }

    private function crmUpdateTag(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var TagService $service */
        $service = $this->container->get('service.tag');
        $tag = $service->update($publicId, $this->tagInput($arguments));
        return is_array($tag) ? ['tag' => $this->publicData($tag)] : ['error' => (string)($tag ?: 'Tag not found.')];
    }

    private function crmListTaskTags(array $arguments): array
    {
        $taskPublicId = trim((string)($arguments['task_public_id'] ?? ''));
        if ($taskPublicId === '') {
            return ['error' => 'task_public_id is required.'];
        }

        /** @var TagService $service */
        $service = $this->container->get('service.tag');
        $items = $service->listTaskTags($taskPublicId, $this->actor());
        return is_array($items) ? ['items' => $this->publicData($items)] : ['error' => 'Task not found.'];
    }

    private function crmAttachTaskTag(array $arguments): array
    {
        $taskPublicId = trim((string)($arguments['task_public_id'] ?? ''));
        $tagPublicId = trim((string)($arguments['tag_public_id'] ?? ''));
        if ($taskPublicId === '' || $tagPublicId === '') {
            return ['error' => 'task_public_id and tag_public_id are required.'];
        }

        /** @var TagService $service */
        $service = $this->container->get('service.tag');
        return $service->attachToTask($taskPublicId, $tagPublicId, $this->actor())
            ? ['ok' => true, 'task_public_id' => $taskPublicId, 'tag_public_id' => $tagPublicId]
            : ['error' => 'Task or tag not found.'];
    }

    private function crmDetachTaskTag(array $arguments): array
    {
        $taskPublicId = trim((string)($arguments['task_public_id'] ?? ''));
        $tagPublicId = trim((string)($arguments['tag_public_id'] ?? ''));
        if ($taskPublicId === '' || $tagPublicId === '') {
            return ['error' => 'task_public_id and tag_public_id are required.'];
        }

        /** @var TagService $service */
        $service = $this->container->get('service.tag');
        return $service->detachFromTask($taskPublicId, $tagPublicId, $this->actor())
            ? ['ok' => true, 'task_public_id' => $taskPublicId, 'tag_public_id' => $tagPublicId]
            : ['error' => 'Task or tag not found.'];
    }

    private function crmListTaskChecklists(array $arguments): array
    {
        $taskPublicId = trim((string)($arguments['task_public_id'] ?? ''));
        if ($taskPublicId === '') {
            return ['error' => 'task_public_id is required.'];
        }

        /** @var ChecklistService $service */
        $service = $this->container->get('service.checklist');
        $items = $service->listByTask($taskPublicId, $this->actor());
        return is_array($items) ? ['items' => $this->publicData($items)] : ['error' => 'Task not found.'];
    }

    private function crmCreateTaskChecklist(array $arguments): array
    {
        $taskPublicId = trim((string)($arguments['task_public_id'] ?? ''));
        $title = trim((string)($arguments['title'] ?? ''));
        if ($taskPublicId === '' || $title === '') {
            return ['error' => 'task_public_id and title are required.'];
        }

        /** @var ChecklistService $service */
        $service = $this->container->get('service.checklist');
        $checklist = $service->create($taskPublicId, ['title' => $title], $this->actor());
        return $checklist ? ['checklist' => $this->publicData($checklist)] : ['error' => 'Task not found.'];
    }

    private function crmUpdateChecklist(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var ChecklistService $service */
        $service = $this->container->get('service.checklist');
        $checklist = $service->update($publicId, $this->pick($arguments, ['title']), $this->actor());
        return $checklist ? ['checklist' => $this->publicData($checklist)] : ['error' => 'Checklist not found.'];
    }

    private function crmListChecklistItems(array $arguments): array
    {
        $checklistPublicId = trim((string)($arguments['checklist_public_id'] ?? ''));
        if ($checklistPublicId === '') {
            return ['error' => 'checklist_public_id is required.'];
        }

        /** @var ChecklistService $service */
        $service = $this->container->get('service.checklist');
        $items = $service->listItems($checklistPublicId, $this->actor());
        return is_array($items) ? ['items' => $this->publicData($items)] : ['error' => 'Checklist not found.'];
    }

    private function crmCreateChecklistItem(array $arguments): array
    {
        $checklistPublicId = trim((string)($arguments['checklist_public_id'] ?? ''));
        $title = trim((string)($arguments['title'] ?? ''));
        if ($checklistPublicId === '' || $title === '') {
            return ['error' => 'checklist_public_id and title are required.'];
        }

        /** @var ChecklistService $service */
        $service = $this->container->get('service.checklist');
        $item = $service->createItem($checklistPublicId, $this->pick($arguments, ['title', 'is_done', 'sort_order']), $this->actor());
        return $item ? ['item' => $this->publicData($item)] : ['error' => 'Checklist not found.'];
    }

    private function crmUpdateChecklistItem(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var ChecklistService $service */
        $service = $this->container->get('service.checklist');
        $item = $service->updateItem($publicId, $this->pick($arguments, ['title', 'is_done', 'sort_order']), $this->actor());
        return $item ? ['item' => $this->publicData($item)] : ['error' => 'Checklist item not found.'];
    }

    private function crmListDependencies(array $arguments): array
    {
        /** @var DependencyService $service */
        $service = $this->container->get('service.dependency');
        return ['items' => $this->publicData($service->list($this->pick($arguments, ['task_public_id', 'depends_on_task_public_id']), $this->actor()))];
    }

    private function crmCreateDependency(array $arguments): array
    {
        $taskPublicId = trim((string)($arguments['task_public_id'] ?? ''));
        $dependsOnTaskPublicId = trim((string)($arguments['depends_on_task_public_id'] ?? ''));
        if ($taskPublicId === '' || $dependsOnTaskPublicId === '') {
            return ['error' => 'task_public_id and depends_on_task_public_id are required.'];
        }

        /** @var DependencyService $service */
        $service = $this->container->get('service.dependency');
        $dependency = $service->create($this->pick($arguments, ['task_public_id', 'depends_on_task_public_id', 'dependency_type']), $this->actor());
        return is_array($dependency) ? ['dependency' => $this->publicData($dependency)] : ['error' => (string)$dependency];
    }

    private function crmListWorklogs(array $arguments): array
    {
        /** @var WorklogService $service */
        $service = $this->container->get('service.worklog');
        return $this->publicData($service->list($this->worklogFilters($arguments), $this->actor()));
    }

    private function crmGetWorklog(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var WorklogService $service */
        $service = $this->container->get('service.worklog');
        $worklog = $service->get($publicId, $this->actor());
        return $worklog ? ['worklog' => $this->publicData($worklog)] : ['error' => 'Worklog not found.'];
    }

    private function crmCreateWorklog(array $arguments): array
    {
        if ((int)($arguments['minutes_spent'] ?? 0) <= 0) {
            return ['error' => 'minutes_spent must be positive.'];
        }

        /** @var WorklogService $service */
        $service = $this->container->get('service.worklog');
        $worklog = $service->create($this->worklogInput($arguments), $this->actor());
        return is_array($worklog) ? ['worklog' => $this->publicData($worklog)] : ['error' => (string)$worklog];
    }

    private function crmUpdateWorklog(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }
        if (array_key_exists('minutes_spent', $arguments) && (int)$arguments['minutes_spent'] <= 0) {
            return ['error' => 'minutes_spent must be positive.'];
        }

        /** @var WorklogService $service */
        $service = $this->container->get('service.worklog');
        $worklog = $service->update($publicId, $this->worklogInput($arguments), $this->actor());
        return is_array($worklog) ? ['worklog' => $this->publicData($worklog)] : ['error' => (string)($worklog ?: 'Worklog not found.')];
    }

    private function crmGetWorklogSummary(array $arguments): array
    {
        /** @var WorklogService $service */
        $service = $this->container->get('service.worklog');
        return $this->publicData($service->summary($this->worklogSummaryFilters($arguments), $this->actor()));
    }

    private function crmListIdeas(array $arguments): array
    {
        /** @var IdeaService $service */
        $service = $this->container->get('service.idea');
        return $this->publicData($service->list($this->ideaFilters($arguments)));
    }

    private function crmGetIdea(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var IdeaService $service */
        $service = $this->container->get('service.idea');
        $idea = $service->get($publicId);
        return $idea ? ['idea' => $this->publicData($idea)] : ['error' => 'Idea not found.'];
    }

    private function crmCreateIdea(array $arguments): array
    {
        $title = trim((string)($arguments['title'] ?? ''));
        if ($title === '') {
            return ['error' => 'title is required.'];
        }

        $publicId = 'idea_' . bin2hex(random_bytes(12));
        $this->pdo()->prepare("
            INSERT INTO ideas (public_id, title, description, author_user_id, category, region, visibility, target_date, created_at)
            VALUES (:public_id, :title, :description, :author_user_id, :category, :region, :visibility, :target_date, NOW())
        ")->execute([
            'public_id' => $publicId,
            'title' => $title,
            'description' => trim((string)($arguments['description'] ?? '')),
            'author_user_id' => (int)($this->actor()['id'] ?? 0),
            'category' => trim((string)($arguments['category'] ?? '')),
            'region' => trim((string)($arguments['region'] ?? '')),
            'visibility' => in_array((string)($arguments['visibility'] ?? 'public'), ['public', 'private'], true) ? (string)($arguments['visibility'] ?? 'public') : 'public',
            'target_date' => trim((string)($arguments['target_date'] ?? '')) ?: null,
        ]);

        /** @var IdeaService $service */
        $service = $this->container->get('service.idea');
        return ['idea' => $this->publicData($service->get($publicId) ?? ['public_id' => $publicId])];
    }

    private function crmAddIdeaComment(array $arguments): array
    {
        $ideaPublicId = trim((string)($arguments['idea_public_id'] ?? ''));
        $body = trim((string)($arguments['body'] ?? ''));
        if ($ideaPublicId === '' || $body === '') {
            return ['error' => 'idea_public_id and body are required.'];
        }
        if (mb_strlen($body) > 8000) {
            return ['error' => 'Comment body is too long.'];
        }

        /** @var IdeaService $service */
        $service = $this->container->get('service.idea');
        if (!$service->get($ideaPublicId)) {
            return ['error' => 'Idea not found.'];
        }

        $commentPublicId = 'cmt_' . bin2hex(random_bytes(8));
        $this->pdo()->prepare("
            INSERT INTO comments (public_id, entity_type, entity_public_id, author_user_id, body, created_at)
            VALUES (:public_id, 'idea', :entity_public_id, :author_user_id, :body, NOW())
        ")->execute([
            'public_id' => $commentPublicId,
            'entity_public_id' => $ideaPublicId,
            'author_user_id' => (int)($this->actor()['id'] ?? 0),
            'body' => $body,
        ]);
        $this->pdo()->prepare("UPDATE ideas SET comment_count = comment_count + 1 WHERE public_id = :public_id")
            ->execute(['public_id' => $ideaPublicId]);

        return ['comment' => ['public_id' => $commentPublicId, 'idea_public_id' => $ideaPublicId]];
    }

    private function crmListChats(array $arguments): array
    {
        $actor = $this->actor();
        $userId = (int)($actor['id'] ?? 0);
        if ($userId <= 0) {
            return ['error' => 'Authenticated user is required.'];
        }

        $limit = $this->limit($arguments, 20, 50);
        $archived = !empty($arguments['archived']);
        $archivedWhere = $this->tableHasColumn('chats', 'archived_at')
            ? ($archived ? 'AND c.archived_at IS NOT NULL' : 'AND c.archived_at IS NULL')
            : '';

        $stmt = $this->pdo()->prepare("
            SELECT c.public_id, c.title, c.type, c.last_message_at, c.created_at,
                   cp.is_favorite, cp.muted_until,
                   COALESCE(rm.last_read_message_id, 0) AS last_read_id,
                   (SELECT COUNT(*) FROM chat_messages cm WHERE cm.chat_id = c.id AND cm.id > COALESCE(rm.last_read_message_id, 0) AND cm.deleted_at IS NULL) AS unread,
                   (SELECT text FROM chat_messages WHERE chat_id = c.id AND deleted_at IS NULL ORDER BY id DESC LIMIT 1) AS last_message,
                   (SELECT message_type FROM chat_messages WHERE chat_id = c.id AND deleted_at IS NULL ORDER BY id DESC LIMIT 1) AS last_message_type,
                   (SELECT GROUP_CONCAT(COALESCE(NULLIF(u.full_name, ''), u.login)) FROM chat_participants cp2 JOIN users u ON u.id = cp2.user_id WHERE cp2.chat_id = c.id AND cp2.user_id <> :uid3) AS participant_names
            FROM chats c
            JOIN chat_participants cp ON cp.chat_id = c.id AND cp.user_id = :uid
            LEFT JOIN chat_read_markers rm ON rm.chat_id = c.id AND rm.user_id = :uid2
            WHERE 1=1 {$archivedWhere}
            ORDER BY cp.is_favorite DESC, COALESCE(c.last_message_at, c.created_at) DESC
            LIMIT :lim
        ");
        $stmt->bindValue('uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue('uid2', $userId, PDO::PARAM_INT);
        $stmt->bindValue('uid3', $userId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $this->publicData($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [])];
    }

    private function crmGetChat(array $arguments): array
    {
        $chatPublicId = $this->argumentPublicId($arguments, ['public_id', 'chat_public_id']);
        if ($chatPublicId === '') {
            return ['error' => 'public_id or chat_public_id is required.'];
        }
        $chat = $this->chatForUser($chatPublicId, (int)($this->actor()['id'] ?? 0));
        if (!$chat) {
            return ['error' => 'Chat not found or access denied.'];
        }
        return ['chat' => $this->publicData($this->pick($chat, ['public_id', 'title', 'type', 'last_message_at', 'created_at', 'is_favorite', 'muted_until'])), 'participants' => $this->publicData($this->participantsForChat((int)$chat['id']))];
    }

    private function crmCreateChat(array $arguments): array
    {
        $actor = $this->actor();
        $userId = (int)($actor['id'] ?? 0);
        if ($userId <= 0) {
            return ['error' => 'Authenticated user is required.'];
        }

        $type = (string)($arguments['type'] ?? 'direct');
        $pdo = $this->pdo();
        /** @var ChatService $service */
        $service = $this->container->get('service.chat');

        if ($type === 'direct') {
            $withUserId = (int)($arguments['user_id'] ?? 0);
            if ($withUserId <= 0 || $withUserId === $userId) {
                return ['error' => 'user_id is required.'];
            }
            $stmt = $pdo->prepare('SELECT id FROM users WHERE id = :id AND is_active = 1 AND deleted_at IS NULL');
            $stmt->execute(['id' => $withUserId]);
            if (!$stmt->fetchColumn()) {
                return ['error' => 'User not found.'];
            }
            return ['chat' => $this->publicData($service->ensureDirectChat($userId, $withUserId))];
        }

        if ($type === 'project') {
            $projectId = (int)($arguments['project_id'] ?? 0);
            if ($projectId <= 0) {
                return ['error' => 'project_id is required.'];
            }
            $stmt = $pdo->prepare("SELECT p.*, t.manager_user_id AS team_manager_user_id, t.member_user_ids AS team_member_user_ids FROM projects p LEFT JOIN teams t ON t.public_id = p.team_public_id WHERE p.id = :id AND p.archived_at IS NULL");
            $stmt->execute(['id' => $projectId]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($project)) {
                return ['error' => 'Project not found.'];
            }
            return ['chat' => $this->publicData($service->ensureProjectChat($project, $actor))];
        }

        if ($type === 'team') {
            $teamId = (int)($arguments['team_id'] ?? 0);
            if ($teamId <= 0) {
                return ['error' => 'team_id is required.'];
            }
            $stmt = $pdo->prepare('SELECT * FROM teams WHERE id = :id');
            $stmt->execute(['id' => $teamId]);
            $team = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($team)) {
                return ['error' => 'Team not found.'];
            }
            return ['chat' => $this->publicData($service->ensureTeamChat($team, $actor))];
        }

        if ($type === 'group') {
            $title = trim((string)($arguments['title'] ?? ''));
            $participantPublicIds = $arguments['participant_public_ids'] ?? [];
            if ($title === '' || !is_array($participantPublicIds) || $participantPublicIds === []) {
                return ['error' => 'title and participant_public_ids are required.'];
            }
            $participantPublicIds = array_values(array_unique(array_filter(array_map(static fn(mixed $id): string => trim((string)$id), $participantPublicIds))));
            $chat = $service->ensureGroupChat($title, $participantPublicIds, $actor);
            return $chat === [] ? ['error' => 'Participants not found.'] : ['chat' => $this->publicData($chat)];
        }

        return ['error' => 'Unsupported chat type.'];
    }

    private function crmGetChatParticipants(array $arguments): array
    {
        $chatPublicId = $this->argumentPublicId($arguments, ['public_id', 'chat_public_id']);
        if ($chatPublicId === '') {
            return ['error' => 'public_id or chat_public_id is required.'];
        }
        $chat = $this->chatForUser($chatPublicId, (int)($this->actor()['id'] ?? 0));
        if (!$chat) {
            return ['error' => 'Chat not found or access denied.'];
        }
        return ['items' => $this->publicData($this->participantsForChat((int)$chat['id']))];
    }

    private function crmListChatMessages(array $arguments): array
    {
        $actor = $this->actor();
        $userId = (int)($actor['id'] ?? 0);
        $chatPublicId = $this->argumentPublicId($arguments, ['public_id', 'chat_public_id']);
        if ($userId <= 0 || $chatPublicId === '') {
            return ['error' => 'public_id or chat_public_id is required.'];
        }

        $chat = $this->chatForUser($chatPublicId, $userId);
        if (!$chat) {
            return ['error' => 'Chat not found or access denied.'];
        }

        $limit = $this->limit($arguments, 30, 100);
        $beforeId = max(0, (int)($arguments['before_id'] ?? 0));
        $afterId = max(0, (int)($arguments['after_id'] ?? 0));
        $where = 'cm.chat_id = :cid AND cm.deleted_at IS NULL';
        $order = 'DESC';
        if ($afterId > 0) {
            $where .= ' AND cm.id > :aid';
            $order = 'ASC';
        } elseif ($beforeId > 0) {
            $where .= ' AND cm.id < :bid';
        }

        $stmt = $this->pdo()->prepare("
            SELECT cm.public_id, cm.id AS message_seq, cm.message_type, cm.text, cm.created_at, cm.edited_at AS updated_at,
                   u.public_id AS sender_public_id, COALESCE(NULLIF(u.full_name, ''), u.login) AS sender_name,
                   rm.public_id AS reply_public_id, rm.text AS reply_text
            FROM chat_messages cm
            JOIN users u ON u.id = cm.sender_user_id
            LEFT JOIN chat_messages rm ON rm.id = cm.reply_to_message_id
            WHERE {$where}
            ORDER BY cm.id {$order}
            LIMIT :lim
        ");
        $stmt->bindValue('cid', (int)$chat['id'], PDO::PARAM_INT);
        if ($afterId > 0) {
            $stmt->bindValue('aid', $afterId, PDO::PARAM_INT);
        } elseif ($beforeId > 0) {
            $stmt->bindValue('bid', $beforeId, PDO::PARAM_INT);
        }
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($afterId <= 0) {
            $items = array_reverse($items);
        }
        $items = $this->attachChatMessageFiles($items, $chatPublicId);

        return ['chat' => $this->publicData($this->pick($chat, ['public_id', 'title', 'type'])), 'items' => $this->publicData($items)];
    }

    private function crmSendChatMessage(array $arguments): array
    {
        $actor = $this->actor();
        $userId = (int)($actor['id'] ?? 0);
        $chatPublicId = $this->argumentPublicId($arguments, ['public_id', 'chat_public_id']);
        $text = trim((string)($arguments['text'] ?? ''));
        if ($userId <= 0 || $chatPublicId === '' || $text === '') {
            return ['error' => 'public_id or chat_public_id and text are required.'];
        }
        if (mb_strlen($text) > 4000) {
            return ['error' => 'Message text is too long.'];
        }

        $chat = $this->chatForUser($chatPublicId, $userId);
        if (!$chat) {
            return ['error' => 'Chat not found or access denied.'];
        }

        $reply = $this->resolveReplyMessage((int)$chat['id'], trim((string)($arguments['reply_to_message_public_id'] ?? '')));
        $messagePublicId = 'msg_' . bin2hex(random_bytes(8));
        $this->pdo()->prepare("
            INSERT INTO chat_messages (public_id, chat_id, sender_user_id, reply_to_message_id, message_type, text, created_at)
            VALUES (:public_id, :chat_id, :sender_user_id, :reply_to_message_id, 'text', :text, NOW())
        ")->execute([
            'public_id' => $messagePublicId,
            'chat_id' => (int)$chat['id'],
            'sender_user_id' => $userId,
            'reply_to_message_id' => $reply ? (int)$reply['id'] : null,
            'text' => $text,
        ]);
        $this->pdo()->prepare("UPDATE chats SET last_message_at = NOW() WHERE id = :chat_id")
            ->execute(['chat_id' => (int)$chat['id']]);

        if ($this->container->has('service.chat')) {
            $this->container->get('service.chat')->markRead((int)$chat['id'], $userId);
        }

        return [
            'message' => [
                'public_id' => $messagePublicId,
                'chat_public_id' => $chatPublicId,
                'text' => $text,
            ],
        ];
    }

    private function crmEditChatMessage(array $arguments): array
    {
        $actor = $this->actor();
        $userId = (int)($actor['id'] ?? 0);
        $chatPublicId = $this->argumentPublicId($arguments, ['public_id', 'chat_public_id']);
        $messagePublicId = trim((string)($arguments['message_public_id'] ?? ''));
        $text = trim((string)($arguments['text'] ?? ''));
        if ($userId <= 0 || $chatPublicId === '' || $messagePublicId === '' || $text === '') {
            return ['error' => 'public_id or chat_public_id, message_public_id and text are required.'];
        }
        if (mb_strlen($text) > 4000) {
            return ['error' => 'Message text is too long.'];
        }
        $chat = $this->chatForUser($chatPublicId, $userId);
        if (!$chat) {
            return ['error' => 'Chat not found or access denied.'];
        }
        $message = $this->editableChatMessage((int)$chat['id'], $messagePublicId, $userId);
        if (!$message) {
            return ['error' => 'Message cannot be edited.'];
        }
        $this->pdo()->prepare("UPDATE chat_messages SET text = :text, edited_at = NOW() WHERE id = :id")->execute(['text' => $text, 'id' => (int)$message['id']]);
        $this->auditChatMessage((int)$message['id'], (int)$chat['id'], $userId, 'edit', (string)($message['text'] ?? ''), $text);
        return ['message' => ['public_id' => $messagePublicId, 'chat_public_id' => $chatPublicId, 'text' => $text]];
    }

    private function crmDeleteChatMessage(array $arguments): array
    {
        $actor = $this->actor();
        $userId = (int)($actor['id'] ?? 0);
        $chatPublicId = $this->argumentPublicId($arguments, ['public_id', 'chat_public_id']);
        $messagePublicId = trim((string)($arguments['message_public_id'] ?? ''));
        if ($userId <= 0 || $chatPublicId === '' || $messagePublicId === '') {
            return ['error' => 'public_id or chat_public_id and message_public_id are required.'];
        }
        $chat = $this->chatForUser($chatPublicId, $userId);
        if (!$chat) {
            return ['error' => 'Chat not found or access denied.'];
        }
        $message = $this->editableChatMessage((int)$chat['id'], $messagePublicId, $userId);
        if (!$message) {
            return ['error' => 'Message cannot be deleted.'];
        }
        $this->pdo()->prepare("UPDATE chat_messages SET deleted_at = NOW(), deleted_by_user_id = :uid WHERE id = :id")->execute(['uid' => $userId, 'id' => (int)$message['id']]);
        $this->auditChatMessage((int)$message['id'], (int)$chat['id'], $userId, 'delete', (string)($message['text'] ?? ''), null);
        return ['deleted' => true];
    }

    private function crmUploadChatAttachment(array $arguments): array
    {
        $actor = $this->actor();
        $userId = (int)($actor['id'] ?? 0);
        $chatPublicId = $this->argumentPublicId($arguments, ['public_id', 'chat_public_id']);
        $name = trim((string)($arguments['name'] ?? ''));
        $contentBase64 = trim((string)($arguments['content_base64'] ?? ''));
        if ($userId <= 0 || $chatPublicId === '' || $name === '' || $contentBase64 === '') {
            return ['error' => 'public_id or chat_public_id, name and content_base64 are required.'];
        }
        $chat = $this->chatForUser($chatPublicId, $userId);
        if (!$chat) {
            return ['error' => 'Chat not found or access denied.'];
        }
        $tmpDir = dirname(__DIR__, 3) . '/storage_api/uploads/chat_mcp';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }
        $tmpFile = $tmpDir . '/upload_' . bin2hex(random_bytes(8)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
        $binary = base64_decode($contentBase64, true);
        if ($binary === false) {
            return ['error' => 'content_base64 is invalid.'];
        }
        file_put_contents($tmpFile, $binary);
        $raw = [
            'name' => $name,
            'tmp_name' => $tmpFile,
            'type' => trim((string)($arguments['mime_type'] ?? 'application/octet-stream')),
            'size' => strlen($binary),
        ];
        $messagePublicId = 'msg_' . bin2hex(random_bytes(8));
        $text = trim((string)($arguments['text'] ?? ''));
        if (mb_strlen($text) > 4000) {
            @unlink($tmpFile);
            return ['error' => 'Message text is too long.'];
        }
        $this->pdo()->prepare("
            INSERT INTO chat_messages (public_id, chat_id, sender_user_id, message_type, text, created_at)
            VALUES (:pid, :cid, :uid, 'attachment', :text, NOW())
        ")->execute(['pid' => $messagePublicId, 'cid' => (int)$chat['id'], 'uid' => $userId, 'text' => $text]);
        $messageId = (int)$this->pdo()->lastInsertId();
        $fileRow = $this->storeChatAttachment($messagePublicId, $raw);
        $this->pdo()->prepare("UPDATE chats SET last_message_at = NOW() WHERE id = :cid")->execute(['cid' => (int)$chat['id']]);
        if ($this->container->has('service.chat')) {
            /** @var ChatService $service */
            $service = $this->container->get('service.chat');
            $service->markRead((int)$chat['id'], $userId);
            $service->notifyMessage($chat, ['public_id' => $messagePublicId, 'id' => $messageId, 'text' => $text !== '' ? $text : ($this->t('chat/messages.attached_file') . ': ' . $fileRow['original_name'])], $actor);
        }
        @unlink($tmpFile);
        return ['message_public_id' => $messagePublicId, 'file' => $this->publicData($fileRow)];
    }

    private function crmListChatAttachments(array $arguments): array
    {
        $chatPublicId = $this->argumentPublicId($arguments, ['public_id', 'chat_public_id']);
        if ($chatPublicId === '') {
            return ['error' => 'public_id or chat_public_id is required.'];
        }
        $chat = $this->chatForUser($chatPublicId, (int)($this->actor()['id'] ?? 0));
        if (!$chat) {
            return ['error' => 'Chat not found or access denied.'];
        }
        $stmt = $this->pdo()->prepare("
            SELECT f.public_id, f.original_name, f.mime_type, f.size_bytes, f.created_at, cm.public_id AS message_public_id
            FROM files f
            JOIN chat_messages cm ON cm.public_id = f.entity_public_id
            WHERE f.entity_type = 'chat_message' AND cm.chat_id = :cid AND f.is_deleted = 0
            ORDER BY f.id ASC
        ");
        $stmt->execute(['cid' => (int)$chat['id']]);
        return ['items' => $this->publicData($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [])];
    }

    private function crmGetChatSettings(array $arguments): array
    {
        $chatPublicId = $this->argumentPublicId($arguments, ['public_id', 'chat_public_id']);
        if ($chatPublicId === '') {
            return ['error' => 'public_id or chat_public_id is required.'];
        }
        $chat = $this->chatForUser($chatPublicId, (int)($this->actor()['id'] ?? 0));
        if (!$chat) {
            return ['error' => 'Chat not found or access denied.'];
        }
        return ['settings' => $this->publicData($this->pick($chat, ['public_id', 'is_favorite', 'muted_until']))];
    }

    private function crmUpdateChatSettings(array $arguments): array
    {
        $chatPublicId = $this->argumentPublicId($arguments, ['public_id', 'chat_public_id']);
        if ($chatPublicId === '') {
            return ['error' => 'public_id or chat_public_id is required.'];
        }
        $chat = $this->chatForUser($chatPublicId, (int)($this->actor()['id'] ?? 0));
        if (!$chat) {
            return ['error' => 'Chat not found or access denied.'];
        }
        $isFavorite = array_key_exists('is_favorite', $arguments) ? (bool)$arguments['is_favorite'] : null;
        $mutedUntil = '__keep__';
        if (array_key_exists('muted_until', $arguments)) {
            $raw = trim((string)$arguments['muted_until']);
            $mutedUntil = $raw !== '' ? $raw : null;
        } elseif (array_key_exists('is_muted', $arguments)) {
            $mutedUntil = (bool)$arguments['is_muted'] ? '9999-12-31 23:59:59' : null;
        }
        /** @var ChatService $service */
        $service = $this->container->get('service.chat');
        return ['settings' => $this->publicData($service->updateParticipantSettings((int)$chat['id'], (int)($this->actor()['id'] ?? 0), $isFavorite, $mutedUntil))];
    }

    private function crmMarkChatRead(array $arguments): array
    {
        $chatPublicId = $this->argumentPublicId($arguments, ['public_id', 'chat_public_id']);
        if ($chatPublicId === '') {
            return ['error' => 'public_id or chat_public_id is required.'];
        }
        $chat = $this->chatForUser($chatPublicId, (int)($this->actor()['id'] ?? 0));
        if (!$chat) {
            return ['error' => 'Chat not found or access denied.'];
        }
        /** @var ChatService $service */
        $service = $this->container->get('service.chat');
        $service->markRead((int)$chat['id'], (int)($this->actor()['id'] ?? 0));
        return ['marked_read' => true];
    }

    private function crmGetChatUnreadCount(): array
    {
        $userId = (int)($this->actor()['id'] ?? 0);
        if ($userId <= 0) {
            return ['error' => 'Authenticated user is required.'];
        }
        $stmt = $this->pdo()->prepare("
            SELECT COUNT(*) FROM chats c
            JOIN chat_participants cp ON cp.chat_id = c.id AND cp.user_id = :uid
            WHERE (SELECT COUNT(*) FROM chat_messages cm WHERE cm.chat_id = c.id AND cm.id > COALESCE((SELECT last_read_message_id FROM chat_read_markers WHERE chat_id = c.id AND user_id = :uid2), 0) AND cm.deleted_at IS NULL) > 0
        ");
        $stmt->execute(['uid' => $userId, 'uid2' => $userId]);
        return ['count' => (int)$stmt->fetchColumn()];
    }

    private function crmArchiveChat(array $arguments): array
    {
        $chatPublicId = $this->argumentPublicId($arguments, ['public_id', 'chat_public_id']);
        if ($chatPublicId === '') {
            return ['error' => 'public_id or chat_public_id is required.'];
        }
        $chat = $this->chatForUser($chatPublicId, (int)($this->actor()['id'] ?? 0));
        if (!$chat) {
            return ['error' => 'Chat not found or access denied.'];
        }
        if ((int)($chat['created_by_user_id'] ?? 0) !== (int)($this->actor()['id'] ?? 0)) {
            return ['error' => 'Only the creator can archive this chat.'];
        }
        /** @var ChatService $service */
        $service = $this->container->get('service.chat');
        $result = $service->archiveChat((int)$chat['id'], (int)($this->actor()['id'] ?? 0));
        return $result === [] ? ['error' => 'Archive failed.'] : ['chat' => $this->publicData($result)];
    }

    private function crmRestoreChat(array $arguments): array
    {
        $chatPublicId = $this->argumentPublicId($arguments, ['public_id', 'chat_public_id']);
        if ($chatPublicId === '') {
            return ['error' => 'public_id or chat_public_id is required.'];
        }
        $stmt = $this->pdo()->prepare("SELECT id FROM chats WHERE public_id = :pid AND archived_by_user_id = :uid AND archived_at IS NOT NULL");
        $stmt->execute(['pid' => $chatPublicId, 'uid' => (int)($this->actor()['id'] ?? 0)]);
        $chatId = (int)$stmt->fetchColumn();
        if ($chatId <= 0) {
            return ['error' => 'Chat not found or access denied.'];
        }
        /** @var ChatService $service */
        $service = $this->container->get('service.chat');
        $result = $service->restoreChat($chatId, (int)($this->actor()['id'] ?? 0));
        return $result === [] ? ['error' => 'Restore failed.'] : ['chat' => $this->publicData($result)];
    }

    private function crmListNotifications(array $arguments): array
    {
        /** @var NotificationService $service */
        $service = $this->container->get('service.notification');
        return $this->publicData($service->list($this->notificationFilters($arguments), $this->actor()));
    }

    private function crmGetNotificationCounters(): array
    {
        /** @var NotificationService $service */
        $service = $this->container->get('service.notification');
        return ['counters' => $this->publicData($service->counters($this->actor()))];
    }

    private function crmCreateNotification(array $arguments): array
    {
        $title = trim((string)($arguments['title'] ?? ''));
        if ($title === '') {
            return ['error' => 'title is required.'];
        }

        /** @var NotificationService $service */
        $service = $this->container->get('service.notification');
        return ['notification' => $this->publicData($service->create($this->notificationInput($arguments), $this->actor()))];
    }

    private function crmListPushSubscriptions(array $arguments): array
    {
        /** @var NotificationPushService $service */
        $service = $this->container->get('service.notification_push');
        return $this->publicData($service->list($this->filters($arguments, 20, 50), $this->actor()));
    }

    private function crmCreatePushSubscription(array $arguments): array
    {
        $endpoint = trim((string)($arguments['endpoint'] ?? ''));
        $p256dh = trim((string)($arguments['p256dh'] ?? ''));
        $auth = trim((string)($arguments['auth'] ?? ''));
        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            return ['error' => 'endpoint, p256dh and auth are required.'];
        }

        /** @var NotificationPushService $service */
        $service = $this->container->get('service.notification_push');
        $item = $service->upsert($this->pick($arguments, ['endpoint', 'p256dh', 'auth', 'device_label', 'user_agent']), $this->actor());
        return $item ? ['subscription' => $this->publicData($item)] : ['error' => 'Invalid push subscription.'];
    }

    private function crmDeletePushSubscription(array $arguments): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var NotificationPushService $service */
        $service = $this->container->get('service.notification_push');
        return $service->delete($publicId, $this->actor()) ? ['deleted' => true] : ['error' => 'Push subscription not found.'];
    }

    private function crmSendPushTest(): array
    {
        /** @var NotificationPushService $service */
        $service = $this->container->get('service.notification_push');
        return $this->publicData($service->sendTestToUser((int)($this->actor()['id'] ?? 0), $this->actor()));
    }

    private function crmSetNotificationReadState(array $arguments, bool $read): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        /** @var NotificationService $service */
        $service = $this->container->get('service.notification');
        $item = $read
            ? $service->markRead($publicId, $this->actor())
            : $service->markUnread($publicId, $this->actor());

        return $item ? ['notification' => $this->publicData($item)] : ['error' => 'Notification not found.'];
    }

    private function crmMarkAllNotificationsRead(array $arguments): array
    {
        $category = trim((string)($arguments['category'] ?? ''));

        /** @var NotificationService $service */
        $service = $this->container->get('service.notification');
        return ['updated' => $service->markAllRead($this->actor(), $category !== '' ? $category : null)];
    }

    private function crmListFavorites(array $arguments): array
    {
        /** @var FavoriteService $service */
        $service = $this->container->get('service.favorite');
        $result = $service->list($this->collaborationFilters($arguments), $this->actor());
        return $result === 'FORBIDDEN' ? ['error' => 'FORBIDDEN'] : $this->publicData($result);
    }

    private function crmCreateFavorite(array $arguments): array
    {
        $error = $this->validateEntityAction($arguments);
        if ($error !== null) {
            return ['error' => $error];
        }

        /** @var FavoriteService $service */
        $service = $this->container->get('service.favorite');
        $favorite = $service->create($this->entityActionInput($arguments), $this->actor());
        return is_array($favorite) ? ['favorite' => $this->publicData($favorite)] : ['error' => (string)$favorite];
    }

    private function crmDeleteFavorite(array $arguments): array
    {
        return $this->deleteOwnedCollaborationItem($arguments, 'service.favorite', 'delete', 'Favorite not found.');
    }

    private function crmListSubscriptions(array $arguments): array
    {
        /** @var SubscriptionService $service */
        $service = $this->container->get('service.subscription');
        $result = $service->list($this->collaborationFilters($arguments), $this->actor());
        return $result === 'FORBIDDEN' ? ['error' => 'FORBIDDEN'] : $this->publicData($result);
    }

    private function crmCreateSubscription(array $arguments): array
    {
        $error = $this->validateEntityAction($arguments);
        if ($error !== null) {
            return ['error' => $error];
        }

        /** @var SubscriptionService $service */
        $service = $this->container->get('service.subscription');
        $subscription = $service->create($this->entityActionInput($arguments), $this->actor());
        return is_array($subscription) ? ['subscription' => $this->publicData($subscription)] : ['error' => (string)$subscription];
    }

    private function crmDeleteSubscription(array $arguments): array
    {
        return $this->deleteOwnedCollaborationItem($arguments, 'service.subscription', 'delete', 'Subscription not found.');
    }

    private function crmListReactions(array $arguments): array
    {
        /** @var ReactionService $service */
        $service = $this->container->get('service.reaction');
        $result = $service->list($this->collaborationFilters($arguments, ['reaction']), $this->actor());
        return $result === 'FORBIDDEN' ? ['error' => 'FORBIDDEN'] : $this->publicData($result);
    }

    private function crmAddReaction(array $arguments): array
    {
        $error = $this->validateEntityAction($arguments);
        if ($error !== null) {
            return ['error' => $error];
        }
        $reaction = strtolower(trim((string)($arguments['reaction'] ?? '')));
        if (!in_array($reaction, $this->allowedReactions(), true)) {
            return ['error' => 'reaction must be one of: ' . implode(', ', $this->allowedReactions()) . '.'];
        }

        /** @var ReactionService $service */
        $service = $this->container->get('service.reaction');
        $item = $service->add($this->entityActionInput($arguments) + ['reaction' => $reaction], $this->actor());
        return is_array($item) ? ['reaction' => $this->publicData($item)] : ['error' => (string)$item];
    }

    private function crmRemoveReaction(array $arguments): array
    {
        return $this->deleteOwnedCollaborationItem($arguments, 'service.reaction', 'remove', 'Reaction not found.');
    }

    private function crmListMentions(array $arguments): array
    {
        /** @var MentionService $service */
        $service = $this->container->get('service.mention');
        $result = $service->list($this->collaborationFilters($arguments, ['mentioned_user_public_id']), $this->actor());
        return $result === 'FORBIDDEN' ? ['error' => 'FORBIDDEN'] : $this->publicData($result);
    }

    private function crmAddMention(array $arguments): array
    {
        $error = $this->validateEntityAction($arguments);
        if ($error !== null) {
            return ['error' => $error];
        }
        $mentionedUserPublicId = trim((string)($arguments['mentioned_user_public_id'] ?? ''));
        if ($mentionedUserPublicId === '') {
            return ['error' => 'mentioned_user_public_id is required.'];
        }

        /** @var MentionService $service */
        $service = $this->container->get('service.mention');
        $mention = $service->add($this->entityActionInput($arguments) + ['mentioned_user_public_id' => $mentionedUserPublicId], $this->actor());
        return is_array($mention) ? ['mention' => $this->publicData($mention)] : ['error' => (string)$mention];
    }

    private function crmDeleteMention(array $arguments): array
    {
        return $this->deleteOwnedCollaborationItem($arguments, 'service.mention', 'delete', 'Mention not found.');
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

    private function payloadData(\Api\System\Library\Http\JsonResponse $response): array
    {
        $payload = $response->payload();
        $data = $payload['data'] ?? null;
        if (is_array($data)) {
            return $this->publicData($data);
        }

        return $this->publicData($payload);
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

    private function tagRepo(): TagRepository
    {
        return new TagRepository($this->pdo());
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

    private function argumentPublicId(array $arguments, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string)($arguments[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function activityFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'entity_type', 'entity_public_id', 'channel', 'project_public_id', 'status', 'priority', 'assigned_user_id', 'updated_since',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 100);

        return $filters;
    }

    private function calendarFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'project_public_id', 'task_public_id', 'starts_from', 'starts_to',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 50);

        return $filters;
    }

    private function cycleFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'project_public_id', 'status',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 50);

        return $filters;
    }

    private function userFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'q', 'is_active',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 50);

        return $filters;
    }

    private function teamFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'team_type',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 50);

        return $filters;
    }

    private function crmEntityFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'search', 'counterparty_type', 'client_type', 'status',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 50);

        return $filters;
    }

    private function statusFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'scope', 'is_active',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 50);

        return $filters;
    }

    private function tagFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'search',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 50);

        return $filters;
    }

    private function contactFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'search', 'counterparty_public_id', 'company_public_id', 'client_public_id',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 50);

        return $filters;
    }

    private function approvalFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'status', 'entity_type', 'entity_public_id',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 50);

        return $filters;
    }

    private function recurringFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'entity_type', 'is_active',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 50);

        return $filters;
    }

    private function workflowFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'trigger_code', 'action_code', 'is_enabled',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 50);

        return $filters;
    }

    private function workflowRunFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'rule_public_id', 'status',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 50);

        return $filters;
    }

    private function ideaFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'status', 'category', 'sort', 'period', 'offset',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 50);

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

    private function cycleInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'title', 'project_public_id', 'description', 'goal', 'status', 'start_at', 'end_at',
            'timezone', 'owner_user_public_id', 'row_version',
        ]) + ['source_type' => 'mcp'];
    }

    private function teamInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'title', 'team_type', 'code', 'parent_public_id', 'manager_user_public_id', 'member_user_public_ids',
        ]);
    }

    private function departmentInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'title', 'code', 'manager_user_id',
        ]);
    }

    private function counterpartyInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'title', 'counterparty_type', 'legal_name', 'person_last_name', 'person_first_name',
            'person_middle_name', 'person_birth_date', 'tax_inn', 'tax_kpp', 'tax_ogrn', 'tax_ogrnip',
            'bank_account', 'bank_name', 'bank_bik', 'bank_corr_account', 'website', 'messenger',
            'address_legal', 'address_postal', 'notes', 'email', 'phone', 'status', 'extra_attributes',
        ]);
    }

    private function clientInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'title', 'client_type', 'legal_name', 'person_last_name', 'person_first_name',
            'person_middle_name', 'person_birth_date', 'tax_inn', 'tax_kpp', 'tax_ogrn', 'tax_ogrnip',
            'bank_account', 'bank_name', 'bank_bik', 'bank_corr_account', 'website', 'messenger',
            'address_legal', 'address_postal', 'notes', 'email', 'phone', 'status',
        ]);
    }

    private function contactInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'full_name', 'email', 'phone', 'role', 'is_primary', 'counterparty_public_id',
            'company_public_id', 'client_public_id',
        ]);
    }

    private function approvalInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'entity_type', 'entity_public_id', 'reviewer_public_ids', 'title', 'comment',
        ]);
    }

    private function recurringInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'title', 'entity_type', 'entity_public_id', 'rrule', 'is_active',
        ]);
    }

    private function workflowInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'title', 'trigger_code', 'action_code', 'payload', 'is_enabled',
        ]);
    }

    private function statusInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'scope', 'code', 'title', 'color', 'sort_order', 'is_active',
        ]);
    }

    private function tagInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'code', 'title', 'name', 'color', 'description',
        ]);
    }

    private function worklogFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'task_public_id', 'user_public_id',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 50);

        if (!empty($arguments['date_from'])) {
            $filters['from'] = (string)$arguments['date_from'];
        }
        if (!empty($arguments['date_to'])) {
            $filters['to'] = (string)$arguments['date_to'];
        }

        return $filters;
    }

    private function worklogInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'minutes_spent', 'task_public_id', 'note', 'logged_at', 'user_public_id',
        ]);
    }

    private function worklogSummaryFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, ['team_public_id']);

        if (!empty($arguments['date_from'])) {
            $filters['from'] = (string)$arguments['date_from'];
        }
        if (!empty($arguments['date_to'])) {
            $filters['to'] = (string)$arguments['date_to'];
        }

        return $filters;
    }

    private function milestoneInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'project_public_id', 'title', 'due_at', 'status',
        ]);
    }

    private function reminderFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'status', 'task_public_id',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 50);

        return $filters;
    }

    private function reminderInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'remind_at', 'status', 'task_public_id',
        ]);
    }

    private function savedViewFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'entity_type', 'access_level', 'layout', 'search', 'is_pinned',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 50);

        return $filters;
    }

    private function savedViewInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'title', 'description', 'entity_type', 'filters', 'access_level', 'display_filters',
            'display_properties', 'rich_filters', 'layout', 'group_by', 'order_by', 'order_dir',
            'sort_order', 'is_system', 'is_locked',
        ]);
    }

    private function stickyNoteFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'context_type', 'context_public_id', 'visibility', 'is_pinned', 'archived',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 50);

        return $filters;
    }

    private function stickyNoteInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'title', 'body', 'color', 'background_color', 'context_type', 'context_public_id',
            'visibility', 'is_pinned', 'sort_order', 'meta_json',
        ]);
    }

    private function estimateSetFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'scope_type', 'project_public_id', 'estimate_type', 'is_active', 'search',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 50);

        return $filters;
    }

    private function estimateSetInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'name', 'code', 'description', 'scope_type', 'project_public_id', 'estimate_type',
            'unit_label', 'currency_code', 'is_default', 'is_active', 'sort_order', 'row_version',
            'options',
        ]);
    }

    private function estimateOptionInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'label', 'code', 'numeric_value', 'color', 'description', 'is_default', 'is_active',
            'sort_order', 'row_version',
        ]);
    }

    private function taskEstimateInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'estimate_set_public_id', 'estimate_option_public_id', 'numeric_value', 'currency_code', 'note',
        ]);
    }

    private function customFieldFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'scope', 'type', 'search',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 50);

        return $filters;
    }

    private function customFieldInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'scope', 'code', 'title', 'type', 'options', 'is_required',
        ]);
    }

    private function slaFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'search',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 50);

        return $filters;
    }

    private function slaPolicyInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'title', 'response_minutes', 'resolve_minutes', 'escalation_payload',
        ]);
    }

    private function templateFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'search', 'is_active',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 50);

        return $filters;
    }

    private function templateInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'title', 'payload', 'is_active',
        ]);
    }

    private function milestoneSchema(): array
    {
        return [
            'project_public_id' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'due_at' => ['type' => 'string'],
            'status' => ['type' => 'string', 'default' => 'planned'],
        ];
    }

    private function reminderSchema(): array
    {
        return [
            'remind_at' => ['type' => 'string'],
            'status' => ['type' => 'string', 'enum' => ['new', 'pending', 'done', 'cancelled'], 'default' => 'new'],
            'task_public_id' => ['type' => 'string'],
        ];
    }

    private function savedViewListSchema(): array
    {
        return [
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            'entity_type' => ['type' => 'string', 'enum' => ['task', 'project', 'client', 'knowledge']],
            'access_level' => ['type' => 'string', 'enum' => ['private', 'public', 'system']],
            'layout' => ['type' => 'string', 'enum' => ['list', 'table', 'board', 'calendar', 'gantt']],
            'search' => ['type' => 'string'],
            'is_pinned' => ['type' => 'boolean'],
        ];
    }

    private function savedViewSchema(): array
    {
        return [
            'title' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'entity_type' => ['type' => 'string', 'enum' => ['task', 'project', 'client', 'knowledge'], 'default' => 'task'],
            'filters' => ['type' => 'object', 'additionalProperties' => true],
            'access_level' => ['type' => 'string', 'enum' => ['private', 'public', 'system'], 'default' => 'private'],
            'display_filters' => ['type' => 'object', 'additionalProperties' => true],
            'display_properties' => ['type' => 'object', 'additionalProperties' => true],
            'rich_filters' => ['type' => 'object', 'additionalProperties' => true],
            'layout' => ['type' => 'string', 'enum' => ['list', 'table', 'board', 'calendar', 'gantt'], 'default' => 'list'],
            'group_by' => ['type' => 'string', 'enum' => ['none', 'status', 'priority', 'assignee', 'project', 'due_date', 'tag']],
            'order_by' => ['type' => 'string'],
            'order_dir' => ['type' => 'string', 'enum' => ['asc', 'desc']],
            'sort_order' => ['type' => 'integer'],
            'is_system' => ['type' => 'boolean'],
            'is_locked' => ['type' => 'boolean'],
        ];
    }

    private function stickyNoteListSchema(): array
    {
        return [
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            'context_type' => ['type' => 'string', 'enum' => ['personal', 'dashboard', 'project', 'task']],
            'context_public_id' => ['type' => 'string'],
            'visibility' => ['type' => 'string', 'enum' => ['private', 'shared']],
            'is_pinned' => ['type' => 'boolean'],
            'archived' => ['type' => 'boolean'],
        ];
    }

    private function stickyNoteSchema(): array
    {
        return [
            'title' => ['type' => 'string'],
            'body' => ['type' => 'string'],
            'color' => ['type' => 'string', 'enum' => ['yellow', 'green', 'blue', 'purple', 'pink', 'red', 'orange', 'teal', 'gray', 'white'], 'default' => 'yellow'],
            'background_color' => ['type' => 'string'],
            'context_type' => ['type' => 'string', 'enum' => ['personal', 'dashboard', 'project', 'task'], 'default' => 'personal'],
            'context_public_id' => ['type' => 'string'],
            'visibility' => ['type' => 'string', 'enum' => ['private', 'shared'], 'default' => 'private'],
            'is_pinned' => ['type' => 'boolean'],
            'sort_order' => ['type' => 'integer'],
            'meta_json' => ['type' => 'string'],
        ];
    }

    private function estimateSetListSchema(): array
    {
        return [
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            'scope_type' => ['type' => 'string', 'enum' => ['global', 'project']],
            'project_public_id' => ['type' => 'string'],
            'estimate_type' => ['type' => 'string', 'enum' => ['story_points', 'tshirt', 'hours', 'cost', 'complexity', 'risk', 'custom']],
            'is_active' => ['type' => 'integer', 'enum' => [0, 1]],
            'search' => ['type' => 'string'],
        ];
    }

    private function estimateSetSchema(): array
    {
        return [
            'name' => ['type' => 'string'],
            'code' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'scope_type' => ['type' => 'string', 'enum' => ['global', 'project'], 'default' => 'project'],
            'project_public_id' => ['type' => 'string'],
            'estimate_type' => ['type' => 'string', 'enum' => ['story_points', 'tshirt', 'hours', 'cost', 'complexity', 'risk', 'custom'], 'default' => 'custom'],
            'unit_label' => ['type' => 'string'],
            'currency_code' => ['type' => 'string'],
            'is_default' => ['type' => 'boolean'],
            'is_active' => ['type' => 'boolean'],
            'sort_order' => ['type' => 'integer'],
            'row_version' => ['type' => 'integer'],
            'options' => [
                'type' => 'array',
                'items' => ['type' => 'object', 'additionalProperties' => true],
            ],
        ];
    }

    private function estimateOptionSchema(): array
    {
        return [
            'label' => ['type' => 'string'],
            'code' => ['type' => 'string'],
            'numeric_value' => ['type' => 'number'],
            'color' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'is_default' => ['type' => 'boolean'],
            'is_active' => ['type' => 'boolean'],
            'sort_order' => ['type' => 'integer'],
            'row_version' => ['type' => 'integer'],
        ];
    }

    private function customFieldListSchema(): array
    {
        return [
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            'scope' => ['type' => 'string', 'enum' => ['task', 'project', 'client', 'company', 'contact', 'user']],
            'type' => ['type' => 'string'],
            'search' => ['type' => 'string'],
        ];
    }

    private function customFieldSchema(): array
    {
        return [
            'scope' => ['type' => 'string', 'enum' => ['task', 'project', 'client', 'company', 'contact', 'user']],
            'code' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'type' => ['type' => 'string', 'enum' => ['text', 'textarea', 'number', 'boolean', 'date', 'datetime', 'select', 'multiselect', 'url', 'email', 'phone']],
            'options' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
            'is_required' => ['type' => 'integer', 'enum' => [0, 1]],
        ];
    }

    private function slaPolicySchema(): array
    {
        return [
            'title' => ['type' => 'string'],
            'response_minutes' => ['type' => 'integer', 'minimum' => 1],
            'resolve_minutes' => ['type' => 'integer', 'minimum' => 1],
            'escalation_payload' => ['type' => 'object', 'additionalProperties' => true],
        ];
    }

    private function templateListSchema(): array
    {
        return [
            'kind' => ['type' => 'string', 'enum' => ['task', 'project']],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            'search' => ['type' => 'string'],
            'is_active' => ['type' => 'integer', 'enum' => [0, 1]],
        ];
    }

    private function templateSchema(): array
    {
        return [
            'kind' => ['type' => 'string', 'enum' => ['task', 'project']],
            'title' => ['type' => 'string'],
            'payload' => ['type' => 'object', 'additionalProperties' => true],
            'is_active' => ['type' => 'integer', 'enum' => [0, 1]],
        ];
    }

    private function notificationFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'category', 'is_read',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 50);

        return $filters;
    }

    private function notificationInput(array $arguments): array
    {
        return $this->pick($arguments, [
            'title', 'body', 'category', 'entity_type', 'entity_public_id', 'action_code', 'link', 'user_public_id',
        ]);
    }

    private function collaborationFilters(array $arguments, array $extraKeys = []): array
    {
        $filters = $this->pick($arguments, array_merge([
            'page', 'entity_type', 'entity_public_id', 'user_public_id',
        ], $extraKeys));
        $filters['limit'] = $this->limit($arguments, 20, 50);

        return $filters;
    }

    private function entityActionInput(array $arguments): array
    {
        return [
            'entity_type' => strtolower(trim((string)($arguments['entity_type'] ?? ''))),
            'entity_public_id' => trim((string)($arguments['entity_public_id'] ?? '')),
        ];
    }

    private function validateEntityAction(array $arguments): ?string
    {
        $input = $this->entityActionInput($arguments);
        if (!in_array($input['entity_type'], $this->allowedCollaborationEntityTypes(), true)) {
            return 'entity_type must be one of: ' . implode(', ', $this->allowedCollaborationEntityTypes()) . '.';
        }
        if ($input['entity_public_id'] === '') {
            return 'entity_public_id is required.';
        }

        return null;
    }

    private function deleteOwnedCollaborationItem(array $arguments, string $serviceId, string $method, string $notFoundMessage): array
    {
        $publicId = trim((string)($arguments['public_id'] ?? ''));
        if ($publicId === '') {
            return ['error' => 'public_id is required.'];
        }

        $service = $this->container->get($serviceId);
        $result = $service->{$method}($publicId, $this->actor());
        if ($result === 'FORBIDDEN') {
            return ['error' => 'FORBIDDEN'];
        }

        return $result ? ['ok' => true, 'public_id' => $publicId] : ['error' => $notFoundMessage];
    }

    private function collaborationListSchema(): array
    {
        return [
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            'entity_type' => ['type' => 'string', 'enum' => ['task', 'project', 'comment']],
            'entity_public_id' => ['type' => 'string'],
            'user_public_id' => ['type' => 'string'],
        ];
    }

    private function entityActionSchema(): array
    {
        return [
            'entity_type' => ['type' => 'string', 'enum' => ['task', 'project', 'comment']],
            'entity_public_id' => ['type' => 'string'],
        ];
    }

    /** @return string[] */
    private function allowedCollaborationEntityTypes(): array
    {
        return ['task', 'project', 'comment'];
    }

    /** @return string[] */
    private function allowedReactions(): array
    {
        return ['like', 'love', 'laugh', 'wow', 'sad', 'angry', 'up'];
    }

    private function slugCode(string $value): string
    {
        $code = strtolower((string)preg_replace('/[^a-zA-Z0-9_]+/', '_', trim($value)));
        $code = trim($code, '_');
        return $code !== '' ? mb_substr($code, 0, 64) : 'tag_' . bin2hex(random_bytes(4));
    }

    private function counterpartySchema(): array
    {
        return [
            'title' => ['type' => 'string'],
            'counterparty_type' => ['type' => 'string', 'enum' => ['organization', 'individual', 'sole_proprietor', 'legal_entity'], 'default' => 'organization'],
            'legal_name' => ['type' => 'string'],
            'person_last_name' => ['type' => 'string'],
            'person_first_name' => ['type' => 'string'],
            'person_middle_name' => ['type' => 'string'],
            'person_birth_date' => ['type' => 'string'],
            'tax_inn' => ['type' => 'string'],
            'tax_kpp' => ['type' => 'string'],
            'tax_ogrn' => ['type' => 'string'],
            'tax_ogrnip' => ['type' => 'string'],
            'bank_account' => ['type' => 'string'],
            'bank_name' => ['type' => 'string'],
            'bank_bik' => ['type' => 'string'],
            'bank_corr_account' => ['type' => 'string'],
            'website' => ['type' => 'string'],
            'messenger' => ['type' => 'string'],
            'address_legal' => ['type' => 'string'],
            'address_postal' => ['type' => 'string'],
            'notes' => ['type' => 'string'],
            'email' => ['type' => 'string'],
            'phone' => ['type' => 'string'],
            'status' => ['type' => 'string', 'default' => 'active'],
            'extra_attributes' => ['type' => 'object', 'additionalProperties' => true],
        ];
    }

    private function teamSchema(): array
    {
        return [
            'title' => ['type' => 'string'],
            'team_type' => ['type' => 'string', 'default' => 'team'],
            'code' => ['type' => 'string'],
            'parent_public_id' => ['type' => 'string'],
            'manager_user_public_id' => ['type' => 'string'],
            'member_user_public_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
        ];
    }

    private function statusSchema(): array
    {
        return [
            'scope' => ['type' => 'string'],
            'code' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'color' => ['type' => 'string'],
            'sort_order' => ['type' => 'integer'],
            'is_active' => ['type' => 'integer', 'enum' => [0, 1], 'default' => 1],
        ];
    }

    private function tagSchema(): array
    {
        return [
            'code' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'color' => ['type' => 'string'],
            'description' => ['type' => 'string'],
        ];
    }

    private function clientSchema(): array
    {
        $schema = $this->counterpartySchema();
        unset($schema['counterparty_type'], $schema['extra_attributes']);
        $schema['client_type'] = ['type' => 'string', 'enum' => ['individual', 'sole_proprietor', 'legal_entity'], 'default' => 'individual'];
        return $schema;
    }

    private function contactSchema(): array
    {
        return [
            'full_name' => ['type' => 'string'],
            'email' => ['type' => 'string'],
            'phone' => ['type' => 'string'],
            'role' => ['type' => 'string'],
            'is_primary' => ['type' => 'boolean'],
            'counterparty_public_id' => ['type' => 'string'],
            'company_public_id' => ['type' => 'string'],
            'client_public_id' => ['type' => 'string'],
        ];
    }

    private function recurringSchema(): array
    {
        return [
            'title' => ['type' => 'string'],
            'entity_type' => ['type' => 'string', 'enum' => ['task', 'project', 'reminder', 'calendar_event']],
            'entity_public_id' => ['type' => 'string'],
            'rrule' => ['type' => 'string', 'description' => 'RFC-style recurrence rule such as FREQ=WEEKLY;INTERVAL=1.'],
            'is_active' => ['type' => 'integer', 'enum' => [0, 1], 'default' => 1],
        ];
    }

    private function intakeSchema(): array
    {
        return [
            'title' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'project_public_id' => ['type' => 'string'],
            'client_public_id' => ['type' => 'string'],
            'contact_public_id' => ['type' => 'string'],
            'priority_code' => ['type' => 'string'],
            'source_type' => ['type' => 'string'],
            'source_ref' => ['type' => 'string'],
            'source_email' => ['type' => 'string'],
            'external_source' => ['type' => 'string'],
            'external_id' => ['type' => 'string'],
            'extra' => ['type' => 'object', 'additionalProperties' => true],
            'due_at' => ['type' => 'string'],
            'assignee_user_id' => ['type' => 'integer'],
        ];
    }

    private function projectModuleSchema(): array
    {
        return [
            'project_public_id' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'lead_user_public_id' => ['type' => 'string'],
            'start_at' => ['type' => 'string'],
            'target_at' => ['type' => 'string'],
            'color' => ['type' => 'string'],
            'icon' => ['type' => 'string'],
            'sort_order' => ['type' => 'integer'],
            'meta_json' => ['type' => 'object', 'additionalProperties' => true],
        ];
    }

    private function projectModuleInputKeys(bool $update = false): array
    {
        $keys = [
            'project_public_id', 'title', 'description', 'status', 'lead_user_public_id',
            'start_at', 'target_at', 'color', 'icon', 'sort_order', 'meta_json', 'row_version',
        ];
        return $update ? array_merge(['public_id'], $keys) : $keys;
    }

    private function intakeFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'status', 'source_type', 'project_public_id', 'client_public_id', 'contact_public_id',
            'assignee_user_id', 'q', 'created_since', 'updated_since', 'sort', 'order',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 100);
        return $filters;
    }

    private function projectModuleFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'project_public_id', 'status', 'q', 'sort', 'order',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 100);
        return $filters;
    }

    private function analyticsListFilters(array $arguments): array
    {
        return $this->pick($arguments, ['limit']);
    }

    private function aiProviderFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, ['page', 'q', 'is_active']);
        $filters['limit'] = $this->limit($arguments, 50, 100);
        return $filters;
    }

    private function aiIntentFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, ['page', 'q', 'is_enabled', 'feature_flag']);
        $filters['limit'] = $this->limit($arguments, 50, 100);
        return $filters;
    }

    private function aiSchemaFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, ['page', 'intent_code', 'locale', 'is_active']);
        $filters['limit'] = $this->limit($arguments, 50, 100);
        return $filters;
    }

    private function aiUsageFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'intent_code', 'action_type', 'provider_public_id', 'status', 'error_code',
            'user_id', 'is_sensitive_context', 'date_from', 'date_to',
        ]);
        $filters['limit'] = $this->limit($arguments, 50, 100);
        return $filters;
    }

    private function aiJobFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'job_type', 'action_type', 'intent_code', 'status', 'scope_type', 'scope_public_id',
            'error_code', 'requested_by_user_id',
        ]);
        $filters['limit'] = $this->limit($arguments, 50, 100);
        return $filters;
    }

    private function aiSuggestionFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'intent_code', 'entity_type', 'entity_public_id', 'status', 'created_by_user_id',
        ]);
        $filters['limit'] = $this->limit($arguments, 50, 100);
        return $filters;
    }

    private function recycleBinFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'entity_type', 'entity_public_id', 'deleted_by_user_public_id', 'sort', 'order',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 100);
        return $filters;
    }

    private function jobFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'type', 'status', 'sort', 'order',
        ]);
        $filters['limit'] = $this->limit($arguments, 20, 100);
        return $filters;
    }

    private function workflowSchema(): array
    {
        return [
            'title' => ['type' => 'string'],
            'trigger_code' => ['type' => 'string', 'enum' => [
                'task_created', 'task_updated', 'task_status_changed', 'comment_added', 'file_uploaded',
                'deadline_reached', 'project_archived', 'user_created',
            ]],
            'action_code' => ['type' => 'string', 'enum' => [
                'assign_user', 'change_status', 'create_reminder', 'send_notification', 'create_comment',
                'create_follow_up_task', 'call_webhook', 'escalate_sla',
            ]],
            'payload' => ['type' => 'object', 'additionalProperties' => true],
            'is_enabled' => ['type' => 'integer', 'enum' => [0, 1], 'default' => 1],
        ];
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

    private function validateMenuPreferencesItems(array $items): array
    {
        $validated = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = $item['key'] ?? null;
            if (!is_string($key) || $key === '') {
                continue;
            }

            $entry = [
                'key' => $key,
                'visible' => (bool)($item['visible'] ?? true),
            ];

            if (str_starts_with($key, 'custom_')) {
                $entry['title'] = trim((string)($item['title'] ?? $item['label'] ?? ''));
                $entry['href'] = trim((string)($item['href'] ?? ''));
                $entry['icon'] = trim((string)($item['icon'] ?? ''));
                if ($entry['title'] === '' || $entry['href'] === '') {
                    continue;
                }
            }

            $validated[] = $entry;
        }

        return $validated;
    }

    private function coreUpdateEmptyChanges(?string $from, string $to): array
    {
        return [
            'ok' => true,
            'status' => 204,
            'data' => [
                'summary' => [
                    'commits' => 0,
                    'files' => 0,
                    'risk_level' => 'none',
                    'from' => $from,
                    'to' => $to !== '' ? $to : null,
                ],
                'commits' => [],
                'files' => [],
                'changes' => [
                    'added' => [],
                    'modified' => [],
                    'deleted' => [],
                    'renamed' => [],
                ],
                'message' => 'No target build is available, so there are no update changes to show.',
            ],
        ];
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

    private function redactSettings(array $payload): array
    {
        if (isset($payload['items']) && is_array($payload['items'])) {
            $payload['items'] = array_map(
                fn(mixed $item): mixed => is_array($item) ? $this->redactSettingItem($item) : $item,
                $payload['items']
            );
        }

        return $payload;
    }

    private function redactSettingItem(array $item): array
    {
        $name = strtolower((string)($item['name'] ?? ''));
        if ($name !== '' && $this->isSensitiveOrInternalKey($name)) {
            $item['value'] = '[redacted]';
            $item['value_redacted'] = true;
        }

        return $item;
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

    private function chatForUser(string $chatPublicId, int $userId): ?array
    {
        $stmt = $this->pdo()->prepare("
            SELECT c.*
            FROM chats c
            JOIN chat_participants cp ON cp.chat_id = c.id AND cp.user_id = :uid
            WHERE c.public_id = :public_id
            LIMIT 1
        ");
        $stmt->execute([
            'public_id' => $chatPublicId,
            'uid' => $userId,
        ]);
        $chat = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($chat) ? $chat : null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function participantsForChat(int $chatId): array
    {
        $stmt = $this->pdo()->prepare("
            SELECT u.id, u.public_id, u.full_name, u.login, u.email, cp.role, cp.joined_at
            FROM chat_participants cp
            JOIN users u ON u.id = cp.user_id
            WHERE cp.chat_id = :cid
            ORDER BY CASE cp.role WHEN 'admin' THEN 0 ELSE 1 END, COALESCE(NULLIF(u.full_name, ''), u.login)
        ");
        $stmt->execute(['cid' => $chatId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function resolveReplyMessage(int $chatId, string $messagePublicId): ?array
    {
        if ($chatId <= 0 || $messagePublicId === '') {
            return null;
        }

        $stmt = $this->pdo()->prepare("SELECT id, public_id, sender_user_id, text FROM chat_messages WHERE chat_id = :chat_id AND public_id = :public_id AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([
            'chat_id' => $chatId,
            'public_id' => $messagePublicId,
        ]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($message) ? $message : null;
    }

    /**
     * @param list<array<string,mixed>> $messages
     * @return list<array<string,mixed>>
     */
    private function attachChatMessageFiles(array $messages, string $chatPublicId): array
    {
        $ids = array_values(array_filter(array_map(static fn(array $message): string => (string)($message['public_id'] ?? ''), $messages)));
        if ($ids === []) {
            return $messages;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo()->prepare("SELECT public_id, entity_public_id, original_name, mime_type, size_bytes, created_at FROM files WHERE entity_type = 'chat_message' AND is_deleted = 0 AND entity_public_id IN ({$placeholders}) ORDER BY id ASC");
        $stmt->execute($ids);
        $byMessage = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $file) {
            $file['download_url'] = '/api/index.php?route=api/v1/chats/' . rawurlencode($chatPublicId) . '/attachments/' . rawurlencode((string)$file['public_id']) . '/download';
            $byMessage[(string)$file['entity_public_id']][] = $file;
        }

        foreach ($messages as &$message) {
            $message['attachments'] = $byMessage[(string)($message['public_id'] ?? '')] ?? [];
        }
        unset($message);

        return $messages;
    }

    private function storeChatAttachment(string $messagePublicId, array $raw): array
    {
        $publicId = 'fil_' . bin2hex(random_bytes(8));
        $name = $this->sanitizeFileName((string)($raw['name'] ?? 'file.bin'));
        $tmp = (string)($raw['tmp_name'] ?? '');
        $mime = $this->normalizeUploadMime($this->detectMime($tmp) ?: (string)($raw['type'] ?? 'application/octet-stream'), $name);
        $size = (int)($raw['size'] ?? 0);
        $dir = dirname(__DIR__, 3) . '/storage_api/uploads/chat';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir . '/' . $publicId . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
        if (!@move_uploaded_file($tmp, $path)) {
            if (!@rename($tmp, $path)) {
                if (!@copy($tmp, $path)) {
                    throw new \RuntimeException('UPLOAD_MOVE_FAILED');
                }
            }
        }

        $this->pdo()->prepare("
            INSERT INTO files (public_id, entity_type, entity_public_id, uploader_user_id, original_name, storage_path, mime_type, size_bytes, is_deleted, created_at)
            VALUES (:pid, 'chat_message', :entity_pid, :uid, :name, :path, :mime, :size, 0, NOW())
        ")->execute([
            'pid' => $publicId,
            'entity_pid' => $messagePublicId,
            'uid' => (int)($this->actor()['id'] ?? 0),
            'name' => $name,
            'path' => $path,
            'mime' => $mime,
            'size' => $size,
        ]);

        return [
            'public_id' => $publicId,
            'original_name' => $name,
            'mime_type' => $mime,
            'size_bytes' => $size,
        ];
    }

    private function sanitizeFileName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'file.bin';
        }
        return preg_replace('/[^\pL\pN._-]+/u', '_', $name) ?? 'file.bin';
    }

    private function detectMime(string $path): ?string
    {
        if ($path === '' || !is_file($path)) {
            return null;
        }
        $mime = function_exists('mime_content_type') ? @mime_content_type($path) : false;
        return is_string($mime) && $mime !== '' ? $mime : null;
    }

    private function normalizeUploadMime(string $mime, string $fileName): string
    {
        $mime = strtolower(trim(explode(';', $mime)[0]));
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($mime === 'image/pjpeg') return 'image/jpeg';
        if ($mime === 'text/comma-separated-values') return 'text/csv';
        if ($mime === 'application/octet-stream' && in_array($ext, ['jpg', 'jpeg'], true)) return 'image/jpeg';
        if ($mime === 'application/octet-stream' && $ext === 'png') return 'image/png';
        if ($mime === 'application/octet-stream' && $ext === 'gif') return 'image/gif';
        return $mime !== '' ? $mime : 'application/octet-stream';
    }

    private function editableChatMessage(int $chatId, string $messagePublicId, int $userId): ?array
    {
        $stmt = $this->pdo()->prepare("
            SELECT *
            FROM chat_messages
            WHERE chat_id = :cid
              AND public_id = :pid
              AND sender_user_id = :uid
              AND deleted_at IS NULL
              AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            LIMIT 1
        ");
        $stmt->execute(['cid' => $chatId, 'pid' => $messagePublicId, 'uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function auditChatMessage(int $messageId, int $chatId, int $actorUserId, string $action, ?string $before, ?string $after): void
    {
        try {
            $this->pdo()->prepare("
                INSERT INTO chat_message_audit_logs (public_id, message_id, chat_id, actor_user_id, action, before_text, after_text, created_at)
                VALUES (:pid, :mid, :cid, :uid, :action, :before_text, :after_text, NOW())
            ")->execute([
                'pid' => 'cma_' . bin2hex(random_bytes(8)),
                'mid' => $messageId,
                'cid' => $chatId,
                'uid' => $actorUserId,
                'action' => $action,
                'before_text' => $before,
                'after_text' => $after,
            ]);
        } catch (Throwable) {
        }
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        try {
            $stmt = $this->pdo()->prepare("SELECT 1 FROM {$table} WHERE {$column} IS NULL LIMIT 0");
            $stmt->execute();
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function pdo(): PDO
    {
        return $this->container->get('db.pdo');
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
