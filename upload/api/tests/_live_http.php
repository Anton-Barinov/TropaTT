<?php
declare(strict_types=1);

const LIVE_API_BASE = 'https://localhost/api/index.php';

function liveAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @param array<string,mixed> $payload
 * @param array<string,string> $headers
 * @return array{status:int,headers:array<int,string>,body:string,payload:array<string,mixed>}
 */
function liveRequest(string $method, string $route, array $payload = [], array $headers = []): array
{
    $method = strtoupper($method);
    $base = trim((string)getenv('CRM_TEST_LIVE_API_BASE'));
    if ($base === '') {
        $base = LIVE_API_BASE;
    }
    $url = $base . '?route=' . rawurlencode($route);

    $headerLines = [
        'Accept: application/json',
    ];

    foreach ($headers as $name => $value) {
        $headerLines[] = $name . ': ' . $value;
    }

    $content = '';
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && $payload !== []) {
        $content = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($content)) {
            $content = '{}';
        }
        $headerLines[] = 'Content-Type: application/json';
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headerLines),
            'content' => $content,
            'ignore_errors' => true,
            'timeout' => 20,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header;
    if (!is_string($body)) {
        $body = '';
    }

    $status = 0;
    if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $m)) {
        $status = (int)$m[1];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        $decoded = [];
    }

    return [
        'status' => $status,
        'headers' => $responseHeaders,
        'body' => $body,
        'payload' => $decoded,
    ];
}

/**
 * @return array{token:string,user_public_id:string,login_response:array<string,mixed>}
 */
function liveLoginRoot(): array
{
    $loginName = trim((string)getenv('CRM_TEST_ROOT_LOGIN'));
    if ($loginName === '') {
        $loginName = 'root';
    }

    $passwordCandidates = [];
    $envPassword = trim((string)getenv('CRM_TEST_ROOT_PASSWORD'));
    if ($envPassword !== '') {
        $passwordCandidates[] = $envPassword;
    }
    $passwordCandidates[] = 'TropaRoot#2026!';
    $passwordCandidates[] = 'TropaTest#2026!';
    $passwordCandidates = array_values(array_unique(array_filter($passwordCandidates, static fn(string $v): bool => $v !== '')));

    $tokenCandidates = [];
    $envToken = trim((string)getenv('CRM_TEST_ROOT_TOKEN'));
    if ($envToken !== '') {
        $tokenCandidates[] = $envToken;
    }
    $tokenCandidates[] = '';
    // Default web/manual test users typically have no token-factor requirement.
    $tokenCandidates[] = '';
    $tokenCandidates = array_values(array_unique($tokenCandidates));

    $last = ['status' => 0, 'payload' => []];
    foreach ($passwordCandidates as $password) {
        foreach ($tokenCandidates as $tokenCandidate) {
            $payload = [
                'login' => $loginName,
                'password' => $password,
            ];
            if ($tokenCandidate !== '') {
                $payload['token'] = $tokenCandidate;
            }

            $response = liveRequest('POST', 'api/v1/auth/login', $payload);
            $last = $response;
            if ($response['status'] !== 200 || ($response['payload']['success'] ?? false) !== true) {
                continue;
            }

            $token = (string)($response['payload']['data']['access_token'] ?? '');
            $userPublicId = (string)($response['payload']['data']['user']['public_id'] ?? '');
            liveAssert($token !== '', 'Access token is required');
            liveAssert($userPublicId !== '', 'User public_id is required');

            return [
                'token' => $token,
                'user_public_id' => $userPublicId,
                'login_response' => $response,
            ];
        }
    }

    $code = (string)($last['payload']['code'] ?? 'UNKNOWN');
    $message = (string)($last['payload']['message'] ?? 'unknown');
    throw new RuntimeException('Login status must be 200, got status=' . (int)$last['status'] . ' code=' . $code . ' message=' . $message);
}
