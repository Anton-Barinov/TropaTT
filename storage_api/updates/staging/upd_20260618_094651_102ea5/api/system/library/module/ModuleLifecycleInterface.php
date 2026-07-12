<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use Api\System\Library\Container;

interface ModuleLifecycleInterface
{
    public function onInstall(Container $container): void;

    public function onActivate(Container $container): void;

    public function onDeactivate(Container $container): void;

    public function onUninstall(Container $container): void;

    public function onUpdate(Container $container, string $fromVersion, string $toVersion): void;
}
