<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Builder;

final class Expression
{
    public function __construct(private readonly string $sql)
    {
    }

    public function toSql(): string
    {
        return $this->sql;
    }
}
