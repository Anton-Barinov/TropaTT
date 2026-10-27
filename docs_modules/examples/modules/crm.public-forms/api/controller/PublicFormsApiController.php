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
    private function getService(bool $withIntakeService = false): PublicFormsService
    {
        $creator = null;
        $calendarCreator = null;
        $calendarAfterCommit = null;
        if ($withIntakeService) {
            $intake = $this->container->get('service.intake_item');
            $creator = static fn(array $input, array $actor): array|string => $intake->create($input, $actor);
            $calendar = $this->container->get('service.calendar');
            $calendarCreator = static fn(array $input, array $actor, array $source): array|string => $calendar->createEvent($input, $actor, $source);
            $calendarAfterCommit = static function (array $event, array $actor) use ($calendar): void {
                $calendar->dispatchCreatedAfterCommit($event, $actor);
            };
        }
        return new PublicFormsService($this->container->get('db.pdo'), $creator, $calendarCreator, $calendarAfterCommit);
    }

    /** @return array{0:int,1:array<string,mixed>}|JsonResponse */
    private function authorizeManager(string $permission): array|JsonResponse
    {
        $auth = $this->user();
        if (!$auth || !is_array($auth['user'] ?? null)) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }
        $contextError = $this->rejectInvalidOrganizationContext();
        if ($contextError !== null) {
            return $contextError;
        }
        $user = (array)$auth['user'];
        if (!empty($user['is_external']) && empty($user['is_root'])) {
            return $this->error('FORBIDDEN', 'External client accounts cannot manage public forms.', 403);
        }
        $organizationId = (int)($user['organization_id'] ?? 0);
        if ($organizationId < 1) {
            return $this->error('WORKSPACE_REQUIRED', 'Select an active workspace before managing forms.', 409);
        }
        if (empty($user['is_root']) && (!$this->container->has('service.authz')
            || !$this->container->get('service.authz')->hasPermissions($user, [$permission]))) {
            return $this->error('FORBIDDEN', 'Insufficient permissions to manage public forms.', 403);
        }
        return [$organizationId, $user];
    }

    public function adminListForms(): JsonResponse
    {
        $access = $this->authorizeManager('settings.view');
        if ($access instanceof JsonResponse) return $access;
        return $this->success('PUBLIC_FORMS_LIST', 'Forms loaded.', ['items' => $this->getService()->listForms($access[0])]);
    }

    public function adminGetForm(array $params): JsonResponse
    {
        $access = $this->authorizeManager('settings.view');
        if ($access instanceof JsonResponse) return $access;
        $form = $this->getService()->getFormForWorkspace($access[0], (string)($params['public_id'] ?? ''));
        if ($form === null) return $this->error('NOT_FOUND', 'Form not found in this workspace.', 404);
        return $this->success('PUBLIC_FORM_DETAIL', 'Form loaded.', ['form' => $form]);
    }

    public function adminSaveForm(array $params = []): JsonResponse
    {
        $access = $this->authorizeManager('settings.edit');
        if ($access instanceof JsonResponse) return $access;
        $input = $this->adminJsonInput();
        if ($input instanceof JsonResponse) return $input;
        $title = $input['title'] ?? null;
        $slug = $input['slug'] ?? null;
        $consent = $input['consent_text'] ?? null;
        $description = $input['description'] ?? '';
        $fields = $input['fields_schema'] ?? null;
        if (!is_string($title) || !is_string($slug) || !is_string($consent) || !is_string($description) || strlen($description) > 8000 || !is_array($fields) || !array_is_list($fields)) {
            return $this->error('VALIDATION_ERROR', 'Title, slug, consent text, and a field list are required.', 422);
        }
        $formType = $input['form_type'] ?? 'intake';
        $targetProject = $input['target_project_public_id'] ?? null;
        $targetAssignee = $input['target_assignee_user_public_id'] ?? null;
        $calendarUser = $input['calendar_user_public_id'] ?? null;
        $slotDuration = $input['slot_duration_minutes'] ?? 30;
        $bufferMinutes = $input['buffer_minutes'] ?? 15;
        $bookingTimezone = $input['booking_timezone'] ?? 'UTC';
        $bookingSchedule = $input['booking_schedule'] ?? [];
        if (!is_string($formType) || ($targetProject !== null && !is_string($targetProject))
            || ($targetAssignee !== null && !is_string($targetAssignee)) || ($calendarUser !== null && !is_string($calendarUser))
            || !is_int($slotDuration) || !is_int($bufferMinutes) || !is_string($bookingTimezone)
            || !is_array($bookingSchedule) || ($formType === 'booking' && ($bookingSchedule === [] || array_is_list($bookingSchedule)))) {
            return $this->error('VALIDATION_ERROR', 'form_type must be a string.', 422);
        }
        $service = $this->getService();
        try {
            $publicId = (string)($params['public_id'] ?? '');
            if ($publicId !== '') {
                $saved = $service->updateForm($access[0], $publicId, trim($slug), trim($title), $formType, $fields, trim($consent), $targetProject, $targetAssignee, $calendarUser, $slotDuration, $bufferMinutes, $bookingTimezone, $bookingSchedule, $description);
                if (!$saved) return $this->error('NOT_FOUND', 'Form not found in this workspace.', 404);
                return $this->success('PUBLIC_FORM_UPDATED', 'Form updated.', ['public_id' => $publicId]);
            }
            $publicId = $service->saveForm($access[0], trim($slug), trim($title), $formType, $fields, trim($consent), $targetProject, $targetAssignee, $calendarUser, $slotDuration, $bufferMinutes, $bookingTimezone, $bookingSchedule, $description);
            return $this->success('PUBLIC_FORM_CREATED', 'Draft form created.', ['public_id' => $publicId], 201);
        } catch (\InvalidArgumentException) {
            return $this->error('VALIDATION_ERROR', 'The form settings or one of its fields is invalid.', 422);
        } catch (\PDOException) {
            return $this->error('FORM_CONFLICT', 'This form URL is already used. Choose a different URL slug.', 409);
        } catch (Throwable) {
            return $this->error('FORM_SAVE_FAILED', 'The form could not be saved.', 500);
        }
    }

    public function adminSetPublished(array $params): JsonResponse
    {
        $access = $this->authorizeManager('settings.edit');
        if ($access instanceof JsonResponse) return $access;
        $input = $this->adminJsonInput();
        if ($input instanceof JsonResponse) return $input;
        if (!is_bool($input['is_published'] ?? null)) {
            return $this->error('VALIDATION_ERROR', 'is_published must be true or false.', 422);
        }
        $saved = $this->getService()->setFormPublished($access[0], (string)($params['public_id'] ?? ''), $input['is_published']);
        if (!$saved) return $this->error('NOT_FOUND', 'Form not found in this workspace.', 404);
        return $this->success('PUBLIC_FORM_PUBLICATION_UPDATED', $input['is_published'] ? 'Form published.' : 'Form unpublished.', ['is_published' => $input['is_published']]);
    }

    public function adminListCalendarUsers(): JsonResponse
    {
        $access = $this->authorizeManager('settings.view');
        if ($access instanceof JsonResponse) return $access;
        return $this->success('PUBLIC_FORM_CALENDAR_USERS', 'Active workspace calendar users loaded.', [
            'items' => $this->getService()->listCalendarUsers($access[0]),
        ]);
    }

    public function adminListRoutingOptions(): JsonResponse
    {
        $access = $this->authorizeManager('settings.view');
        if ($access instanceof JsonResponse) return $access;
        return $this->success('PUBLIC_FORM_ROUTING_OPTIONS', 'Workspace routing options loaded.', $this->getService()->listRoutingOptions($access[0]));
    }

    public function adminListSubmissions(): JsonResponse
    {
        $access = $this->authorizeManager('client.manage');
        if ($access instanceof JsonResponse) return $access;
        $rawLimit = $this->request()->query['limit'] ?? 50;
        if (!is_string($rawLimit) && !is_int($rawLimit)) {
            return $this->error('VALIDATION_ERROR', 'limit must be a single integer.', 422);
        }
        $limitValue = filter_var($rawLimit, FILTER_VALIDATE_INT);
        if ($limitValue === false) {
            return $this->error('VALIDATION_ERROR', 'limit must be a single integer.', 422);
        }
        $limit = max(1, min(100, $limitValue));
        return $this->success('PUBLIC_FORM_SUBMISSIONS', 'Submissions loaded.', ['items' => $this->getService()->listSubmissions($access[0], $limit)]);
    }

    /** @return array<string,mixed>|JsonResponse */
    private function adminJsonInput(): array|JsonResponse
    {
        $request = $this->request();
        if ($request->bodyTooLarge || strlen($request->rawBody) > 16384) {
            return $this->error('PAYLOAD_TOO_LARGE', 'Request exceeds the 16 KB limit.', 413);
        }
        $contentType = strtolower(trim(explode(';', (string)($request->header('Content-Type') ?? ''))[0]));
        if ($contentType !== 'application/json') {
            return $this->error('UNSUPPORTED_MEDIA_TYPE', 'Requests must use application/json.', 415);
        }
        if (!is_array($request->jsonBody) || array_is_list($request->jsonBody)) {
            return $this->error('INVALID_REQUEST_BODY', 'Request body must be a JSON object.', 400);
        }
        return $request->jsonBody;
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

    public function getBookingSlots(array $params): JsonResponse
    {
        $slug = (string)($params['slug'] ?? '');
        $from = $this->request()->query['from'] ?? '';
        $to = $this->request()->query['to'] ?? '';
        if (!is_string($from) || !is_string($to)) return $this->error('BOOKING_RANGE_INVALID', 'Choose a valid date range.', 422);
        try {
            $items = $this->getService()->getBookingSlots($slug, $from, $to, $this->request()->ip());
            return $this->success('PUBLIC_FORM_BOOKING_SLOTS', 'Available appointment times loaded.', ['items' => $items]);
        } catch (PublicFormSubmissionException $e) {
            return $this->error($e->errorCode, $e->getMessage(), $e->httpStatus);
        } catch (Throwable) {
            return $this->error('BOOKING_SLOTS_UNAVAILABLE', 'Available appointment times could not be loaded.', 503);
        }
    }

    public function submitForm(array $params): JsonResponse
    {
        $slug = (string)($params['slug'] ?? '');
        $service = $this->getService(true);
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
                'intake_public_id' => $result['intake_public_id'] ?? null,
                'duplicate' => (bool)($result['duplicate'] ?? false),
            ]);
        } catch (PublicFormSubmissionException $e) {
            return $this->error($e->errorCode, $e->getMessage(), $e->httpStatus);
        } catch (Throwable $e) {
            return $this->error('SUBMISSION_ERROR', 'Submission could not be processed.', 500);
        }
    }
}
