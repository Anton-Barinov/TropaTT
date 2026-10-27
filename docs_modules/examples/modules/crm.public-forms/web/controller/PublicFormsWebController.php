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

    public function publicForm(): void
    {
        $slug = trim((string)($_GET['slug'] ?? ''));
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{1,62}[a-z0-9])?$/D', $slug)) {
            http_response_code(404);
            $this->render(__DIR__ . '/../template/page/public_form_view.php', [
                'title' => 'Форма не найдена',
                'route' => 'public-form',
                'form_slug' => '',
            ], 404);
            return;
        }
        $this->render(__DIR__ . '/../template/page/public_form_view.php', [
            'title' => 'Публичная форма',
            'route' => 'public-form',
            'form_slug' => $slug,
        ]);
    }
}
