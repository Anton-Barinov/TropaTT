<?php
declare(strict_types=1);

namespace Api\Controller\System;

use Api\Controller\Common\BaseController;
use Api\System\Library\Support\Documentation;
use Api\System\Library\Update\CoreUpdateConfig;
use Api\System\Library\Update\CoreVersion;

final class CoreVersionController extends BaseController
{
    public function show(): \Api\System\Library\Http\JsonResponse
    {
        $config = CoreUpdateConfig::load();
        $version = new CoreVersion((string)$config['storage_dir'], dirname(__DIR__, 3));
        $data = $version->current();
        
        // SEC-019: Remove internal build identifiers from public endpoint.
        // Admin users with settings.manage can still see full version data via /api/v1/core/version.
        $authUser = $this->user();
        $isAdmin = $authUser && !empty($authUser['user']['is_root']);
        if (!$isAdmin) {
            unset($data['source_sha'], $data['short_sha'], $data['adopted']);
        }

        // Public discovery aid for API/MCP clients (including AI agents): where
        // the API, MCP and Modules documentation lives. The version endpoint is
        // unauthenticated, so a fresh client can find the docs before it holds
        // any credentials.
        $data['documentation'] = Documentation::links();

        return $this->success('CORE_VERSION', $this->t('system/messages.core_version'), $data);
    }
}
