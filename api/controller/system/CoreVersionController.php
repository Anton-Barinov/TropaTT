<?php
declare(strict_types=1);

namespace Api\Controller\System;

use Api\Controller\Common\BaseController;
use Api\System\Library\Update\CoreUpdateConfig;
use Api\System\Library\Update\CoreVersion;

final class CoreVersionController extends BaseController
{
    public function show(): \Api\System\Library\Http\JsonResponse
    {
        $config = CoreUpdateConfig::load();
        $version = new CoreVersion((string)$config['storage_dir'], dirname(__DIR__, 3));
        return $this->success('CORE_VERSION', 'Core version', $version->current());
    }
}
