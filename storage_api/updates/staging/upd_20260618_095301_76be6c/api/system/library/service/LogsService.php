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
            'items' => $items,
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
            'items' => $items,
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
            'items' => $items,
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
}
