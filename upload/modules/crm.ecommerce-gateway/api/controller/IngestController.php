<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Http\Request;
use Module\Crm\EcommerceGateway\Repository\IngestRepository;
use Module\Crm\EcommerceGateway\Repository\StoreRepository;
use Module\Crm\EcommerceGateway\Service\ContactResolver;
use Module\Crm\EcommerceGateway\Service\CoreIntakeWriter;
use Module\Crm\EcommerceGateway\Service\IdempotencyService;
use Module\Crm\EcommerceGateway\Service\IngestService;
use Module\Crm\EcommerceGateway\Service\IntakeComposer;
use Module\Crm\EcommerceGateway\Service\PayloadValidator;
use Module\Crm\EcommerceGateway\Service\SignatureService;
use Module\Crm\EcommerceGateway\Service\StoreAuthService;

/**
 * Public (unsigned at router level) ingestion entry point.
 *
 * Every action authenticates the store itself: the router cannot use a user
 * session here, so `StoreAuthService` is the only gate before data access.
 * The five ingestion routes intentionally share one pipeline — validation,
 * idempotency, contact resolution and intake creation must behave identically
 * for orders, callbacks, feedback and custom forms.
 */
final class IngestController
{
    private StoreAuthService $auth;
    private StoreRepository $repository;
    private IngestService $ingest;

    public function __construct(private readonly Container $container)
    {
        $this->repository = new StoreRepository($container->get('db.pdo'));
        $config = $this->moduleConfig();
        $this->auth = new StoreAuthService(
            $this->repository,
            (int)($config['timestamp_tolerance_seconds'] ?? SignatureService::DEFAULT_TIMESTAMP_TOLERANCE),
            (int)($config['nonce_retention_seconds'] ?? 900),
        );

        $ingestRepository = new IngestRepository($container->get('db.pdo'));
        $this->ingest = new IngestService(
            $this->repository,
            $ingestRepository,
            new PayloadValidator(),
            new IdempotencyService($ingestRepository),
            new ContactResolver($ingestRepository),
            new IntakeComposer(),
            $container->has('service.intake_item') ? new CoreIntakeWriter($container->get('service.intake_item')) : null,
            $container->has('service.task') ? $container->get('service.task') : null,
            $config
        );
    }

    /**
     * Signed handshake used by the connection wizard and the synthetic test.
     */
    public function ping(): JsonResponse
    {
        $request = $this->request();
        $tooLarge = $this->bodySizeGuard($request);
        if ($tooLarge !== null) {
            return $tooLarge;
        }

        $auth = $this->auth->authenticate($request);
        if (!$auth['ok']) {
            return JsonResponse::error($auth['code'], 'Store authentication failed', $auth['http_status'], [], (string)$request->requestId);
        }

        /** @var array<string,mixed> $store */
        $store = $auth['store'];

        return JsonResponse::success('INGESTION_PONG', 'OK', [
            'store_key' => (string)$store['api_key'],
            'store_title' => (string)$store['name'],
            'cms_type' => (string)$store['cms_type'],
            'protocol_version' => '1.0',
            'capabilities' => [
                'ping',
                'order',
                'quick_order',
                'callback',
                'feedback',
                'form',
            ],
            'crm_version' => (string)$this->config('default.app.version', ''),
            'server_time' => gmdate('c'),
        ], 200, (string)$request->requestId, '', $this->meta($store, $request, ''));
    }

    public function orders(): JsonResponse
    {
        return $this->handleIngest('order');
    }

    public function quickOrders(): JsonResponse
    {
        return $this->handleIngest('quick_order');
    }

    public function callbacks(): JsonResponse
    {
        return $this->handleIngest('callback');
    }

    public function feedback(): JsonResponse
    {
        return $this->handleIngest('feedback');
    }

    public function forms(): JsonResponse
    {
        return $this->handleIngest('form');
    }

