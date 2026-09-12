<?php
declare(strict_types=1);

namespace Api\Controller\System;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\RawJsonResponse;
use Api\System\Library\Update\CoreUpdateConfig;
use Api\System\Library\Update\CoreVersion;

/**
 * A2A Agent Card Controller.
 *
 * Exposes /.well-known/agent-card.json in compliance with RFC 8615
 * and the Google / Linux Foundation Agent-to-Agent (A2A) protocol specification.
 *
 * Allows autonomous AI agents, multi-agent frameworks (AutoGen, CrewAI, LangGraph),
 * and external LLMs to discover TropaTT CRM capabilities, protocols, endpoints,
 * and security schemes without full catalog inspection.
 */
final class AgentCardController extends BaseController
{
    public function show(): RawJsonResponse
    {
        $config = CoreUpdateConfig::load();
        $versionService = new CoreVersion((string)$config['storage_dir'], dirname(__DIR__, 3));
        $versionData = $versionService->current();
        $coreVersion = (string)($versionData['core_version'] ?? '0.1.0');

        $baseUrl = $this->resolveBaseUrl();

        $card = [
            'schema_version' => 'https://a2a-protocol.org/schemas/v0.3/agent-card.json',
            'name' => 'TropaTT AgentOS',
            'description' => 'Autonomous enterprise coordination platform, multi-agent communication hub, and project operating system for human and AI teams.',
            'version' => $coreVersion,
            'vendor' => [
                'name' => 'TropaTT',
                'url' => 'https://tropatt.com',
            ],
            'protocols' => [
                'mcp' => [
                    'version' => '2025-06-18',
                    'transport' => 'streamable_http',
                    'endpoint' => $baseUrl . '/api/index.php?route=api/v1/mcp',
                    'default_toolset' => 'core',
                    'available_toolsets' => [
                        'core' => 'Essential starter profile: mega-tools for tasks, projects, people, time, knowledge, chat, ai, admin, bundle, and memory.',
                        'tasks' => 'Full task work management, subtasks, checklists, dependencies, and SLA.',
                        'projects' => 'Project governance, teams, roadmaps, and lifecycle.',
                        'kb' => 'Knowledge vault, wiki documents, and spaces.',
                        'crm' => 'Clients, counterparties, contacts, companies, organizations.',
                        'people' => 'User directory, teams, invitations.',
                        'time' => 'Worklogs, time tracking, calendars, holidays.',
                        'admin' => 'System configuration, webhooks, intake, audit.',
                        'all' => 'Complete unfiltered catalog with 600+ granular CRUD tools.',
                    ],
                ],
                'a2a' => [
                    'version' => '0.3',
                    'role' => 'coordinator',
                    'task_lifecycle' => [
                        'states' => ['new', 'in_progress', 'review', 'done', 'cancelled'],
                        'state_machine' => 'A2A-TaskState-v1',
                    ],
                ],
                'rest' => [
                    'version' => 'v1',
                    'endpoint' => $baseUrl . '/api/v1',
                    'openapi' => $baseUrl . '/api/v1/admin/openapi/spec',
                ],
            ],
            'authentication' => [
                'type' => 'bearer',
                'description' => 'Permanent API token (apk_...) issued via Admin -> API Clients or crm_admin issue_api_client_key.',
                'token_format' => 'apk_[a-zA-Z0-9]{40,}',
            ],
            'skills' => [
                [
                    'id' => 'task-management',
                    'name' => 'Task & Work Management',
                    'description' => 'Create, assign, schedule, track, and decompose complex projects and tasks with DoD checklists, dependencies (BLOCKS/FS), and time tracking.',
                    'tool' => 'crm_task',
                ],
                [
                    'id' => 'knowledge-vault',
                    'name' => 'Structured Knowledge Base',
                    'description' => 'Corporate memory, rules, engineering regulations, architecture specs, and bidirectional links to tasks and projects.',
                    'tool' => 'crm_knowledge',
                ],
                [
                    'id' => 'team-chat-hub',
                    'name' => 'Multi-Agent Collaboration & Chat',
                    'description' => 'Direct and project channels for agent-to-agent and agent-to-human coordination, structured DataPart messages, and event dispatch.',
                    'tool' => 'crm_chat',
                ],
                [
                    'id' => 'governed-memory',
                    'name' => 'Governed Shared Memory',
                    'description' => 'Persistent key-value, entity-linked semantic memory, substring search, and knowledge graph export for multi-agent workflows.',
                    'tool' => 'crm_agent_memory',
                ],
                [
                    'id' => 'agent-bundle',
                    'name' => 'One-shot Atomic Task Decomposition',
                    'description' => 'Create epic task, subtasks, DoD checklists, and QA verification tasks in a single token-efficient atomic MCP call.',
                    'tool' => 'crm_agent_bundle',
                ],
            ],
            'optimistic_concurrency' => [
                'supported' => true,
                'header' => 'If-Match',
                'mcp_param' => 'expected_row_version',
                'conflict_code' => 409,
                'description' => 'STORM pattern write-time conflict detection via row_version attribute on mutating operations.',
            ],
        ];

        return new RawJsonResponse($card, 200, [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function resolveBaseUrl(): string
    {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'work.tropatt.com');
        return $proto . '://' . $host;
    }
}
