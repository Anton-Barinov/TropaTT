<?php

declare(strict_types=1);
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }


$apiRoot = dirname(__DIR__);
$routesFile = $apiRoot . '/config/routes.php';
$outDir = $apiRoot . '/docs/openapi';
$outFile = $outDir . '/openapi.v1.json';

/** @var array<int,array<string,mixed>> $routes */
$routes = require $routesFile;

$spec = [
    'openapi' => '3.1.0',
    'info' => [
        'title' => 'CRM API',
        'version' => 'v1',
        'description' => 'Generated from api/config/routes.php',
    ],
    'servers' => [
        ['url' => '/api/index.php?route='],
        ['url' => '/'],
    ],
    'tags' => [],
    'paths' => [],
    'components' => [
        'securitySchemes' => [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
                'description' => 'Bearer token from /api/v1/auth/login',
            ],
            'cookieSession' => [
                'type' => 'apiKey',
                'in' => 'cookie',
                'name' => 'crm_api_session',
                'description' => 'HttpOnly session cookie',
            ],
            'csrfHeader' => [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => 'X-CSRF-Token',
                'description' => 'Required for cookie-auth write methods',
            ],
        ],
        'schemas' => [
            'EnvelopeSuccess' => [
                'type' => 'object',
                'required' => ['success', 'code', 'message', 'data', 'errors', 'meta'],
                'properties' => [
                    'success' => ['type' => 'boolean', 'const' => true],
                    'code' => ['type' => 'string'],
                    'message' => ['type' => 'string'],
                    'data' => ['type' => 'object', 'additionalProperties' => true],
                    'errors' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'meta' => ['$ref' => '#/components/schemas/EnvelopeMeta'],
                ],
            ],
            'EnvelopeError' => [
                'type' => 'object',
                'required' => ['success', 'code', 'message', 'data', 'errors', 'meta'],
                'properties' => [
                    'success' => ['type' => 'boolean', 'const' => false],
                    'code' => ['type' => 'string'],
                    'message' => ['type' => 'string'],
                    'data' => ['type' => 'null'],
                    'errors' => ['type' => 'object', 'additionalProperties' => true],
                    'meta' => ['$ref' => '#/components/schemas/EnvelopeMeta'],
                ],
            ],
            'EnvelopeMeta' => [
                'type' => 'object',
                'required' => ['request_id', 'timestamp', 'version', 'correlation_id'],
                'properties' => [
                    'request_id' => ['type' => 'string'],
                    'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                    'version' => ['type' => 'string', 'example' => 'v1'],
                    'correlation_id' => ['type' => 'string'],
                ],
            ],
            'PaginatedList' => [
                'type' => 'object',
                'properties' => [
                    'items' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                    'total' => ['type' => 'integer'],
                    'page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                ],
                'additionalProperties' => true,
            ],
            'AuthLoginData' => [
                'type' => 'object',
                'properties' => [
                    'access_token' => ['type' => 'string'],
                    'csrf_token' => ['type' => 'string'],
                    'session_public_id' => ['type' => 'string'],
                    'user' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'additionalProperties' => true,
            ],
            'TaskEntity' => [
                'type' => 'object',
                'properties' => [
                    'public_id' => ['type' => 'string'],
                    'title' => ['type' => 'string'],
                    'status_code' => ['type' => 'string'],
                    'priority_code' => ['type' => 'string'],
                    'assignee_user_public_id' => ['type' => ['string', 'null']],
                    'due_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                ],
                'additionalProperties' => true,
            ],
            'NotificationEntity' => [
                'type' => 'object',
                'properties' => [
                    'public_id' => ['type' => 'string'],
                    'title' => ['type' => 'string'],
                    'body' => ['type' => 'string'],
                    'type' => ['type' => 'string'],
                    'priority' => ['type' => 'string'],
                    'is_read' => ['type' => 'boolean'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
                'additionalProperties' => true,
            ],
            'AiProviderEntity' => [
                'type' => 'object',
                'properties' => [
                    'public_id' => ['type' => 'string'],
                    'code' => ['type' => 'string'],
                    'title' => ['type' => 'string'],
                    'provider_type' => ['type' => 'string'],
                    'base_url' => ['type' => ['string', 'null']],
                    'is_active' => ['type' => 'boolean'],
                ],
                'additionalProperties' => true,
            ],
        ],
        'responses' => [
            'Success200' => [
                'description' => 'Success',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/EnvelopeSuccess'],
                    ],
                ],
            ],
            'Unauthorized401' => [
                'description' => 'Unauthorized',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/EnvelopeError'],
                    ],
                ],
            ],
            'Forbidden403' => [
                'description' => 'Forbidden',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/EnvelopeError'],
                    ],
                ],
            ],
            'Validation422' => [
                'description' => 'Validation error',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/EnvelopeError'],
                    ],
                ],
            ],
            'RateLimited429' => [
                'description' => 'Rate limited',
                'headers' => [
                    'Retry-After' => ['schema' => ['type' => 'integer']],
                ],
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/EnvelopeError'],
                    ],
                ],
            ],
        ],
    ],
];

