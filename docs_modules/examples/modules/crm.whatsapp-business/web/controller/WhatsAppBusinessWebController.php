<?php
declare(strict_types=1);

namespace Module\Crm\WhatsappBusiness\Web\Controller;

use Web\System\Core\Controller;

final class WhatsAppBusinessWebController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/crm_whatsapp_business.php', [
            'title' => 'WhatsApp Business',
            'route' => 'module-whatsapp-business',
        ]);
    }
}
