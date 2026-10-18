<?php
declare(strict_types=1);

namespace Module\Crm\PublicForms\Api\Controller;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\PublicForms\Service\PublicFormsService;
use Module\Crm\PublicForms\Service\PublicFormSubmissionException;
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
        $request = $this->request();
        $contentLength = (int)($request->server['CONTENT_LENGTH'] ?? $request->server['HTTP_CONTENT_LENGTH'] ?? 0);
        if ($contentLength > 32768 || strlen($request->rawBody) > 32768) {
            return $this->error('PAYLOAD_TOO_LARGE', 'Submission exceeds the 32 KB limit.', 413);
        }
        $contentType = strtolower(trim(explode(';', (string)($request->header('Content-Type') ?? ''))[0]));
        if ($contentType !== 'application/json') {
            return $this->error('UNSUPPORTED_MEDIA_TYPE', 'Submissions must use application/json.', 415);
        }
        $data = $this->request()->allInput();
        $headerKey = trim((string)($request->header('Idempotency-Key') ?? ''));
        if ($headerKey !== '') {
            $data['_idempotency_key'] = $headerKey;
        }
        $ip = $request->ip();

        try {
            $result = $service->submit($slug, $data, $ip);
            return $this->success('PUBLIC_FORM_SUBMITTED', $result['message'], [
                'submission_id' => $result['submission_id'],
                'duplicate' => (bool)($result['duplicate'] ?? false),
            ]);
        } catch (PublicFormSubmissionException $e) {
            return $this->error($e->errorCode, $e->getMessage(), $e->httpStatus);
        } catch (Throwable $e) {
            return $this->error('SUBMISSION_ERROR', 'Submission could not be processed.', 500);
        }
    }
}
