<?php
declare(strict_types=1);

namespace Api\Controller\Mcp;

use Api\Controller\Common\BaseController;
use Api\Model\Knowledge\KnowledgeRepository;
use Api\System\Library\Http\RawJsonResponse;
use Api\System\Library\Service\AuthzService;
use Api\System\Library\Service\CalendarService;
use Api\System\Library\Service\ClientService;
use Api\System\Library\Service\CompanyService;
use Api\System\Library\Service\CommentService;
use Api\System\Library\Service\ContactService;
use Api\System\Library\Service\CounterpartyService;
use Api\System\Library\Service\IdeaService;
use Api\System\Library\Service\ProjectService;
use Api\System\Library\Service\SearchService;
use Api\System\Library\Service\TaskService;
use Api\System\Library\Service\UserService;
use Api\System\Library\Service\WorkCycleService;
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
            'crm_list_projects' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmListProjects($arguments))),
            'crm_get_project' => $this->withPermission('project.manage', fn() => $this->toolResult($this->crmGetProject($arguments))),
            'crm_list_knowledge_pages' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmListKnowledgePages($arguments))),
            'crm_get_knowledge_page' => $this->withPermission('knowledge.view', fn() => $this->toolResult($this->crmGetKnowledgePage($arguments))),
            'crm_create_knowledge_page' => $this->withPermission('knowledge.create', fn() => $this->toolResult($this->crmCreateKnowledgePage($arguments))),
            'crm_list_calendar_events' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmListCalendarEvents($arguments))),
            'crm_get_calendar_agenda' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmGetCalendarAgenda($arguments))),
            'crm_create_calendar_event' => $this->withPermission('task.manage', fn() => $this->toolResult($this->crmCreateCalendarEvent($arguments))),
            'crm_list_ideas' => $this->toolResult($this->crmListIdeas($arguments)),
            'crm_get_idea' => $this->toolResult($this->crmGetIdea($arguments)),
            'crm_create_idea' => $this->toolResult($this->crmCreateIdea($arguments)),
            'crm_add_idea_comment' => $this->toolResult($this->crmAddIdeaComment($arguments)),
            'crm_list_chats' => $this->toolResult($this->crmListChats($arguments)),
            'crm_list_chat_messages' => $this->toolResult($this->crmListChatMessages($arguments)),
            'crm_send_chat_message' => $this->toolResult($this->crmSendChatMessage($arguments)),
            default => $this->toolError('Unknown tool: ' . $name),
        };
    }

    private function crmGetCurrentUser(): array
    {
        return ['user' => $this->publicData($this->actor())];
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

    private function crmListChatMessages(array $arguments): array
    {
        $actor = $this->actor();
        $userId = (int)($actor['id'] ?? 0);
        $chatPublicId = trim((string)($arguments['chat_public_id'] ?? ''));
        if ($userId <= 0 || $chatPublicId === '') {
            return ['error' => 'chat_public_id is required.'];
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
            SELECT cm.public_id, cm.id AS message_seq, cm.message_type, cm.text, cm.created_at, cm.updated_at,
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

        return [
            'chat' => $this->publicData($this->pick($chat, ['public_id', 'title', 'type'])),
            'items' => $this->publicData($items),
        ];
    }

    private function crmSendChatMessage(array $arguments): array
    {
        $actor = $this->actor();
        $userId = (int)($actor['id'] ?? 0);
        $chatPublicId = trim((string)($arguments['chat_public_id'] ?? ''));
        $text = trim((string)($arguments['text'] ?? ''));
        if ($userId <= 0 || $chatPublicId === '' || $text === '') {
            return ['error' => 'chat_public_id and text are required.'];
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

    private function crmEntityFilters(array $arguments): array
    {
        $filters = $this->pick($arguments, [
            'page', 'search', 'counterparty_type', 'client_type', 'status',
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
