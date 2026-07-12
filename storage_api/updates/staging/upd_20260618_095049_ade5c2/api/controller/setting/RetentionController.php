<?php
declare(strict_types=1);

namespace Api\Controller\Setting;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\RetentionService;

final class RetentionController extends BaseController
{
    public function getMetadata(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var RetentionService $service */
        $service = $this->container->get('service.retention');

        return $this->success('RETENTION_METADATA', $this->t('retention/messages.metadata'), [
            'retention' => $service->getMetadata(),
        ]);
    }

    public function setMetadata(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $errors = $this->validate($input);
        if ($errors !== []) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $errors);
        }

        /** @var RetentionService $service */
        $service = $this->container->get('service.retention');
        $retention = $service->upsertMetadata($input);

        return $this->success('RETENTION_METADATA_UPDATED', $this->t('retention/messages.updated'), [
            'retention' => $retention,
        ]);
    }

    /** @param array<string,mixed> $input */
    private function validate(array $input): array
    {
        $errors = [];
        $intFields = [
            'request_logs_days',
            'security_logs_days',
            'audit_logs_days',
            'recycle_bin_days',
            'orphan_files_days',
            'backup_metadata_days',
        ];

        foreach ($intFields as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            if (filter_var($input[$field], FILTER_VALIDATE_INT) === false || (int)$input[$field] < 1) {
                $errors[$field][] = $this->t('retention/messages.int_gt_zero');
            }
        }

        if (array_key_exists('enabled', $input) && !is_bool($input['enabled'])) {
            $errors['enabled'][] = $this->t('retention/messages.enabled_boolean');
        }

        return $errors;
    }
}
