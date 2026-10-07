<?php
declare(strict_types=1);

namespace Module\Crm\CorporateMail\Web\Controller;

use Web\System\Core\Controller;

final class CorporateMailWebController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/crm_corporate_mail.php', [
            'title' => 'Корпоративная почта',
            'route' => 'module-corporate-mail',
        ]);
    }
}