$tagSet = [];

foreach ($routes as $route) {
    $pattern = (string)($route['pattern'] ?? '');
    if ($pattern === '') {
        continue;
    }

    // Do not publish install/internal-only routes or routes explicitly marked
    // outside the public contract.
    if (str_starts_with($pattern, '/install/')
        || str_starts_with($pattern, '/internal/')
        || ($route['openapi'] ?? true) === false
    ) {
        continue;
    }

    $methods = (array)($route['methods'] ?? []);
    if ($methods === []) {
        continue;
    }

    $tag = 'misc';
    if (str_starts_with($pattern, '/api/v1/')) {
        $rest = substr($pattern, strlen('/api/v1/'));
        $tag = explode('/', trim((string)$rest, '/'))[0] ?: 'misc';
    } elseif (str_starts_with($pattern, '/v1/')) {
        $rest = substr($pattern, strlen('/v1/'));
        $tag = explode('/', trim((string)$rest, '/'))[0] ?: 'misc';
    }

    $tagSet[$tag] = true;

    $path = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '{$1}', $pattern);
    if (!is_string($path)) {
        $path = $pattern;
    }

    if (!isset($spec['paths'][$path])) {
        $spec['paths'][$path] = [];
    }

    foreach ($methods as $method) {
        $methodUpper = strtoupper(trim((string)$method));
        $httpMethod = strtolower($methodUpper);
        if ($httpMethod === '') {
            continue;
        }

        $action = (string)($route['action'] ?? 'handle');
        $controller = (string)($route['controller'] ?? 'Controller');
        $controllerShort = $controller;
        if (str_contains($controller, '\\')) {
            $parts = explode('\\', $controller);
            $controllerShort = (string)end($parts);
        }

        $op = [
            'operationId' => preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($controllerShort . '_' . $action . '_' . $methodUpper)) ?: strtolower($controllerShort . '_' . $action),
            'tags' => [$tag],
            'summary' => $controllerShort . '::' . $action,
            'description' => 'Generated route contract entry.',
            'parameters' => [
                ['name' => 'X-Request-Id', 'in' => 'header', 'required' => false, 'schema' => ['type' => 'string', 'maxLength' => 64]],
                ['name' => 'X-Correlation-Id', 'in' => 'header', 'required' => false, 'schema' => ['type' => 'string', 'maxLength' => 64]],
                ['name' => 'X-Locale', 'in' => 'header', 'required' => false, 'schema' => ['type' => 'string', 'enum' => ['ru-ru', 'en-gb']]],
            ],
            'responses' => [
                '200' => ['$ref' => '#/components/responses/Success200'],
                '401' => ['$ref' => '#/components/responses/Unauthorized401'],
                '403' => ['$ref' => '#/components/responses/Forbidden403'],
                '422' => ['$ref' => '#/components/responses/Validation422'],
                '429' => ['$ref' => '#/components/responses/RateLimited429'],
            ],
            'x-auth-required' => (bool)($route['auth'] ?? true),
            'x-required-permissions' => array_values((array)($route['required_permissions'] ?? [])),
        ];

        if ((bool)($route['auth'] ?? true) === true) {
            $op['security'] = [
                ['bearerAuth' => []],
                ['cookieSession' => []],
            ];
        }

        if (in_array($methodUpper, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $op['parameters'][] = ['name' => 'X-CSRF-Token', 'in' => 'header', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'Required for cookie-auth write requests'];
            $op['parameters'][] = ['name' => 'X-Idempotency-Key', 'in' => 'header', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'Recommended for idempotent write operations'];
        }

        if (in_array($methodUpper, ['POST', 'PUT', 'PATCH'], true)) {
            $op['requestBody'] = [
                'required' => false,
                'content' => [
                    'application/json' => [
                        'schema' => ['type' => 'object', 'additionalProperties' => true],
                    ],
                ],
            ];
        }

        if ($methodUpper === 'POST') {
            $op['responses']['201'] = ['$ref' => '#/components/responses/Success200'];
        }

        if ($methodUpper === 'DELETE') {
            $op['responses']['204'] = ['description' => 'No content'];
        }

        // Document the task list query filters (GET /api/v1/tasks). All values
        // resolve server-side over the full dataset; list filters accept
        // comma-separated values and the "__none" marker (no value).
        if ($httpMethod === 'get' && $path === '/api/v1/tasks') {
            $queryParams = [
                'search' => 'Text search across title, description and task key.',
                'status' => 'Single status code filter.',
                'priority' => 'Single priority code filter.',
                'exclude_statuses' => 'Comma-separated status codes to exclude.',
                'assignee_user_public_id' => 'Comma-separated assignee user public ids, or "none"/"__none" for tasks without an assignee.',
                'manager_user_public_id' => 'Comma-separated project-manager user public ids, or "none"/"__none" for tasks without a project manager.',
                'project_public_id' => 'Comma-separated project public ids, or "none"/"__none" for tasks without a project.',
                'cycle_public_id' => 'Comma-separated cycle public ids, or "none"/"__none" for tasks without a cycle.',
                'tag_public_id' => 'Comma-separated tag public ids (any match).',
                'client_public_id' => 'Single client public id.',
                'team_public_id' => 'Single team public id.',
                'due' => 'Due preset: "overdue", "today" or "week".',
                'due_at' => 'Single due date (YYYY-MM-DD).',
                'due_at_from' => 'Due date range start: YYYY-MM-DD or full timestamp YYYY-MM-DD HH:MM:SS.',
                'due_at_to' => 'Due date range end: YYYY-MM-DD or full timestamp YYYY-MM-DD HH:MM:SS.',
                'archived' => 'Set to "1" to include archived tasks.',
                'sort' => 'Sort column: title, due_at, created_at, updated_at, status_code, priority_code.',
                'order' => 'Sort direction: ASC or DESC.',
                'limit' => 'Page size (0 = unlimited, default 20, max 500).',
                'page' => 'Page number (offset pagination).',
                'cursor' => 'Opaque cursor for cursor pagination.',
                'with_status_counts' => 'Set to "1" to include per-status counts in meta for the full filtered set.',
            ];
            foreach ($queryParams as $paramName => $paramDescription) {
                $op['parameters'][] = [
                    'name' => $paramName,
                    'in' => 'query',
                    'required' => false,
                    'schema' => ['type' => 'string'],
                    'description' => $paramDescription,
                ];
            }
        }

        $spec['paths'][$path][$httpMethod] = $op;
    }
}

$tags = [];
$tagNames = array_keys($tagSet);
sort($tagNames);
foreach ($tagNames as $tagName) {
    $tags[] = ['name' => $tagName];
}
$spec['tags'] = $tags;

ksort($spec['paths']);

if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

file_put_contents(
    $outFile,
    json_encode($spec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n"
);

echo "[OK] Generated {$outFile}\n";
echo "Paths: " . count($spec['paths']) . "\n";
