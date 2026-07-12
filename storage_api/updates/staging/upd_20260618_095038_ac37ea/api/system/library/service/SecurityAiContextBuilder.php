<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class SecurityAiContextBuilder
{
    public function __construct(
        private readonly LogsService $logs,
        private readonly AiMaskingService $masking
    ) {
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function buildSecurityReviewContext(array $input): array
    {
        $limit = max(1, min(200, (int)($input['limit'] ?? 50)));
        $logs = $this->logs->securityList(['limit' => $limit]);
        $items = is_array($logs['items'] ?? null) ? (array)$logs['items'] : [];

        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $result[] = [
                'created_at' => (string)($item['created_at'] ?? ''),
                'event_type' => trim((string)($item['event_type'] ?? '')),
                'details' => $this->masking->maskSensitiveText((string)json_encode($item['details'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ];
        }

        return [
            'security_logs' => $result,
            'meta' => (array)($logs['meta'] ?? []),
        ];
    }
}
