<?php
declare(strict_types=1);

namespace Api\Controller\Docs;

use Api\Controller\Common\BaseController;
use Api\System\Library\Support\OpenApiSpec;

final class DocsController extends BaseController
{
    public function openapi(): \Api\System\Library\Http\JsonResponse
    {
        $request = $this->request();
        return \Api\System\Library\Http\JsonResponse::success(
            code: 'DOCS_OPENAPI',
            message: $this->t('docs/messages.openapi'),
            data: ['spec' => OpenApiSpec::build()],
            requestId: $request->requestId,
            correlationId: $request->correlationId,
        );
    }

    public function schema(): \Api\System\Library\Http\JsonResponse
    {
        $path = dirname(__DIR__, 2) . '/docs/json-schema/response.schema.json';
        if (!is_file($path)) {
            return $this->error('DOCS_NOT_FOUND', $this->t('docs/messages.schema_not_found'), 404);
        }

        $json = json_decode((string)file_get_contents($path), true);
        return $this->success('DOCS_SCHEMA', $this->t('docs/messages.schema'), [
            'schema' => is_array($json) ? $json : [],
        ]);
    }
}
