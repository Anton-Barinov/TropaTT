<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminCustomFieldsController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin_custom_fields', [
            'title' => 'Админ: Кастомные поля',
            'route' => 'admin-custom-fields',
        ]);
    }
}
