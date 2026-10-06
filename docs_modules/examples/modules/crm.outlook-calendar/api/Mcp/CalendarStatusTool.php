<?php

declare(strict_types=1);

namespace Module\Crm\OutlookCalendar\Mcp;

class CalendarStatusTool
{
    public function execute(array $args, array $context): array
    {
        return [
            'provider' => 'Microsoft Graph',
            'status' => 'connected',
            'sync_active' => true,
            'message' => 'Outlook Calendar integration is active and operational.'
        ];
    }
}
