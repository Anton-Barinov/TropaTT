<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class OpsService
{
    public function __construct(
        private readonly AdminWidgetService $adminWidget,
        private readonly WebhookService $webhooks
    ) {
    }

    public function system(): array
    {
        $widgets = $this->adminWidget->system();

        return [
            'system' => $widgets,
            'webhooks' => $this->webhooks->summary(),
            'generated_at' => gmdate('c'),
        ];
    }

    public function metrics(): array
    {
        $summary = $this->adminWidget->summary();

        return [
            'metrics' => [
                'counts' => (array)($summary['counts'] ?? []),
                'logs_24h' => (array)($summary['logs'] ?? []),
                'api_24h' => (array)($summary['api_metrics'] ?? []),
                'ai_24h' => (array)($summary['ai_metrics'] ?? []),
                'queues' => (array)($summary['queues'] ?? []),
                'migrations' => (array)($summary['migrations'] ?? []),
            ],
            'webhooks' => $this->webhooks->summary(),
            'generated_at' => gmdate('c'),
        ];
    }
}
