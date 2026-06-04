<?php
declare(strict_types=1);

namespace Api\Controller\Docs;

use Api\Controller\Common\BaseController;

final class DocsController extends BaseController
{
    public function openapi(): \Api\System\Library\Http\JsonResponse
    {
        $path = dirname(__DIR__, 2) . '/docs/openapi/openapi.v1.json';
        if (!is_file($path)) {
            return $this->error('DOCS_NOT_FOUND', $this->t('docs/messages.openapi_not_found'), 404);
        }

        $json = json_decode((string)file_get_contents($path), true);
        return $this->success('DOCS_OPENAPI', $this->t('docs/messages.openapi'), [
            'spec' => is_array($json) ? $json : [],
        ]);
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
