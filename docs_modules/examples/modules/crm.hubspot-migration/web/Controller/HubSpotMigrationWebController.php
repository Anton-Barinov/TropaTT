<?php
declare(strict_types=1);

namespace Module\Crm\HubspotMigration\Web\Controller;

use Web\System\Core\Controller;

final class HubSpotMigrationWebController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/crm_hubspot_migration.php', [
            'title' => 'Миграция из HubSpot',
            'route' => 'module-hubspot-migration',
        ]);
    }
}
