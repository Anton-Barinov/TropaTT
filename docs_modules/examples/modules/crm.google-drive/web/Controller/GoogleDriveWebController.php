<?php

declare(strict_types=1);

namespace Module\Crm\GoogleDrive\Web;

class GoogleDriveWebController
{
    public function index(): string
    {
        return '<div class="crm-google-drive-wrap"><h2>Google Drive Workspace</h2><p>Интеграция облачного хранилища документов, таблиц и файлов проектов</p></div>';
    }
}
