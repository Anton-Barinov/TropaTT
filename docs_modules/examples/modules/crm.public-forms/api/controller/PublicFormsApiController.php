<?php
declare(strict_types=1);

namespace Module\Crm\PublicForms\Api\Controller;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\PublicForms\Service\PublicFormsService;
use Throwable;

final class PublicFormsApiController extends BaseController
{
    private function getService(): PublicFormsService
    {
        return new PublicFormsService($this->container->get('db.pdo'));
    }

    public function getForm(array $params): JsonResponse
    {
        $slug = (string)($params['slug'] ?? '');
        $service = $this->getService();
        $form = $service->getPublishedFormBySlug($slug);

        if (!$form) {
            return $this->error('NOT_FOUND', 'Form not found or unpublished', 404);
        }

        return $this->success('PUBLIC_FORM_DATA', 'Form retrieved', ['form' => $form]);
    }

    public function submitForm(array $params): JsonResponse
    {
        $slug = (string)($params['slug'] ?? '');
        $service = $this->getService();
        $data = $this->request()->allInput();
        $ip = (string)($this->request()->server('REMOTE_ADDR') ?? '127.0.0.1');

        try {
            $result = $service->submit($slug, $data, $ip);
            return $this->success('PUBLIC_FORM_SUBMITTED', $result['message'], [
                'submission_id' => $result['submission_id'],
            ]);
        } catch (Throwable $e) {
            return $this->error('SUBMISSION_ERROR', $e->getMessage(), 422);
        }
    }
}
