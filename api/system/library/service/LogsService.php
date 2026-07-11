<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Logs\LogsRepository;

final class LogsService
{
    public function __construct(private readonly LogsRepository $logs)
    {
    }

    public function requestList(array $filters): array
    {
        [$items, $total, $page, $limit] = $this->logs->listRequests($filters);
        return [
            'items' => array_map(fn(array $item): array => $this->maskIpInItem($item), $items),
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
            ],
        ];
    }

    public function securityList(array $filters): array
    {
        [$items, $total, $page, $limit] = $this->logs->listSecurity($filters);
        return [
            'items' => array_map(fn(array $item): array => $this->maskIpInItem($item), $items),
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
            ],
        ];
    }

    public function auditList(array $filters): array
    {
        [$items, $total, $page, $limit] = $this->logs->listAudit($filters);
        return [
            'items' => array_map(fn(array $item): array => $this->maskIpInItem($item), $items),
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
            ],
        ];
    }

    /**
     * Mask the last octet of an IP address in log items.
     */
    private function maskIpInItem(array $item): array
    {
        foreach (['ip', 'client_ip', 'remote_addr'] as $key) {
            if (isset($item[$key]) && is_string($item[$key]) && $item[$key] !== '') {
                $item[$key] = $this->maskIp((string)$item[$key]);
            }
        }
        return $item;
    }

    private function maskIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                $parts[3] = 'x';
                return implode('.', $parts);
            }
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            $count = count($parts);
            if ($count >= 2) {
                $parts[$count - 1] = 'xxxx';
                return implode(':', $parts);
            }
        }
        return $ip;
    }
}
