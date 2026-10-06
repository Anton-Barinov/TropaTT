<?php

declare(strict_types=1);

namespace Module\Crm\Zoom\Web;

class ZoomWebController
{
    public function index(): string
    {
        return '<div class="crm-zoom-wrap"><h2>Zoom Видеоконференции</h2><p>Планирование онлайн-встреч, интеграция с календарем CRM и архивирование записей созвонов</p></div>';
    }
}
