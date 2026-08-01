<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/**
 * Lightweight full-route audit refresh.
 * Broken routes:
 * - transport/runtime failures (status 0 or >=500)
 * - 404 with ROUTE_NOT_FOUND code
 */

function pickSamplePublicId(string $pattern): string
{
    $suffix = 'AUDIT' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

    return match (true) {
        str_contains($pattern, '/users/') => 'usr_' . $suffix,
        str_contains($pattern, '/roles/') => 'rol_' . $suffix,
        str_contains($pattern, '/teams/') => 'tea_' . $suffix,
        str_contains($pattern, '/departments/') => 'dep_' . $suffix,
        str_contains($pattern, '/companies/') => 'com_' . $suffix,
        str_contains($pattern, '/clients/') => 'cli_' . $suffix,
        str_contains($pattern, '/contacts/') => 'cnt_' . $suffix,
        str_contains($pattern, '/projects/') => 'prj_' . $suffix,
        str_contains($pattern, '/tasks/') => 'tsk_' . $suffix,
        str_contains($pattern, '/comments/') => 'cmt_' . $suffix,
        str_contains($pattern, '/files/') => 'fil_' . $suffix,
        str_contains($pattern, '/statuses/') => 'sts_' . $suffix,
        str_contains($pattern, '/priorities/') => 'pri_' . $suffix,
        str_contains($pattern, '/tags/') => 'tag_' . $suffix,
        str_contains($pattern, '/notifications/') => 'ntf_' . $suffix,
        str_contains($pattern, '/reminders/') => 'rmn_' . $suffix,
        str_contains($pattern, '/worklogs/') => 'wlg_' . $suffix,
        str_contains($pattern, '/api-clients/') => 'apc_' . $suffix,
        str_contains($pattern, '/api-keys/') => 'apk_' . $suffix,
        str_contains($pattern, '/webhooks/') => 'whk_' . $suffix,
        str_contains($pattern, '/organizations/') => 'org_' . $suffix,
        str_contains($pattern, '/retention/') => 'ret_' . $suffix,
        str_contains($pattern, '/template/') => 'tpl_' . $suffix,
        str_contains($pattern, '/workflow/rules/') => 'wfr_' . $suffix,
        str_contains($pattern, '/workflow/runs/') => 'wfrun_' . $suffix,
        str_contains($pattern, '/sla/policies/') => 'sla_' . $suffix,
        str_contains($pattern, '/approvals/') => 'apr_' . $suffix,
        str_contains($pattern, '/milestones/') => 'mls_' . $suffix,
        str_contains($pattern, '/dependencies/') => 'dep_' . $suffix,
        str_contains($pattern, '/recycle-bin/') => 'rcb_' . $suffix,
        default => 'obj_' . $suffix,
    };
}

function materializeRoute(string $pattern): string
{
    $route = $pattern;
    if (str_contains($route, '{entity_type}')) {
        $route = str_replace('{entity_type}', 'task', $route);
    }
    if (str_contains($route, '{user_public_id}')) {
        $route = str_replace('{user_public_id}', 'usr_AUDITUSER', $route);
    }

    if (preg_match_all('/\{[^}]+\}/', $route, $m)) {
        foreach ($m[0] as $token) {
            $sample = pickSamplePublicId($route);
            $route = str_replace($token, $sample, $route);
        }
    }

    return ltrim($route, '/');
}

try {
    $routes = require __DIR__ . '/../../config/routes.php';
    liveAssert(is_array($routes), 'Routes config must return array');

    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];

    $results = [];
    $ok = 0;
    $partial = 0;
    $broken = 0;

    foreach ($routes as $row) {
        $methods = (array)($row['methods'] ?? []);
        $pattern = (string)($row['pattern'] ?? '');
        $authRequired = (bool)($row['auth'] ?? false);
        if ($pattern === '' || $methods === []) {
            continue;
        }

        foreach ($methods as $method) {
            $method = strtoupper((string)$method);
            $route = materializeRoute($pattern);
            $headers = $authRequired ? $rootHeaders : [];
            $payload = [];

            // Avoid revoking current root session via audit run.
            if ($route === 'api/v1/auth/logout') {
                $headers = [];
            }

            $response = liveRequest($method, $route, $payload, $headers);
            $status = (int)$response['status'];
            $code = (string)($response['payload']['code'] ?? '');
            $class = 'partial';

            if (($status === 404 && $code === 'ROUTE_NOT_FOUND') || $status === 0 || $status >= 500) {
                $class = 'broken';
                $broken++;
            } elseif ($status >= 200 && $status < 300) {
                $class = 'ok';
                $ok++;
            } else {
                $partial++;
            }

            $results[] = [
                'method' => $method,
                'pattern' => $pattern,
                'route' => $route,
                'status' => $status,
                'code' => $code,
                'class' => $class,
            ];
        }
    }

    $artifact = [
        'generated_at' => gmdate('c'),
        'summary' => [
            'ok' => $ok,
            'partial' => $partial,
            'broken' => $broken,
        ],
        'results' => $results,
    ];

    $storageBase = trim((string)(getenv('CRM_STORAGE_BASE') ?: dirname(__DIR__, 3) . '/storage_api'));
    $targetDir = rtrim($storageBase, '/') . '/generated/changelog';
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0775, true);
    }
    $target = $targetDir . '/live_route_audit_2026-04-19.json';
    file_put_contents($target, json_encode($artifact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

    echo '[OK] final_live_route_audit_refresh';
    echo ' ok=' . $ok;
    echo ' partial=' . $partial;
    echo ' broken=' . $broken;
    echo ' file=' . $target;
    echo "\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] final_live_route_audit_refresh: ' . $e->getMessage() . "\n");
    exit(1);
}
