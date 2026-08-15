<?php
declare(strict_types=1);

namespace Module\Crm\Raycast\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;

final class RaycastController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @return array<string, mixed>
     */
    private function actor(): array
    {
        $auth = $this->container->get('auth_user');
        return is_array($auth['user'] ?? null) ? $auth['user'] : [];
    }

    private function hasPermission(string $code): bool
    {
        $user = $this->actor();
        if (!empty($user['is_root'])) {
            return true;
        }
        $perms = array_map('strval', (array)($user['permission_codes'] ?? []));
        return in_array('*', $perms, true) || in_array($code, $perms, true);
    }

    /**
     * Build the MCP endpoint URL from the current request (never a hard-coded
     * domain), so the module works on any domain, subdomain and sub-path.
     */
    private function mcpUrl(string $route): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (strtolower((string)($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https')
            ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/api/index.php');
        if ($script === '' || $script === '/') {
            $script = '/api/index.php';
        }
        return $scheme . '://' . $host . $script . '?route=' . rawurlencode($route);
    }

    public function getConfig(): JsonResponse
    {
        if (!$this->hasPermission('module.raycast.view')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $mcpRoute = 'api/v1/mcp';
        $mcpUrl = $this->mcpUrl($mcpRoute);

        return JsonResponse::success('RAYCAST_CONFIG', 'OK', [
            'server_name' => 'TropaTT',
            'mcp_route' => $mcpRoute,
            'mcp_url' => $mcpUrl,
            'auth_required' => true,
            'status' => 'available',
            'instructions' => [
                '1. Откройте Raycast → Settings → Extensions.',
                '2. Найдите «MCP» и добавьте новый сервер.',
                '3. Укажите URL эндпоинта и Bearer-токен пользователя TropaTT (Настройки → API-клиенты).',
                '4. Сохраните и выберите сервер TropaTT в MCP-командах.',
            ],
        ]);
    }
}
