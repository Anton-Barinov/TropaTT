<?php

declare(strict_types=1);

namespace Api\System\Library\Http {
    final class JsonResponse
    {
        public function __construct(public int $statusCode, public array $payload) {}
    }
}

namespace Api\Controller\Common {
    use Api\System\Library\Container;
    use Api\System\Library\Http\JsonResponse;

    abstract class BaseController
    {
        public function __construct(protected Container $container, private array $auth, private object $requestObject) {}
        protected function user(): array { return $this->auth; }
        protected function request(): object { return $this->requestObject; }
        protected function rejectInvalidOrganizationContext(): ?JsonResponse { return null; }
        protected function error(string $code, string $message, int $status = 400): JsonResponse { return new JsonResponse($status, ['code' => $code, 'message' => $message]); }
        protected function success(string $code, string $message, array $data = [], int $status = 200): JsonResponse { return new JsonResponse($status, ['code' => $code, 'data' => $data]); }
        protected function invalidateCache(string $name): void {}
    }
}

namespace PublicFormsQa {
    use Api\System\Library\Container;
    use Module\Crm\PublicForms\Api\Controller\PublicFormsApiController;

    require_once __DIR__ . '/test_support.php';
    require_once __DIR__ . '/../api/controller/PublicFormsApiController.php';

    $failures = [];
    function assertCase(bool $condition, string $message): void
    {
        global $failures;
        if (!$condition) {
            $failures[] = $message;
            fwrite(STDERR, "FAIL: {$message}\n");
        }
    }

    final class RequestStub
    {
        public function __construct(
            public bool $bodyTooLarge,
            public string $rawBody,
            public array $server,
            public array $jsonBody = [],
            private array $headers = []
        ) {}
        public array $query = [];
        public function header(string $name): ?string { return $this->headers[strtolower($name)] ?? null; }
        public function ip(): string { return '192.0.2.123'; }
        public function allInput(): array { return $this->jsonBody; }
    }

    function runSubmitRequest(RequestStub $request): \Api\System\Library\Http\JsonResponse
    {
        $db = database();
        $container = new Container(['db.pdo' => $db, 'service.intake_item' => new \stdClass(), 'service.calendar' => new \stdClass()]);
        $controller = new PublicFormsApiController($container, ['user' => ['id' => 1, 'organization_id' => 10, 'is_root' => 1]], $request);
        return $controller->submitForm(['slug' => 'not-existing']);
    }

    function runAdminRequest(RequestStub $request): \Api\System\Library\Http\JsonResponse
    {
        $container = new Container(['db.pdo' => database()]);
        $controller = new PublicFormsApiController($container, ['user' => ['id' => 1, 'organization_id' => 10, 'is_root' => 1]], $request);
        return $controller->adminSaveForm([]);
    }

    assertCase(runSubmitRequest(new RequestStub(true, '', [], [], ['content-type' => 'application/json']))->statusCode === 413, 'upstream bodyTooLarge signal is rejected for public submission');
    assertCase(runSubmitRequest(new RequestStub(true, '', ['CONTENT_LENGTH' => '128'], [], ['content-type' => 'application/json']))->statusCode === 413, 'upstream bodyTooLarge signal overrides a missing/truncated raw body and understated content length');
    assertCase(runSubmitRequest(new RequestStub(false, str_repeat('x', 32769), [], [], ['content-type' => 'application/json']))->statusCode === 413, 'raw submission beyond 32 KB is rejected');
    assertCase(runSubmitRequest(new RequestStub(false, '', ['CONTENT_LENGTH' => '32769'], [], ['content-type' => 'application/json']))->statusCode === 413, 'declared submission length beyond 32 KB is rejected');
    assertCase(runSubmitRequest(new RequestStub(false, '', ['CONTENT_LENGTH' => '32768'], [], ['content-type' => 'application/json']))->statusCode === 404, 'boundary-sized request proceeds past the size gate to normal form lookup');
    assertCase(runAdminRequest(new RequestStub(true, '', [], [], ['content-type' => 'application/json']))->statusCode === 413, 'upstream bodyTooLarge signal is rejected for form management');
    assertCase(runAdminRequest(new RequestStub(false, str_repeat('x', 16385), [], [], ['content-type' => 'application/json']))->statusCode === 413, 'admin form JSON beyond 16 KB is rejected');

    if ($failures !== []) {
        fwrite(STDERR, count($failures) . " Public Forms HTTP request-size check(s) failed\n");
        exit(1);
    }
    echo "Public Forms HTTP request-size checks passed\n";
}
