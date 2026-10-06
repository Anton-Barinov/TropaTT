<?php

declare(strict_types=1);

namespace Module\Crm\YandexDisk\Web;

class YandexDiskWebController
{
    public function index(): string
    {
        return '<div class="crm-yandex-disk-wrap"><h2>Яндекс Диск Хранилище</h2><p>Облачная синхронизация файлов и резервных копий</p></div>';
    }
}
