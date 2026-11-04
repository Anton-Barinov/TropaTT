<?php
declare(strict_types=1);

namespace Api\Controller\Counterparty;

use Api\System\Library\Module\ModuleEvents;
use Api\Controller\Common\BaseController;
use Api\System\Library\Service\CounterpartyService;
use Throwable;

final class CounterpartyController extends BaseController
{
    private const COUNTERPARTY_TYPES = ['organization', 'individual', 'sole_proprietor', 'legal_entity'];

    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }
        $contextError = $this->rejectInvalidOrganizationContext();
        if ($contextError !== null) {
            return $contextError;
        }
        $authUser['user'] = $this->organizationScopedActor((array)$authUser['user']);

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $input = $this->request()->allInput();
            ksort($input);
            $cacheKey = 'list:' . $this->cacheUserId() . ':' . $this->organizationContextCacheKey() . ':' . hash('sha256', json_encode($input));
            $result = $cache->remember('counterparty', $cacheKey, 60, function () use ($input, $authUser) {
                /** @var CounterpartyService $service */
                $service = $this->container->get('service.counterparty');
                return $service->list($input, $authUser['user']);
            });
        } else {
            /** @var CounterpartyService $service */
            $service = $this->container->get('service.counterparty');
            $result = $service->list($this->request()->allInput(), $authUser['user']);
        }

        return $this->success('COUNTERPARTY_LIST', $this->t('counterparty/messages.list'), ['items' => $result['items']], meta: $result['meta']);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }
        $contextError = $this->rejectInvalidOrganizationContext();
        if ($contextError !== null) {
            return $contextError;
        }
        $authUser['user'] = $this->organizationScopedActor((array)$authUser['user']);

        /** @var CounterpartyService $service */
        $service = $this->container->get('service.counterparty');
        $item = $service->get((string)$params['public_id'], $authUser['user']);
        if (!$item) {
            return $this->error('COUNTERPARTY_NOT_FOUND', $this->t('counterparty/messages.not_found'), 404, [
                'counterparty' => [$this->t('counterparty/messages.not_found')],
            ]);
        }

        return $this->success('COUNTERPARTY_DETAIL', $this->t('counterparty/messages.detail'), ['counterparty' => $item]);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }
        $contextError = $this->rejectInvalidOrganizationContext();
        if ($contextError !== null) {
            return $contextError;
        }
        $authUser['user'] = $this->organizationScopedActor((array)$authUser['user']);

        $input = $this->request()->allInput();
        $input = $this->normalizeCounterpartyRequisites($input);
        if (empty($input['counterparty_type'])) $input['counterparty_type'] = 'organization';
        if (empty($input['status'])) $input['status'] = 'active';
        $errors = $this->validateCounterpartyPayload($input, true);
        if ($errors !== []) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $errors);
        }

        /** @var CounterpartyService $service */
        $service = $this->container->get('service.counterparty');
        return $this->withIdempotency(function () use ($service, $input, $authUser): \Api\System\Library\Http\JsonResponse {
            $item = $service->create($input, $authUser['user']);

            $this->invalidateCache('counterparty');

            $this->dispatchModuleHook(ModuleEvents::COUNTERPARTY_CREATED, [
                'counterparty_public_id' => (string)($item['public_id'] ?? ''),
                'title' => (string)($item['title'] ?? ''),
                'actor_id' => (int)($this->user()['user']['id'] ?? 0),
            ]);

            return $this->success('COUNTERPARTY_CREATED', $this->t('counterparty/messages.created'), ['counterparty' => $item], 201);
        });
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }
        $contextError = $this->rejectInvalidOrganizationContext();
        if ($contextError !== null) {
            return $contextError;
        }
        $authUser['user'] = $this->organizationScopedActor((array)$authUser['user']);

        /** @var CounterpartyService $service */
        $service = $this->container->get('service.counterparty');
        $current = $service->get((string)$params['public_id'], $authUser['user']);
        if (!$current) {
            return $this->error('COUNTERPARTY_NOT_FOUND', $this->t('counterparty/messages.not_found'), 404, [
                'counterparty' => [$this->t('counterparty/messages.not_found')],
            ]);
        }

        $input = $this->request()->allInput();
        $input = $this->normalizeCounterpartyRequisites($input);
        $errors = $this->validateCounterpartyPayload($input, false, $current);
        if ($errors !== []) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $errors);
        }

        try {
            $item = $service->update((string)$params['public_id'], $input, $authUser['user']);
        } catch (Throwable $e) {
            if ($e->getMessage() === 'COUNTERPARTY_NOT_FOUND') {
                return $this->error('COUNTERPARTY_NOT_FOUND', $this->t('counterparty/messages.not_found'), 404, [
                    'counterparty' => [$this->t('counterparty/messages.not_found')],
                ]);
            }
            throw $e;
        }

        $this->invalidateCache('counterparty');

        $this->dispatchModuleHook(ModuleEvents::COUNTERPARTY_UPDATED, [
            'counterparty_public_id' => (string)($item['public_id'] ?? ''),
            'title' => (string)($item['title'] ?? ''),
            'actor_id' => (int)($this->user()['user']['id'] ?? 0),
        ]);

        return $this->success('COUNTERPARTY_UPDATED', $this->t('counterparty/messages.updated'), ['counterparty' => $item]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }
        $contextError = $this->rejectInvalidOrganizationContext();
        if ($contextError !== null) {
            return $contextError;
        }
        $authUser['user'] = $this->organizationScopedActor((array)$authUser['user']);

        /** @var CounterpartyService $service */
        $service = $this->container->get('service.counterparty');
        try {
            $service->delete((string)$params['public_id'], $authUser['user']);
        } catch (Throwable $e) {
            if ($e->getMessage() === 'COUNTERPARTY_NOT_FOUND') {
                return $this->error('COUNTERPARTY_NOT_FOUND', $this->t('counterparty/messages.not_found'), 404, [
                    'counterparty' => [$this->t('counterparty/messages.not_found')],
                ]);
            }
            throw $e;
        }

        $this->invalidateCache('counterparty');

        $this->dispatchModuleHook(ModuleEvents::COUNTERPARTY_DELETED, [
            'counterparty_public_id' => (string)($params['public_id']),
            'actor_id' => (int)($this->user()['user']['id'] ?? 0),
        ]);

        return $this->success('COUNTERPARTY_DELETED', $this->t('counterparty/messages.deleted'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function validateCounterpartyPayload(array $input, bool $isCreate, ?array $current = null): array
    {
        $errors = [];

        if ($isCreate && empty($input['title'])) {
            $errors['title'] = [$this->t('counterparty/messages.title_required')];
        }

        if (!empty($input['counterparty_type']) && !in_array($input['counterparty_type'], self::COUNTERPARTY_TYPES, true)) {
            $errors['counterparty_type'] = [$this->t('counterparty/messages.invalid_type')];
        }

        if (!empty($input['tax_inn'])) {
            $inn = preg_replace('/\D+/', '', (string)$input['tax_inn']);
            if (strlen($inn) !== 10 && strlen($inn) !== 12) {
                $errors['tax_inn'] = [$this->t('counterparty/messages.invalid_inn')];
            }
        }

        if (!empty($input['tax_ogrn'])) {
            $ogrn = preg_replace('/\D+/', '', (string)$input['tax_ogrn']);
            if (strlen($ogrn) !== 13) {
                $errors['tax_ogrn'] = [$this->t('counterparty/messages.invalid_ogrn')];
            }
        }

        if (!empty($input['tax_ogrnip'])) {
            $ogrnip = preg_replace('/\D+/', '', (string)$input['tax_ogrnip']);
            if (strlen($ogrnip) !== 15) {
                $errors['tax_ogrnip'] = [$this->t('counterparty/messages.invalid_ogrnip')];
            }
        }

        if (!empty($input['tax_kpp'])) {
            $kpp = trim((string)$input['tax_kpp']);
            if (strlen($kpp) !== 9) {
                $errors['tax_kpp'] = [$this->t('counterparty/messages.invalid_kpp')];
            }
        }

        if (!empty($input['bank_bik'])) {
            $bik = trim((string)$input['bank_bik']);
            if (!ctype_digit($bik) || (strlen($bik) !== 9 && strlen($bik) !== 8 && strlen($bik) !== 11)) {
                $errors['bank_bik'] = [$this->t('counterparty/messages.invalid_bik')];
            }
        }

        if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = [$this->t('counterparty/messages.invalid_email')];
        }

        return $errors;
    }

    /**
     * Normalizes counterparty requisites payload:
     * - Trims whitespace and casts empty strings to null.
     * - Strips non-digits from INN.
     * - Intelligently maps 15-digit registration in tax_ogrn to tax_ogrnip (e.g. from DaData party autocomplete).
     * - For 12-digit INN (sole proprietor): clears tax_kpp (KPP does not exist for ИП in Russia).
     * - For 10-digit INN (legal entity): maps misrouted 13-digit tax_ogrnip to tax_ogrn.
     */
    private function normalizeCounterpartyRequisites(array $input): array
    {
        $fields = [
            'tax_inn', 'tax_kpp', 'tax_ogrn', 'tax_ogrnip', 'legal_name',
            'bank_account', 'bank_bik', 'bank_corr_account', 'bank_name',
            'address_legal', 'address_postal', 'address_actual',
        ];
        foreach ($fields as $field) {
            if (array_key_exists($field, $input)) {
                $val = is_string($input[$field]) ? trim($input[$field]) : $input[$field];
                $input[$field] = ($val === '') ? null : $val;
            }
        }

        $innDigits = !empty($input['tax_inn']) ? preg_replace('/\D+/', '', (string)$input['tax_inn']) : null;
        if ($innDigits !== null) {
            $input['tax_inn'] = $innDigits;
        }

        $rawOgrn = !empty($input['tax_ogrn']) ? preg_replace('/\D+/', '', (string)$input['tax_ogrn']) : null;
        $rawOgrnip = !empty($input['tax_ogrnip']) ? preg_replace('/\D+/', '', (string)$input['tax_ogrnip']) : null;

        // If a 15-digit registration is supplied in tax_ogrn (typical for DaData party suggestions where ogrn holds OGRNIP)
        // and tax_ogrnip is empty:
        if ($rawOgrn !== null && strlen($rawOgrn) === 15 && empty($rawOgrnip)) {
            $input['tax_ogrnip'] = $rawOgrn;
            $input['tax_ogrn'] = null;
            $rawOgrnip = $rawOgrn;
            $rawOgrn = null;
        }

        // If 12-digit INN (sole proprietor):
        if ($innDigits !== null && strlen($innDigits) === 12) {
            if (array_key_exists('tax_kpp', $input) && empty($input['tax_kpp'])) {
                $input['tax_kpp'] = null;
            }
            if ($rawOgrn !== null && strlen($rawOgrn) === 15) {
                $input['tax_ogrnip'] = $rawOgrn;
                $input['tax_ogrn'] = null;
            }
        }

        // If 10-digit INN (legal entity):
        if ($innDigits !== null && strlen($innDigits) === 10) {
            if ($rawOgrnip !== null && strlen($rawOgrnip) === 13 && empty($rawOgrn)) {
                $input['tax_ogrn'] = $rawOgrnip;
                $input['tax_ogrnip'] = null;
            }
        }

        return $input;
    }
}
