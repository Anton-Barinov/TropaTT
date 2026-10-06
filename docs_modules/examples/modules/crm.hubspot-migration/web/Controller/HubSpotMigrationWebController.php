<?php

declare(strict_types=1);

namespace Module\Crm\HubSpotMigration\Web;

class HubSpotMigrationWebController
{
    public function index(): string
    {
        return '<div class="crm-hubspot-migration-wrap"><h2>HubSpot Migration Console</h2><p>Safe data transition pipeline</p></div>';
    }
}
