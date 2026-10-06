<?php
declare(strict_types=1);

namespace Module\Crm\TelegramBot\Web\Controller;

use Api\System\Library\Database\DatabaseConnectionPool;
use PDO;

final class TelegramBotWebController
{
    public function index(): void
    {
        $orgId = (int)($_SESSION['user']['organization_id'] ?? 1);
        $db = DatabaseConnectionPool::getConnection();

        $stmt = $db->prepare("SELECT * FROM crm_telegram_configs WHERE organization_id = :org_id LIMIT 1");
        $stmt->execute([':org_id' => $orgId]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        include __DIR__ . '/../template/page/telegram_settings.php';
    }
}
