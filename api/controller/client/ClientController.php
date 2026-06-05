<?php
declare(strict_types=1);

namespace Api\Controller\Client;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\ClientService;
use Api\System\Library\Validation\Validator;
use Throwable;

final class ClientController extends BaseController
{
    private const CLIENT_TYPES = ['individual', 'sole_proprietor', 'legal_entity'];

    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ClientService $service */
        $service = $this->container->get('service.client');
        $result = $service->list($this->request()->allInput(), $authUser['user']);

        return $this->success('CLIENT_LIST', $this->t('client/messages.list'), ['items' => $result['items']], meta: $result['meta']);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ClientService $service */
        $service = $this->container->get('service.client');
        $item = $service->get((string)$params['public_id'], $authUser['user']);
        if (!$item) {
            return $this->error('CLIENT_NOT_FOUND', $this->t('client/messages.not_found'), 404, [
                'client' => [$this->t('client/messages.not_found')],
            ]);
        }

        return $this->success('CLIENT_DETAIL', $this->t('client/messages.detail'), ['client' => $item]);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        if (empty($input['status'])) $input['status'] = 'active';
        $errors = $this->validateClientPayload($input, true);
        if ($errors !== []) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $errors);
        }

        /** @var ClientService $service */
        $service = $this->container->get('service.client');
        try {
            $item = $service->create($input, $authUser['user']);
        } catch (Throwable $e) {
            throw $e;
        }

        return $this->success('CLIENT_CREATED', $this->t('client/messages.created'), ['client' => $item], 201);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ClientService $service */
        $service = $this->container->get('service.client');
        $current = $service->get((string)$params['public_id'], $authUser['user']);
        if (!$current) {
            return $this->error('CLIENT_NOT_FOUND', $this->t('client/messages.not_found'), 404, [
                'client' => [$this->t('client/messages.not_found')],
            ]);
        }

        $input = $this->request()->allInput();
        $errors = $this->validateClientPayload($input, false, $current);
        if ($errors !== []) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $errors);
        }

        try {
            $item = $service->update((string)$params['public_id'], $input, $authUser['user']);
        } catch (Throwable $e) {
            throw $e;
        }
        if (!$item) {
            return $this->error('CLIENT_NOT_FOUND', $this->t('client/messages.not_found'), 404, [
                'client' => [$this->t('client/messages.not_found')],
            ]);
        }

        return $this->success('CLIENT_UPDATED', $this->t('client/messages.updated'), ['client' => $item]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ClientService $service */
        $service = $this->container->get('service.client');
        $ok = $service->delete((string)$params['public_id'], $authUser['user']);
        if (!$ok) {
            return $this->error('CLIENT_NOT_FOUND', $this->t('client/messages.not_found'), 404, [
                'client' => [$this->t('client/messages.not_found')],
            ]);
        }

        return $this->success('CLIENT_DELETED', $this->t('client/messages.deleted'));
    }

    /** @return array<string,array<int,string>> */
    private function validateClientPayload(array $input, bool $isCreate, ?array $current = null): array
    {
        $v = new Validator();
        if ($isCreate) {
            $v->require($input, 'title', $this->t('common/messages.field_required'));
        }

        if (array_key_exists('title', $input) && trim((string)$input['title']) === '') {
            $v->require(['title' => ''], 'title', $this->t('common/messages.field_required'));
        }

        $v->maxLen($input, 'title', 255, $this->t('client/messages.max_255'))
            ->maxLen($input, 'legal_name', 255, $this->t('client/messages.max_255'))
            ->maxLen($input, 'person_last_name', 120, $this->t('client/messages.max_120'))
            ->maxLen($input, 'person_first_name', 120, $this->t('client/messages.max_120'))
            ->maxLen($input, 'person_middle_name', 120, $this->t('client/messages.max_120'))
            ->maxLen($input, 'email', 190, $this->t('client/messages.max_190'))
            ->maxLen($input, 'phone', 64, $this->t('client/messages.max_64'))
            ->maxLen($input, 'status', 64, $this->t('client/messages.max_64'))
            ->maxLen($input, 'messenger', 190, $this->t('client/messages.max_190'))
            ->maxLen($input, 'website', 2048, $this->t('client/messages.max_2048'))
            ->date($input, 'person_birth_date', $this->t('client/messages.invalid_date'))
            ->enum($input, 'client_type', self::CLIENT_TYPES, $this->t('client/messages.invalid_client_type'));

        $errors = $v->fails() ? $v->errors() : [];

        $merged = $current ?? [];
        foreach ($input as $field => $value) {
            $merged[$field] = $value;
        }

        $clientType = (string)($merged['client_type'] ?? 'individual');
        if ($clientType === '' || !in_array($clientType, self::CLIENT_TYPES, true)) {
            $clientType = 'individual';
        }

        if (array_key_exists('website', $input) && !$this->isValidWebsite($input['website'])) {
            $errors['website'][] = $this->t('client/messages.invalid_website');
        }

        if (array_key_exists('extra_attributes', $input) && !$this->isValidExtraAttributes($input['extra_attributes'])) {
            $errors['extra_attributes'][] = $this->t('client/messages.invalid_extra_attributes');
        }

        $strictByType = $isCreate || array_key_exists('client_type', $input);
        $this->validateTypeSpecific($errors, $merged, $clientType, $strictByType);

        $this->validateDigitField($errors, $input, $merged, 'tax_inn', [10, 12], $strictByType, $this->t('client/messages.invalid_tax_inn'));
        $this->validateDigitField($errors, $input, $merged, 'tax_kpp', [9], $strictByType, $this->t('client/messages.invalid_tax_kpp'));
        $this->validateDigitField($errors, $input, $merged, 'tax_ogrn', [13], $strictByType, $this->t('client/messages.invalid_tax_ogrn'));
        $this->validateDigitField($errors, $input, $merged, 'tax_ogrnip', [15], $strictByType, $this->t('client/messages.invalid_tax_ogrnip'));
        $this->validateDigitField($errors, $input, $merged, 'bank_account', [20], $strictByType, $this->t('client/messages.invalid_bank_account'));
        $this->validateDigitField($errors, $input, $merged, 'bank_bik', [9], $strictByType, $this->t('client/messages.invalid_bank_bik'));
        $this->validateDigitField($errors, $input, $merged, 'bank_corr_account', [20], $strictByType, $this->t('client/messages.invalid_bank_corr_account'));

        return $errors;
    }

    /**
     * @param array<string,array<int,string>> $errors
     * @param array<string,mixed> $merged
     */
    private function validateTypeSpecific(array &$errors, array $merged, string $clientType, bool $strictByType): void
    {
        if ($strictByType) {
            if ($clientType === 'sole_proprietor') {
                $this->requireField($errors, $merged, 'legal_name', $this->t('client/messages.required_legal_name'));
                $this->requireField($errors, $merged, 'tax_inn', $this->t('client/messages.required_tax_inn'));
                $this->requireField($errors, $merged, 'tax_ogrnip', $this->t('client/messages.required_tax_ogrnip'));
            }

            if ($clientType === 'legal_entity') {
                $this->requireField($errors, $merged, 'legal_name', $this->t('client/messages.required_legal_name'));
                $this->requireField($errors, $merged, 'tax_inn', $this->t('client/messages.required_tax_inn'));
                $this->requireField($errors, $merged, 'tax_kpp', $this->t('client/messages.required_tax_kpp'));
                $this->requireField($errors, $merged, 'tax_ogrn', $this->t('client/messages.required_tax_ogrn'));
            }
        }

        if ($clientType === 'individual') {
            return;
        }

    }

    /**
     * @param array<string,array<int,string>> $errors
     * @param array<string,mixed> $source
     */
    private function requireField(array &$errors, array $source, string $field, string $message): void
    {
        $value = $source[$field] ?? null;
        if ($value === null || trim((string)$value) === '') {
            $errors[$field][] = $message;
        }
    }

    /**
     * @param array<string,array<int,string>> $errors
     * @param array<string,mixed> $input
     * @param array<string,mixed> $merged
     * @param array<int,int> $lengths
     */
    private function validateDigitField(array &$errors, array $input, array $merged, string $field, array $lengths, bool $strictByType, string $message): void
    {
        if (!$strictByType && !array_key_exists($field, $input)) {
            return;
        }

        $rawValue = $strictByType ? ($merged[$field] ?? null) : ($input[$field] ?? null);
        if ($rawValue === null || trim((string)$rawValue) === '') {
            return;
        }

        $value = trim((string)$rawValue);
        if (!ctype_digit($value) || !in_array(strlen($value), $lengths, true)) {
            $errors[$field][] = $message;
        }
    }

    private function isValidWebsite(mixed $website): bool
    {
        if ($website === null || trim((string)$website) === '') {
            return true;
        }

        $value = trim((string)$website);
        $isUrl = filter_var($value, FILTER_VALIDATE_URL) !== false;
        if (!$isUrl) {
            return false;
        }

        $scheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));
        return $scheme === 'http' || $scheme === 'https';
    }

    private function isValidExtraAttributes(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return is_array($value);
    }
}
