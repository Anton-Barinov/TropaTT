<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

use Api\System\Library\Container;
use Api\System\Library\Module\ModuleJobDispatcher;
use Module\Crm\EcommerceGateway\Job\EcommerceWebhookJob;
use Module\Crm\EcommerceGateway\Repository\OutboxRepository;
use Module\Crm\EcommerceGateway\Repository\StoreRepository;

/**
 * Two-way reactive status synchronization service (E-COM-04).
 *
 * Coordinates state machine validation, echo loop prevention, status mapping,
 * and outbox event creation when CRM tasks change status.
 */
final class StatusSyncService
{
    public function __construct(
        private readonly OutboxRepository $outboxRepo,
        private readonly StatusMappingService $statusMappingService,
        private readonly StoreRepository $storeRepo,
        private readonly ?ModuleJobDispatcher $jobDispatcher = null,
        private readonly array $config = []
    ) {
    }

    /**
     * Handles task.status_changed hook payload from ModuleHookDispatcher.
     *
     * @param array{
     *     task_id?: int,
     *     task_public_id?: string,
     *     old_status?: string,
     *     new_status?: string,
     *     actor_id?: int
     * } $payload
     * @return array<string,mixed>|null Created outbox event or null if skipped/not applicable
     */
    public function handleTaskStatusChanged(array $payload): ?array
    {
        // 1. Echo Loop Prevention (E-COM-04 §2)
        if (StatusSyncContext::isCmsInitiated()) {
            return null;
        }

        $taskId = (int)($payload['task_id'] ?? 0);
        $taskPublicId = (string)($payload['task_public_id'] ?? '');
        $oldStatus = (string)($payload['old_status'] ?? '');
        $newStatus = (string)($payload['new_status'] ?? '');

        if ($newStatus === '' || $oldStatus === $newStatus) {
            return null;
        }

        // 2. Resolve store and external order link
        $link = $this->outboxRepo->findStoreForTask($taskId, $taskPublicId);
        if ($link === null) {
            return null;
        }

        $storeId = (int)$link['store_id'];
        $externalOrderId = (string)$link['external_order_id'];

        $store = $this->outboxRepo->getStore($storeId);
        if ($store === null || empty($store['webhook_url']) || ($store['status'] ?? '') === 'disabled') {
            return null;
        }

        // 3. State Machine Validation (E-COM-04 §1)
        if ($oldStatus !== '') {
            $validation = OrderStatusStateMachine::validateTransition($oldStatus, $newStatus);
            if (!$validation['allowed']) {
                $this->storeRepo->logSecurityEvent(
                    'order.invalid_status_transition',
                    'warning',
                    $storeId,
                    null,
                    null,
                    [
                        'task_id' => $taskId,
                        'task_public_id' => $taskPublicId,
                        'external_order_id' => $externalOrderId,
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                        'reason' => $validation['reason'],
                    ]
                );
                return null;
            }
        }

        // 4. Map CRM status to external CMS status
        $externalStatus = $this->statusMappingService->mapCrmToExternal($storeId, $newStatus, 'order');

        // 5. Build canonical webhook payload
        $timestamp = time();
        $webhookPayload = [
            'event' => 'order.status_changed',
            'store_key' => (string)$store['public_id'],
            'external_order_id' => $externalOrderId,
            'crm_task_public_id' => $taskPublicId !== '' ? $taskPublicId : null,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'external_status' => $externalStatus,
            'sync_initiator' => 'crm',
            'timestamp' => $timestamp,
        ];

        // 6. Record event in Transactional Outbox (E-COM-04 §3)
        $maxAttempts = (int)($this->config['outbox_max_attempts'] ?? 8);
        $event = $this->outboxRepo->createEvent([
            'store_id' => $storeId,
            'event_type' => 'order.status_changed',
            'external_order_id' => $externalOrderId,
            'crm_task_id' => $taskId > 0 ? $taskId : null,
            'crm_task_public_id' => $taskPublicId !== '' ? $taskPublicId : null,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'external_status' => $externalStatus,
            'payload' => $webhookPayload,
            'sync_initiator' => 'crm',
            'max_attempts' => $maxAttempts,
        ]);

        // 7. Dispatch background job if dispatcher is available
        if ($this->jobDispatcher !== null) {
            try {
                $this->jobDispatcher->dispatch(
                    'crm.ecommerce-gateway',
                    EcommerceWebhookJob::class,
                    ['outbox_id' => (int)$event['id']]
                );
            } catch (\Throwable $e) {
                error_log('[StatusSyncService] Failed to dispatch background job: ' . $e->getMessage());
            }
        }

        return $event;
    }
}
