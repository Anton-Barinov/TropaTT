<?php

namespace Tropatt\Crm\Model\Registry;

/**
 * Echo guard: while the CRM webhook writes a status, order saves must not be
 * published back to the CRM.
 */
class EchoGuard
{
    /** @var bool */
    private $suppressed = false;

    /**
     * @return bool
     */
    public function isSuppressed()
    {
        return $this->suppressed;
    }

    /**
     * @return void
     */
    public function suppress($flag = true)
    {
        $this->suppressed = (bool)$flag;
    }
}
