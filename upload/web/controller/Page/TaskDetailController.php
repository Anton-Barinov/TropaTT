<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class TaskDetailController extends Controller
{
    public function index(): void
    {
        $customTitle = null;
        $taskPublicId = trim((string)($_GET['task_public_id'] ?? $_GET['id'] ?? ''));
        $taskKeyParam = trim((string)($_GET['task_key'] ?? $_GET['key'] ?? ''));

        if (($taskPublicId !== '' || $taskKeyParam !== '') && function_exists('crmWebApiDbConnect')) {
            try {
                $pdo = crmWebApiDbConnect($this->baseDir);
                if ($pdo !== null) {
                    if ($taskPublicId !== '') {
                        $stmt = $pdo->prepare('SELECT task_key, task_key_prefix, task_sequence_number, title FROM tasks WHERE public_id = ? AND deleted_at IS NULL LIMIT 1');
                        $stmt->execute([$taskPublicId]);
                    } else {
                        $stmt = $pdo->prepare('SELECT task_key, task_key_prefix, task_sequence_number, title FROM tasks WHERE task_key = ? AND deleted_at IS NULL LIMIT 1');
                        $stmt->execute([$taskKeyParam]);
                    }
                    $task = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($task) {
                        $key = trim((string)($task['task_key'] ?? ''));
                        if ($key === '' && !empty($task['task_key_prefix']) && !empty($task['task_sequence_number'])) {
                            $key = $task['task_key_prefix'] . '-' . $task['task_sequence_number'];
                        }
                        $title = trim((string)($task['title'] ?? ''));
                        if ($key !== '' && $title !== '') {
                            $customTitle = '[' . $key . '] ' . $title . ' — TropaTT';
                        } elseif ($title !== '') {
                            $customTitle = $title . ' — TropaTT';
                        } elseif ($key !== '') {
                            $customTitle = '[' . $key . '] — TropaTT';
                        }
                    }
                }
            } catch (\Throwable) {
                // Fallback to default title
            }
        }

        $params = [
            'title' => $customTitle ?? 'Карточка задачи',
            'route' => 'task-detail',
        ];
        if ($customTitle !== null) {
            $params['custom_title'] = $customTitle;
        }

        $this->render('page/task_detail', $params);
    }
}
