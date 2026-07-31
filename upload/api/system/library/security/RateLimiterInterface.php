<?php
declare(strict_types=1);

namespace Api\System\Library\Security;

interface RateLimiterInterface
{
    /** @return array{blocked:bool,retry_after:int} */
    public function check(string $key): array;

    /** @return array{blocked:bool,retry_after:int} */
    public function hit(string $key): array;

    public function clear(string $key): void;
}
