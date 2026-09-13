<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Controller;

use Web\System\Core\Controller;

final class EcommerceGatewayPageController extends Controller
{
    public function index(): void
    {
        $i18n = \Web\System\I18n\I18n::fromRequest($this->baseDir);
        $i18n->loadModuleTranslations('crm', 'ecommerce-gateway');

        $this->render(__DIR__ . '/../template/page/ecommerce_gateway.php', [
            'title' => $i18n->t('ecommerce_gateway.title', 'Шлюз интернет-магазинов'),
            'route' => 'module-ecommerce-gateway',
        ]);
    }
}
