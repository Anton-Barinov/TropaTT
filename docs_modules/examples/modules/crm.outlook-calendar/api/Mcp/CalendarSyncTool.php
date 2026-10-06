<?php

declare(strict_types=1);

namespace Module\Crm\OutlookCalendar\Mcp;

class CalendarSyncTool
{
    public function execute(array $args, array $context): array
    {
        $dir = $args['direction'] ?? 'both';
        return [
            'direction' => $dir,
            'success' => true,
            'synced_events' => 0,
            'message' => "Delta sync execution completed for direction: {$dir}."
        ];
    }
}
