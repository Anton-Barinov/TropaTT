<?php
declare(strict_types=1);

namespace Api\System\Library\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\Request;

abstract class BaseController
{
    protected readonly Container $container;
    protected readonly Request $request;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->request = $container->get('request');
    }
}
