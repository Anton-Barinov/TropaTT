<?php
declare(strict_types=1);

namespace Api\System\Library\Language;

trait TranslatableTrait
{
    private LanguageManager $lang;

    protected function t(string $key, string $default = ''): string
    {
        return $this->lang->get($key, $default !== '' ? $default : $key);
    }
}
