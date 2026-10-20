<?php
declare(strict_types=1);

namespace Module\Crm\WipLimit\Controller;

use Web\System\Core\Controller;

final class WipPageController extends Controller
{
    public function index(): void
    {
        $sessionCookieName = trim((string)(getenv('CRM_API_SESSION_COOKIE') ?: 'crm_api_session'));
        $sessionToken = trim((string)($_COOKIE[$sessionCookieName] ?? ''));
        $canView = function_exists('crmWebApiCheckAnyPermission')
            && crmWebApiCheckAnyPermission($sessionToken, ['module.wip-limit.view', 'module.wip-limit.manage'], $this->baseDir);
        if (!$canView) {
            http_response_code(403);
            header('Content-Type: text/html; charset=utf-8');
            echo \Web\System\I18n\EarlyResponse::forbiddenPage($this->baseDir);
            return;
        }

        $canManage = crmWebApiCheckAnyPermission($sessionToken, ['module.wip-limit.manage'], $this->baseDir);

        $this->render(__DIR__ . '/../template/page/module_wip_limit.php', [
            'title' => 'WIP-лимиты',
            'route' => 'module-wip-limit',
            'can_manage' => $canManage,
        ]);
    }
}
