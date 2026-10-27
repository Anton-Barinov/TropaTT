<?php
declare(strict_types=1);

require dirname(__DIR__) . '/api/service/GitLabClient.php';
require dirname(__DIR__) . '/api/repository/GitLabRepository.php';

use Module\Crm\GitlabIntegration\Repository\GitLabRepository;
use Module\Crm\GitlabIntegration\Service\GitLabClient;

$routes = require dirname(__DIR__) . '/api/config/routes.php';
foreach ($routes as $route) {
    if (($route['auth'] ?? false) === true) expect(($route['workspace_required'] ?? false) === true, 'authenticated route lacks workspace requirement');
}

function expect(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}

try { GitLabClient::assertCurlSupportAvailable(false, true); throw new RuntimeException('missing cURL extension was accepted'); } catch (RuntimeException $e) { expect($e->getMessage() === 'GITLAB_TRANSPORT_UNAVAILABLE', 'missing cURL extension did not fail closed'); }
try { GitLabClient::assertCurlSupportAvailable(true, false); throw new RuntimeException('missing cURL function was accepted'); } catch (RuntimeException $e) { expect($e->getMessage() === 'GITLAB_TRANSPORT_UNAVAILABLE', 'missing cURL function did not fail closed'); }
$responseBuffer = '';
$responseOverflow = false;
expect(GitLabClient::appendResponseChunk($responseBuffer, str_repeat('a', 2097152), $responseOverflow) === 2097152, 'response cap rejected the allowed boundary');
expect(GitLabClient::appendResponseChunk($responseBuffer, 'x', $responseOverflow) === 0 && $responseOverflow, 'bounded callback accepted a byte over limit');
expect(strlen($responseBuffer) === 2097152, 'bounded callback buffered data above its limit');
expect(GitLabClient::validateBaseUrl('https://gitlab.com/api/v4', ['gitlab.com']), 'official GitLab host should be accepted');
expect(GitLabClient::validateBaseUrl('https://git.example.test/api/v4', ['gitlab.com', 'git.example.test']), 'explicit exact self-managed host should be accepted');
foreach ([
    'http://gitlab.com/api/v4',
    'https://gitlab.com.attacker.test/api/v4',
    'https://attacker.test/api/v4',
    'https://user:pass@gitlab.com/api/v4',
    'https://gitlab.com:8443/api/v4',
    'https://127.0.0.1/api/v4',
    'https://[::1]/api/v4',
    'https://gitlab.com/api/v4?token=secret',
] as $url) {
    expect(!GitLabClient::validateBaseUrl($url, ['gitlab.com', 'git.example.test']), 'unsafe URL accepted: ' . $url);
}
expect(GitLabClient::normalizeAllowedHosts(['*.gitlab.com', '10.0.0.1', 'trusted.test']) === ['trusted.test'], 'wildcard/IP hosts must be discarded');

$dnsCalls = 0;
$httpCalls = 0;
$dns = static function (string $host) use (&$dnsCalls): array { $dnsCalls++; return ['93.184.216.34']; };
$http = static function (string $url, string $method, array $headers, ?array $body, array $addresses) use (&$httpCalls): array {
    $httpCalls++;
    expect(str_starts_with($url, 'https://gitlab.com/api/v4/'), 'mock transport received unexpected URL');
    expect(in_array('93.184.216.34', $addresses, true), 'pinned resolver result missing');
    return ['body' => '{"username":"mock-user"}', 'http_code' => 200, 'error' => ''];
};
$client = new GitLabClient(2, 1, ['gitlab.com'], $dns, $http);
try {
    $client->request('secret-token', 'https://attacker.example/api/v4', 'GET', '/user');
    throw new RuntimeException('untrusted host unexpectedly reached transport');
} catch (RuntimeException $e) {
    expect($e->getMessage() === 'GITLAB_UNTRUSTED_HOST', 'unexpected rejection reason');
}
expect($dnsCalls === 0 && $httpCalls === 0, 'untrusted host caused DNS or HTTP activity');
expect($client->testConnection('mock-token', 'https://gitlab.com/api/v4')['success'], 'mocked official request should succeed');

