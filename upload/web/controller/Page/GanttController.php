<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class GanttController extends Controller
{
    public function index(): void
    {
        $ganttMaxTasks = 0; // 0 = show every accessible task; N = latest N tasks
        if (function_exists('crmWebApiDbConnect')) {
            try {
                $pdo = crmWebApiDbConnect($this->baseDir);
                if ($pdo !== null) {
                    $stmt = $pdo->prepare('SELECT value FROM settings WHERE scope = ? AND name = ? LIMIT 1');
                    $stmt->execute(['system', 'gantt_max_tasks']);
                    $raw = $stmt->fetchColumn();
                    if ($raw !== false && $raw !== null) {
                        $decoded = json_decode((string)$raw, true);
                        $ganttMaxTasks = max(0, (int)(is_int($decoded) ? $decoded : (string)$raw));
                    }
                }
            } catch (\Throwable $e) {
                error_log('[GanttController] failed to read gantt_max_tasks: ' . $e->getMessage());
            }
        }

        $this->render('page/gantt', [
            'title' => 'Гант',
            'route' => 'gantt',
            'gantt_max_tasks' => $ganttMaxTasks,
        ]);
    }
}
