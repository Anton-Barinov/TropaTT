<?php
declare(strict_types=1);

namespace Module\Crm\PublicForms\Web\Controller;

use Api\System\Library\Database\DatabaseConnectionPool;
use Module\Crm\PublicForms\Service\PublicFormsService;

final class PublicFormsWebController
{
    public function render(array $params): void
    {
        $slug = (string)($params['slug'] ?? '');
        $db = DatabaseConnectionPool::getConnection();
        $service = new PublicFormsService($db);
        $form = $service->getPublishedFormBySlug($slug);

        if (!$form) {
            http_response_code(404);
            echo "Форма не найдена или снята с публикации.";
            return;
        }

        include __DIR__ . '/../template/page/public_form_view.php';
    }
}
