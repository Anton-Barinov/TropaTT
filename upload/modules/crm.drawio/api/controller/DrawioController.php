<?php
declare(strict_types=1);

namespace Module\Crm\Drawio\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\Drawio\Repository\DrawioRepository;
use PDO;

final class DrawioController
{
    private PDO $pdo;
    private DrawioRepository $repo;

    public function __construct(private readonly Container $container)
    {
        $this->pdo = $container->get('db.pdo');
        $this->repo = new DrawioRepository($this->pdo);
    }

    private function requestBody(): array
    {
        $req = $this->container->get('request');
        $raw = $req->rawBody ?? '';
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function actor(): array
    {
        $auth = $this->container->get('auth_user');
        return is_array($auth['user'] ?? null) ? $auth['user'] : [];
    }

    private function actorUserId(): int
    {
        $id = (int)($this->actor()['id'] ?? 0);
        if ($id > 0) {
            return $id;
        }
        $publicId = (string)($this->actor()['public_id'] ?? '');
        if ($publicId === '') {
            return 0;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    private function hasPermission(string $code): bool
    {
        $user = $this->actor();
        if (!empty($user['is_root'])) {
            return true;
        }
        $perms = array_map('strval', (array)($user['permission_codes'] ?? []));
        return in_array('*', $perms, true) || in_array($code, $perms, true);
    }

    /**
     * @param array<string, mixed> $diagram
     */
    private function canAccess(array $diagram): bool
    {
        if ($this->hasPermission('module.drawio.manage')) {
            return true;
        }
        return (int)($diagram['created_by_user_id'] ?? 0) === $this->actorUserId();
    }

    /**
     * @param array<string, mixed> $diagram
     * @return array<string, mixed>
     */
    private function sanitize(array $diagram): array
    {
        unset($diagram['xml_content']);
        return $diagram;
    }

    public function listDiagrams(): JsonResponse
    {
        if (!$this->hasPermission('module.drawio.view') && !$this->hasPermission('module.drawio.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $req = $this->container->get('request');
        $query = $req->query ?? [];
        $pagePublicId = trim((string)($query['page_public_id'] ?? ''));
        $isManager = $this->hasPermission('module.drawio.manage');
        $userId = $this->actorUserId();

        $diagrams = array_values(array_filter(
            $this->repo->listDiagrams($pagePublicId !== '' ? $pagePublicId : null),
            function ($d) use ($isManager, $userId) {
                return $isManager || (int)($d['created_by_user_id'] ?? 0) === $userId;
            }
        ));

        return JsonResponse::success('DIAGRAMS_LIST', 'OK', ['diagrams' => $diagrams]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function getDiagram(array $params): JsonResponse
    {
        if (!$this->hasPermission('module.drawio.view') && !$this->hasPermission('module.drawio.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $diagram = $this->repo->getDiagram((string)$params['public_id']);
        if (!$diagram) {
            return JsonResponse::error('NOT_FOUND', 'Diagram not found', 404);
        }
        if (!$this->canAccess($diagram)) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        return JsonResponse::success('DIAGRAM', 'OK', ['diagram' => $diagram]);
    }

    public function createDiagram(): JsonResponse
    {
        if (!$this->hasPermission('module.drawio.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $body = $this->requestBody();
        $title = trim((string)($body['title'] ?? ''));
        $xml = (string)($body['xml_content'] ?? $body['xml'] ?? '');

        if ($title === '') {
            return JsonResponse::error('VALIDATION_ERROR', 'Title is required', 422);
        }
        if (trim($xml) === '') {
            return JsonResponse::error('VALIDATION_ERROR', 'xml_content is required', 422);
        }

        $maxBytes = 2000000;
        if (strlen($xml) > $maxBytes) {
            return JsonResponse::error('DIAGRAM_TOO_LARGE', 'Diagram XML exceeds the size limit', 422);
        }

        $diagram = $this->repo->createDiagram([
            'title' => $title,
            'page_public_id' => trim((string)($body['page_public_id'] ?? '')) ?: null,
            'xml_content' => $xml,
            'created_by_user_id' => $this->actorUserId(),
        ]);

        return JsonResponse::success('DIAGRAM_CREATED', 'Diagram created', ['diagram' => $this->sanitize($diagram)], 201);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function updateDiagram(array $params): JsonResponse
    {
        if (!$this->hasPermission('module.drawio.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $diagram = $this->repo->getDiagram((string)$params['public_id']);
        if (!$diagram) {
            return JsonResponse::error('NOT_FOUND', 'Diagram not found', 404);
        }
        if (!$this->canAccess($diagram)) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $body = $this->requestBody();
        $update = [];
        if (array_key_exists('title', $body)) {
            $title = trim((string)$body['title']);
            if ($title === '') {
                return JsonResponse::error('VALIDATION_ERROR', 'Title is required', 422);
            }
            $update['title'] = $title;
        }
        if (array_key_exists('page_public_id', $body)) {
            $update['page_public_id'] = trim((string)$body['page_public_id']) ?: null;
        }
        if (array_key_exists('xml_content', $body) || array_key_exists('xml', $body)) {
            $xml = (string)($body['xml_content'] ?? $body['xml'] ?? '');
            if (strlen($xml) > 2000000) {
                return JsonResponse::error('DIAGRAM_TOO_LARGE', 'Diagram XML exceeds the size limit', 422);
            }
            $update['xml_content'] = $xml;
        }

        if ($update !== []) {
            $this->repo->updateDiagram((string)$params['public_id'], $update);
        }

        return JsonResponse::success('DIAGRAM_UPDATED', 'Diagram updated', ['diagram' => $this->sanitize($this->repo->getDiagram((string)$params['public_id']))]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function deleteDiagram(array $params): JsonResponse
    {
        if (!$this->hasPermission('module.drawio.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $diagram = $this->repo->getDiagram((string)$params['public_id']);
        if (!$diagram) {
            return JsonResponse::error('NOT_FOUND', 'Diagram not found', 404);
        }
        if (!$this->canAccess($diagram)) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $this->repo->deleteDiagram((string)$params['public_id']);
        return JsonResponse::success('DIAGRAM_DELETED', 'Diagram deleted');
    }
}