try {
    $largeClient = new GitLabClient(2, 1, ['gitlab.com'], $dns, static function () use (&$httpCalls): array { $httpCalls++; return ['body' => str_repeat('x', 2097153), 'http_code' => 200, 'error' => '']; });
    $largeClient->request('must-not-be-logged-token', 'https://gitlab.com/api/v4', 'GET', '/user');
    throw new RuntimeException('oversized body unexpectedly accepted');
} catch (RuntimeException $e) {
    expect($e->getMessage() === 'GITLAB_RESPONSE_TOO_LARGE', 'oversized body did not fail closed');
    expect(!str_contains($e->getMessage(), 'must-not-be-logged-token'), 'transport error leaked token');
}

expect($dnsCalls === 2 && $httpCalls === 2, 'trusted or bounded response request did not use mocked DNS/HTTP');
try {
    GitLabClient::resolvePublicBaseUrl('https://gitlab.com/api/v4', ['gitlab.com'], static fn(string $host): array => ['10.0.0.8']);
    throw new RuntimeException('private DNS answer accepted');
} catch (RuntimeException $e) {
    expect($e->getMessage() === 'GITLAB_PRIVATE_DESTINATION_BLOCKED', 'private DNS did not fail closed');
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE module_gitlab_connections (id INTEGER PRIMARY KEY, public_id TEXT, organization_id INTEGER, name TEXT, base_url TEXT, token_encrypted TEXT, last_status TEXT, last_message TEXT, created_by_user_id INTEGER, created_at TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE module_gitlab_project_links (id INTEGER PRIMARY KEY, public_id TEXT, organization_id INTEGER, connection_id INTEGER, project_path TEXT, project_public_id TEXT, webhook_secret_encrypted TEXT, is_active INTEGER, created_by_user_id INTEGER, created_at TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE module_gitlab_sync_items (id INTEGER PRIMARY KEY, organization_id INTEGER, link_id INTEGER, source_type TEXT, source_id TEXT, target_type TEXT, target_public_id TEXT, status TEXT, payload_json TEXT, created_at TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE module_gitlab_sync_logs (id INTEGER PRIMARY KEY, organization_id INTEGER, link_id INTEGER, level TEXT, message TEXT, created_at TEXT)');
$pdo->exec("INSERT INTO module_gitlab_connections VALUES (1,'conn-a',10,'A','https://gitlab.com/api/v4','secret-a',NULL,NULL,1,'now','now'),(2,'conn-b',20,'B','https://gitlab.com/api/v4','secret-b',NULL,NULL,2,'now','now')");
$pdo->exec("INSERT INTO module_gitlab_project_links VALUES (1,'link-a',10,1,'a/proj','proj-a',NULL,1,1,'now','now'),(2,'link-b',20,2,'b/proj','proj-b',NULL,1,2,'now','now')");
$pdo->exec("INSERT INTO module_gitlab_sync_logs VALUES (1,10,1,'info','workspace A','now'),(2,20,2,'info','workspace B','now')");
$a = new GitLabRepository($pdo, 10);
expect(count($a->listConnections()) === 1 && $a->listConnections()[0]['public_id'] === 'conn-a', 'connections leaked between workspaces');
expect(count($a->listLinks()) === 1 && $a->listLinks()[0]['public_id'] === 'link-a', 'links leaked between workspaces');
expect(count($a->listLogs()) === 1 && $a->listLogs()[0]['message'] === 'workspace A', 'logs leaked between workspaces');
expect($a->getConnection('conn-b') === null && $a->getLink('link-b') === null, 'cross-workspace ID lookup succeeded');
(new GitLabRepository($pdo, 0))->addLog(null, 'info', 'must not be globally visible');
expect(count($a->listLogs()) === 1, 'workspace-less log became visible');
expect(count(GitLabRepository::listActiveLinksForWorker($pdo)) === 2, 'worker must enumerate only scoped non-null links');
$a->deleteConnection('conn-b');
expect($a->getConnection('conn-a') !== null && $a->getConnection('conn-b') === null, 'cross-workspace delete affected another tenant');
echo "GitLab security regressions passed\n";
