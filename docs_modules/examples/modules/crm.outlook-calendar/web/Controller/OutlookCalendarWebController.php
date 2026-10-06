<?php

declare(strict_types=1);

namespace Module\Crm\OutlookCalendar\Web;

class OutlookCalendarWebController
{
    public function index(): string
    {
        return '<div class="crm-outlook-calendar-wrap"><h2>Microsoft Outlook Calendar</h2><p>Two-way sync console</p></div>';
    }
}
