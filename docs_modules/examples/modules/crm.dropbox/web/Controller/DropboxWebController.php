<?php

declare(strict_types=1);

namespace Module\Crm\Dropbox\Web;

class DropboxWebController
{
    public function index(): string
    {
        return '<div class="crm-dropbox-wrap"><h2>Dropbox Хранилище</h2><p>Интеграция корпоративного диска и проектов</p></div>';
    }
}
