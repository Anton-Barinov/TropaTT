<?php
declare(strict_types=1);

namespace Module\Crm\PublicForms\Web\Controller;

use Web\System\Core\Controller;

final class PublicFormsWebController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/crm_public_forms.php', [
            'title' => 'Публичные веб-формы',
            'route' => 'module-public-forms',
        ]);
    }
}
