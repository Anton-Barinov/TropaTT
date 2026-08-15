<?php
declare(strict_types=1);

namespace Module\Crm\Drawio\Repository;

use PDO;

final class DrawioRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    private function publicId(): string
    {
        return 'drw_' . bin2hex(random_bytes(10));
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listDiagrams(?string $pagePublicId = null): array
    {
        if ($pagePublicId !== null && $pagePublicId !== '') {
            $stmt = $this->pdo->prepare('SELECT id, public_id, title, page_public_id, created_by_user_id, created_at, updated_at FROM module_drawio_diagrams WHERE page_public_id = :page ORDER BY updated_at DESC');
            $stmt->execute(['page' => $pagePublicId]);
        } else {
            $stmt = $this->pdo->query('SELECT id, public_id, title, page_public_id, created_by_user_id, created_at, updated_at FROM module_drawio_diagrams ORDER BY updated_at DESC');
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDiagram(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, title, page_public_id, xml_content, created_by_user_id, created_at, updated_at FROM module_drawio_diagrams WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createDiagram(array $data): array
    {
        $publicId = $this->publicId();
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_drawio_diagrams (public_id, title, page_public_id, xml_content, created_by_user_id, created_at, updated_at) VALUES (:public_id, :title, :page_public_id, :xml_content, :created_by_user_id, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'title' => $data['title'],
            'page_public_id' => $data['page_public_id'] ?? null,
            'xml_content' => $data['xml_content'],
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->getDiagram($publicId) ?? ['public_id' => $publicId];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateDiagram(string $publicId, array $data): void
    {
        $sets = [];
        $params = ['public_id' => $publicId];
        foreach (['title', 'page_public_id', 'xml_content'] as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }
        if ($sets === []) {
            return;
        }
        $params['updated_at'] = $this->now();
        $sets[] = 'updated_at = :updated_at';
        $this->pdo->prepare('UPDATE module_drawio_diagrams SET ' . implode(', ', $sets) . ' WHERE public_id = :public_id')->execute($params);
    }

    public function deleteDiagram(string $publicId): void
    {
        $this->pdo->prepare('DELETE FROM module_drawio_diagrams WHERE public_id = :public_id')->execute(['public_id' => $publicId]);
    }
}