    /**
     * Shared signed-ingestion pipeline (§5, §8, §9).
     */
    private function handleIngest(string $type): JsonResponse
    {
        $request = $this->request();
        $tooLarge = $this->bodySizeGuard($request);
        if ($tooLarge !== null) {
            return $tooLarge;
        }

        $auth = $this->auth->authenticate($request);
        if (!$auth['ok']) {
            return JsonResponse::error($auth['code'], 'Store authentication failed', $auth['http_status'], [], (string)$request->requestId);
        }

        /** @var array<string,mixed> $store */
        $store = $auth['store'];

        $idempotencyHeader = trim((string)$request->header(SignatureService::HEADER_IDEMPOTENCY, ''));
        $result = $this->ingest->ingest(
            $store,
            $type,
            (string)$request->rawBody,
            (string)$request->requestId,
            $request->clientIp(),
            substr((string)$request->header('User-Agent', ''), 0, 512),
            $idempotencyHeader === '' ? null : $idempotencyHeader
        );

        $meta = $this->meta($store, $request, (string)($result['idempotency_key'] ?? ''));

        if (!$result['ok']) {
            return JsonResponse::error(
                (string)$result['code'],
                $this->errorMessage((string)$result['code']),
                (int)$result['http_status'],
                (array)$result['errors'],
                (string)$request->requestId,
                '',
                $meta
            );
        }

        return JsonResponse::success(
            (string)$result['code'],
            'OK',
            (array)$result['data'],
            (int)$result['http_status'],
            (string)$request->requestId,
            '',
            $meta
        );
    }

    /**
     * Response meta required by the contract (§7.1): locale, protocol version,
     * server time and the idempotency key actually used.
     *
     * @param array<string,mixed> $store
     * @return array<string,mixed>
     */
    private function meta(array $store, Request $request, string $idempotencyKey): array
    {
        $locale = (string)($store['locale'] ?? '');
        if ($locale === '') {
            $locale = (string)($this->moduleConfig()['default_locale'] ?? 'ru-ru');
        }

        return [
            'locale' => $locale,
            'protocol_version' => '1.0',
            'server_time' => gmdate('c'),
            'idempotency_key' => $idempotencyKey,
        ];
    }

    private function errorMessage(string $code): string
    {
        return match ($code) {
            'INGESTION_PAYLOAD_INVALID' => 'The request body could not be parsed',
            'INGESTION_PAYLOAD_TOO_LARGE' => 'The request body exceeds the configured limit',
            'INGESTION_VALIDATION_FAILED' => 'The payload failed validation',
            'INGESTION_CONTACT_REQUIRED' => 'A contact channel (phone, email or messenger) is required',
            'INGESTION_AMOUNT_NOT_INTEGER' => 'Money amounts must be integers in minor units',
            'INGESTION_EXTERNAL_ID_CONFLICT' => 'The idempotency key is bound to another external_id',
            'INGESTION_INTERNAL_ERROR' => 'Internal CRM error',
            default => 'Ingestion failed',
        };
    }

    /**
     * Shared guard for every ingestion action (E-COM-01 §4.3).
     */
    private function bodySizeGuard(Request $request): ?JsonResponse
    {
        $max = (int)($this->moduleConfig()['max_body_bytes'] ?? 1048576);
        if ($max > 0 && strlen($request->rawBody) > $max) {
            return JsonResponse::error(
                'INGESTION_PAYLOAD_TOO_LARGE',
                'Request body exceeds the configured limit',
                400,
                [],
                (string)$request->requestId
            );
        }

        return null;
    }

    private function request(): Request
    {
        return $this->container->get('request');
    }

    /**
     * @return array<string,mixed>
     */
    private function moduleConfig(): array
    {
        if (!$this->container->has('module.config')) {
            return [];
        }
        $config = $this->container->get('module.config')->getAll('crm.ecommerce-gateway');

        return is_array($config) ? $config : [];
    }

    private function config(string $key, mixed $default = null): mixed
    {
        if (!$this->container->has('config')) {
            return $default;
        }

        return $this->container->get('config')->get($key, $default);
    }
}
