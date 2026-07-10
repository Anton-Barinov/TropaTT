<?php

declare(strict_types=1);
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }


$root = dirname(__DIR__);
require_once $root . '/system/library/support/Autoloader.php';

$envFile = $root . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_DATABASE') ?: getenv('DB_NAME') ?: 'tropatt';
$dbUser = getenv('DB_USERNAME') ?: getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

function findSpaceId(PDO $pdo, string $publicId): ?int {
    $stmt = $pdo->prepare('SELECT id FROM knowledge_spaces WHERE public_id = ? LIMIT 1');
    $stmt->execute([$publicId]);
    $row = $stmt->fetch();
    return $row ? (int)$row['id'] : null;
}

function ulid(): string {
    return 'kbs_' . strtoupper(bin2hex(random_bytes(8)));
}

$now = gmdate('Y-m-d H:i:s');
$count = 0;

$spaces = $pdo->query('SELECT public_id, title, slug FROM knowledge_spaces WHERE parent_id IS NULL ORDER BY sort_order')->fetchAll();
if (!$spaces) {
    fwrite(STDERR, "No spaces found. Run seed_knowledge.php first.\n");
    exit(1);
}

foreach ($spaces as $space) {
    echo "Processing: {$space['title']} (slug: {$space['slug']})\n";

    $parentId = findSpaceId($pdo, $space['public_id']);
    if ($parentId === null) continue;

    $subspaces = match ($space['slug']) {
        'general' => [
            ['FAQ', 'Часто задаваемые вопросы', 'faq', [
                ['Технические вопросы', 'FAQ по техническим проблемам', 'faq-technical'],
                ['Организационные вопросы', 'FAQ по процессам', 'faq-org'],
            ]],
            ['Стандарты', 'Корпоративные стандарты', 'standards', [
                ['Код-стайл', 'Стандарты написания кода', 'code-style'],
                ['Дизайн-система', 'Правила UI/UX', 'design-system'],
            ]],
        ],
        default => [],
    };

    if (empty($subspaces)) {
        echo "  No template for this space, skipping.\n";
        continue;
    }

    foreach ($subspaces as $sub) {
        [$subTitle, $subDesc, $subSlug, $children] = array_pad($sub, 4, []);

        $exists = $pdo->prepare('SELECT id FROM knowledge_spaces WHERE slug = ?');
        $exists->execute([$subSlug]);
        if ($exists->fetch()) {
            echo "  Skip existing: {$subTitle}\n";
            continue;
        }

        $subPublicId = ulid();
        $stmt = $pdo->prepare('INSERT INTO knowledge_spaces (public_id, title, slug, description, parent_id, visibility, default_access_level, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$subPublicId, $subTitle, $subSlug, $subDesc, $parentId, 'public', 'view', 100, $now, $now]);
        $newId = (int)$pdo->lastInsertId();
        $count++;
        echo "  Created: {$subTitle} (id={$newId})\n";

        if (!empty($children)) {
            foreach ($children as $child) {
                [$childTitle, $childDesc, $childSlug] = array_pad($child, 3, '');
                $childExists = $pdo->prepare('SELECT id FROM knowledge_spaces WHERE slug = ?');
                $childExists->execute([$childSlug]);
                if ($childExists->fetch()) {
                    echo "    Skip existing: {$childTitle}\n";
                    continue;
                }

                $childPublicId = ulid();
                $cstmt = $pdo->prepare('INSERT INTO knowledge_spaces (public_id, title, slug, description, parent_id, visibility, default_access_level, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $cstmt->execute([$childPublicId, $childTitle, $childSlug, $childDesc, $newId, 'public', 'view', 100, $now, $now]);
                $count++;
                echo "    Created: {$childTitle}\n";
            }
        }
    }
}

echo "\nDone. Created {$count} nested spaces.\n";
