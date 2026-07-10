<?php
declare(strict_types=1);

namespace Api\Controller\Idea;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Module\ModuleCronScheduler;
use PDO;

final class IdeaController extends BaseController
{
    public function list(): JsonResponse
    {
        $cache = $this->cacheApi();
        if ($cache !== null) {
            $input = $this->request()->allInput();
            ksort($input);
            $cacheKey = 'list:' . $this->cacheUserId() . ':' . md5(json_encode($input));
            $result = $cache->remember('idea', $cacheKey, 60, function () use ($input) {
                return $this->executeListQuery($input);
            });
            return $this->success('IDEAS_LIST', $this->t('idea/messages.list'), ['items' => $result['items'], 'current_user_id' => $result['current_user_id']], meta: [
                'pagination' => $result['pagination'],
            ]);
        }

        $input = $this->request()->allInput();
        $result = $this->executeListQuery($input);
        return $this->success('IDEAS_LIST', $this->t('idea/messages.list'), ['items' => $result['items'], 'current_user_id' => $result['current_user_id']], meta: [
            'pagination' => $result['pagination'],
        ]);
    }

    private function executeListQuery(array $input): array
    {
        $pdo = $this->container->get('db.pdo');
        $status = (string)($input['status'] ?? '');
        $category = (string)($input['category'] ?? '');
        $sort = (string)($input['sort'] ?? 'votes');
        $period = (string)($input['period'] ?? '');
        $limit = min(50, max(1, (int)($input['limit'] ?? 20)));
        $offset = max(0, (int)($input['offset'] ?? 0));

        $user = $this->user()['user'] ?? [];
        $userId = (int)($user['id'] ?? 0);

        $where = [];
        $params = [];
        if ($status !== '') { $where[] = 'i.status = :status'; $params['status'] = $status; }
        if ($category !== '') { $where[] = 'i.category = :category'; $params['category'] = $category; }
        if ($userId > 0) {
            $where[] = '(i.visibility = \'public\' OR i.author_user_id = :uid)';
            $params['uid'] = $userId;
        } else {
            $where[] = 'i.visibility = \'public\'';
        }
        if ($period === 'today') { $where[] = 'DATE(i.created_at) = CURDATE()'; }
        elseif ($period === 'week') { $where[] = 'i.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'; }
        elseif ($period === 'month') { $where[] = 'i.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'; }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $orderBy = match ($sort) {
            'newest' => 'i.created_at DESC',
            'oldest' => 'i.created_at ASC',
            'comments' => 'comment_count DESC, i.created_at DESC',
            default => 'i.vote_count DESC, i.created_at DESC',
        };

        $stmt = $pdo->prepare("SELECT i.*, u.full_name as author_name, u.login as author_login, u.public_id as author_public_id, (SELECT COUNT(*) FROM comments c WHERE c.entity_type = 'idea' AND c.entity_public_id = i.public_id) as comment_count FROM ideas i LEFT JOIN users u ON u.id = i.author_user_id {$whereSql} ORDER BY {$orderBy} LIMIT :limit OFFSET :offset");
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM ideas i {$whereSql}");
        foreach ($params as $k => $v) $countStmt->bindValue($k, $v);
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        return [
            'items' => $items,
            'current_user_id' => $userId,
            'pagination' => ['total' => $total, 'limit' => $limit, 'offset' => $offset],
        ];
    }

    public function get(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $idea = null;
        $cache = $this->cacheApi();
        if ($cache !== null) {
            $cacheKey = 'get:' . $this->cacheUserId() . ':' . $publicId;
            $idea = $cache->remember('idea', $cacheKey, 60, function () use ($publicId) {
                /** @var IdeaService $service */
                $service = $this->container->get('service.idea');
                return $service->get($publicId);
            });
        } else {
            /** @var IdeaService $service */
            $service = $this->container->get('service.idea');
            $idea = $service->get($publicId);
        }

        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $pdo = $this->container->get('db.pdo');
        $user = $this->user()['user'] ?? [];
        $userId = (int)($user['id'] ?? 0);
        if ($userId > 0) {
            $voteStmt = $pdo->prepare("SELECT 1 FROM idea_votes WHERE idea_id = :iid AND user_id = :uid");
            $voteStmt->execute(['iid' => $idea['id'], 'uid' => $userId]);
            $idea['user_has_voted'] = (bool)$voteStmt->fetchColumn();
        }

        return $this->success('IDEA_DETAIL', $this->t('common/messages.ok'), ['idea' => $idea, 'current_user_id' => $userId]);
    }

    public function create(): JsonResponse
    {
        $input = $this->request()->allInput();
        $title = trim((string)($input['title'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $category = trim((string)($input['category'] ?? ''));
        $region = trim((string)($input['region'] ?? ''));
        $visibility = in_array((string)($input['visibility'] ?? 'public'), ['public', 'private']) ? ($input['visibility'] ?? 'public') : 'public';
        $targetDate = trim((string)($input['target_date'] ?? '')) ?: null;

        if ($title === '') return $this->error('VALIDATION', $this->t('idea/messages.title_required'), 400);

        $user = $this->user()['user'] ?? [];
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);

        $pdo = $this->container->get('db.pdo');
        $publicId = 'idea_' . bin2hex(random_bytes(12));

        $stmt = $pdo->prepare("INSERT INTO ideas (public_id, title, description, author_user_id, category, region, visibility, target_date, created_at) VALUES (:pid, :title, :desc, :uid, :cat, :region, :vis, :target_date, NOW())");
        $stmt->execute(['pid' => $publicId, 'title' => $title, 'desc' => $description, 'uid' => $userId, 'cat' => $category, 'region' => $region, 'vis' => $visibility, 'target_date' => $targetDate]);

        try {
            if ($this->container->has('service.notification')) {
                $this->container->get('service.notification')->notifyUsers([$userId], [
                    'category' => 'ideas',
                    'title' => $this->t('idea/messages.notif_idea_created_title'),
                    'body' => $this->t('idea/messages.notif_idea_created_body') . ' "' . $title . '" ' . $this->t('idea/messages.notif_idea_created_body2'),
                    'entity_type' => 'idea',
                    'entity_public_id' => $publicId,
                    'action_code' => 'idea_created',
                    'link' => 'index.php?route=idea-detail&id=' . $publicId,
                ], $userId);
            }
        } catch (\Throwable) {}

        $this->invalidateCache('idea');

        return $this->success('IDEA_CREATED', $this->t('idea/messages.created'), ['public_id' => $publicId], status: 201);
    }

    public function update(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $pdo = $this->container->get('db.pdo');
        $stmt = $pdo->prepare("SELECT * FROM ideas WHERE public_id = :pid");
        $stmt->execute(['pid' => $publicId]);
        $idea = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $user = $this->user()['user'] ?? [];
        if ((int)$idea['author_user_id'] !== (int)($user['id'] ?? 0)) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        $input = $this->request()->allInput();
        $title = trim((string)($input['title'] ?? $idea['title']));
        $description = trim((string)($input['description'] ?? $idea['description']));
        $category = trim((string)($input['category'] ?? $idea['category']));
        $region = array_key_exists('region', $input) ? trim((string)($input['region'])) : $idea['region'];
        $visibility = in_array((string)($input['visibility'] ?? $idea['visibility']), ['public', 'private']) ? ($input['visibility'] ?? $idea['visibility'] ?? 'public') : ($idea['visibility'] ?? 'public');
        $targetDate = array_key_exists('target_date', $input) ? (trim((string)($input['target_date'])) ?: null) : ($idea['target_date'] ?? null);

        $stmt = $pdo->prepare("UPDATE ideas SET title = :title, description = :desc, category = :cat, region = :region, visibility = :vis, target_date = :td WHERE public_id = :pid");
        $stmt->execute(['title' => $title, 'desc' => $description, 'cat' => $category, 'region' => $region, 'vis' => $visibility, 'td' => $targetDate, 'pid' => $publicId]);

        $this->invalidateCache('idea');

        return $this->success('IDEA_UPDATED', $this->t('idea/messages.updated'));
    }

    public function delete(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $pdo = $this->container->get('db.pdo');
        $stmt = $pdo->prepare("SELECT * FROM ideas WHERE public_id = :pid");
        $stmt->execute(['pid' => $publicId]);
        $idea = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $user = $this->user()['user'] ?? [];
        if ((int)$idea['author_user_id'] !== (int)($user['id'] ?? 0)) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        $pdo->prepare("DELETE FROM idea_votes WHERE idea_id = :iid")->execute(['iid' => $idea['id']]);
        $pdo->prepare("DELETE FROM comments WHERE entity_type = 'idea' AND entity_public_id = :pid")->execute(['pid' => $publicId]);
        $pdo->prepare("DELETE FROM ideas WHERE public_id = :pid")->execute(['pid' => $publicId]);

        $this->invalidateCache('idea');

        return $this->success('IDEA_DELETED', $this->t('idea/messages.deleted'));
    }

    public function vote(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $pdo = $this->container->get('db.pdo');
        $stmt = $pdo->prepare("SELECT id FROM ideas WHERE public_id = :pid");
        $stmt->execute(['pid' => $publicId]);
        $ideaId = $stmt->fetchColumn();
        if (!$ideaId) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $user = $this->user()['user'] ?? [];
        $userId = (int)($user['id'] ?? 0);

        $existing = $pdo->prepare("SELECT id FROM idea_votes WHERE idea_id = :iid AND user_id = :uid");
        $existing->execute(['iid' => $ideaId, 'uid' => $userId]);

        if ($existing->fetchColumn()) {
            $pdo->prepare("DELETE FROM idea_votes WHERE idea_id = :iid AND user_id = :uid")->execute(['iid' => $ideaId, 'uid' => $userId]);
            $pdo->prepare("UPDATE ideas SET vote_count = vote_count - 1 WHERE id = :iid")->execute(['iid' => $ideaId]);
            $action = 'unvoted';
        } else {
            $pdo->prepare("INSERT INTO idea_votes (idea_id, user_id) VALUES (:iid, :uid)")->execute(['iid' => $ideaId, 'uid' => $userId]);
            $pdo->prepare("UPDATE ideas SET vote_count = vote_count + 1 WHERE id = :iid")->execute(['iid' => $ideaId]);
            $action = 'voted';
            try {
                $stmt = $pdo->prepare("SELECT author_user_id, title FROM ideas WHERE public_id = :pid");
                $stmt->execute(['pid' => $publicId]);
                $idea = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($idea && (int)$idea['author_user_id'] !== $userId && $this->container->has('service.notification')) {
                    $this->container->get('service.notification')->notifyUsers([(int)$idea['author_user_id']], [
                        'category' => 'ideas',
                        'title' => $this->t('idea/messages.notif_idea_voted_title'),
                        'body' => $this->t('idea/messages.notif_idea_voted_body') . ' "' . ($idea['title'] ?? '') . '".',
                        'entity_type' => 'idea',
                        'entity_public_id' => $publicId,
                        'action_code' => 'idea_voted',
                        'link' => 'index.php?route=idea-detail&id=' . $publicId,
                    ], $userId);
                }
            } catch (\Throwable) {}
        }

        return $this->success('IDEA_VOTED', $this->t('idea/messages.' . $action), ['action' => $action]);
    }

    public function updateStatus(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $newStatus = (string)($params['status'] ?? '');

        if ($publicId === '' || $newStatus === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $allowed = ['new', 'under_review', 'approved', 'rejected', 'in_progress', 'completed'];
        if (!in_array($newStatus, $allowed, true)) return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $pdo = $this->container->get('db.pdo');
        $stmt = $pdo->prepare("SELECT author_user_id FROM ideas WHERE public_id = :pid");
        $stmt->execute(['pid' => $publicId]);
        $idea = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $user = $this->user()['user'] ?? [];
        if ((int)$idea['author_user_id'] !== (int)($user['id'] ?? 0) && !(bool)($user['is_root'] ?? false)) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        $pdo->prepare("UPDATE ideas SET status = :status WHERE public_id = :pid")->execute(['status' => $newStatus, 'pid' => $publicId]);

        try {
            $stmt = $pdo->prepare("SELECT author_user_id, title FROM ideas WHERE public_id = :pid");
            $stmt->execute(['pid' => $publicId]);
            $idea = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($idea && $this->container->has('service.notification')) {
                $this->container->get('service.notification')->notifyUsers([(int)$idea['author_user_id']], [
                    'category' => 'ideas',
                    'title' => $this->t('idea/messages.notif_idea_status_changed_title'),
                    'body' => $this->t('idea/messages.notif_idea_status_changed_body1') . ' "' . ($idea['title'] ?? '') . '" ' . $this->t('idea/messages.notif_idea_status_changed_body2') . ' "' . $newStatus . '".',
                    'entity_type' => 'idea',
                    'entity_public_id' => $publicId,
                    'action_code' => 'idea_status_changed',
                    'link' => 'index.php?route=idea-detail&id=' . $publicId,
                ]);
            }
        } catch (\Throwable) {}

        $this->invalidateCache('idea');

        return $this->success('IDEA_STATUS_UPDATED', $this->t('idea/messages.status_updated'), ['status' => $newStatus]);
    }

    public function aiAnalyze(array $params = []): JsonResponse
    {
        $this->requireFeatureEnabled();
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $service = $this->container->get('service.idea');
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);
        $pdo = $this->container->get('db.pdo');
        $this->ensureIdeaWorkflowTables($pdo);
        $pdo = $this->container->get('db.pdo');
        $this->ensureIdeaWorkflowTables($pdo);
        $pdo = $this->container->get('db.pdo');
        $this->ensureIdeaWorkflowTables($pdo);

        $ideaId = (int)$idea['id'];
        $status = $idea['status'] ?? 'draft';
        $pdo = $this->container->get('db.pdo');

        // Fast path: return current state if already progressing with questions
        if ($status !== 'draft') {
            $existingQuestions = $service->getQuestions($ideaId, $this->getCurrentCycleId($ideaId));
            if (count($existingQuestions) > 0) {
                $coverage = json_decode($idea['coverage_json'] ?? '{}', true) ?: [];
                $recAction = $this->getRecommendedNextAction($ideaId, $coverage);
                return $this->success('QUESTIONS_NEEDED', $this->t('idea/messages.current_state'), [
                    'status' => $status,
                    'code' => 'QUESTIONS_NEEDED',
                    'active_questions' => $existingQuestions,
                    'active_questions_count' => count($existingQuestions),
                    'recommended_next_action' => $recAction,
                    'message' => $status === 'ready_for_analysis'
                        ? $this->t('idea/messages.data_sufficient_click_analyze')
                        : $this->t('idea/messages.answer_questions_click_send'),
                    'available_actions' => $status === 'ready_for_analysis'
                        ? ['run_analysis', 'edit_idea']
                        : ['answer_questions', 'edit_idea'],
                ]);
            }
            // Status says questioning/ready but no questions — reset to draft
            $pdo->prepare("UPDATE ideas SET status = 'draft' WHERE id = :id")->execute(['id' => $ideaId]);
            $status = 'draft';
        }

        // SAFE MODE: deterministic questions without AI calls
        if ($this->isSafeModeEnabled()) {
            $questions = $this->buildSafeModeQuestions($ideaId);
            $service->saveQuestions($ideaId, 1, $questions);
            $pdo->prepare("UPDATE ideas SET status = 'questioning', ai_analysis_at = NOW() WHERE id = :id")
                ->execute(['id' => $ideaId]);

            $savedQuestions = $service->getQuestions($ideaId, 1);
            return $this->success('IDEA_AI_ANALYZED', $this->t('idea/messages.questions_formed_safe_mode'), [
                'status' => 'questioning',
                'code' => 'QUESTIONS_NEEDED',
                'active_questions' => $savedQuestions,
                'active_questions_count' => count($savedQuestions),
                'message' => $this->t('idea/messages.answer_for_quality_analysis'),
                'available_actions' => ['answer_questions', 'edit_idea'],
            ]);
        }

        // Normal AI path: single unified call for classification + questions
        $user = $this->user()['user'] ?? [];
        $title = $this->stripTags($idea['title']);
        $description = $this->stripTags($idea['description'] ?? '');
        $createdAt = $idea['created_at'] ?? date('Y-m-d H:i:s');
        $currentDate = date('Y-m-d');

        set_time_limit(30);
        try {
            $ai = $this->container->get('service.ai_action');
        } catch (\Throwable $e) {
            return $this->error('AI_SERVICE_UNAVAILABLE', $this->t('idea/messages.ai_service_unavailable'), 503);
        }

        try {
            // Single AI call: classify + analysis_map + questions combined
            $result = $ai->execute('idea_initial_questions', [
                'title' => $title, 'description' => $description,
                'created_at' => $createdAt, 'current_date' => $currentDate,
            ], $user);
            $data = $this->extractStructuredResult($result);
            ai_diag_log("[AI_ANALYZE_DEBUG] result_ok=".($result['ok']?'1':'0')." code=".($result['code']??'null')." data_keys=".(is_array($data)?implode(',',array_keys($data)):'NULL'));

            // Fallback: if AI response parsing failed, try parsing raw text
            if ($data === null) {
                $rawText = $result['result']['preview']['summary'] ?? '';
                if ($rawText !== '') {
                    $data = json_decode($rawText, true);
                    if (!is_array($data)) $data = null;
                }
            }

            if ($data !== null && is_array($data)) {
                // Save classification
                if (isset($data['idea_type'])) {
                    $service->saveClassification($ideaId, $data);
                    $service->saveAnalysis($ideaId, 'classification', $data);
                }
                // Save analysis_map data
                if (isset($data['known_facts']) || isset($data['unknowns'])) {
                    $mapData = [
                        'coverage' => $data['coverage'] ?? [],
                        'critical_gaps' => $data['critical_gaps'] ?? [],
                        'recommended_next_action' => $data['recommended_next_action'] ?? 'ask_questions',
                        'assumptions' => $data['assumptions'] ?? [],
                    ];
                    $service->saveAnalysisMap($ideaId, $mapData);
                    $this->saveAnalysisMapNormalized($ideaId, $mapData, $service);
                }
                // Save questions
                $questions = $data['questions'] ?? [];
                if (!is_array($questions) || $questions === []) {
                    $questions = $this->parseAiQuestions($result, $idea);
                }
                if (is_array($questions) && $questions !== []) {
                    $service->saveQuestions($ideaId, 1, $questions);
                }
            }

            $nextAction = $data['recommended_next_action'] ?? 'ask_questions';
            $newStatus = ($nextAction === 'ready_for_analysis') ? 'ready_for_analysis' : 'questioning';

            $pdo->prepare("UPDATE ideas SET status = :s, ai_analysis_at = NOW() WHERE id = :id")
                ->execute(['s' => $newStatus, 'id' => $ideaId]);

            $iterId = 'iai_' . bin2hex(random_bytes(8));
            $pdo->prepare("INSERT INTO idea_ai_iterations (public_id, idea_id, iteration, type, request_payload, response_payload, created_at) VALUES (:pid, :iid, 1, 'analyze', :req, :resp, NOW())")
                ->execute(['pid' => $iterId, 'iid' => $ideaId, 'req' => json_encode(['title' => $title, 'description' => $description, 'created_at' => $createdAt, 'current_date' => $currentDate, 'data' => $data], JSON_UNESCAPED_UNICODE), 'resp' => json_encode($result, JSON_UNESCAPED_UNICODE)]);

            return $this->success('IDEA_AI_ANALYZED', $this->t('idea/messages.analysis_completed'), [
                'status' => $newStatus,
                'visible_mode' => ($newStatus === 'questioning') ? 'questions' : 'ready_for_analysis',
                'code' => 'QUESTIONS_NEEDED',
                'active_questions' => $service->getQuestions($ideaId, 1),
                'message' => ($newStatus === 'questioning')
                    ? $this->t('idea/messages.answer_questions_then_send')
                    : $this->t('idea/messages.data_sufficient_can_analyze'),
                'available_actions' => ($newStatus === 'questioning')
                    ? ['answer_questions', 'edit_idea']
                    : ['run_analysis', 'edit_idea'],
            ]);
        } catch (\Throwable $e) {
            $reqId = bin2hex(random_bytes(6));
            ai_diag_log("[AI_ANALYZE_FAILED][{$reqId}] {$e->getMessage()}");
            return $this->error('AI_ANALYSIS_FAILED', $this->t('idea/messages.ai_provider_not_responding'), 503);
        }
    }

    private function buildSafeModeQuestions(int $ideaId): array
    {
        $pdo = $this->container->get('db.pdo');
        $pdo->prepare("DELETE FROM idea_questions WHERE idea_id = :id AND cycle_id = 1")->execute(['id' => $ideaId]);
        return [
            ['question_text' => $this->t('idea/messages.sm_q1_text'), 'reason' => $this->t('idea/messages.sm_q1_reason'), 'question_type' => 'single_choice', 'options' => [['key' => 'local', 'label' => $this->t('idea/messages.sm_q1_local'), 'description' => null], ['key' => 'regional', 'label' => $this->t('idea/messages.sm_q1_regional'), 'description' => null], ['key' => 'national', 'label' => $this->t('idea/messages.sm_q1_national'), 'description' => null], ['key' => 'unknown', 'label' => $this->t('idea/messages.sm_not_sure'), 'description' => null]], 'allow_custom_answer' => true, 'allow_unknown' => true, 'required' => true, 'dimension' => 'operations', 'impact' => 'critical', 'sort_order' => 1],
            ['question_text' => $this->t('idea/messages.sm_q2_text'), 'reason' => $this->t('idea/messages.sm_q2_reason'), 'question_type' => 'single_choice', 'options' => [['key' => 'minimal', 'label' => $this->t('idea/messages.sm_q2_minimal'), 'description' => null], ['key' => 'small', 'label' => $this->t('idea/messages.sm_q2_small'), 'description' => null], ['key' => 'medium', 'label' => $this->t('idea/messages.sm_q2_medium'), 'description' => null], ['key' => 'unknown', 'label' => $this->t('idea/messages.sm_not_sure'), 'description' => null]], 'allow_custom_answer' => true, 'allow_unknown' => true, 'required' => true, 'dimension' => 'finance', 'impact' => 'critical', 'sort_order' => 2],
            ['question_text' => $this->t('idea/messages.sm_q3_text'), 'reason' => $this->t('idea/messages.sm_q3_reason'), 'question_type' => 'multiple_choice', 'options' => [['key' => 'individuals', 'label' => $this->t('idea/messages.sm_q3_individuals'), 'description' => null], ['key' => 'businesses', 'label' => $this->t('idea/messages.sm_q3_businesses'), 'description' => null], ['key' => 'both', 'label' => $this->t('idea/messages.sm_q3_both'), 'description' => null], ['key' => 'unknown', 'label' => $this->t('idea/messages.sm_not_sure'), 'description' => null]], 'allow_custom_answer' => true, 'allow_unknown' => true, 'required' => true, 'dimension' => 'target_audience', 'impact' => 'high', 'sort_order' => 3],
            ['question_text' => $this->t('idea/messages.sm_q4_text'), 'reason' => $this->t('idea/messages.sm_q4_reason'), 'question_type' => 'single_choice', 'options' => [['key' => 'experienced', 'label' => $this->t('idea/messages.sm_q4_experienced'), 'description' => null], ['key' => 'no_experience', 'label' => $this->t('idea/messages.sm_q4_no_experience'), 'description' => null], ['key' => 'have_knowledge', 'label' => $this->t('idea/messages.sm_q4_have_knowledge'), 'description' => null], ['key' => 'unknown', 'label' => $this->t('idea/messages.sm_not_sure'), 'description' => null]], 'allow_custom_answer' => true, 'allow_unknown' => true, 'required' => true, 'dimension' => 'resources', 'impact' => 'high', 'sort_order' => 4],
            ['question_text' => $this->t('idea/messages.sm_q5_text'), 'reason' => $this->t('idea/messages.sm_q5_reason'), 'question_type' => 'text', 'options' => [], 'allow_custom_answer' => true, 'allow_unknown' => true, 'required' => false, 'dimension' => 'risks', 'impact' => 'medium', 'sort_order' => 5],
        ];
    }

    private function buildSafeModeAnalysisBlocks(array $idea, array $answersSummary): array
    {
        $title = $idea['title'] ?? '';
        $desc = strip_tags($idea['description'] ?? '');

        $realKnownFacts = [];
        $realUnknowns = [];
        foreach ($answersSummary as $a) {
            $l = $a['label'] ?? '';
            $t = $a['answer_text'] ?? '';
            $k = $a['answer_key'] ?? '';
            if ($l === $this->t('idea/messages.sm_not_sure') || $k === 'unknown') {
                $realUnknowns[] = $a['question'];
            } elseif ($l !== '') {
                $realKnownFacts[] = $a['question'] . ' — ' . $l;
            } elseif ($t !== '') {
                $realKnownFacts[] = $a['question'] . ' — ' . $t;
            }
        }
        if (!$realKnownFacts) $realKnownFacts = [$title];
        if (!$realUnknowns) $realUnknowns = [$this->t('idea/messages.sm_exact_demand'), $this->t('idea/messages.sm_budget'), $this->t('idea/messages.sm_competitors')];
        if (count($realKnownFacts) > 6) $realKnownFacts = array_slice($realKnownFacts, 0, 6);
        if (count($realUnknowns) > 6) $realUnknowns = array_slice($realUnknowns, 0, 6);

        return [
            'main_analysis' => ['_demo_mode' => true, 'summary' => "Идея «{$title}» " . $this->t('idea/messages.sm_analysis_summary') . ' ' . $desc, 'idea_interpretation' => $desc, 'strengths' => [$this->t('idea/messages.sm_idea_described')], 'weaknesses' => [$this->t('idea/messages.sm_no_detailed_data')], 'key_hypotheses' => [$this->t('idea/messages.sm_check_demand')], 'first_checks' => [$this->t('idea/messages.sm_study_competitors')], 'preliminary_recommendation' => 'validate_first', 'confidence' => 'low'],
            'final_report' => ['_demo_mode' => true, 'executive_summary' => "Идея «{$title}» " . $this->t('idea/messages.sm_final_needs_verification'), 'known_facts' => $realKnownFacts, 'unknowns' => $realUnknowns, 'assumptions' => [$this->t('idea/messages.sm_market_exists')], 'strengths' => [$this->t('idea/messages.sm_idea_described')], 'weaknesses' => [$this->t('idea/messages.sm_few_data')], 'critical_findings' => [$this->t('idea/messages.sm_check_demand_before')], 'top_risks' => [['title' => $this->t('idea/messages.sm_insufficient_data'), 'why_it_matters' => $this->t('idea/messages.sm_without_data_hard'), 'mitigation' => $this->t('idea/messages.sm_answer_questions_action')]], 'pitfalls' => [$this->t('idea/messages.sm_no_market_analysis')], 'opportunities' => [$this->t('idea/messages.sm_study_demand_district')], 'validation_plan_short' => [['hypothesis' => $this->t('idea/messages.sm_demand_exists'), 'how_to_test' => $this->t('idea/messages.sm_survey_audience'), 'success_metric' => $this->t('idea/messages.sm_positive_answers')]], 'recommended_path' => $this->t('idea/messages.sm_check_competitors_path'), 'next_3_actions' => [$this->t('idea/messages.sm_action1'), $this->t('idea/messages.sm_action2'), $this->t('idea/messages.sm_action3')], 'decision' => 'validate_first', 'confidence' => 'low'],
            'critical_analysis' => ['_demo_mode' => true, 'main_doubts' => [$this->t('idea/messages.sm_no_demand_data')], 'what_to_check_before_investing' => [$this->t('idea/messages.sm_check_demand_label'), $this->t('idea/messages.sm_check_competitors_label')], 'confidence' => 'low'],
        ];
    }

    private function saveAnalysisMapNormalized(int $ideaId, array $analysisMap, object $service): void
    {
        $copy = $analysisMap;
        if (isset($copy['coverage']) && is_array($copy['coverage'])) {
            foreach ($copy['coverage'] as $k => $v) {
                if (is_numeric($v)) {
                    $fv = (float)$v;
                    if ($fv > 0 && $fv <= 1) $copy['coverage'][$k] = (int)round($fv * 100);
                    elseif ($fv > 1 && $fv <= 100) $copy['coverage'][$k] = (int)round($fv);
                }
            }
        }
        $service->saveAnalysis($ideaId, 'analysis_map', $copy);
    }

    private function saveAnalysis(\PDO $pdo, int $ideaId, string $type, mixed $result): void
    {
        $svc = $this->container->get('service.idea');
        $svc->saveAnalysis($ideaId, $type, $result);
    }

    /**
     * Parse questions from AI response. Handles both structured JSON and markdown text.
     * @return array<int,array<string,mixed>>
     */
    private function parseAiQuestions(mixed $result, array $idea): array
    {
        $questions = $result['questions'] ?? $result['result']['questions'] ?? [];
        if (is_array($questions) && $questions !== []) return $questions;

        $text = is_array($result) ? (($result['result']['preview']['summary'] ?? $result['preview']['summary'] ?? '')) : (string)$result;
        if ($text === '') return [];

        $qs = [];
        $numbered = '/[\(]?(\d+)[\)\.]\s*\*\*(.*?)\*\*/s';
        if (!preg_match_all($numbered, $text, $matches, PREG_SET_ORDER)) return $qs;

        $segments = [];
        $count = count($matches);
        for ($i = 0; $i < $count; $i++) {
            $start = strpos($text, $matches[$i][0]) + strlen($matches[$i][0]);
            $end = ($i + 1 < $count) ? strpos($text, $matches[$i + 1][0]) : strlen($text);
            $segment = substr($text, $start, $end - $start);
            $segments[] = ['title' => trim($matches[$i][2]), 'segment' => $segment];
        }

        foreach ($segments as $seg) {
            $opts = $this->extractOptions($seg['segment']);
            $qs[] = [
                'question_text' => $seg['title'],
                'reason' => '',
                'question_type' => $opts ? 'single_choice' : 'text',
                'options' => $opts,
                'allow_custom_answer' => true,
                'allow_unknown' => true,
                'required' => true,
                'dimension' => 'other',
                'impact' => 'medium',
                'sort_order' => count($qs),
            ];
        }
        return $qs;
    }

    /** @return array<int,array{key:string,label:string,description:null}> */
    private function extractOptions(string $text): array
    {
        $opts = [];

        $p1 = '/-\s*([A-ZА-ЯЁ]+)\)\s*(.+?)(?=\n|$)/u';
        if (preg_match_all($p1, $text, $optM, PREG_SET_ORDER) && count($optM) >= 2) {
            foreach ($optM as $om) {
                $label = trim($om[2]);
                $prefix = trim($om[1]);
                if ($label === '') continue;
                $key = (mb_strlen($prefix) === 1 && ctype_alpha($prefix)) ? strtolower($prefix) : $this->optionKeyFromLabel($label);
                $opts[] = ['key' => $key, 'label' => $label, 'description' => null];
            }
            return $opts;
        }

        $p2 = '/-\s*(.+?)(?=\n-|\n\n|$)/us';
        if (preg_match_all($p2, $text, $optM, PREG_SET_ORDER) && count($optM) >= 2) {
            foreach ($optM as $om) {
                $label = trim($om[1]);
                if ($label === '') continue;
                $opts[] = ['key' => $this->optionKeyFromLabel($label), 'label' => $label, 'description' => null];
            }
            return $opts;
        }

        return $opts;
    }

    private function optionKeyFromLabel(string $label): string
    {
        $translit = $this->transliterate($label);
        $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($translit));
        $slug = trim($slug, '_');
        if ($slug === '' || preg_match('/^[a-z]$/', $slug)) {
            return 'opt_' . bin2hex(random_bytes(4));
        }
        return mb_substr($slug, 0, 48);
    }

    private function transliterate(string $text): string
    {
        static $map = null;
        if ($map === null) {
            $map = [
                'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo',
                'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
                'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
                'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
                'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
                ' ' => '_', '/' => '_', '-' => '_', ',' => '', '.' => '', '(' => '', ')' => '',
            ];
        }
        return strtr(mb_strtolower($text), $map);
    }

    public function aiRefine(array $params = []): JsonResponse
    {
        $this->requireFeatureEnabled();
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $pdo = $this->container->get('db.pdo');
        $stmt = $pdo->prepare("SELECT * FROM ideas WHERE public_id = :pid");
        $stmt->execute(['pid' => $publicId]);
        $idea = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $ideaId = (int)$idea['id'];
        $input = $this->request()->allInput();
        $answers = $input['answers'] ?? $input['questions_answers'] ?? [];
        $region = (string)($input['region'] ?? $input['country'] ?? 'ru');

        $iterStmt = $pdo->prepare("SELECT MAX(iteration) as max_iter FROM idea_ai_iterations WHERE idea_id = :iid");
        $iterStmt->execute(['iid' => $idea['id']]);
        $iteration = ((int)$iterStmt->fetchColumn()) + 1;

        // Save answers BEFORE AI call — never lose user input on AI failure
        $reqPayload = json_encode(['questions_answers' => $answers, 'region' => $region], JSON_UNESCAPED_UNICODE);
        $answerSvc = $this->container->get('service.idea');

        $cycleStmt = $pdo->prepare("SELECT MAX(cycle_id) as max_cycle FROM idea_questions WHERE idea_id = :iid");
        $cycleStmt->execute(['iid' => $idea['id']]);
        $currentCycle = (int)($cycleStmt->fetchColumn() ?: 1);

        if (is_array($answers) && $answers !== []) {

            $qStmt = $pdo->prepare("SELECT id, question_text, options_json FROM idea_questions WHERE idea_id = :iid AND cycle_id = :cycle");
            $qStmt->execute(['iid' => $idea['id'], 'cycle' => $currentCycle]);
            $qById = [];
            $qOptions = [];
            $qByText = [];
            foreach ($qStmt->fetchAll(PDO::FETCH_ASSOC) as $eq) {
                $qById[(int)$eq['id']] = (int)$eq['id'];
                $opts = json_decode($eq['options_json'] ?? '[]', true);
                $qOptions[(int)$eq['id']] = is_array($opts) ? $opts : [];
                $qText = (string)($eq['question_text'] ?? '');
                $qNorm = $this->normalizeQuestionText($qText);
                if ($qNorm !== '') {
                    $qByText[$qNorm] = (int)$eq['id'];
                }
            }

            $normalizedAnswers = [];
            foreach ($answers as $qa) {
                $qId = (int)($qa['question_id'] ?? 0);
                $selKey = $qa['selected_option_key'] ?? null;
                $selOpts = $qa['selected_options'] ?? [];
                $ansText = $qa['answer_text'] ?? $qa['answer'] ?? null;
                $isCustom = !empty($qa['is_custom']);
                $isUnknown = !empty($qa['is_unknown']);

                if (($qId <= 0 || !isset($qById[$qId])) && !empty($qa['question_text'])) {
                    $fromText = $qByText[$this->normalizeQuestionText((string)$qa['question_text'])] ?? 0;
                    if ($fromText > 0) {
                        $qId = $fromText;
                    }
                }

                if ($qId > 0 && isset($qById[$qId])) {
                    // Validate selected_option_key is in question's options
                    $validKeys = [];
                    foreach ($qOptions[$qId] ?? [] as $opt) {
                        $validKeys[] = $opt['key'] ?? '';
                    }
                    if ($selKey !== null && $selKey !== '' && !in_array($selKey, $validKeys, true)) {
                        continue;
                    }
                    if ($isUnknown && !in_array('unknown', $validKeys, true)) {
                        $selKey = 'unknown';
                    }
                    if (($ansText === null || trim((string)$ansText) === '') && $selKey !== null && $selKey !== '') {
                        foreach ($qOptions[$qId] ?? [] as $opt) {
                            if (($opt['key'] ?? '') === $selKey) {
                                $ansText = (string)($opt['label'] ?? $selKey);
                                break;
                            }
                        }
                    }
                    if (($ansText === null || trim((string)$ansText) === '') && is_array($selOpts) && $selOpts !== []) {
                        $labels = [];
                        foreach ($selOpts as $sk) {
                            foreach ($qOptions[$qId] ?? [] as $opt) {
                                if (($opt['key'] ?? '') === $sk) {
                                    $labels[] = (string)($opt['label'] ?? $sk);
                                    break;
                                }
                            }
                        }
                        if ($labels !== []) {
                            $ansText = implode(', ', $labels);
                        }
                    }
                    $normalizedAnswers[] = [
                        'question_id' => $qId,
                        'selected_option_key' => $selKey,
                        'selected_options' => $selOpts,
                        'answer_text' => $ansText,
                        'is_custom' => $isCustom,
                        'is_unknown' => $isUnknown,
                    ];
                }
            }
            if ($normalizedAnswers !== []) {
                $answerSvc->saveAnswers((int)$idea['id'], $normalizedAnswers);
            }
        }

        // Step 8-9: send ALL previous questions+answers to AI for decide_next_step
        try {
        set_time_limit(0);
            $user = $this->user()['user'] ?? [];
            $ai = $this->container->get('service.ai_action');

            // Collect ALL questions and answers from every cycle
            $allQuestions = $answerSvc->getQuestions((int)$idea['id']);
            $allAnswers = [];
            foreach ($allQuestions as $q) {
                $la = $q['last_answer'] ?? null;
                if ($la) {
                    $allAnswers[] = [
                        'question_id' => (int)$q['id'],
                        'question_text' => $q['question_text'] ?? '',
                        'cycle_id' => (int)($q['cycle_id'] ?? 1),
                        'selected_option_key' => $la['selected_option_key'] ?? null,
                        'selected_option_label' => $la['selected_option_label'] ?? null,
                        'answer_text' => $la['answer_text'] ?? null,
                        'is_custom' => (bool)($la['is_custom'] ?? false),
                        'is_unknown' => (bool)($la['is_unknown'] ?? false),
                    ];
                }
            }

            $title = $this->stripTags($idea['title']);
            $desc = $this->stripTags($idea['description'] ?? '');
            $createdAt = $idea['created_at'] ?? date('Y-m-d H:i:s');
            $currentDate = date('Y-m-d');

            // Collect ALL questions (without answers) for context
            $previousQuestions = [];
            foreach ($allQuestions as $q) {
                $previousQuestions[] = [
                    'cycle_id' => (int)($q['cycle_id'] ?? 1),
                    'question_text' => $q['question_text'] ?? '',
                    'question_type' => $q['question_type'] ?? '',
                ];
            }

            // AI decides: more questions or enough data
            $processResult = $ai->execute('idea_process_answers', [
                'title' => $title,
                'description' => $desc,
                'created_at' => $createdAt,
                'current_date' => $currentDate,
                'answers' => $allAnswers,
                'previous_questions' => $previousQuestions,
                'cycle' => $currentCycle,
                'total_cycles' => $currentCycle,
                'total_questions_asked' => count($previousQuestions),
            ], $user);
            $this->saveAnalysis($pdo, (int)$idea['id'], 'answers_processing', $processResult);

            $pr = $this->extractStructuredResult($processResult) ?? $processResult;
            $readyForAnalysis = (bool)($pr['ready_for_analysis'] ?? false);
            $needMoreQuestions = (bool)($pr['need_more_questions'] ?? false);

            // AI decided: data is sufficient
            if ($readyForAnalysis) {
                $pdo->prepare("UPDATE ideas SET status = 'ready_for_analysis' WHERE id = :id")
                    ->execute(['id' => $idea['id']]);
                        return $this->success('IDEA_AI_REFINED', $this->t('idea/messages.enough_data'), [
                    'result' => [
                        'ready_for_analysis' => true,
                        'summary_for_user' => $pr['summary_for_user'] ?? $this->t('idea/messages.enough_data_for_analysis'),
                    ],
                    'active_questions' => [],
                    'status' => 'ready_for_analysis',
                    'next_action' => 'ready_for_analysis',
                    'message' => $this->t('idea/messages.enough_data_can_analyze'),
                    'summary_for_user' => $pr['summary_for_user'] ?? '',
                    'available_actions' => ['run_analysis', 'edit_idea'],
                    'iteration' => $iteration,
                ]);
            }

            // AI decided: need more questions — generate next cycle with expanding context
            if ($needMoreQuestions) {
                $nextCycle = $currentCycle + 1;
                $qResult = $ai->execute('idea_questions', [
                    'title' => $title,
                    'description' => $desc,
                    'created_at' => $createdAt,
                    'current_date' => $currentDate,
                    'previous_answers' => $allAnswers,
                    'previous_questions' => $previousQuestions,
                    'classification' => json_decode($idea['known_facts_json'] ?? '{}', true),
                    'critical_gaps' => $pr['critical_gaps'] ?? [],
                    'coverage' => $pr['coverage'] ?? [],
                    'cycle' => $nextCycle,
                    'iteration' => $iteration,
                ], $user);
                $qData = $this->extractStructuredResult($qResult);
                $nextQuestions = $qData['questions'] ?? [];
                if (is_array($nextQuestions) && $nextQuestions !== []) {
                    $existingQuestionTexts = [];
                    foreach ($previousQuestions as $pq) {
                        $existingQuestionTexts[$this->normalizeQuestionText((string)($pq['question_text'] ?? ''))] = true;
                    }
                    $uniqueNextQuestions = [];
                    foreach ($nextQuestions as $nq) {
                        $rawText = (string)($nq['question_text'] ?? '');
                        $normText = $this->normalizeQuestionText($rawText);
                        if ($normText === '' || isset($existingQuestionTexts[$normText])) {
                            continue;
                        }
                        $existingQuestionTexts[$normText] = true;
                        $uniqueNextQuestions[] = $nq;
                    }

                    if ($uniqueNextQuestions === []) {
                        $pdo->prepare("UPDATE ideas SET status = 'ready_for_analysis' WHERE id = :id")
                            ->execute(['id' => $idea['id']]);
                return $this->success('IDEA_AI_REFINED', $this->t('idea/messages.enough_data'), [

                            'result' => ['ready_for_analysis' => true, 'summary_for_user' => $this->t('idea/messages.no_new_questions_remaining')],
                            'active_questions' => [],
                            'status' => 'ready_for_analysis',
                            'next_action' => 'ready_for_analysis',
                            'message' => $this->t('idea/messages.no_new_questions_can_analyze'),
                            'available_actions' => ['run_analysis', 'edit_idea'],
                            'iteration' => $iteration,
                        ]);
                    }

                    $answerSvc->saveQuestions((int)$idea['id'], $nextCycle, $uniqueNextQuestions);
                    $pdo->prepare("UPDATE ideas SET status = 'questioning' WHERE id = :id")
                        ->execute(['id' => $idea['id']]);
                    return $this->success('IDEA_AI_REFINED', $this->t('idea/messages.need_clarification'), [
                        'result' => [
                            'ready_for_analysis' => false,
                            'new_questions' => $uniqueNextQuestions,
                            'summary_for_user' => $pr['summary_for_user'] ?? $this->t('idea/messages.answers_saved_need_clarification'),
                        ],
                        'active_questions' => $answerSvc->getQuestions((int)$idea['id'], $nextCycle),
                        'status' => 'questioning',
                        'next_action' => 'answer_questions',
                        'message' => $pr['summary_for_user'] ?? $this->t('idea/messages.answers_saved_need_more_clarification'),
                        'summary_for_user' => $pr['summary_for_user'] ?? '',
                        'available_actions' => ['answer_questions'],
                        'iteration' => $iteration,
                    ]);
                }
            }

            // Neither ready nor need_more: fallback
            $pdo->prepare("UPDATE ideas SET status = 'ready_for_analysis' WHERE id = :id")
                ->execute(['id' => $idea['id']]);
            return $this->success('IDEA_AI_REFINED', $this->t('idea/messages.answers_saved_label'), [
                'result' => ['ready_for_analysis' => true, 'summary_for_user' => $this->t('idea/messages.answers_saved_label')],
                'active_questions' => [],
                'status' => 'ready_for_analysis',
                'next_action' => 'ready_for_analysis',
                'message' => $this->t('idea/messages.answers_saved_can_analyze'),
                'available_actions' => ['run_analysis', 'edit_idea'],
                'iteration' => $iteration,
            ]);
        } catch (\Throwable $e) {
            $reqId = bin2hex(random_bytes(6));
            ai_diag_log("[AI_REFINE_FAILED][{$reqId}] {$e->getMessage()}");
            return $this->error('AI_REFINE_FAILED', $this->t('idea/messages.refine_failed'), 503);
        }
    }

    private function normalizeQuestionText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text) ?? '';
        return trim($text);
    }

    public function aiCreateTasks(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $input = $this->request()->allInput();
        $tasks = $input['tasks'] ?? [];

        if ($publicId === '' || !is_array($tasks) || $tasks === []) {
            return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);
        }

        $user = $this->user()['user'] ?? [];
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);

        $pdo = $this->container->get('db.pdo');
        $stmt = $pdo->prepare("SELECT id FROM ideas WHERE public_id = :pid");
        $stmt->execute(['pid' => $publicId]);
        $ideaId = (int)$stmt->fetchColumn();
        if ($ideaId <= 0) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $pdo->beginTransaction();
        $created = [];
        $idMap = [];

        try {
            foreach ($tasks as $i => $task) {
                if (empty($task['title'])) continue;
                $taskPid = 'task_' . bin2hex(random_bytes(8));
                $now = date('Y-m-d H:i:s');
                $deadlineDays = max(0, (int)($task['deadline_days'] ?? 0));
                $dueAt = $deadlineDays > 0 ? date('Y-m-d H:i:s', strtotime($now . ' +' . $deadlineDays . ' days')) : null;

                $parentId = null;
                if (isset($task['parent_index']) && $task['parent_index'] !== null) {
                    $parentId = $idMap[(int)$task['parent_index']] ?? null;
                }

                $pdo->prepare("INSERT INTO tasks (public_id, title, description, status_code, priority_code, parent_task_id, due_at, creator_user_id, created_at, updated_at) VALUES (:pid, :title, :desc, 'new', :pri, :parent, :due, :uid, :now, :now)")
                    ->execute([
                        'pid' => $taskPid,
                        'title' => trim((string)($task['title'])),
                        'desc' => trim((string)($task['description'] ?? '')),
                        'pri' => in_array($task['priority'] ?? '', ['urgent','high','normal','low'], true) ? $task['priority'] : 'normal',
                        'parent' => $parentId,
                        'due' => $dueAt,
                        'uid' => $userId,
                        'now' => $now,
                    ]);

                $createdId = (int)$pdo->lastInsertId();
                $idMap[$i] = $createdId;
                $created[] = ['public_id' => $taskPid, 'title' => $task['title'], 'crm_task_id' => $createdId];

                // Save to task drafts with crm_task_id (skip if already created)
                $dupCheck = $pdo->prepare("SELECT id FROM idea_task_drafts WHERE idea_id = :iid AND crm_task_id = :tid");
                $dupCheck->execute(['iid' => $ideaId, 'tid' => $createdId]);
                if (!$dupCheck->fetchColumn()) {
                    $tdPid = 'itd_' . bin2hex(random_bytes(6));
                    $pdo->prepare("INSERT INTO idea_task_drafts (public_id, idea_id, parent_id, crm_task_id, title, description, type, priority, stage, sort_order, created_at) VALUES (:pid, :iid, :parent, :tid, :title, :desc, 'implementation', :pri, 'launch', :sort, NOW())")
                        ->execute(['pid' => $tdPid, 'iid' => $ideaId, 'parent' => $parentId, 'tid' => $createdId, 'title' => $task['title'], 'desc' => $task['description'] ?? '', 'pri' => $task['priority'] ?? 'normal', 'sort' => $i]);
                }
            }

            // Update parent_task_id for children that were inserted before parent
            foreach ($tasks as $i => $task) {
                if (isset($task['parent_index']) && $task['parent_index'] !== null && isset($idMap[$i])) {
                    $resolvedParent = $idMap[(int)$task['parent_index']] ?? null;
                    if ($resolvedParent && $resolvedParent !== $idMap[$i]) {
                        $pdo->prepare("UPDATE tasks SET parent_task_id = :parent WHERE id = :id")
                            ->execute(['parent' => $resolvedParent, 'id' => $idMap[$i]]);
                    }
                }
            }

            $pdo->commit();
            return $this->success('TASKS_CREATED', $this->t('idea/messages.tasks_created'), ['tasks' => $created], 201);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return $this->error('CREATE_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * GET /ideas/{id}/debug-log — full debug snapshot for the idea.
     */
    public function debugLog(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);
        $service = $this->container->get('service.idea');
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);
        $ideaId = (int)$idea['id'];
        $pdo = $this->container->get('db.pdo');

        // DELETE: clear all AI iterations for this idea
        if (($this->request()->method ?? '') === 'DELETE') {
            $pdo->prepare("DELETE FROM idea_ai_iterations WHERE idea_id = :iid")->execute(['iid' => $ideaId]);
            return $this->success('DEBUG_CLEARED', $this->t('idea/messages.debug_logs_cleared'));
        }

        $iterStmt = $pdo->prepare("SELECT * FROM idea_ai_iterations WHERE idea_id = :iid ORDER BY created_at ASC");
        $iterStmt->execute(['iid' => $ideaId]);
        $iterations = $iterStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $questions = $service->getQuestions($ideaId);
        $analyses = $service->getAnalyses($ideaId);
        $provStmt = $pdo->prepare("SELECT provider_code, default_model, is_active FROM ai_providers WHERE is_active = 1 ORDER BY is_default DESC LIMIT 1");
        $provStmt->execute();
        $provider = $provStmt->fetch(PDO::FETCH_ASSOC);

        $iterCount = count($iterations);
        $qCount = count($questions);
        $aCount = count($analyses);

        $iterArr = []; foreach ($iterations as $it) {
            $reqRaw = json_decode($it['request_payload'] ?? '{}', true) ?: [];
            $resRaw = json_decode($it['response_payload'] ?? '{}', true) ?: [];
            $reqPreview = json_encode($reqRaw, JSON_UNESCAPED_UNICODE);
            $resPreview = json_encode($resRaw, JSON_UNESCAPED_UNICODE);
            $iterArr[] = ['iteration' => (int)$it['iteration'], 'type' => $it['type'], 'created' => $it['created_at'], 'req_size' => strlen($it['request_payload'] ?? ''), 'res_size' => strlen($it['response_payload'] ?? ''), 'req_preview' => $reqPreview, 'res_preview' => $resPreview];
        }
        $qArr = []; foreach ($questions as $q) {
            $la = $q['last_answer'] ?? null;
            $ans = null;
            if ($la) {
                if (!empty($la['answer_text']) && empty($la['is_unknown'])) $ans = $la['answer_text'];
                elseif (!empty($la['is_unknown'])) $ans = $this->t('idea/messages.answer_dont_know');
                elseif (!empty($la['selected_option_label'])) $ans = $la['selected_option_label'];
                elseif (!empty($la['selected_option_key'])) $ans = $la['selected_option_key'];
            }
            $qArr[] = ['id' => $q['id'], 'cycle' => $q['cycle_id'] ?? 1, 'text' => mb_substr($q['question_text'] ?? '', 0, 80), 'type' => $q['question_type'], 'has_answer' => !empty($la), 'answer' => $ans];
        }
        $aArr = []; foreach ($analyses as $a) { $aArr[] = ['type' => $a['analysis_type'], 'status' => $a['status'], 'has_result' => !empty($a['result_json']), 'created' => $a['created_at'] ?? null]; }

        return $this->success('DEBUG_LOG', 'OK', [
            'idea' => ['id' => $publicId, 'status' => $idea['status'], 'title' => $idea['title']],
            'iterations_count' => $iterCount, 'iterations' => $iterArr,
            'questions_count' => $qCount, 'questions' => $qArr,
            'analyses_count' => $aCount, 'analyses' => $aArr,
            'provider' => $provider ? $provider['provider_code'] . ' / ' . $provider['default_model'] : 'none',
            'safe_mode' => (int)(function () use ($pdo) {
                $ss = $pdo->prepare("SELECT value FROM settings WHERE scope = 'features' AND name = 'ideas_ai_safe_mode' ORDER BY created_at DESC LIMIT 1");
                $ss->execute();
                return $ss->fetchColumn() ?: 0;
            })(),
            'snapshot_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function aiIterations(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $pdo = $this->container->get('db.pdo');
        $stmt = $pdo->prepare("SELECT i.* FROM idea_ai_iterations i JOIN ideas d ON d.id = i.idea_id WHERE d.public_id = :pid ORDER BY i.iteration ASC");
        $stmt->execute(['pid' => $publicId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($items as &$item) {
            $item['response_payload'] = json_decode($item['response_payload'] ?? '{}', true);
            $item['request_payload'] = json_decode($item['request_payload'] ?? '{}', true);
        }

        return $this->success('AI_ITERATIONS_LIST', $this->t('common/messages.ok'), ['items' => $items]);
    }

    public function questions(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $pdo = $this->container->get('db.pdo');
        $stmt = $pdo->prepare("SELECT iq.*, d.id as idea_db_id, d.coverage_json FROM idea_questions iq JOIN ideas d ON d.id = iq.idea_id WHERE d.public_id = :pid ORDER BY iq.sort_order ASC");
        $stmt->execute(['pid' => $publicId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Mark clarification and gap questions
        $coverageJson = ($items !== []) ? ($items[0]['coverage_json'] ?? '{}') : '{}';
        $coverage = json_decode($coverageJson, true) ?: [];
        $clarPids = [];
        foreach (($coverage['additional_clarifications']['questions'] ?? []) as $cq) {
            if (!empty($cq['public_id'])) $clarPids[$cq['public_id']] = true;
        }
        $gapPids = [];
        foreach (($coverage['gap_clarifications']['questions'] ?? []) as $gq) {
            if (!empty($gq['public_id'])) $gapPids[$gq['public_id']] = true;
        }

        foreach ($items as &$item) {
            $item['options_json'] = json_decode($item['options_json'] ?? '[]', true);
            if (!is_array($item['options_json'])) $item['options_json'] = [];
            $item['options'] = $item['options_json'];
            $item['is_clarification'] = isset($clarPids[$item['public_id']]);
            $item['is_gap'] = isset($gapPids[$item['public_id']]);
            $ansStmt = $pdo->prepare("SELECT * FROM idea_answers WHERE question_id = :qid ORDER BY created_at DESC LIMIT 1");
            $ansStmt->execute(['qid' => $item['id']]);
            $item['last_answer'] = $ansStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        return $this->success('QUESTIONS_LIST', $this->t('common/messages.ok'), ['items' => $items]);
    }

    /**
     * POST /ideas/{id}/additional-questions — AI analysis: generate additional clarifying questions.
     * DELETE — clear additional clarifications.
     */
    public function additionalQuestions(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);
        $service = $this->container->get('service.idea');
        $pdo = $this->container->get('db.pdo');
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);
        $ideaId = (int)$idea['id'];

        // GET: return existing clarifications — filter out old entries without public_id, and answered ones
        if (($this->request()->method ?? 'GET') === 'GET') {
            $coverage = json_decode($idea['coverage_json'] ?? '{}', true) ?: [];
            $raw = $coverage['additional_clarifications'] ?? ['questions' => []];
            $raw['questions'] = array_values(array_filter($raw['questions'] ?? [], fn($q) => !empty($q['public_id'])));
            // Filter out questions that already have answers
            if (!empty($raw['questions'])) {
                $pids = array_column($raw['questions'], 'public_id');
                $placeholders = implode(',', array_fill(0, count($pids), '?'));
                $stmt = $pdo->prepare("SELECT iq.public_id FROM idea_questions iq JOIN idea_answers ia ON ia.question_id = iq.id WHERE iq.public_id IN ({$placeholders}) AND iq.idea_id = ?");
                $stmt->execute([...$pids, $ideaId]);
                $answeredPids = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                $answeredSet = array_flip($answeredPids);
                $raw['questions'] = array_values(array_filter($raw['questions'], fn($q) => !isset($answeredSet[$q['public_id']])));
            }
            return $this->success('CLARIFICATIONS_LOADED', 'OK', $raw);
        }

        // DELETE: clear additional clarifications
        if (($this->request()->method ?? '') === 'DELETE') {
            $existingCoverage = json_decode($idea['coverage_json'] ?? '{}', true) ?: [];
            unset($existingCoverage['additional_clarifications']);
            $pdo->prepare("UPDATE ideas SET coverage_json = :cov WHERE id = :iid")
                ->execute(['cov' => json_encode($existingCoverage, JSON_UNESCAPED_UNICODE), 'iid' => $ideaId]);
            return $this->success('CLARIFICATIONS_CLEARED', 'OK');
        }

        // POST: generate additional clarifications
        set_time_limit(120);

        // Collect all idea data + questions + answers into $info
        $questions = $service->getQuestions($ideaId);
        $qaList = [];
        foreach ($questions as $q) {
            $ans = $q['last_answer'] ?? null;
            $answerText = '';
            if ($ans) {
                $answerText = $ans['selected_option_label'] ?? $ans['selected_option_key'] ?? $ans['answer_text'] ?? '—';
            }
            $qaList[] = [
                'question' => $q['question_text'] ?? '',
                'answer' => $answerText ?: $this->t('idea/messages.no_answer'),
            ];
        }

        $info = [
            'title' => $idea['title'] ?? '',
            'description' => $idea['description'] ?? '',
            'category' => $idea['category'] ?? '',
            'region' => $idea['region'] ?? '',
            'target_date' => $idea['target_date'] ?? '',
            'status' => $idea['status'] ?? '',
            'visibility' => $idea['visibility'] ?? '',
            'questions_and_answers' => $qaList,
        ];
        $infoJson = json_encode($info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
Analyze the idea data below. Identify gaps, risks, and missing information. Generate clarifying questions. Each question must have 4-7 answer options (include "Not sure" and "Custom"). Do not repeat already answered questions.

Return only JSON:
{
  "additional_questions": [{
    "question": "...",
    "dimension": "unique_topic_key",
    "why": "Why this question matters",
    "answers": [{"value": "short_key", "label": "Option label"}]
  }]
}

Data:
{$infoJson}
PROMPT;

        // Send to AI
        try {
            $aiSvc = $this->container->get('service.ai_action');
            $result = $aiSvc->execute('idea_analyze', [
                '__usr' => "[SYSTEM]\n" . $this->t('idea/messages.prompt_analyst_system') . $this->localeInstruction() . "\n[/SYSTEM]\n\n[USER]\n" . $prompt . "\n[/USER]",
            ], $this->user()['user'] ?? []);

            $rawText = $result['result']['preview']['summary'] ?? '';

            // Log to debug iterations
            $maxIterStmt = $pdo->prepare("SELECT COALESCE(MAX(iteration), 0) + 1 FROM idea_ai_iterations WHERE idea_id = :iid");
            $maxIterStmt->execute(['iid' => $ideaId]);
            $iter = (int)$maxIterStmt->fetchColumn();
            $pdo->prepare("INSERT INTO idea_ai_iterations (public_id, idea_id, iteration, type, request_payload, response_payload, created_at) VALUES (:pid, :iid, :iter, 'clarification', :req, :res, NOW())")
                ->execute([
                    'pid' => 'iai_'.bin2hex(random_bytes(6)), 'iid' => $ideaId, 'iter' => $iter,
                    'req' => json_encode(['user_prompt' => $prompt], JSON_UNESCAPED_UNICODE),
                    'res' => json_encode(['raw_text' => $rawText], JSON_UNESCAPED_UNICODE),
                ]);

            $data = json_decode($rawText, true);
            if (!is_array($data) && preg_match('/\{.*\}/s', $rawText, $m)) {
                $data = json_decode($m[0], true);
            }

            $additionalQuestions = $data['additional_questions'] ?? [];

            // Save each clarification question to idea_questions so answers can be stored
            $maxCycleStmt = $pdo->prepare("SELECT COALESCE(MAX(cycle_id), 0) + 1 FROM idea_questions WHERE idea_id = :iid");
        $maxCycleStmt->execute(['iid' => $ideaId]);
        $cycleId = (int)$maxCycleStmt->fetchColumn();
            $savedQs = [];
            foreach ($additionalQuestions as $idx => $q) {
                $qId = 'iq_'.bin2hex(random_bytes(7));
                $answerOptions = $this->normalizeAiAnswerOptions($q['answers'] ?? []);
                $normalizedOpts = array_map(fn($o) => ['key' => $o['value'], 'label' => $o['label'], 'description' => null], $answerOptions);
                $dim = $q['dimension'] ?? 'additional';
                $pdo->prepare("INSERT INTO idea_questions (public_id, idea_id, cycle_id, question_text, reason, question_type, options_json, allow_custom, allow_unknown, required, dimension, impact, sort_order, created_at) VALUES (:pid, :iid, :cycle, :qt, :reason, 'multiple_choice', :opts, 1, 1, 0, :dim, 'medium', :sort, NOW())")
                    ->execute([
                        'pid' => $qId, 'iid' => $ideaId, 'cycle' => $cycleId,
                        'qt' => $q['question'] ?? '',
                        'reason' => $q['why'] ?? '',
                        'opts' => json_encode($normalizedOpts, JSON_UNESCAPED_UNICODE),
                        'dim' => $dim,
                        'sort' => $idx,
                    ]);
                $q['public_id'] = $qId;
                $q['answers'] = $answerOptions;
                $savedQs[] = $q;
            }
            $clarifications = ['questions' => $savedQs, 'generated_at' => gmdate('c'), 'idea' => $info];

            // Store in coverage_json (merge with existing)
            $existingCoverage = json_decode($idea['coverage_json'] ?? '{}', true) ?: [];
            $existingCoverage['additional_clarifications'] = $clarifications;
            $pdo->prepare("UPDATE ideas SET coverage_json = :cov WHERE id = :iid")
                ->execute(['cov' => json_encode($existingCoverage, JSON_UNESCAPED_UNICODE), 'iid' => $ideaId]);

            return $this->success('CLARIFICATIONS_GENERATED', 'OK', $clarifications);
        } catch (\Throwable $e) {
            ai_diag_log("[ADDITIONAL_QUESTIONS_ERROR] " . $e->getMessage());
            return $this->error('AI_UNAVAILABLE', $this->t('idea/messages.ai_analysis_failed'), 503);
        }
    }

    /**
     * GET  /ideas/{id}/understanding-card — load existing card
     * POST — build/rebuild card via AI
     * DELETE — clear card
     */
    public function understandingCard(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);
        $service = $this->container->get('service.idea');
        $pdo = $this->container->get('db.pdo');
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);
        $ideaId = (int)$idea['id'];
        $this->ensureIdeaWorkflowTables($pdo);

        // GET: return existing card
        if (($this->request()->method ?? 'GET') === 'GET') {
            $stmt = $pdo->prepare("SELECT * FROM idea_understanding_cards WHERE idea_id = :iid");
            $stmt->execute(['iid' => $ideaId]);
            $card = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            return $this->success('CARD_LOADED', 'OK', $card ?: ['empty' => true]);
        }

        // DELETE: clear card
        if (($this->request()->method ?? '') === 'DELETE') {
            $pdo->prepare("DELETE FROM idea_understanding_cards WHERE idea_id = :iid")->execute(['iid' => $ideaId]);
            return $this->success('CARD_CLEARED', 'OK');
        }

        // POST: build card
        set_time_limit(120);

        // Collect all questions and answers
        $questions = $service->getQuestions($ideaId);
        $qaList = [];
        foreach ($questions as $q) {
            $ans = $q['last_answer'] ?? null;
            $qaList[] = [
                'question_id' => (string)($q['id'] ?? ''),
                'question' => $q['question_text'] ?? '',
                'dimension' => $q['dimension'] ?? '',
                'semantic_key' => $q['dimension'] ?? '',
                'answer' => $ans ? [
                    'selected_option' => $ans['selected_option_label'] ?? $ans['selected_option_key'] ?? '',
                    'custom_answer' => $ans['answer_text'] ?? '',
                    'is_unknown' => (bool)($ans['is_unknown'] ?? false),
                ] : null,
            ];
        }

        $desc = $idea['description'] ?? '';
        $plainDesc = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $desc)));

        $coverage = json_decode($idea['coverage_json'] ?? '{}', true) ?: [];

        $payload = [
            'idea' => [
                'title' => $idea['title'] ?? '',
                'short_description' => mb_substr($plainDesc, 0, 200),
                'description_plain_text' => $plainDesc,
                'category' => $idea['category'] ?? '',
                'product' => $idea['product'] ?? '',
                'region' => $idea['region'] ?? '',
                'target_date' => $idea['target_date'] ?? null,
                'current_date' => date('Y-m-d'),
            ],
            'questions_and_answers' => $qaList,
            'already_covered_topics' => $coverage['already_covered_topics'] ?? [],
            'do_not_ask_again_topics' => $coverage['do_not_ask_again_topics'] ?? [],
        ];

        $systemPrompt = $this->t('idea/messages.system_prompt_card');

        try {
            $aiSvc = $this->container->get('service.ai_action');
            $maxRetries = 2;
            $rawText = '';
            $parsed = ['ok' => false, 'data' => null, 'error' => 'not_started'];
            for ($retry = 0; $retry <= $maxRetries; $retry++) {
                $result = $aiSvc->execute('idea_analyze', [
                    '__usr' => "[SYSTEM]\n" . $systemPrompt . $this->localeInstruction() . "\n[/SYSTEM]\n\n[USER]\n" . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n[/USER]",
                ], $this->user()['user'] ?? []);

                $rawText = $result['result']['preview']['summary'] ?? '';
                $parsed = $this->extractAiJson($rawText);
                if ($parsed['ok'] && !empty($parsed['data']['idea_profile'])) break;
                if ($parsed['ok'] && empty($parsed['data']['idea_profile']) && !empty($parsed['data']['summary'])) {
                    $parsed['data'] = ['idea_profile' => $parsed['data']];
                    break;
                }
                ai_diag_log("[UNDERSTANDING_CARD_RETRY] attempt=" . ($retry + 1) . " error=" . ($parsed['error'] ?? 'invalid_resp') . " text_len=" . strlen($rawText));
                if ($retry < $maxRetries) usleep(1000000);
            }

            try { $pdo->query('SELECT 1'); } catch (\Throwable) { $pdo = $this->container->get('db.pdo'); }
            $maxIterStmt = $pdo->prepare("SELECT COALESCE(MAX(iteration), 0) + 1 FROM idea_ai_iterations WHERE idea_id = :iid");
            $maxIterStmt->execute(['iid' => $ideaId]);
            $iter = (int)$maxIterStmt->fetchColumn();
            $pdo->prepare("INSERT INTO idea_ai_iterations (public_id, idea_id, iteration, type, request_payload, response_payload, created_at) VALUES (:pid, :iid, :iter, 'understanding_card', :req, :res, NOW())")
                ->execute(['pid' => 'iai_'.bin2hex(random_bytes(6)), 'iid' => $ideaId, 'iter' => $iter, 'req' => json_encode(['system_prompt' => $systemPrompt, 'payload' => $payload], JSON_UNESCAPED_UNICODE), 'res' => json_encode(['raw_text' => $rawText], JSON_UNESCAPED_UNICODE)]);

            $data = $parsed['ok'] && is_array($parsed['data']) ? $parsed['data'] : null;
            if (!is_array($data) || empty($data['idea_profile'])) {
                ai_diag_log("[UNDERSTANDING_CARD_PARSE_FAIL] text_len=".strlen($rawText)." parse_error=".($parsed['error'] ?? 'unknown')." preview=".substr($rawText, 0, 300));
                $data = $this->buildFallbackUnderstandingCardData($idea, $plainDesc, $qaList, (string)($parsed['error'] ?? 'invalid_ai_json'));
            }

            $profile = $data['idea_profile'] ?? [];
            $nextStep = $data['next_step'] ?? [];
            $completeness = $profile['completeness'] ?? [];
            $overall = max(0, min(1, (float)($completeness['overall'] ?? 0)));
            $confidence = max(0, min(1, (float)($profile['confidence_score'] ?? 0)));

            $card = [
                'profile_json' => json_encode($profile, JSON_UNESCAPED_UNICODE),
                'summary' => $profile['summary'] ?? '',
                'idea_type' => $profile['idea_type'] ?? '',
                'specificity_level' => $profile['specificity_level'] ?? '',
                'completeness_score' => $overall,
                'confidence_score' => $confidence,
                'next_action' => $nextStep['action'] ?? '',
                'ai_request_json' => json_encode(['system_prompt' => $systemPrompt, 'payload' => $payload], JSON_UNESCAPED_UNICODE),
                'ai_response_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ];

            $existingStmt = $pdo->prepare("SELECT id FROM idea_understanding_cards WHERE idea_id = :iid");
            $existingStmt->execute(['iid' => $ideaId]);
            $existingCard = $existingStmt->fetch(\PDO::FETCH_ASSOC);

            if ($existingCard) {
                $card['idea_id'] = $ideaId;
                $pdo->prepare("UPDATE idea_understanding_cards SET profile_json=:profile_json,summary=:summary,idea_type=:idea_type,specificity_level=:specificity_level,completeness_score=:completeness_score,confidence_score=:confidence_score,next_action=:next_action,ai_request_json=:ai_request_json,ai_response_json=:ai_response_json,updated_at=NOW() WHERE idea_id=:idea_id")
                    ->execute($card);
            } else {
                $card['idea_id'] = $ideaId;
                $pdo->prepare("INSERT INTO idea_understanding_cards (idea_id,profile_json,summary,idea_type,specificity_level,completeness_score,confidence_score,next_action,ai_request_json,ai_response_json) VALUES (:idea_id,:profile_json,:summary,:idea_type,:specificity_level,:completeness_score,:confidence_score,:next_action,:ai_request_json,:ai_response_json)")
                    ->execute($card);
            }

            // Re-read to get timestamps
            $fresh = $pdo->prepare("SELECT * FROM idea_understanding_cards WHERE idea_id = :iid");
            $fresh->execute(['iid' => $ideaId]);
            $row = $fresh->fetch(\PDO::FETCH_ASSOC) ?: [];
            return $this->success('CARD_BUILT', 'OK', $row ?: $card);
        } catch (\Throwable $e) {
            ai_diag_log("[UNDERSTANDING_CARD_ERROR] " . $e->getMessage());
            return $this->error('AI_UNAVAILABLE', $this->t('idea/messages.ai_card_failed'), 503);
        }
    }

    /**
     * GET  /ideas/{id}/gap-questions — load existing gap questions
     * POST — generate gap-targeted questions via AI based on understanding card
     * DELETE — clear gap questions
     */
    public function gapQuestions(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);
        $service = $this->container->get('service.idea');
        $pdo = $this->container->get('db.pdo');
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);
        $ideaId = (int)$idea['id'];

        // GET
        if (($this->request()->method ?? 'GET') === 'GET') {
            $coverage = json_decode($idea['coverage_json'] ?? '{}', true) ?: [];
            $raw = $coverage['gap_clarifications'] ?? ['questions' => []];
            $raw['questions'] = array_values(array_filter($raw['questions'] ?? [], fn($q) => !empty($q['public_id'])));
            // Filter out answered
            if (!empty($raw['questions'])) {
                $pids = array_column($raw['questions'], 'public_id');
                $placeholders = implode(',', array_fill(0, count($pids), '?'));
                $stmt = $pdo->prepare("SELECT iq.public_id FROM idea_questions iq JOIN idea_answers ia ON ia.question_id = iq.id WHERE iq.public_id IN ({$placeholders}) AND iq.idea_id = ?");
                $stmt->execute([...$pids, $ideaId]);
                $answeredPids = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                $answeredSet = array_flip($answeredPids);
                $raw['questions'] = array_values(array_filter($raw['questions'], fn($q) => !isset($answeredSet[$q['public_id']])));
            }
            return $this->success('GAP_QUESTIONS_LOADED', 'OK', $raw);
        }

        // DELETE
        if (($this->request()->method ?? '') === 'DELETE') {
            $existingCoverage = json_decode($idea['coverage_json'] ?? '{}', true) ?: [];
            unset($existingCoverage['gap_clarifications']);
            $pdo->prepare("UPDATE ideas SET coverage_json = :cov WHERE id = :iid")
                ->execute(['cov' => json_encode($existingCoverage, JSON_UNESCAPED_UNICODE), 'iid' => $ideaId]);
            return $this->success('GAP_QUESTIONS_CLEARED', 'OK');
        }

        // POST: generate gap questions based on understanding card
        set_time_limit(120);

        // Read understanding card
        $cardStmt = $pdo->prepare("SELECT * FROM idea_understanding_cards WHERE idea_id = :iid");
        $cardStmt->execute(['iid' => $ideaId]);
        $card = $cardStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$card) {
            return $this->error('NO_CARD', $this->t('idea/messages.card_first_required'), 400);
        }

        $profile = json_decode($card['profile_json'] ?? '{}', true) ?: [];
        $cardSummary = json_encode([
            'summary' => $card['summary'] ?? '',
            'idea_type' => $card['idea_type'] ?? '',
            'specificity_level' => $card['specificity_level'] ?? '',
            'completeness' => $card['completeness_score'] ?? 0,
            'confidence' => $card['confidence_score'] ?? 0,
            'known_facts' => $profile['known_facts'] ?? [],
            'missing_facts' => $profile['missing_facts'] ?? [],
            'user_unknowns' => $profile['user_unknowns'] ?? [],
            'assumptions' => $profile['assumptions'] ?? [],
            'constraints' => $profile['constraints'] ?? [],
            'early_risks' => $profile['early_risks'] ?? [],
            'next_action' => $card['next_action'] ?? '',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $questions = $service->getQuestions($ideaId);
        $qaList = [];
        foreach ($questions as $q) {
            $ans = $q['last_answer'] ?? null;
            $answerText = '';
            if ($ans) {
                $answerText = $ans['selected_option_label'] ?? $ans['selected_option_key'] ?? $ans['answer_text'] ?? '—';
            }
            $qaList[] = ['question' => $q['question_text'] ?? '', 'answer' => $answerText ?: $this->t('idea/messages.no_answer')];
        }
        $info = ['title' => $idea['title'] ?? '', 'description' => $idea['description'] ?? '', 'category' => $idea['category'] ?? '', 'questions_and_answers' => $qaList];
        $infoJson = json_encode($info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
Understanding card (known facts & identified gaps):

{$cardSummary}

Additional idea info:

{$infoJson}

1. Analyze missing_facts, user_unknowns, assumptions, constraints, early_risks.
2. Identify inaccuracies, contradictions, gaps needing clarification.
3. Generate questions that close those gaps.
4. Per question: 4-7 short options, include "Other" + "Don't know yet".
5. Skip already-answered questions.

Return JSON:
{
"additional_questions": [{
"question": "Question text",
"dimension": "unique_topic_key",
"why": "Why this question is needed",
"answers": [{"value": "short_key", "label": "Answer option"}]
}]
}
PROMPT;

        try {
            $aiSvc = $this->container->get('service.ai_action');
            $result = $aiSvc->execute('idea_analyze', [
                '__usr' => "[SYSTEM]\nAnalyze understanding card. Find gaps. Generate clarifying questions." . $this->localeInstruction() . "\n[/SYSTEM]\n\n[USER]\n" . $prompt . "\n[/USER]",
            ], $this->user()['user'] ?? []);

            $rawText = $result['result']['preview']['summary'] ?? '';

            $maxIterStmt = $pdo->prepare("SELECT COALESCE(MAX(iteration), 0) + 1 FROM idea_ai_iterations WHERE idea_id = :iid");
            $maxIterStmt->execute(['iid' => $ideaId]);
            $iter = (int)$maxIterStmt->fetchColumn();
            $pdo->prepare("INSERT INTO idea_ai_iterations (public_id, idea_id, iteration, type, request_payload, response_payload, created_at) VALUES (:pid, :iid, :iter, 'gap_question', :req, :res, NOW())")
                ->execute(['pid' => 'iai_'.bin2hex(random_bytes(6)), 'iid' => $ideaId, 'iter' => $iter, 'req' => json_encode(['user_prompt' => $prompt], JSON_UNESCAPED_UNICODE), 'res' => json_encode(['raw_text' => $rawText], JSON_UNESCAPED_UNICODE)]);

            $data = json_decode($rawText, true);
            if (!is_array($data) && preg_match('/\{.*\}/s', $rawText, $m)) {
                $data = json_decode($m[0], true);
            }

            $gapQuestions = $data['additional_questions'] ?? [];
            $maxCycleStmt = $pdo->prepare("SELECT COALESCE(MAX(cycle_id), 0) + 1 FROM idea_questions WHERE idea_id = :iid");
        $maxCycleStmt->execute(['iid' => $ideaId]);
        $cycleId = (int)$maxCycleStmt->fetchColumn();
            $savedQs = [];
            foreach ($gapQuestions as $idx => $q) {
                $qId = 'iq_'.bin2hex(random_bytes(7));
                $answerOptions = $this->normalizeAiAnswerOptions($q['answers'] ?? []);
                $normalizedOpts = array_map(fn($o) => ['key' => $o['value'], 'label' => $o['label'], 'description' => null], $answerOptions);
                $dim = $q['dimension'] ?? 'gap';
                $qt = '🔍 ' . ($q['question'] ?? '');
                $pdo->prepare("INSERT INTO idea_questions (public_id, idea_id, cycle_id, question_text, reason, question_type, options_json, allow_custom, allow_unknown, required, dimension, impact, sort_order, created_at) VALUES (:pid, :iid, :cycle, :qt, :reason, 'multiple_choice', :opts, 1, 1, 0, :dim, 'medium', :sort, NOW())")
                    ->execute(['pid' => $qId, 'iid' => $ideaId, 'cycle' => $cycleId, 'qt' => $qt, 'reason' => $q['why'] ?? '', 'opts' => json_encode($normalizedOpts, JSON_UNESCAPED_UNICODE), 'dim' => $dim, 'sort' => $idx]);
                $q['public_id'] = $qId;
                $q['answers'] = $answerOptions;
                $savedQs[] = $q;
            }
            $gapData = ['questions' => $savedQs, 'generated_at' => gmdate('c')];

            $existingCoverage = json_decode($idea['coverage_json'] ?? '{}', true) ?: [];
            $existingCoverage['gap_clarifications'] = $gapData;
            $pdo->prepare("UPDATE ideas SET coverage_json = :cov WHERE id = :iid")
                ->execute(['cov' => json_encode($existingCoverage, JSON_UNESCAPED_UNICODE), 'iid' => $ideaId]);

            return $this->success('GAP_QUESTIONS_GENERATED', 'OK', $gapData);
        } catch (\Throwable $e) {
            ai_diag_log("[GAP_QUESTIONS_ERROR] " . $e->getMessage());
            return $this->error('AI_UNAVAILABLE', $this->t('idea/messages.ai_gaps_failed'), 503);
        }
    }

    /**
     * GET  /ideas/{id}/refined-card — load refined card
     * POST — refine understanding card based on all Q&A + existing card
     * DELETE — clear refined card
     */
    public function refinedCard(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);
        $service = $this->container->get('service.idea');
        $pdo = $this->container->get('db.pdo');
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);
        $ideaId = (int)$idea['id'];
        $this->ensureIdeaWorkflowTables($pdo);

        if (($this->request()->method ?? 'GET') === 'GET') {
            $stmt = $pdo->prepare("SELECT * FROM idea_refined_cards WHERE idea_id = :iid");
            $stmt->execute(['iid' => $ideaId]);
            $card = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            return $this->success('REFINED_CARD_LOADED', 'OK', $card ?: ['empty' => true]);
        }

        if (($this->request()->method ?? '') === 'DELETE') {
            $pdo->prepare("DELETE FROM idea_refined_cards WHERE idea_id = :iid")->execute(['iid' => $ideaId]);
            return $this->success('REFINED_CARD_CLEARED', 'OK');
        }

        // POST: build refined card
        set_time_limit(0);

        // Read original card
        $origStmt = $pdo->prepare("SELECT * FROM idea_understanding_cards WHERE idea_id = :iid");
        $origStmt->execute(['iid' => $ideaId]);
        $origCard = $origStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$origCard) return $this->error('NO_CARD', $this->t('idea/messages.card_first_required'), 400);

        // Collect answered questions (unanswered not useful for card refinement)
        $questions = $service->getQuestions($ideaId);
        $qaList = [];
        foreach ($questions as $q) {
            $ans = $q['last_answer'] ?? null;
            if (!$ans) continue;
            $qaList[] = [
                'question' => $q['question_text'] ?? '',
                'dimension' => $q['dimension'] ?? '',
                'answer' => $ans['selected_option_label'] ?? $ans['selected_option_key'] ?? $ans['answer_text'] ?? '',
            ];
        }

        $origProfile = json_decode($origCard['profile_json'] ?? '{}', true) ?: [];
        $existingCardJson = json_encode([
            'summary' => $origCard['summary'] ?? '',
            'idea_type' => $origCard['idea_type'] ?? '',
            'specificity_level' => $origCard['specificity_level'] ?? '',
            'completeness' => $origCard['completeness_score'] ?? 0,
            'confidence' => $origCard['confidence_score'] ?? 0,
            'known_facts' => $origProfile['known_facts'] ?? [],
            'missing_facts' => $origProfile['missing_facts'] ?? [],
            'user_unknowns' => $origProfile['user_unknowns'] ?? [],
            'assumptions' => $origProfile['assumptions'] ?? [],
            'constraints' => $origProfile['constraints'] ?? [],
            'early_risks' => $origProfile['early_risks'] ?? [],
            'next_action' => $origCard['next_action'] ?? '',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $desc = $idea['description'] ?? '';
        $plainDesc = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $desc)));
        $coverage = json_decode($idea['coverage_json'] ?? '{}', true) ?: [];

        $payload = [
            'idea' => ['title' => $idea['title'] ?? '', 'short_description' => mb_substr($plainDesc, 0, 200), 'description_plain_text' => $plainDesc, 'category' => $idea['category'] ?? '', 'product' => $idea['product'] ?? '', 'region' => $idea['region'] ?? '', 'target_date' => $idea['target_date'] ?? null, 'current_date' => date('Y-m-d')],
            'existing_understanding_card' => json_decode($existingCardJson, true),
            'all_questions_and_answers' => $qaList,
            'already_covered_topics' => $coverage['already_covered_topics'] ?? [],
            'do_not_ask_again_topics' => $coverage['do_not_ask_again_topics'] ?? [],
        ];

        $systemPrompt = $this->t('idea/messages.system_prompt_refined_card');

        try {
            $aiSvc = $this->container->get('service.ai_action');
            $maxRetries = 2;
            $rawText = '';
            $parsed = ['ok' => false, 'data' => null, 'error' => 'not_started'];
            for ($retry = 0; $retry <= $maxRetries; $retry++) {
                $result = $aiSvc->execute('idea_analyze', [
                    '__usr' => "[SYSTEM]\n" . $systemPrompt . $this->localeInstruction() . "\n[/SYSTEM]\n\n[USER]\n" . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n[/USER]",
                ], $this->user()['user'] ?? []);

                $rawText = $result['result']['preview']['summary'] ?? '';
                $parsed = $this->extractAiJson($rawText);
                if ($parsed['ok'] && !empty($parsed['data']['idea_profile'])) break;
                if ($parsed['ok'] && empty($parsed['data']['idea_profile']) && !empty($parsed['data']['summary'])) {
                    $parsed['data'] = ['idea_profile' => $parsed['data']];
                    break;
                }
                ai_diag_log("[REFINED_CARD_RETRY] attempt=" . ($retry + 1) . " error=" . ($parsed['error'] ?? 'invalid_resp') . " text_len=" . strlen($rawText));
                if ($retry < $maxRetries) usleep(1000000);
            }

            try { $pdo->query('SELECT 1'); } catch (\Throwable) { $pdo = $this->container->get('db.pdo'); }
            $maxIterStmt = $pdo->prepare("SELECT COALESCE(MAX(iteration), 0) + 1 FROM idea_ai_iterations WHERE idea_id = :iid");
            $maxIterStmt->execute(['iid' => $ideaId]);
            $iter = (int)$maxIterStmt->fetchColumn();
            $pdo->prepare("INSERT INTO idea_ai_iterations (public_id, idea_id, iteration, type, request_payload, response_payload, created_at) VALUES (:pid, :iid, :iter, 'refined_card', :req, :res, NOW())")
                ->execute(['pid' => 'iai_'.bin2hex(random_bytes(6)), 'iid' => $ideaId, 'iter' => $iter, 'req' => json_encode(['system_prompt' => $systemPrompt, 'payload' => $payload], JSON_UNESCAPED_UNICODE), 'res' => json_encode(['raw_text' => $rawText], JSON_UNESCAPED_UNICODE)]);

            $data = $parsed['ok'] && is_array($parsed['data']) ? $parsed['data'] : null;
            // Accept unwrapped JSON (AI may return flat profile without idea_profile wrapper)
            if (is_array($data) && empty($data['idea_profile']) && !empty($data['summary'])) {
                $data = ['idea_profile' => $data];
            }
            if (!is_array($data) || empty($data['idea_profile'])) {
                ai_diag_log("[REFINED_CARD_PARSE_FAIL] text_len=".strlen($rawText)." parse_error=".($parsed['error'] ?? 'unknown')." preview=".substr($rawText, 0, 300));
                $data = $this->buildFallbackUnderstandingCardData($idea, $plainDesc, $qaList, (string)($parsed['error'] ?? 'invalid_ai_json'));
                if (is_array($origProfile) && !empty($origProfile)) {
                    $fallbackProfile = $data['idea_profile'];
                    foreach (['known_facts', 'user_unknowns', 'missing_facts', 'assumptions', 'constraints', 'early_risks', 'key_decision_factors'] as $field) {
                        $fallbackProfile[$field] = array_values(array_unique(array_filter(array_merge(
                            is_array($origProfile[$field] ?? null) ? (array)$origProfile[$field] : [],
                            is_array($fallbackProfile[$field] ?? null) ? (array)$fallbackProfile[$field] : []
                        ))));
                    }
                    $fallbackProfile['summary'] = (string)($fallbackProfile['summary'] ?: ($origProfile['summary'] ?? ''));
                    $fallbackProfile['idea_type'] = (string)($fallbackProfile['idea_type'] ?: ($origProfile['idea_type'] ?? 'other'));
                    $fallbackProfile['specificity_level'] = (string)($fallbackProfile['specificity_level'] ?: ($origProfile['specificity_level'] ?? 'low'));
                    $fallbackProfile['_fallback_source'] = 'refined_card';
                    $data['idea_profile'] = $fallbackProfile;
                }
            }

            $profile = $data['idea_profile'] ?? [];
            $nextStep = $data['next_step'] ?? [];
            $completeness = $profile['completeness'] ?? [];
            $overall = max(0, min(1, (float)($completeness['overall'] ?? 0)));
            $confidence = max(0, min(1, (float)($profile['confidence_score'] ?? 0)));

            $card = [
                'profile_json' => json_encode($profile, JSON_UNESCAPED_UNICODE),
                'summary' => $profile['summary'] ?? '',
                'idea_type' => $profile['idea_type'] ?? '',
                'specificity_level' => $profile['specificity_level'] ?? '',
                'completeness_score' => $overall,
                'confidence_score' => $confidence,
                'next_action' => $nextStep['action'] ?? '',
                'ai_request_json' => json_encode(['system_prompt' => $systemPrompt, 'payload' => $payload], JSON_UNESCAPED_UNICODE),
                'ai_response_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'idea_id' => $ideaId,
            ];

            $exists = $pdo->prepare("SELECT id FROM idea_refined_cards WHERE idea_id = :iid");
            $exists->execute(['iid' => $ideaId]);
            if ($exists->fetch()) {
                $pdo->prepare("UPDATE idea_refined_cards SET profile_json=:profile_json,summary=:summary,idea_type=:idea_type,specificity_level=:specificity_level,completeness_score=:completeness_score,confidence_score=:confidence_score,next_action=:next_action,ai_request_json=:ai_request_json,ai_response_json=:ai_response_json,updated_at=NOW() WHERE idea_id=:idea_id")->execute($card);
            } else {
                $pdo->prepare("INSERT INTO idea_refined_cards (idea_id,profile_json,summary,idea_type,specificity_level,completeness_score,confidence_score,next_action,ai_request_json,ai_response_json) VALUES (:idea_id,:profile_json,:summary,:idea_type,:specificity_level,:completeness_score,:confidence_score,:next_action,:ai_request_json,:ai_response_json)")->execute($card);
            }

            $fresh = $pdo->prepare("SELECT * FROM idea_refined_cards WHERE idea_id = :iid");
            $fresh->execute(['iid' => $ideaId]);
            return $this->success('REFINED_CARD_BUILT', 'OK', $fresh->fetch(\PDO::FETCH_ASSOC) ?: $card);
        } catch (\Throwable $e) {
            ai_diag_log("[REFINED_CARD_ERROR] " . $e->getMessage());
            return $this->error('AI_UNAVAILABLE', $this->t('idea/messages.ai_refine_card_failed'), 503);
        }
    }

    /**
     * GET/POST/DELETE /ideas/{id}/potential — calculate idea potential score
     */
    public function potentialScore(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);
        $service = $this->container->get('service.idea');
        $pdo = $this->container->get('db.pdo');
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);
        $ideaId = (int)$idea['id'];
        $this->ensureIdeaWorkflowTables($pdo);

        if (($this->request()->method ?? 'GET') === 'GET') {
            $stmt = $pdo->prepare("SELECT * FROM idea_potential_scores WHERE idea_id = :iid");
            $stmt->execute(['iid' => $ideaId]);
            return $this->success('POTENTIAL_LOADED', 'OK', $stmt->fetch(\PDO::FETCH_ASSOC) ?: ['empty' => true]);
        }
        if (($this->request()->method ?? '') === 'DELETE') {
            $pdo->prepare("DELETE FROM idea_potential_scores WHERE idea_id = :iid")->execute(['iid' => $ideaId]);
            return $this->success('POTENTIAL_CLEARED', 'OK');
        }

        set_time_limit(0);

        $questions = $service->getQuestions($ideaId);
        $qaList = [];
        foreach ($questions as $q) {
            $ans = $q['last_answer'] ?? null;
            if (!$ans) continue;
            $qaList[] = ['question' => $q['question_text'] ?? '', 'dimension' => $q['dimension'] ?? '', 'answer' => $ans['selected_option_label'] ?? $ans['selected_option_key'] ?? $ans['answer_text'] ?? ''];
        }

        $desc = $idea['description'] ?? '';
        $plainDesc = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $desc)));
        $coverage = json_decode($idea['coverage_json'] ?? '{}', true) ?: [];

        // Read understanding card if exists
        $cardStmt = $pdo->prepare("SELECT * FROM idea_understanding_cards WHERE idea_id = :iid");
        $cardStmt->execute(['iid' => $ideaId]);
        $card = $cardStmt->fetch(\PDO::FETCH_ASSOC);
        $ucData = $card ? ['exists' => true, 'summary' => $card['summary'] ?? '', 'idea_type' => $card['idea_type'] ?? '', 'completeness' => $card['completeness_score'] ?? 0, 'confidence' => $card['confidence_score'] ?? 0, 'next_action' => $card['next_action'] ?? ''] : ['exists' => false];

        $payload = [
            'idea' => ['title' => $idea['title'] ?? '', 'short_description' => mb_substr($plainDesc, 0, 200), 'description_plain_text' => $plainDesc, 'category' => $idea['category'] ?? '', 'product' => $idea['product'] ?? '', 'region' => $idea['region'] ?? '', 'target_date' => $idea['target_date'] ?? null, 'current_date' => date('Y-m-d')],
            'understanding_card' => $ucData,
            'questions_and_answers' => $qaList,
            'already_covered_topics' => $coverage['already_covered_topics'] ?? [],
            'do_not_ask_again_topics' => $coverage['do_not_ask_again_topics'] ?? [],
        ];

        $systemPrompt = <<<'PROMPT'
Rate idea potential (0-100). No business plans, no invented facts. Separate facts from assumptions. If data is scarce, lower confidence_score and mark calculation_type as "preliminary".

Select 5-8 criteria adapted to the idea type (category/idea_type). Assign each a weight (sum=100) and score (0-10). Final potential = sum(weight × score / 10).

Rules:
1. Do not use { or } inside string values.
2. Use ( ) or [ ] when brackets are needed in text.
3. Return only JSON, no markdown and no text before or after JSON.

JSON:
{"potential":{"potential_score":0,"potential_level":"medium","calculation_type":"preliminary","verdict":"","summary":"","confidence_score":0.5,"completeness_score":0.5},"criteria":[{"criterion_id":"key","title":"","weight":100,"score":5,"weighted_score":50,"reason":"","positive_factors":[],"negative_factors":[],"missing_data":[]}],"strengths":[],"weaknesses":[],"growth_factors":[],"risk_factors":[],"missing_data":[],"assumptions":[],"what_can_improve_score":[],"what_can_reduce_score":[],"recommended_next_step":{"action":"finalize","reason":""}}
PROMPT;

        try {
            $aiSvc = $this->container->get('service.ai_action');
            $maxRetries = 2;
            $rawText = '';
            $parsed = ['ok' => false, 'data' => null, 'error' => 'not_started'];
            for ($retry = 0; $retry <= $maxRetries; $retry++) {
                $result = $aiSvc->execute('idea_analyze', ['__usr' => "[SYSTEM]\n" . $systemPrompt . $this->localeInstruction() . "\n[/SYSTEM]\n\n[USER]\n" . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n[/USER]"], $this->user()['user'] ?? []);
                $rawText = $result['result']['preview']['summary'] ?? '';
                $parsed = $this->extractAiJson($rawText);
                if ($parsed['ok'] && !empty($parsed['data']['potential'])) break;
                ai_diag_log("[POTENTIAL_RETRY] attempt=" . ($retry + 1) . " error=" . ($parsed['error'] ?? 'invalid_resp') . " text_len=" . strlen($rawText));
                if ($retry < $maxRetries) usleep(1000000);
            }

            try { $pdo->query('SELECT 1'); } catch (\Throwable) { $pdo = $this->container->get('db.pdo'); }
            $iter = (int)$pdo->query("SELECT COALESCE(MAX(iteration),0)+1 FROM idea_ai_iterations WHERE idea_id={$ideaId}")->fetchColumn();
            $pdo->prepare("INSERT INTO idea_ai_iterations (public_id, idea_id, iteration, type, request_payload, response_payload, created_at) VALUES (:pid, :iid, :iter, 'potential_score', :req, :res, NOW())")->execute(['pid' => 'iai_'.bin2hex(random_bytes(6)), 'iid' => $ideaId, 'iter' => $iter, 'req' => json_encode(['system_prompt' => $systemPrompt, 'payload' => $payload], JSON_UNESCAPED_UNICODE), 'res' => json_encode(['raw_text' => $rawText], JSON_UNESCAPED_UNICODE)]);

            $data = $parsed['ok'] && is_array($parsed['data']) ? $parsed['data'] : null;
            if (!is_array($data) || empty($data['potential'])) {
                ai_diag_log("[POTENTIAL_PARSE_FAIL] text_len=".strlen($rawText)." parse_error=".($parsed['error'] ?? 'unknown')." preview=".substr($rawText, 0, 300));
                $data = $this->buildFallbackPotentialData($idea, $ucData, $qaList, (string)($parsed['error'] ?? 'invalid_ai_json'));
            }

            $pot = $data['potential'] ?? [];
            $criteria = $data['criteria'] ?? [];

            // Recalculate score from criteria for accuracy
            $calcScore = 0; $calcWeight = 0;
            foreach ($criteria as $c) { $calcScore += ((int)($c['score'] ?? 0)) * ((int)($c['weight'] ?? 0)) / 10; $calcWeight += (int)($c['weight'] ?? 0); }
            $calcScore = min(100, max(0, (int)round($calcScore)));
            $aiScore = min(100, max(0, (int)($pot['potential_score'] ?? 0)));
            $finalScore = abs($calcScore - $aiScore) > 5 ? $calcScore : $aiScore;

            $level = $finalScore <= 20 ? 'very_low' : ($finalScore <= 40 ? 'low' : ($finalScore <= 60 ? 'medium' : ($finalScore <= 80 ? 'high' : 'very_high')));

            $conf = max(0, min(1, (float)($pot['confidence_score'] ?? 0)));
            $comp = max(0, min(1, (float)($pot['completeness_score'] ?? 0)));

            $row = [
                'potential_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'potential_score' => $finalScore, 'potential_level' => $level,
                'confidence_score' => $conf, 'completeness_score' => $comp,
                'calculation_type' => $pot['calculation_type'] ?? ($comp < 0.5 ? 'preliminary' : 'normal'),
                'verdict' => $pot['verdict'] ?? '', 'idea_id' => $ideaId,
                'ai_request_json' => json_encode(['system_prompt' => $systemPrompt, 'payload' => $payload], JSON_UNESCAPED_UNICODE),
                'ai_response_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ];

            $exists = $pdo->prepare("SELECT id FROM idea_potential_scores WHERE idea_id = :iid");
            $exists->execute(['iid' => $ideaId]);
            if ($exists->fetch()) {
                $pdo->prepare("UPDATE idea_potential_scores SET potential_json=:potential_json,potential_score=:potential_score,potential_level=:potential_level,confidence_score=:confidence_score,completeness_score=:completeness_score,calculation_type=:calculation_type,verdict=:verdict,ai_request_json=:ai_request_json,ai_response_json=:ai_response_json,updated_at=NOW() WHERE idea_id=:idea_id")->execute($row);
            } else {
                $pdo->prepare("INSERT INTO idea_potential_scores (idea_id,potential_json,potential_score,potential_level,confidence_score,completeness_score,calculation_type,verdict,ai_request_json,ai_response_json) VALUES (:idea_id,:potential_json,:potential_score,:potential_level,:confidence_score,:completeness_score,:calculation_type,:verdict,:ai_request_json,:ai_response_json)")->execute($row);
            }

            $fresh = $pdo->prepare("SELECT * FROM idea_potential_scores WHERE idea_id = :iid");
            $fresh->execute(['iid' => $ideaId]);
            return $this->success('POTENTIAL_CALCULATED', 'OK', $fresh->fetch(\PDO::FETCH_ASSOC) ?: $row);
        } catch (\Throwable $e) {
            ai_diag_log("[POTENTIAL_ERROR] " . $e->getMessage());
            return $this->error('AI_UNAVAILABLE', $this->t('idea/messages.ai_potential_failed'), 503);
        }
    }

    /**
     * GET/POST/DELETE /ideas/{id}/risk-report — calculate idea risks
     */
    public function riskReport(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);
        $service = $this->container->get('service.idea');
        $pdo = $this->container->get('db.pdo');
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);
        $ideaId = (int)$idea['id'];
        $this->ensureIdeaWorkflowTables($pdo);

        if (($this->request()->method ?? 'GET') === 'GET') {
            $stmt = $pdo->prepare("SELECT * FROM idea_risk_reports WHERE idea_id = :iid");
            $stmt->execute(['iid' => $ideaId]);
            return $this->success('RISK_LOADED', 'OK', $stmt->fetch(\PDO::FETCH_ASSOC) ?: ['empty' => true]);
        }
        if (($this->request()->method ?? '') === 'DELETE') {
            $pdo->prepare("DELETE FROM idea_risk_reports WHERE idea_id = :iid")->execute(['iid' => $ideaId]);
            return $this->success('RISK_CLEARED', 'OK');
        }

        set_time_limit(0);
        $questions = $service->getQuestions($ideaId);
        $qaList = []; foreach ($questions as $q) { $ans = $q['last_answer'] ?? null; if (!$ans) continue; $qaList[] = ['question' => $q['question_text'] ?? '', 'dimension' => $q['dimension'] ?? '', 'answer' => $ans['selected_option_label'] ?? $ans['selected_option_key'] ?? $ans['answer_text'] ?? '']; }
        $desc = $idea['description'] ?? ''; $plainDesc = trim(strip_tags(str_replace(['<br>','<br/>','<br />'],"\n",$desc)));
        $coverage = json_decode($idea['coverage_json'] ?? '{}', true) ?: [];
        $cardStmt = $pdo->prepare("SELECT * FROM idea_understanding_cards WHERE idea_id = :iid"); $cardStmt->execute(['iid' => $ideaId]); $card = $cardStmt->fetch(\PDO::FETCH_ASSOC);
        $uc = $card ? ['exists' => true, 'summary' => $card['summary'] ?? '', 'idea_type' => $card['idea_type'] ?? '', 'completeness' => $card['completeness_score'] ?? 0] : ['exists' => false];
        // Also include refined card if available
        $refinedStmt = $pdo->prepare("SELECT * FROM idea_refined_cards WHERE idea_id = :iid"); $refinedStmt->execute(['iid' => $ideaId]); $refinedCard = $refinedStmt->fetch(\PDO::FETCH_ASSOC);
        $ruc = $refinedCard ? ['exists' => true, 'summary' => $refinedCard['summary'] ?? '', 'idea_type' => $refinedCard['idea_type'] ?? '', 'completeness' => $refinedCard['completeness_score'] ?? 0] : ['exists' => false];

        $payload = ['idea' => ['title' => $idea['title'] ?? '', 'short_description' => mb_substr($plainDesc, 0, 200), 'description_plain_text' => $plainDesc, 'category' => $idea['category'] ?? '', 'product' => $idea['product'] ?? '', 'region' => $idea['region'] ?? '', 'target_date' => $idea['target_date'] ?? null, 'current_date' => date('Y-m-d')], 'understanding_card' => $uc, 'refined_card' => $ruc, 'questions_and_answers' => $qaList, 'already_covered_topics' => $coverage['already_covered_topics'] ?? [], 'do_not_ask_again_topics' => $coverage['do_not_ask_again_topics'] ?? []];

        $sp = $this->t('idea/messages.system_prompt_risk');

        try {
            $aiSvc = $this->container->get('service.ai_action');
            $maxRetries = 2; $rawText = '';
            for ($retry = 0; $retry <= $maxRetries; $retry++) {
                $result = $aiSvc->execute('idea_analyze', ['__usr' => "[SYSTEM]\n" . $sp . $this->localeInstruction() . "\n[/SYSTEM]\n\n[USER]\n" . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n[/USER]"], $this->user()['user'] ?? []);
                $rawText = $result['result']['preview']['summary'] ?? '';
                $parsed = $this->extractAiJson($rawText);
                if ($parsed['ok'] && !empty($parsed['data']['risk_report'])) break;
                ai_diag_log("[RISK_RETRY] attempt=" . ($retry+1) . " error=" . ($parsed['error'] ?? 'invalid_resp') . " text_len=" . strlen($rawText));
                if ($retry < $maxRetries) usleep(1000000);
            }

            try { $pdo->query('SELECT 1'); } catch (\Throwable) { $pdo = $this->container->get('db.pdo'); }
            $iter = (int)$pdo->query("SELECT COALESCE(MAX(iteration),0)+1 FROM idea_ai_iterations WHERE idea_id={$ideaId}")->fetchColumn();
            $pdo->prepare("INSERT INTO idea_ai_iterations (public_id, idea_id, iteration, type, request_payload, response_payload, created_at) VALUES (:pid, :iid, :iter, 'risk_report', :req, :res, NOW())")->execute(['pid' => 'iai_'.bin2hex(random_bytes(6)), 'iid' => $ideaId, 'iter' => $iter, 'req' => json_encode(['system_prompt' => $sp, 'payload' => $payload], JSON_UNESCAPED_UNICODE), 'res' => json_encode(['raw_text' => $rawText], JSON_UNESCAPED_UNICODE)]);

            if (!$parsed['ok'] || empty($parsed['data']['risk_report'])) {
                ai_diag_log("[RISK_PARSE_FAIL] text_len=".strlen($rawText)." parse_error=".($parsed['error']??'unknown')." preview=".substr($rawText,0,300));
                // Save fallback record instead of returning error
                $row = ['risk_report_json' => json_encode(['risk_report' => ['summary' => $this->t('idea/messages.ai_risk_fallback_summary'), 'risks' => [], 'overall_risk_score' => 1, 'overall_risk_level' => 'unknown', 'confidence_score' => 0]], JSON_UNESCAPED_UNICODE), 'overall_risk_score' => 1, 'overall_risk_level' => 'unknown', 'critical_risks_count' => 0, 'high_risks_count' => 0, 'medium_risks_count' => 0, 'low_risks_count' => 0, 'confidence_score' => 0, 'ai_request_json' => json_encode(['note' => 'AI analysis failed', 'system_prompt' => $sp, 'payload' => $payload], JSON_UNESCAPED_UNICODE), 'ai_response_json' => json_encode(['raw_text' => $rawText], JSON_UNESCAPED_UNICODE), 'idea_id' => $ideaId];
                $pdo->prepare("INSERT INTO idea_risk_reports (idea_id,risk_report_json,overall_risk_score,overall_risk_level,critical_risks_count,high_risks_count,medium_risks_count,low_risks_count,confidence_score,ai_request_json,ai_response_json) VALUES (:idea_id,:risk_report_json,:overall_risk_score,:overall_risk_level,:critical_risks_count,:high_risks_count,:medium_risks_count,:low_risks_count,:confidence_score,:ai_request_json,:ai_response_json)")->execute($row);
                $fresh = $pdo->prepare("SELECT * FROM idea_risk_reports WHERE idea_id = :iid"); $fresh->execute(['iid' => $ideaId]);
                return $this->success('RISK_FALLBACK', 'OK', $fresh->fetch(\PDO::FETCH_ASSOC) ?: $row);
            }
            $data = $parsed['data'];

            $rr = $data['risk_report'] ?? []; $risks = $rr['risks'] ?? [];
            // Validate and fix risk scores
            foreach ($risks as &$r) { $r['risk_score'] = ((int)($r['probability_score'] ?? 1)) * ((int)($r['impact_score'] ?? 1)); $rs = $r['risk_score']; $r['risk_level'] = $rs <= 5 ? 'low' : ($rs <= 10 ? 'medium' : ($rs <= 15 ? 'high' : 'critical')); }
            $crit = count(array_filter($risks, fn($r) => ($r['risk_level'] ?? '') === 'critical'));
            $high = count(array_filter($risks, fn($r) => ($r['risk_level'] ?? '') === 'high'));
            $med = count(array_filter($risks, fn($r) => ($r['risk_level'] ?? '') === 'medium'));
            $lowR = count(array_filter($risks, fn($r) => ($r['risk_level'] ?? '') === 'low'));
            $data['risk_report']['risk_distribution'] = ['critical' => $crit, 'high' => $high, 'medium' => $med, 'low' => $lowR];
            $conf = max(0, min(1, (float)($rr['confidence_score'] ?? 0)));
            $overall = max(1, min(25, (int)($rr['overall_risk_score'] ?? 1)));
            $olevel = $overall <= 5 ? 'low' : ($overall <= 10 ? 'medium' : ($overall <= 15 ? 'high' : 'critical'));
            $data['risk_report']['overall_risk_score'] = $overall;
            $data['risk_report']['overall_risk_level'] = $olevel;

            $row = ['risk_report_json' => json_encode($data, JSON_UNESCAPED_UNICODE), 'overall_risk_score' => $overall, 'overall_risk_level' => $olevel, 'critical_risks_count' => $crit, 'high_risks_count' => $high, 'medium_risks_count' => $med, 'low_risks_count' => $lowR, 'confidence_score' => $conf, 'ai_request_json' => json_encode(['system_prompt' => $sp, 'payload' => $payload], JSON_UNESCAPED_UNICODE), 'ai_response_json' => json_encode($data, JSON_UNESCAPED_UNICODE), 'idea_id' => $ideaId];

            $exists = $pdo->prepare("SELECT id FROM idea_risk_reports WHERE idea_id = :iid"); $exists->execute(['iid' => $ideaId]);
            if ($exists->fetch()) { $pdo->prepare("UPDATE idea_risk_reports SET risk_report_json=:risk_report_json,overall_risk_score=:overall_risk_score,overall_risk_level=:overall_risk_level,critical_risks_count=:critical_risks_count,high_risks_count=:high_risks_count,medium_risks_count=:medium_risks_count,low_risks_count=:low_risks_count,confidence_score=:confidence_score,ai_request_json=:ai_request_json,ai_response_json=:ai_response_json,updated_at=NOW() WHERE idea_id=:idea_id")->execute($row); }
            else { $pdo->prepare("INSERT INTO idea_risk_reports (idea_id,risk_report_json,overall_risk_score,overall_risk_level,critical_risks_count,high_risks_count,medium_risks_count,low_risks_count,confidence_score,ai_request_json,ai_response_json) VALUES (:idea_id,:risk_report_json,:overall_risk_score,:overall_risk_level,:critical_risks_count,:high_risks_count,:medium_risks_count,:low_risks_count,:confidence_score,:ai_request_json,:ai_response_json)")->execute($row); }
            $fresh = $pdo->prepare("SELECT * FROM idea_risk_reports WHERE idea_id = :iid"); $fresh->execute(['iid' => $ideaId]);
            return $this->success('RISK_CALCULATED', 'OK', $fresh->fetch(\PDO::FETCH_ASSOC) ?: $row);
        } catch (\Throwable $e) { ai_diag_log("[RISK_ERROR] " . $e->getMessage()); return $this->error('AI_UNAVAILABLE', $this->t('idea/messages.ai_risks_failed'), 503); }
    }

    /**
     * GET/POST/DELETE /ideas/{id}/pitfalls — calculate hidden pitfalls
     */
    public function pitfallsReport(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);
        $service = $this->container->get('service.idea');
        $pdo = $this->container->get('db.pdo');
        $this->ensureIdeaWorkflowTables($pdo);
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);
        $ideaId = (int)$idea['id'];

        if (($this->request()->method ?? 'GET') === 'GET') {
            $stmt = $pdo->prepare("SELECT * FROM idea_pitfalls_reports WHERE idea_id = :iid");
            $stmt->execute(['iid' => $ideaId]);
            return $this->success('PITFALLS_LOADED', 'OK', $stmt->fetch(\PDO::FETCH_ASSOC) ?: ['empty' => true]);
        }
        if (($this->request()->method ?? '') === 'DELETE') {
            $pdo->prepare("DELETE FROM idea_pitfalls_reports WHERE idea_id = :iid")->execute(['iid' => $ideaId]);
            return $this->success('PITFALLS_CLEARED', 'OK');
        }

        set_time_limit(0);
        $questions = $service->getQuestions($ideaId);
        $qaList = []; foreach ($questions as $q) { $ans = $q['last_answer'] ?? null; if (!$ans) continue; $qaList[] = ['question' => $q['question_text'] ?? '', 'dimension' => $q['dimension'] ?? '', 'answer' => $ans['selected_option_label'] ?? $ans['selected_option_key'] ?? $ans['answer_text'] ?? '']; }
        $desc = $idea['description'] ?? ''; $plainDesc = trim(strip_tags(str_replace(['<br>','<br/>','<br />'],"\n",$desc)));
        $coverage = json_decode($idea['coverage_json'] ?? '{}', true) ?: [];
        $cardStmt = $pdo->prepare("SELECT * FROM idea_understanding_cards WHERE idea_id = :iid"); $cardStmt->execute(['iid' => $ideaId]); $card = $cardStmt->fetch(\PDO::FETCH_ASSOC);
        $uc = $card ? ['exists' => true, 'summary' => $card['summary'] ?? '', 'idea_type' => $card['idea_type'] ?? '', 'completeness' => $card['completeness_score'] ?? 0] : ['exists' => false];
        $refinedStmt = $pdo->prepare("SELECT * FROM idea_refined_cards WHERE idea_id = :iid"); $refinedStmt->execute(['iid' => $ideaId]); $refinedCard = $refinedStmt->fetch(\PDO::FETCH_ASSOC);
        $ruc = $refinedCard ? ['exists' => true, 'summary' => $refinedCard['summary'] ?? '', 'idea_type' => $refinedCard['idea_type'] ?? '', 'completeness' => $refinedCard['completeness_score'] ?? 0] : ['exists' => false];

        $payload = ['idea' => ['title' => $idea['title'] ?? '', 'short_description' => mb_substr($plainDesc, 0, 200), 'description_plain_text' => $plainDesc, 'category' => $idea['category'] ?? '', 'product' => $idea['product'] ?? '', 'region' => $idea['region'] ?? '', 'target_date' => $idea['target_date'] ?? null, 'current_date' => date('Y-m-d')], 'understanding_card' => $uc, 'refined_card' => $ruc, 'questions_and_answers' => $qaList, 'already_covered_topics' => $coverage['already_covered_topics'] ?? [], 'do_not_ask_again_topics' => $coverage['do_not_ask_again_topics'] ?? []];

        $sp = $this->t('idea/messages.system_prompt_pitfalls');

        try {
            $aiSvc = $this->container->get('service.ai_action'); $maxRetries = 2; $rawText = ''; $parsed = ['ok' => false];
            for ($retry = 0; $retry <= $maxRetries; $retry++) {
                $result = $aiSvc->execute('idea_analyze', ['__usr' => "[SYSTEM]\n" . $sp . $this->localeInstruction() . "\n[/SYSTEM]\n\n[USER]\n" . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n[/USER]"], $this->user()['user'] ?? []);
                $rawText = $result['result']['preview']['summary'] ?? '';
                $parsed = $this->extractAiJson($rawText);
                if ($parsed['ok'] && !empty($parsed['data']['pitfalls'])) break;
                ai_diag_log("[PITFALLS_RETRY] attempt=" . ($retry+1) . " error=" . ($parsed['error'] ?? 'invalid_resp'));
                if ($retry < $maxRetries) usleep(1000000);
            }

            try { $pdo->query('SELECT 1'); } catch (\Throwable) { $pdo = $this->container->get('db.pdo'); }
            $iter = (int)$pdo->query("SELECT COALESCE(MAX(iteration),0)+1 FROM idea_ai_iterations WHERE idea_id={$ideaId}")->fetchColumn();
            $pdo->prepare("INSERT INTO idea_ai_iterations (public_id, idea_id, iteration, type, request_payload, response_payload, created_at) VALUES (:pid, :iid, :iter, 'pitfalls_report', :req, :res, NOW())")->execute(['pid' => 'iai_'.bin2hex(random_bytes(6)), 'iid' => $ideaId, 'iter' => $iter, 'req' => json_encode(['system_prompt' => $sp, 'payload' => $payload], JSON_UNESCAPED_UNICODE), 'res' => json_encode(['raw_text' => $rawText], JSON_UNESCAPED_UNICODE)]);

            if (!$parsed['ok'] || empty($parsed['data']['pitfalls'])) {
                ai_diag_log("[PITFALLS_PARSE_FAIL] parse_error=".($parsed['error']??'unknown')." text_len=".strlen($rawText));
                $row = ['pitfalls_json' => '[]', 'overall_summary' => $this->t('idea/messages.ai_pitfalls_fallback_summary'), 'data_confidence' => 0, 'ai_request_json' => json_encode(['note' => 'AI analysis failed'], JSON_UNESCAPED_UNICODE), 'ai_response_json' => json_encode(['raw_text' => $rawText], JSON_UNESCAPED_UNICODE), 'idea_id' => $ideaId];
                $pdo->prepare("INSERT INTO idea_pitfalls_reports (idea_id,pitfalls_json,overall_summary,data_confidence,ai_request_json,ai_response_json) VALUES (:idea_id,:pitfalls_json,:overall_summary,:data_confidence,:ai_request_json,:ai_response_json)")->execute($row);
                return $this->success('PITFALLS_FALLBACK', 'OK', $row);
            }
            $data = $parsed['data'];

            $pitfalls = $data['pitfalls'] ?? [];
            // Backend-calculated priority: prob*impact*4 + hidden*2 + urgency*2
            foreach ($pitfalls as &$p) {
                $prob = max(1, min(5, (int)($p['probability_score'] ?? 1)));
                $imp = max(1, min(5, (int)($p['impact_score'] ?? 1)));
                $hid = max(1, min(5, (int)($p['hiddenness_score'] ?? 1)));
                $urg = max(1, min(5, (int)($p['urgency_score'] ?? 1)));
                $p['probability_score'] = $prob; $p['impact_score'] = $imp; $p['hiddenness_score'] = $hid; $p['urgency_score'] = $urg;
                $prio = $prob * $imp * 4 + $hid * 2 + $urg * 2;
                $p['priority_score'] = $prio;
                $p['priority_level'] = $prio >= 90 ? 'critical' : ($prio >= 60 ? 'high' : ($prio >= 30 ? 'medium' : 'low'));
            }
            // Sort by priority descending
            usort($pitfalls, fn($a, $b) => ($b['priority_score'] ?? 0) <=> ($a['priority_score'] ?? 0));

            $confidence = max(0, min(1, (float)($data['data_confidence'] ?? 0)));

            $row = ['overall_hidden_complexity' => $data['overall_hidden_complexity'] ?? 'medium', 'overall_summary' => $data['overall_summary'] ?? '', 'pitfalls_json' => json_encode(array_merge($data, ['pitfalls' => $pitfalls]), JSON_UNESCAPED_UNICODE), 'data_confidence' => $confidence, 'ai_request_json' => json_encode(['system_prompt' => $sp, 'payload' => $payload], JSON_UNESCAPED_UNICODE), 'ai_response_json' => json_encode($data, JSON_UNESCAPED_UNICODE), 'idea_id' => $ideaId];

            $exists = $pdo->prepare("SELECT id FROM idea_pitfalls_reports WHERE idea_id = :iid"); $exists->execute(['iid' => $ideaId]);
            if ($exists->fetch()) { $pdo->prepare("UPDATE idea_pitfalls_reports SET overall_hidden_complexity=:overall_hidden_complexity,overall_summary=:overall_summary,pitfalls_json=:pitfalls_json,data_confidence=:data_confidence,ai_request_json=:ai_request_json,ai_response_json=:ai_response_json,updated_at=NOW() WHERE idea_id=:idea_id")->execute($row); }
            else { $pdo->prepare("INSERT INTO idea_pitfalls_reports (idea_id,overall_hidden_complexity,overall_summary,pitfalls_json,data_confidence,ai_request_json,ai_response_json) VALUES (:idea_id,:overall_hidden_complexity,:overall_summary,:pitfalls_json,:data_confidence,:ai_request_json,:ai_response_json)")->execute($row); }
            $fresh = $pdo->prepare("SELECT * FROM idea_pitfalls_reports WHERE idea_id = :iid"); $fresh->execute(['iid' => $ideaId]);
            return $this->success('PITFALLS_CALCULATED', 'OK', $fresh->fetch(\PDO::FETCH_ASSOC) ?: $row);
        } catch (\Throwable $e) { ai_diag_log("[PITFALLS_ERROR] " . $e->getMessage()); return $this->error('AI_UNAVAILABLE', $this->t('idea/messages.ai_pitfalls_failed'), 503); }
    }

    /**
     * GET/POST/DELETE /ideas/{id}/implementation-plan
     */
    public function implementationPlan(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);
        $service = $this->container->get('service.idea');
        $pdo = $this->container->get('db.pdo');
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);
        $ideaId = (int)$idea['id'];

        if (($this->request()->method ?? 'GET') === 'GET') {
            $stmt = $pdo->prepare("SELECT * FROM idea_implementation_plans WHERE idea_id = :iid");
            $stmt->execute(['iid' => $ideaId]);
            return $this->success('PLAN_LOADED', 'OK', $stmt->fetch(\PDO::FETCH_ASSOC) ?: ['empty' => true]);
        }
        if (($this->request()->method ?? '') === 'DELETE') {
            $pdo->prepare("DELETE FROM idea_implementation_plans WHERE idea_id = :iid")->execute(['iid' => $ideaId]);
            return $this->success('PLAN_CLEARED', 'OK');
        }

        $this->ensureIdeaWorkflowTables($pdo);
        set_time_limit(0);
        $questions = $service->getQuestions($ideaId);
        $qaList = []; foreach ($questions as $q) { $ans = $q['last_answer'] ?? null; if (!$ans) continue; $qaList[] = ['question' => $q['question_text'] ?? '', 'dimension' => $q['dimension'] ?? '', 'answer' => $ans['selected_option_label'] ?? $ans['selected_option_key'] ?? $ans['answer_text'] ?? '']; }
        $desc = $idea['description'] ?? ''; $plainDesc = trim(strip_tags(str_replace(['<br>','<br/>','<br />'],"\n",$desc)));
        $coverage = json_decode($idea['coverage_json'] ?? '{}', true) ?: [];
        $cardStmt = $pdo->prepare("SELECT * FROM idea_understanding_cards WHERE idea_id = :iid"); $cardStmt->execute(['iid' => $ideaId]); $card = $cardStmt->fetch(\PDO::FETCH_ASSOC);
        $uc = $card ? ['exists' => true, 'summary' => $card['summary'] ?? '', 'idea_type' => $card['idea_type'] ?? '', 'completeness' => $card['completeness_score'] ?? 0] : ['exists' => false];
        $refinedStmt = $pdo->prepare("SELECT * FROM idea_refined_cards WHERE idea_id = :iid"); $refinedStmt->execute(['iid' => $ideaId]); $refinedCard = $refinedStmt->fetch(\PDO::FETCH_ASSOC);
        $ruc = $refinedCard ? ['exists' => true, 'summary' => $refinedCard['summary'] ?? '', 'idea_type' => $refinedCard['idea_type'] ?? '', 'completeness' => $refinedCard['completeness_score'] ?? 0] : ['exists' => false];

        $payload = ['idea' => ['title' => $idea['title'] ?? '', 'short_description' => mb_substr($plainDesc, 0, 200), 'description_plain_text' => $plainDesc, 'category' => $idea['category'] ?? '', 'product' => $idea['product'] ?? '', 'region' => $idea['region'] ?? '', 'target_date' => $idea['target_date'] ?? null, 'current_date' => date('Y-m-d')], 'understanding_card' => $uc, 'refined_card' => $ruc, 'questions_and_answers' => $qaList, 'already_covered_topics' => $coverage['already_covered_topics'] ?? [], 'do_not_ask_again_topics' => $coverage['do_not_ask_again_topics'] ?? []];

        $sp = $this->t('idea/messages.system_prompt_plan');

        try {
            $aiSvc = $this->container->get('service.ai_action'); $maxRetries = 2; $rawText = ''; $parsed = ['ok' => false];
            for ($retry = 0; $retry <= $maxRetries; $retry++) {
                $result = $aiSvc->execute('idea_analyze', ['__usr' => "[SYSTEM]\n" . $sp . $this->localeInstruction() . "\n[/SYSTEM]\n\n[USER]\n" . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n[/USER]"], $this->user()['user'] ?? []);
                $rawText = $result['result']['preview']['summary'] ?? '';
                $parsed = $this->extractAiJson($rawText);
                if ($parsed['ok'] && !empty($parsed['data']['implementation_plan'])) break;
                ai_diag_log("[PLAN_RETRY] attempt=" . ($retry+1) . " error=" . ($parsed['error'] ?? 'invalid_resp'));
                if ($retry < $maxRetries) usleep(1000000);
            }

            try { $pdo->query('SELECT 1'); } catch (\Throwable) { $pdo = $this->container->get('db.pdo'); }
            $iter = (int)$pdo->query("SELECT COALESCE(MAX(iteration),0)+1 FROM idea_ai_iterations WHERE idea_id={$ideaId}")->fetchColumn();
            $pdo->prepare("INSERT INTO idea_ai_iterations (public_id, idea_id, iteration, type, request_payload, response_payload, created_at) VALUES (:pid, :iid, :iter, 'implementation_plan', :req, :res, NOW())")->execute(['pid' => 'iai_'.bin2hex(random_bytes(6)), 'iid' => $ideaId, 'iter' => $iter, 'req' => json_encode(['system_prompt' => $sp, 'payload' => $payload], JSON_UNESCAPED_UNICODE), 'res' => json_encode(['raw_text' => $rawText], JSON_UNESCAPED_UNICODE)]);

            if (!$parsed['ok'] || empty($parsed['data']['implementation_plan'])) {
                ai_diag_log("[PLAN_PARSE_FAIL] parse_error=".($parsed['error']??'unknown')." text_len=".strlen($rawText));
                $row = ['plan_json' => '{}', 'summary' => $this->t('idea/messages.ai_plan_fallback_summary'), 'planning_horizon' => '', 'plan_type' => 'preliminary', 'confidence_score' => 0, 'ai_request_json' => json_encode(['note' => 'AI analysis failed'], JSON_UNESCAPED_UNICODE), 'ai_response_json' => json_encode(['raw_text' => $rawText], JSON_UNESCAPED_UNICODE), 'idea_id' => $ideaId];
                $pdo->prepare("INSERT INTO idea_implementation_plans (idea_id,plan_json,summary,planning_horizon,plan_type,confidence_score,ai_request_json,ai_response_json) VALUES (:idea_id,:plan_json,:summary,:planning_horizon,:plan_type,:confidence_score,:ai_request_json,:ai_response_json)")->execute($row);
                return $this->success('PLAN_FALLBACK', 'OK', $row);
            }
            $data = $parsed['data'];

            $ip = $data['implementation_plan'] ?? [];
            if (!isset($ip['stages']) || !is_array($ip['stages'])) $ip['stages'] = [];
            if (!isset($ip['next_7_days']) || !is_array($ip['next_7_days'])) $ip['next_7_days'] = ['summary' => '', 'tasks' => []];
            if (!isset($ip['next_7_days']['tasks']) || !is_array($ip['next_7_days']['tasks'])) $ip['next_7_days']['tasks'] = [];
            if (!isset($ip['milestones']) || !is_array($ip['milestones'])) $ip['milestones'] = [];
            if (!isset($ip['risks']) || !is_array($ip['risks'])) $ip['risks'] = [];
            $data['implementation_plan'] = $ip;
            $conf = max(0, min(1, (float)($ip['confidence_score'] ?? 0)));

            $row = ['plan_json' => json_encode($data, JSON_UNESCAPED_UNICODE), 'summary' => $ip['summary'] ?? '', 'planning_horizon' => $ip['planning_horizon'] ?? '', 'plan_type' => $ip['plan_type'] ?? ($conf < 0.5 ? 'preliminary' : 'standard'), 'confidence_score' => $conf, 'ai_request_json' => json_encode(['system_prompt' => $sp, 'payload' => $payload], JSON_UNESCAPED_UNICODE), 'ai_response_json' => json_encode($data, JSON_UNESCAPED_UNICODE), 'idea_id' => $ideaId];

            $exists = $pdo->prepare("SELECT id FROM idea_implementation_plans WHERE idea_id = :iid"); $exists->execute(['iid' => $ideaId]);
            if ($exists->fetch()) { $pdo->prepare("UPDATE idea_implementation_plans SET plan_json=:plan_json,summary=:summary,planning_horizon=:planning_horizon,plan_type=:plan_type,confidence_score=:confidence_score,ai_request_json=:ai_request_json,ai_response_json=:ai_response_json,updated_at=NOW() WHERE idea_id=:idea_id")->execute($row); }
            else { $pdo->prepare("INSERT INTO idea_implementation_plans (idea_id,plan_json,summary,planning_horizon,plan_type,confidence_score,ai_request_json,ai_response_json) VALUES (:idea_id,:plan_json,:summary,:planning_horizon,:plan_type,:confidence_score,:ai_request_json,:ai_response_json)")->execute($row); }
            $fresh = $pdo->prepare("SELECT * FROM idea_implementation_plans WHERE idea_id = :iid"); $fresh->execute(['iid' => $ideaId]);
            return $this->success('PLAN_CALCULATED', 'OK', $fresh->fetch(\PDO::FETCH_ASSOC) ?: $row);
        } catch (\Throwable $e) { ai_diag_log("[PLAN_ERROR] " . $e->getMessage()); return $this->error('AI_UNAVAILABLE', $this->t('idea/messages.ai_plan_failed'), 503); }
    }

    /**
     * GET/POST/DELETE /ideas/{id}/final-recommendation — итоговая рекомендация на основе всех блоков
     */
    public function finalRecommendation(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);
        $service = $this->container->get('service.idea');
        $pdo = $this->container->get('db.pdo');
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);
        $ideaId = (int)$idea['id'];

        if (($this->request()->method ?? 'GET') === 'GET') {
            $stmt = $pdo->prepare("SELECT * FROM idea_final_recommendations WHERE idea_id = :iid");
            $stmt->execute(['iid' => $ideaId]);
            return $this->success('FINAL_LOADED', 'OK', $stmt->fetch(\PDO::FETCH_ASSOC) ?: ['empty' => true]);
        }
        if (($this->request()->method ?? '') === 'DELETE') {
            $pdo->prepare("DELETE FROM idea_final_recommendations WHERE idea_id = :iid")->execute(['iid' => $ideaId]);
            return $this->success('FINAL_CLEARED', 'OK');
        }

        set_time_limit(0);
        $questions = $service->getQuestions($ideaId);
        $qaList = []; foreach ($questions as $q) { $ans = $q['last_answer'] ?? null; if (!$ans) continue; $qaList[] = ['question' => $q['question_text'] ?? '', 'dimension' => $q['dimension'] ?? '', 'answer' => $ans['selected_option_label'] ?? $ans['selected_option_key'] ?? $ans['answer_text'] ?? '']; }
        $desc = $idea['description'] ?? ''; $plainDesc = trim(strip_tags(str_replace(['<br>','<br/>','<br />'],"\n",$desc)));
        $coverage = json_decode($idea['coverage_json'] ?? '{}', true) ?: [];

        // Helper: load block row, strip bulk
        $loadBlock = function(string $table) use ($pdo, $ideaId) {
            $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE idea_id = :iid");
            $stmt->execute(['iid' => $ideaId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) return ['exists' => false];
            unset($row['id'], $row['idea_id'], $row['ai_request_json'], $row['ai_response_json'], $row['profile_json'], $row['potential_json'], $row['risk_report_json'], $row['pitfalls_json'], $row['plan_json']);
            $row['exists'] = true;
            return $row;
        };

        // Extract compact summaries from each block — refined takes priority over original
        $sum = fn($t,$k,$d='') => ($t['exists'] ?? false) ? ($t[$k] ?? $d) : null;
        $score = fn($t,$k,$d=0) => max(0,min(100,(float)($t[$k]??$d)));

        $uc = $loadBlock('idea_understanding_cards');
        $rc = $loadBlock('idea_refined_cards');
        // Use refined card if available, otherwise fall back to original
        $card = ($rc['exists'] ?? false) ? $rc : $uc;
        $cardSummary = $card['exists'] ? ['summary' => $sum($card,'summary',''), 'idea_type' => $sum($card,'idea_type',''), 'completeness' => $score($card,'completeness_score'), 'confidence' => $score($card,'confidence_score'), 'next_action' => $sum($card,'next_action','')] : ['exists' => false];

        $pot = $loadBlock('idea_potential_scores');
        $potentialSummary = $pot['exists'] ? ['score' => $score($pot,'potential_score'), 'level' => $sum($pot,'potential_level',''), 'verdict' => $sum($pot,'verdict','')] : ['exists' => false];

        $riskBlock = $loadBlock('idea_risk_reports');
        $riskSummary = $riskBlock['exists'] ? ['overall_score' => $score($riskBlock,'overall_risk_score'), 'level' => $sum($riskBlock,'overall_risk_level',''), 'critical_count' => (int)($riskBlock['critical_risks_count']??0), 'high_count' => (int)($riskBlock['high_risks_count']??0)] : ['exists' => false];

        $pitBlock = $loadBlock('idea_pitfalls_reports');
        $pitSummary = $pitBlock['exists'] ? ['complexity' => $sum($pitBlock,'overall_hidden_complexity',''), 'summary' => $sum($pitBlock,'overall_summary','')] : ['exists' => false];

        $planBlock = $loadBlock('idea_implementation_plans');
        $planSummary = $planBlock['exists'] ? ['summary' => $sum($planBlock,'summary',''), 'horizon' => $sum($planBlock,'planning_horizon',''), 'type' => $sum($planBlock,'plan_type',''), 'confidence' => $score($planBlock,'confidence_score')] : ['exists' => false];

        $blocks = [
            'understanding_card' => $cardSummary,
            'potential' => $potentialSummary,
            'risks' => $riskSummary,
            'pitfalls' => $pitSummary,
            'implementation_plan' => $planSummary,
        ];

        $payload = ['idea' => ['title' => $idea['title'] ?? '', 'short_description' => mb_substr($plainDesc, 0, 200), 'description_plain_text' => $plainDesc, 'category' => $idea['category'] ?? '', 'product' => $idea['product'] ?? '', 'region' => $idea['region'] ?? '', 'target_date' => $idea['target_date'] ?? null, 'current_date' => date('Y-m-d')], 'questions_and_answers' => $qaList] + $blocks;

        $sp = $this->t('idea/messages.system_prompt_final');

        try {
            $aiSvc = $this->container->get('service.ai_action'); $maxRetries = 2; $rawText = ''; $parsed = ['ok' => false, 'data' => null, 'error' => 'not_started'];
            for ($retry = 0; $retry <= $maxRetries; $retry++) {
                $result = $aiSvc->execute('idea_analyze', ['__usr' => "[SYSTEM]\n" . $sp . $this->localeInstruction() . "\n[/SYSTEM]\n\n[USER]\n" . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n[/USER]"], $this->user()['user'] ?? []);
                $rawText = $result['result']['preview']['summary'] ?? '';
                $parsed = $this->extractAiJson($rawText);
                if ($parsed['ok'] && !empty($parsed['data']['final_recommendation'])) break;
                ai_diag_log("[FINAL_RECOMMENDATION_RETRY] attempt=" . ($retry + 1) . " error=" . ($parsed['error'] ?? 'invalid_resp') . " text_len=" . strlen($rawText));
                if ($retry < $maxRetries) usleep(1000000);
            }

            try { $pdo->query('SELECT 1'); } catch (\Throwable) { $pdo = $this->container->get('db.pdo'); }
            $iter = (int)$pdo->query("SELECT COALESCE(MAX(iteration),0)+1 FROM idea_ai_iterations WHERE idea_id={$ideaId}")->fetchColumn();
            $pdo->prepare("INSERT INTO idea_ai_iterations (public_id, idea_id, iteration, type, request_payload, response_payload, created_at) VALUES (:pid, :iid, :iter, 'final_recommendation', :req, :res, NOW())")->execute(['pid' => 'iai_'.bin2hex(random_bytes(6)), 'iid' => $ideaId, 'iter' => $iter, 'req' => json_encode(['system_prompt' => $sp, 'payload' => $payload], JSON_UNESCAPED_UNICODE), 'res' => json_encode(['raw_text' => $rawText], JSON_UNESCAPED_UNICODE)]);

            $data = $parsed['ok'] && is_array($parsed['data']) ? $parsed['data'] : null;
            if (!is_array($data) || empty($data['final_recommendation'])) {
                ai_diag_log("[FINAL_RECOMMENDATION_PARSE_FAIL] text_len=".strlen($rawText)." parse_error=".($parsed['error'] ?? 'unknown')." preview=".substr($rawText, 0, 300));
                $data = $this->buildFallbackFinalRecommendationData($blocks, (string)($parsed['error'] ?? 'invalid_ai_json'));
            }

            $fr = $data['final_recommendation'] ?? [];
            $scores = fn($k) => max(0, min(100, (float)($fr[$k] ?? 0)));
            $pot = $scores('potential_score'); $feas = $scores('feasibility_score'); $risk = $scores('risk_score');
            $dcs = $scores('data_completeness_score'); $pqs = $scores('plan_quality_score'); $blk = $scores('blocker_score');
            $conf = max(0, min(100, (float)($fr['confidence_score'] ?? 0))) / 100;

            // Backend formula
            $calcScore = $pot * 0.30 + $feas * 0.25 + $dcs * 0.15 + $pqs * 0.15 + ($conf * 100) * 0.15 - $risk * 0.25 - $blk * 0.20;
            $calcScore = max(0, min(100, (int)round($calcScore)));

            // Backend status override
            $status = $dcs < 35 ? 'collect_more_data' : ($blk >= 85 ? 'reject_current_form' : ($blk >= 75 ? 'postpone' : ($risk >= 75 && $pot < 70 ? 'reject_current_form' : ($calcScore >= 75 && $risk <= 55 && $dcs >= 60 ? 'proceed' : ($calcScore >= 60 && $pot >= 65 ? 'proceed_with_validation' : ($calcScore >= 45 ? 'refine_first' : ($calcScore < 45 && $blk < 75 ? 'postpone' : 'reject_current_form')))))));
            if ($calcScore < 35) $status = 'reject_current_form';

            $labels = ['proceed' => $this->t('idea/messages.status_proceed'), 'proceed_with_validation' => $this->t('idea/messages.status_proceed_with_validation'), 'refine_first' => $this->t('idea/messages.status_refine_first'), 'collect_more_data' => $this->t('idea/messages.status_collect_more_data'), 'postpone' => $this->t('idea/messages.status_postpone'), 'reject_current_form' => $this->t('idea/messages.status_reject')];

            $row = ['status' => $status, 'status_label' => $labels[$status] ?? $status, 'recommendation_score' => $calcScore, 'ai_recommendation_score' => $scores('recommendation_score'), 'calculated_recommendation_score' => $calcScore, 'potential_score' => $pot, 'feasibility_score' => $feas, 'risk_score' => $risk, 'data_completeness_score' => $dcs, 'plan_quality_score' => $pqs, 'blocker_score' => $blk, 'confidence_score' => $conf, 'recommendation_json' => json_encode($data, JSON_UNESCAPED_UNICODE), 'ai_request_json' => json_encode(['system_prompt' => $sp, 'payload' => $payload], JSON_UNESCAPED_UNICODE), 'ai_response_json' => json_encode($data, JSON_UNESCAPED_UNICODE), 'idea_id' => $ideaId];

            $exists = $pdo->prepare("SELECT id FROM idea_final_recommendations WHERE idea_id = :iid"); $exists->execute(['iid' => $ideaId]);
            if ($exists->fetch()) { $pdo->prepare("UPDATE idea_final_recommendations SET status=:status,status_label=:status_label,recommendation_score=:recommendation_score,ai_recommendation_score=:ai_recommendation_score,calculated_recommendation_score=:calculated_recommendation_score,potential_score=:potential_score,feasibility_score=:feasibility_score,risk_score=:risk_score,data_completeness_score=:data_completeness_score,plan_quality_score=:plan_quality_score,blocker_score=:blocker_score,confidence_score=:confidence_score,recommendation_json=:recommendation_json,ai_request_json=:ai_request_json,ai_response_json=:ai_response_json,updated_at=NOW() WHERE idea_id=:idea_id")->execute($row); }
            else { $pdo->prepare("INSERT INTO idea_final_recommendations (idea_id,status,status_label,recommendation_score,ai_recommendation_score,calculated_recommendation_score,potential_score,feasibility_score,risk_score,data_completeness_score,plan_quality_score,blocker_score,confidence_score,recommendation_json,ai_request_json,ai_response_json) VALUES (:idea_id,:status,:status_label,:recommendation_score,:ai_recommendation_score,:calculated_recommendation_score,:potential_score,:feasibility_score,:risk_score,:data_completeness_score,:plan_quality_score,:blocker_score,:confidence_score,:recommendation_json,:ai_request_json,:ai_response_json)")->execute($row); }
            $fresh = $pdo->prepare("SELECT * FROM idea_final_recommendations WHERE idea_id = :iid"); $fresh->execute(['iid' => $ideaId]);
            return $this->success('FINAL_CALCULATED', 'OK', $fresh->fetch(\PDO::FETCH_ASSOC) ?: $row);
        } catch (\Throwable $e) { ai_diag_log("[FINAL_RECOMMENDATION_ERROR] " . $e->getMessage()); return $this->error('AI_UNAVAILABLE', $this->t('idea/messages.ai_recommendation_failed'), 503); }
    }

    /**
     * GET/POST/DELETE /ideas/{id}/suggested-tasks — tree-structured tasks from final recommendation + plan
     */
    public function suggestedTasks(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);
        $service = $this->container->get('service.idea');
        $pdo = $this->container->get('db.pdo');
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);
        $ideaId = (int)$idea['id'];

        if (($this->request()->method ?? 'GET') === 'GET') {
            $stmt = $pdo->prepare("SELECT * FROM idea_suggested_tasks WHERE idea_id = :iid");
            $stmt->execute(['iid' => $ideaId]);
            return $this->success('TASKS_LOADED', 'OK', $stmt->fetch(\PDO::FETCH_ASSOC) ?: ['empty' => true]);
        }
        if (($this->request()->method ?? '') === 'DELETE') {
            $pdo->prepare("DELETE FROM idea_suggested_tasks WHERE idea_id = :iid")->execute(['iid' => $ideaId]);
            return $this->success('TASKS_CLEARED', 'OK');
        }

        set_time_limit(0);

        // Read final recommendation and implementation plan — extract ONLY summaries, not full JSON
        $loadPlanSummary = function() use ($pdo, $ideaId): array {
            $r = $pdo->query("SELECT summary, planning_horizon, plan_type, confidence_score, plan_json FROM idea_implementation_plans WHERE idea_id={$ideaId}")->fetch(\PDO::FETCH_ASSOC);
            if (!$r) return ['exists' => false];
            // Extract only stage titles/goals, skip full task trees
            $pj = json_decode($r['plan_json'] ?? '{}', true) ?: [];
            $stages = [];
            foreach ($pj['implementation_plan']['stages'] ?? [] as $s) {
                $stages[] = ['title' => $s['title'] ?? '', 'goal' => $s['goal'] ?? ''];
            }
            return ['exists' => true, 'summary' => $r['summary'] ?? '', 'planning_horizon' => $r['planning_horizon'] ?? '', 'plan_type' => $r['plan_type'] ?? '', 'confidence_score' => $r['confidence_score'] ?? null, 'stages' => $stages];
        };
        $loadFinalSummary = function() use ($pdo, $ideaId): array {
            $r = $pdo->query("SELECT status, status_label, recommendation_score, confidence_score, recommendation_json FROM idea_final_recommendations WHERE idea_id={$ideaId}")->fetch(\PDO::FETCH_ASSOC);
            if (!$r) return ['exists' => false];
            $fj = json_decode($r['recommendation_json'] ?? '{}', true) ?: [];
            $fr = $fj['final_recommendation'] ?? [];
            return ['exists' => true, 'status' => $r['status'] ?? '', 'status_label' => $r['status_label'] ?? '', 'recommendation_score' => $r['recommendation_score'] ?? 0, 'confidence_score' => $r['confidence_score'] ?? 0, 'short_verdict' => $fr['short_verdict'] ?? '', 'main_reasons' => $fr['main_reasons'] ?? [], 'next_best_actions' => $fr['next_best_actions'] ?? []];
        };

        $final = $loadFinalSummary();
        $plan = $loadPlanSummary();

        $desc = $idea['description'] ?? '';
        $plainDesc = trim(strip_tags(str_replace(['<br>','<br/>','<br />'],"\n",$desc)));

        $payload = ['idea' => ['title' => $idea['title'] ?? '', 'short_description' => mb_substr($plainDesc, 0, 200), 'category' => $idea['category'] ?? '', 'current_date' => date('Y-m-d'), 'target_date' => $idea['target_date'] ?? null], 'final_recommendation' => $final, 'implementation_plan' => $plan];

         $sp = $this->t('idea/messages.system_prompt_project');

        try {
            $aiSvc = $this->container->get('service.ai_action'); $maxRetries = 2; $rawText = ''; $parsed = ['ok' => false];
            for ($retry = 0; $retry <= $maxRetries; $retry++) {
                $result = $aiSvc->execute('idea_analyze', ['__usr' => "[SYSTEM]\n" . $sp . $this->localeInstruction() . "\n[/SYSTEM]\n\n[USER]\n" . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n[/USER]"], $this->user()['user'] ?? []);
                $rawText = $result['result']['preview']['summary'] ?? '';
                $parsed = $this->extractAiJson($rawText);
                if ($parsed['ok'] && (!empty($parsed['data']['projects']) || !empty($parsed['data']['tasks']))) break;
                ai_diag_log("[TASKS_RETRY] attempt=" . ($retry+1) . " error=" . ($parsed['error'] ?? 'invalid_resp'));
                if ($retry < $maxRetries) usleep(1000000);
            }

            try { $pdo->query('SELECT 1'); } catch (\Throwable) { $pdo = $this->container->get('db.pdo'); }
            $iter = (int)$pdo->query("SELECT COALESCE(MAX(iteration),0)+1 FROM idea_ai_iterations WHERE idea_id={$ideaId}")->fetchColumn();
            $pdo->prepare("INSERT INTO idea_ai_iterations (public_id, idea_id, iteration, type, request_payload, response_payload, created_at) VALUES (:pid, :iid, :iter, 'suggested_tasks', :req, :res, NOW())")->execute(['pid' => 'iai_'.bin2hex(random_bytes(6)), 'iid' => $ideaId, 'iter' => $iter, 'req' => json_encode(['system_prompt' => $sp, 'payload' => $payload], JSON_UNESCAPED_UNICODE), 'res' => json_encode(['raw_text' => $rawText], JSON_UNESCAPED_UNICODE)]);

            if (!$parsed['ok'] || (empty($parsed['data']['projects']) && empty($parsed['data']['tasks']))) {
                ai_diag_log("[TASKS_PARSE_FAIL] parse_error=".($parsed['error']??'unknown')." text_len=".strlen($rawText));
                $row = ['tasks_json' => '{}', 'summary' => $this->t('idea/messages.ai_tasks_fallback_summary'), 'ai_request_json' => json_encode(['note' => 'AI analysis failed'], JSON_UNESCAPED_UNICODE), 'ai_response_json' => json_encode(['raw_text' => $rawText], JSON_UNESCAPED_UNICODE), 'idea_id' => $ideaId];
                $pdo->prepare("INSERT INTO idea_suggested_tasks (idea_id,tasks_json,summary,ai_request_json,ai_response_json) VALUES (:idea_id,:tasks_json,:summary,:ai_request_json,:ai_response_json)")->execute($row);
                return $this->success('TASKS_FALLBACK', 'OK', $row);
            }
            $data = $parsed['data'];

            // Normalize: if AI returned flat tasks, wrap in a single default project
            if (empty($data['projects']) && !empty($data['tasks'])) {
                $data = ['summary' => $data['summary'] ?? '', 'projects' => [['id' => 'p1', 'title' => $this->t('idea/messages.plan_title'), 'description' => '', 'tasks' => $data['tasks']]]];
            }

            $row = ['tasks_json' => json_encode($data, JSON_UNESCAPED_UNICODE), 'summary' => $data['summary'] ?? '', 'ai_request_json' => json_encode(['system_prompt' => $sp, 'payload' => $payload], JSON_UNESCAPED_UNICODE), 'ai_response_json' => json_encode($data, JSON_UNESCAPED_UNICODE), 'idea_id' => $ideaId];

            $exists = $pdo->prepare("SELECT id FROM idea_suggested_tasks WHERE idea_id = :iid"); $exists->execute(['iid' => $ideaId]);
            if ($exists->fetch()) { $pdo->prepare("UPDATE idea_suggested_tasks SET tasks_json=:tasks_json,summary=:summary,ai_request_json=:ai_request_json,ai_response_json=:ai_response_json,updated_at=NOW() WHERE idea_id=:idea_id")->execute($row); }
            else { $pdo->prepare("INSERT INTO idea_suggested_tasks (idea_id,tasks_json,summary,ai_request_json,ai_response_json) VALUES (:idea_id,:tasks_json,:summary,:ai_request_json,:ai_response_json)")->execute($row); }
            $fresh = $pdo->prepare("SELECT * FROM idea_suggested_tasks WHERE idea_id = :iid"); $fresh->execute(['iid' => $ideaId]);
            return $this->success('TASKS_CALCULATED', 'OK', $fresh->fetch(\PDO::FETCH_ASSOC) ?: $row);
        } catch (\Throwable $e) { ai_diag_log("[SUGGESTED_TASKS_ERROR] " . $e->getMessage()); return $this->error('AI_UNAVAILABLE', $this->t('idea/messages.ai_tasks_failed'), 503); }
    }

    /**
     * POST /ideas/{id}/create-project-tasks — create project + hierarchical tasks from suggested tasks
     */
    public function createProjectFromTasks(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);
        $pdo = $this->container->get('db.pdo');
        $idea = $this->container->get('service.idea')->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);
        $ideaId = (int)$idea['id'];
        $userId = (int)($this->user()['user']['id'] ?? 0);

        // Read suggested tasks
        $stmt = $pdo->prepare("SELECT * FROM idea_suggested_tasks WHERE idea_id = :iid");
        $stmt->execute(['iid' => $ideaId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row || empty($row['tasks_json'])) return $this->error('NO_TASKS', $this->t('idea/messages.generate_tasks_first'), 400);

        $tasksData = json_decode($row['tasks_json'], true) ?: [];
        $projects = $tasksData['projects'] ?? [];
        $flatTasks = $tasksData['tasks'] ?? [];
        if (empty($projects) && empty($flatTasks)) return $this->error('NO_TASKS', $this->t('idea/messages.no_tasks_to_create'), 400);

        // Use project-level data or wrap flat tasks
        $prjTitle = $idea['title'] ?? $this->t('idea/messages.project_label');
        $prjDesc = $idea['description'] ?? '';
        $allTasks = [];
        if (!empty($projects)) {
            $prj = $projects[0];
            $prjDesc = $prj['description'] ?: $prjDesc;
            $allTasks = $prj['tasks'] ?? [];
        } else {
            $allTasks = $flatTasks;
        }

        // Create project
        $projSvc = $this->container->get('service.project');
        $actor = $this->user()['user'] ?? ['id' => 0];
        $projResult = $projSvc->create(['title' => $prjTitle, 'description' => $prjDesc, 'status' => 'active', 'priority' => 'medium'], $actor);
        $projectId = (int)($projResult['id'] ?? 0);
        $projectPublicId = $projResult['public_id'] ?? '';

        // Create tasks with infinite hierarchy via SubtaskService (each subtask is a full task with relation)
        $taskSvc = $this->container->get('service.task');
        $subSvc = $this->container->get('service.subtask');
        $created = 0;
        $orderBaseTs = time();
        $orderSequence = 0;
        $createTasks = function(array $tasks, ?string $parentPublicId = null, int $depth = 0) use ($taskSvc, $subSvc, $projectPublicId, $actor, &$createTasks, &$created, $orderBaseTs, &$orderSequence) {
            foreach ($tasks as $i => $t) {
                $title = $t['title'] ?? ($this->t('idea/messages.task_label') . ' '.($i+1));
                $desc = $t['description'] ?? '';
                $prio = in_array(($t['priority'] ?? ''), ['high','medium','low'], true) ? $t['priority'] : 'medium';
                $sortOrder = ($i + 1) * 10;
                $orderedAt = gmdate('Y-m-d H:i:s', $orderBaseTs - $orderSequence);
                $orderSequence++;

                $publicId = null;
                if ($parentPublicId === null) {
                    // Top level: create task in project
                    $taskInput = ['title' => $title, 'description' => $desc, 'project_public_id' => $projectPublicId, 'status' => 'new', 'priority' => $prio, 'assignee_user_id' => (int)($actor['id'] ?? 0), 'created_at' => $orderedAt, 'updated_at' => $orderedAt];
                    $taskResult = $taskSvc->create($taskInput, $actor);
                    if (is_array($taskResult) && !empty($taskResult['public_id'])) {
                        $created++;
                        $publicId = (string)$taskResult['public_id'];
                    } elseif (is_string($taskResult) && $taskResult !== '') {
                        $created++;
                        $publicId = $taskResult;
                    }
                } else {
                    // Nested: create subtask via SubtaskService — each becomes a full task linked to parent
                    $subInput = ['title' => $title, 'description' => $desc, 'status' => 'new', 'priority' => $prio, 'assignee_user_public_id' => null, 'sort_order' => $sortOrder, 'created_at' => $orderedAt, 'updated_at' => $orderedAt];
                    $subResult = $subSvc->create($parentPublicId, $subInput, $actor);
                    if (is_array($subResult) && !empty($subResult['public_id'])) { $created++; $publicId = $subResult['public_id']; }
                }

                // Recurse into subtasks — infinite nesting via SubtaskService
                $subs = $t['subtasks'] ?? [];
                if ($subs && $publicId) $createTasks($subs, $publicId, $depth + 1);
            }
        };
        $createTasks($allTasks);

        return $this->success('PROJECT_CREATED', $this->t('idea/messages.project_created') . ' «{$prjTitle}» с {$created} ' . $this->t('idea/messages.tasks_created_count'), ['project_public_id' => $projectPublicId, 'tasks_created' => $created]);
    }

    private function localeInstruction(): string
    {
        $locale = trim((string)($this->request()->header('X-Locale') ?? 'ru-ru'));
        $lang = explode('-', $locale)[0];
        $langNames = ['ru' => 'Russian', 'en' => 'English', 'de' => 'German', 'fr' => 'French', 'es' => 'Spanish', 'pt' => 'Portuguese', 'it' => 'Italian', 'zh' => 'Chinese', 'ja' => 'Japanese', 'ko' => 'Korean', 'ar' => 'Arabic', 'tr' => 'Turkish'];
        $langName = $langNames[$lang] ?? 'Russian';
        return "\n\nCRITICAL: You MUST write your ENTIRE response in {$langName} language (locale: {$locale}). All question texts, option labels, verdicts, summaries, and explanations must be in {$langName}. Do NOT use English unless locale is English.";
    }

    private function stripTags(string $text): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/\[[\/]?[a-z]+\]/i', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private function isFeatureEnabled(): bool
    {
        try {
            $setting = (new \Api\Model\Setting\SettingRepository($this->container->get('db.pdo')))
                ->findByScopeAndName('features', 'ideas_ai_enabled');
            return $setting && ((int)($setting['value'] ?? 1) === 1);
        } catch (\Throwable) {
            return true;
        }
    }

    private function isSafeModeEnabled(): bool
    {
        try {
            $setting = (new \Api\Model\Setting\SettingRepository($this->container->get('db.pdo')))
                ->findByScopeAndName('features', 'ideas_ai_safe_mode');
            return $setting && ((int)($setting['value'] ?? 0) === 1);
        } catch (\Throwable) {
            return false;
        }
    }

    private function getCurrentCycleId(int $ideaId): int
    {
        $stmt = $this->container->get('db.pdo')->prepare("SELECT MAX(cycle_id) FROM idea_questions WHERE idea_id = :id");
        $stmt->execute(['id' => $ideaId]);
        return (int)($stmt->fetchColumn() ?: 1);
    }

    /**
     * POST /ideas/{id}/interview — AI diagnostic interview: generate questions.
     */
    public function aiInterview(array $params = []): JsonResponse
    {
        // DELETE: clear all questions and answers for this idea
        if (($this->request()->method ?? '') === 'DELETE') {
            $publicId = (string)($params['public_id'] ?? '');
            if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);
            $service = $this->container->get('service.idea');
            $idea = $service->getByPublicId($publicId);
            if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);
            $ideaId = (int)$idea['id'];
            $pdo = $this->container->get('db.pdo');
            $pdo->exec("DELETE FROM idea_answers WHERE idea_id={$ideaId}");
            $pdo->exec("DELETE FROM idea_questions WHERE idea_id={$ideaId}");
            $pdo->exec("DELETE FROM idea_ai_iterations WHERE idea_id={$ideaId}");
            $pdo->prepare("UPDATE ideas SET coverage_json = NULL WHERE id = :iid")->execute(['iid' => $ideaId]);
            return $this->success('INTERVIEW_CLEARED', $this->t('idea/messages.interview_cleared'));
        }

        set_time_limit(0);
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $service = $this->container->get('service.idea');
        $pdo = $this->container->get('db.pdo');
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $ideaId = (int)$idea['id'];

        // Check question limit (max 25, at least 5 per batch) — exclude clarifications and gaps from limit
        $totalQ = (int)$pdo->query("SELECT COUNT(*) FROM idea_questions WHERE idea_id={$ideaId}")->fetchColumn();
        $coverage = json_decode($idea['coverage_json'] ?? '{}', true) ?: [];
        $clarPids = [];
        foreach (($coverage['additional_clarifications']['questions'] ?? []) as $cq) {
            if (!empty($cq['public_id'])) $clarPids[$cq['public_id']] = true;
        }
        $gapPids = [];
        foreach (($coverage['gap_clarifications']['questions'] ?? []) as $gq) {
            if (!empty($gq['public_id'])) $gapPids[$gq['public_id']] = true;
        }
        $interviewQ = $totalQ - count($clarPids) - count($gapPids);
        $remaining = 25 - $interviewQ;
        if ($remaining < 5) {
            return $this->success('INTERVIEW_COMPLETE', $this->t('idea/messages.interview_limit_reached'), [
                'complete' => true, 'total' => $interviewQ, 'questions' => $service->getQuestions($ideaId),
            ]);
        }

        // Load existing questions for dedup and prompt context
        $existingQuestions = $service->getQuestions($ideaId);
        $asked = [];
        $coveredDimensions = [];
        $doNotAskAgainDimensions = [];
        foreach ($existingQuestions as $q) {
            $ans = $q['last_answer'] ?? null;
            $dim = $q['dimension'] ?? '';
            $isUnk = $ans && ((int)($ans['is_unknown'] ?? 0) === 1);
            $entry = ['question_id' => (string)$q['id'], 'question' => $q['question_text'] ?? '', 'dimension' => $dim, 'semantic_key' => $dim, 'topic_covered' => $ans !== null, 'user_knows_answer' => $ans !== null && !$isUnk];
            if ($ans) {
                if ($isUnk) { $entry['answer'] = ['is_unknown' => true]; $doNotAskAgainDimensions[$dim] = true; }
                elseif (!empty($ans['answer_text'])) { $entry['answer'] = ['custom_answer' => $ans['answer_text'], 'is_custom' => true]; $coveredDimensions[$dim] = true; }
                else { $entry['answer'] = ['selected_option' => $ans['selected_option_label'] ?? $ans['selected_option_key'] ?? '']; $coveredDimensions[$dim] = true; }
            }
            $asked[] = $entry;
        }
        $coverage['already_covered_topics'] = array_values(array_unique(array_merge($coverage['already_covered_topics'] ?? [], array_keys($coveredDimensions))));
        $coverage['do_not_ask_again_topics'] = array_values(array_unique(array_merge($coverage['do_not_ask_again_topics'] ?? [], array_keys($doNotAskAgainDimensions))));

        // Build prompts
        $promptSvc = new \Api\System\Library\Service\IdeaInterviewPromptService();
        $systemPrompt = $promptSvc->buildSystemPrompt();
        $userPrompt = $promptSvc->buildUserPrompt([
            'idea' => [
                'title' => $this->stripTags($idea['title']),
                'description' => $this->stripTags($idea['description'] ?? ''),
                'category' => $idea['category'] ?? '',
                'region' => $idea['region'] ?? '',
                'target_date' => $idea['target_date'] ?? null,
            ],
            'question_limits' => ['total' => 25, 'asked' => $interviewQ],
            'already_asked_questions' => $asked,
            'already_covered_topics' => $coverage['already_covered_topics'] ?? [],
            'do_not_ask_again_topics' => $coverage['do_not_ask_again_topics'] ?? [],
            'is_first_interview' => empty($asked),
        ]);

        $genQuestions = [];
        $aiFailed = false;
        $rawText = '';
        $aiMode = 'unknown';
        $maxRetries = 4; // 5 total attempts
        for ($retry = 0; $retry <= $maxRetries; $retry++) {
        try {
            $aiSvc = $this->container->get('service.ai_action');

            // Merge system + user prompts into single user_prompt (no separate __sys)
            $combinedPrompt = "[SYSTEM]\n" . $systemPrompt . $this->localeInstruction() . "\n[/SYSTEM]\n\n[USER]\n" . $userPrompt . "\n[/USER]";

            @set_time_limit(0);
            $result = $aiSvc->execute('idea_analyze', [
                '__usr' => $combinedPrompt . $this->localeInstruction(),
                'max_tokens' => 128000,
                'timeout_ms' => 240000,
            ], $this->user()['user'] ?? []);
            
            $rawText = $result['result']['preview']['summary'] ?? ($result['result']['text'] ?? '');
            $aiMode = $result['result']['mode'] ?? 'unknown';
            $aiFailed = $aiMode === 'safe_mock' || trim($rawText) === '' || str_contains($rawText, 'AI не смог сформировать ответ');
            // Reconnect PDO if connection dropped during AI processing
            try { $pdo->query('SELECT 1'); } catch (\Throwable) { $pdo = $this->container->get('db.pdo'); }

            // Log iteration for debug — include AI error diagnostics
            $iter = (int)$pdo->query("SELECT COALESCE(MAX(iteration),0)+1 FROM idea_ai_iterations WHERE idea_id={$ideaId}")->fetchColumn();
            $debugRes = ['raw_text' => $rawText, 'questions_count' => 0, 'ai_mode' => $aiMode];
            if ($aiFailed) {
                $debugRes['ai_error'] = $result['result']['error_code'] ?? ($result['result']['code'] ?? 'AI_PROVIDER_UNAVAILABLE');
                $debugRes['http_status'] = (int)($result['result']['http_status'] ?? 0);
                $debugRes['ai_failed'] = true;
            }
            $pdo->prepare("INSERT INTO idea_ai_iterations (public_id, idea_id, iteration, type, request_payload, response_payload, created_at) VALUES (:pid, :iid, :iter, 'interview', :req, :res, NOW())")
                ->execute(['pid' => 'iai_'.bin2hex(random_bytes(6)), 'iid' => $ideaId, 'iter' => $iter, 'req' => json_encode(['user_prompt' => $combinedPrompt], JSON_UNESCAPED_UNICODE), 'res' => json_encode($debugRes, JSON_UNESCAPED_UNICODE)]);
            if (!empty($rawText)) {
                $clean = $rawText;
                $clean = preg_replace('/^```(?:json)?\s*\n?/i', '', $clean);
                $clean = preg_replace('/\n?```\s*$/i', '', $clean);
                $data = json_decode($clean, true);
                if (!is_array($data) && preg_match('/\{.*\}/s', $clean, $m)) {
                    $data = json_decode($m[0], true);
                }
                if (is_array($data)) {
                    // Extract AI's own assessment of covered topics — persist for next cycle
                    if (!empty($data['idea_diagnostics']['already_covered_topics'])) {
                        $aiCovered = is_array($data['idea_diagnostics']['already_covered_topics']) ? $data['idea_diagnostics']['already_covered_topics'] : [];
                        $coverage['already_covered_topics'] = array_values(array_unique(array_merge($coverage['already_covered_topics'] ?? [], $aiCovered)));
                    }
                    if (!empty($data['idea_diagnostics']['do_not_ask_again_topics'])) {
                        $aiDna = is_array($data['idea_diagnostics']['do_not_ask_again_topics']) ? $data['idea_diagnostics']['do_not_ask_again_topics'] : [];
                        $coverage['do_not_ask_again_topics'] = array_values(array_unique(array_merge($coverage['do_not_ask_again_topics'] ?? [], $aiDna)));
                    }
                    $rawQuestions = $data['questions'] ?? $data['gen_questions'] ?? $data['generated_questions'] ?? [];
                    if (!empty($rawQuestions)) {
                    foreach ($rawQuestions as $q) {
                        $qt = $q['question'] ?? $q['question_text'] ?? '';
                        if (trim($qt) === '') continue;
                        $genQuestions[] = [
                            'question' => $qt,
                            'reason' => $q['why_needed'] ?? '',
                            'question_type' => 'multiple_choice',
                            'dimension' => $q['dimension'] ?? $q['semantic_key'] ?? 'other',
                            'options' => array_map(function($o) { 
                                return ['key' => $o['value'] ?? '', 'label' => $o['label'] ?? '']; 
                            }, $q['options'] ?? []),
                        ];
                    }
                    }
                }
            }
            // Update iteration with actual questions count after parsing
            $pdo->prepare("UPDATE idea_ai_iterations SET response_payload = :res WHERE id = (SELECT MAX(id) FROM (SELECT id FROM idea_ai_iterations WHERE idea_id = :iid ORDER BY id DESC LIMIT 1) t)")
                ->execute(['res' => json_encode(array_merge($debugRes, ['questions_count' => count($genQuestions)]), JSON_UNESCAPED_UNICODE), 'iid' => $ideaId]);
            if (count($genQuestions) === 0 && !$aiFailed && trim($rawText ?? '') !== '') {
                $lastChar = mb_substr(trim($rawText ?? ''), -1);
                $truncated = !in_array($lastChar, ['}', ']'], true) ? '1' : '0';
                ai_diag_log("[AI_INTERVIEW_PARSE] idea_id={$ideaId} ai_ok but 0 questions parsed. json_valid=".(is_array($data??null)?'1':'0')." json_error=".json_last_error_msg()." has_questions_key=".(!empty(($data??[])['questions']??[])?'1':'0')." truncated={$truncated} last_char={$lastChar} text_len=".strlen($rawText??'')." text_preview=".substr(trim($rawText??''),0,200));
            }
            if (!$aiFailed && count($genQuestions) >= 5) {
                break;
            }
            if (!$aiFailed && count($genQuestions) > 0) {
                ai_diag_log("[AI_INTERVIEW_SHORT] idea_id={$ideaId} interview={$interviewQ} questions=" . count($genQuestions) . " (less than 5, using as-is)");
                break;
            }
            if ($retry < $maxRetries && $aiFailed) {
                $errType = $debugRes['ai_error'] ?? '';
                $retryable = in_array($errType, ['AI_PROVIDER_INVALID_RESPONSE', 'AI_PROVIDER_TIMEOUT', 'AI_PROVIDER_SERVER_ERROR', 'AI_PROVIDER_CONNECTION_FAILED', 'AI_PROVIDER_RATE_LIMITED', 'AI_PROVIDER_HTTP_ERROR'], true);
                if ($retryable) ai_diag_log("[AI_INTERVIEW_RETRY] attempt " . ($retry+2) . " for idea_id={$ideaId} error={$errType}");
                usleep(($retry + 1) * 1500000);
                continue;
            }
            if (!$aiFailed) break;
        } catch (\Throwable $e) {
            $aiFailed = true;
            ai_diag_log("[AI_INTERVIEW_WARN] " . $e->getMessage());
            if ($retry < $maxRetries) {
                usleep(($retry + 1) * 1500000);
                continue;
            }
        }
        } // end retry loop

        // Reconnect PDO if connection dropped during long AI processing
        try { $pdo->query('SELECT 1'); } catch (\Throwable) {
            $pdo = $this->container->get('db.pdo');
        }

        // No fallback — all questions MUST be AI-generated
        if (count($genQuestions) === 0 && $interviewQ === 0) {
            ai_diag_log("[AI_INTERVIEW_EMPTY] idea_id={$ideaId} interview={$interviewQ} aiFailed=".($aiFailed?'1':'0')." aiMode={$aiMode} textLen=".strlen($rawText));
            return $this->error('AI_UNAVAILABLE', $this->t('idea/messages.ai_questions_generation_failed'), 503);
        }

        // Take up to remaining count (cap at 15 per batch for response size)
        $genQuestions = array_slice($genQuestions, 0, min($remaining, 15));

        // Deduplicate against existing questions before saving
        // Semantic aliases: when the AI uses a different dimension name for the same topic
        $semanticAliases = [
            'region' => 'region', 'location' => 'region', 'city' => 'region', 'place' => 'region', 'area' => 'region', 'market' => 'region', 'geography' => 'region',
            'finance' => 'finance', 'budget' => 'finance', 'investment' => 'finance', 'startup_costs' => 'finance', 'money' => 'finance', 'resources' => 'finance',
            'experience' => 'experience', 'skills' => 'experience', 'competence' => 'experience', 'background' => 'experience', 'qualification' => 'experience',
            'timeline' => 'timeline', 'deadline' => 'timeline', 'launch_date' => 'timeline', 'start_date' => 'timeline', 'timeframe' => 'timeline', 'launch_plan' => 'timeline',
            'target_audience' => 'target_audience', 'clients' => 'target_audience', 'customers' => 'target_audience', 'audience' => 'target_audience', 'buyer_persona' => 'target_audience',
            'service' => 'service', 'product' => 'service', 'services' => 'service', 'products' => 'service', 'offering' => 'service',
            'business_model' => 'business_model', 'operations' => 'business_model', 'format' => 'business_model', 'model' => 'business_model', 'work_organization' => 'business_model',
            'marketing' => 'marketing', 'sales' => 'marketing', 'promotion' => 'marketing', 'channels' => 'marketing', 'customer_acquisition' => 'marketing',
            'team' => 'team', 'staff' => 'team', 'employees' => 'team', 'hiring' => 'team', 'masters' => 'team', 'partners' => 'team',
            'legal' => 'legal', 'registration' => 'legal', 'status' => 'legal', 'ip' => 'legal', 'taxes' => 'legal', 'sole_proprietor' => 'legal',
            'premises' => 'premises', 'office' => 'premises', 'rent' => 'premises', 'space' => 'premises', 'room' => 'premises', 'studio' => 'premises',
            'competitors' => 'competitors', 'competition' => 'competitors', 'rivals' => 'competitors', 'competitive_environment' => 'competitors',
            'risks' => 'risks', 'challenges' => 'risks', 'threats' => 'risks', 'obstacles' => 'risks', 'risk_analysis' => 'risks',
            'suppliers' => 'suppliers', 'materials' => 'suppliers', 'procurement' => 'suppliers', 'inventory' => 'suppliers', 'equipment' => 'suppliers',
            'personal_involvement' => 'personal_involvement', 'owner_role' => 'personal_involvement', 'self_work' => 'personal_involvement', 'participation' => 'personal_involvement',
            'goal' => 'goal', 'objective' => 'goal', 'success_criteria' => 'goal', 'purpose' => 'goal', 'expected_result' => 'goal', 'revenue_goal' => 'goal',
        ];
        // Layer 1: semantic_key / dimension dedup (with alias resolution)
        $existingDims = [];
        foreach ($existingQuestions as $eq) {
            $dim = $eq['dimension'] ?? '';
            if ($dim !== '') {
                $canonical = $semanticAliases[$dim] ?? $dim;
                $existingDims[$canonical] = true;
            }
        }
        $doNotAskSet = [];
        foreach (($coverage['do_not_ask_again_topics'] ?? []) as $d) {
            $canonical = $semanticAliases[$d] ?? $d;
            $doNotAskSet[$canonical] = true;
        }
        $doNotAskSet = array_merge($doNotAskSet, $existingDims);
        // Layer 2: text-normalized dedup
        $existingTexts = [];
        foreach ($existingQuestions as $eq) {
            $t = mb_strtolower(trim(preg_replace('/[^\p{L}\p{N}]/u', '', $eq['question_text'] ?? '')));
            if ($t !== '') $existingTexts[$t] = true;
        }
        $dedupedQuestions = [];
        $dedupedTexts = [];
        foreach ($genQuestions as $q) {
            $dim = $q['dimension'] ?? $q['semantic_key'] ?? '';
            $canonical = ($dim !== '' && isset($semanticAliases[$dim])) ? $semanticAliases[$dim] : $dim;
            if ($canonical !== '' && isset($doNotAskSet[$canonical])) continue;
            $t = mb_strtolower(trim(preg_replace('/[^\p{L}\p{N}]/u', '', $q['question_text'] ?? $q['question'] ?? '')));
            if ($t === '' || isset($existingTexts[$t]) || isset($dedupedTexts[$t])) continue;
            $dedupedTexts[$t] = true;
            if (empty($q['question_text'])) $q['question_text'] = $q['question'] ?? '';
            $dedupedQuestions[] = $q;
        }

        // Save questions using service (handles column mapping correctly)
        $cycleId = (int)$pdo->query("SELECT COALESCE(MAX(cycle_id),0)+1 FROM idea_questions WHERE idea_id={$ideaId}")->fetchColumn();
        if ($dedupedQuestions !== []) {
            $service->saveQuestions($ideaId, $cycleId, $dedupedQuestions);
            $interviewQ += count($dedupedQuestions);
        }

        // Persist coverage state (already_covered_topics, do_not_ask_again_topics) for next cycle
        $coverageJson = json_encode($coverage, JSON_UNESCAPED_UNICODE);
        $pdo->prepare("UPDATE ideas SET coverage_json = :cov WHERE id = :iid")
            ->execute(['cov' => $coverageJson, 'iid' => $ideaId]);

        $savedQuestions = $service->getQuestions($ideaId);
        return $this->success('INTERVIEW_QUESTIONS', $this->t('idea/messages.questions_generated'), [
            'questions' => $savedQuestions,
            'total' => $interviewQ,
        ]);
    }

    /**
     * POST /ideas/{id}/interview-answers — save interview answers.
     */
    public function saveInterviewAnswers(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $service = $this->container->get('service.idea');
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $ideaId = (int)$idea['id'];
        $input = $this->request()->allInput();
        $answers = $input['answers'] ?? [];

        if (!is_array($answers) || $answers === []) {
            return $this->error('VALIDATION', $this->t('idea/messages.no_answers_to_save'), 400);
        }

        // Validate and normalize
        $pdo = $this->container->get('db.pdo');
        $normalized = [];
        foreach ($answers as $a) {
            $qId = (int)($a['question_id'] ?? 0);
            if ($qId <= 0 && !empty($a['question_public_id'])) {
                $stmt = $pdo->prepare("SELECT id FROM idea_questions WHERE public_id = :pid AND idea_id = :iid");
                $stmt->execute(['pid' => (string)$a['question_public_id'], 'iid' => $ideaId]);
                $qId = (int)($stmt->fetchColumn() ?: 0);
            }
            if ($qId <= 0) continue;
            $ver = $pdo->prepare("SELECT 1 FROM idea_questions WHERE id = :qid AND idea_id = :iid");
            $ver->execute(['qid' => $qId, 'iid' => $ideaId]);
            if (!$ver->fetchColumn()) continue;

            $selectedOptions = $this->normalizeSelectedOptions($a['selected_options'] ?? []);
            if ($selectedOptions === [] && (!empty($a['selected_option_key']) || !empty($a['selected_option_label']))) {
                $selectedOptions[] = [
                    'key' => (string)($a['selected_option_key'] ?? ''),
                    'label' => (string)($a['selected_option_label'] ?? $a['selected_option_key'] ?? ''),
                ];
            }
            $selectedLabels = array_values(array_filter(array_map(fn($opt) => trim((string)($opt['label'] ?? $opt['key'] ?? '')), $selectedOptions)));
            $firstOption = $selectedOptions[0] ?? null;
            $answerText = isset($a['answer_text']) ? trim((string)$a['answer_text']) : null;

            if ($selectedOptions === [] && ($answerText === null || $answerText === '')) continue;

            $normalized[] = [
                'question_id' => $qId,
                'selected_option_key' => $a['selected_option_key'] ?? ($firstOption['key'] ?? null),
                'selected_option_label' => $a['selected_option_label'] ?? ($selectedLabels !== [] ? implode(', ', $selectedLabels) : null),
                'answer_text' => $answerText !== '' ? $answerText : null,
                'is_custom' => (int)($a['is_custom'] ?? ($answerText ? 1 : 0)),
                'is_unknown' => (int)($a['is_unknown'] ?? $this->selectedOptionsContainUnknown($selectedOptions)),
                'selected_options' => $selectedOptions,
            ];
        }

        if ($normalized !== []) {
            $service->saveAnswers($ideaId, $normalized);
        }

        $updatedQuestions = $service->getQuestions($ideaId);
        return $this->success('ANSWERS_SAVED', $this->t('idea/messages.answers_saved_label'), ['questions' => $updatedQuestions, 'saved_count' => count($normalized)]);
    }

    private function normalizeSelectedOptions(mixed $options): array
    {
        if (is_string($options)) {
            $decoded = json_decode($options, true);
            $options = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($options)) return [];

        $normalized = [];
        $seen = [];
        foreach ($options as $option) {
            if (is_array($option)) {
                $key = trim((string)($option['key'] ?? $option['value'] ?? $option['label'] ?? ''));
                $label = trim((string)($option['label'] ?? $option['value'] ?? $key));
            } else {
                $key = trim((string)$option);
                $label = trim((string)$option);
            }
            if ($key === '' && $label === '') continue;
            $fingerprint = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $label !== '' ? $label : $key) ?? ($label !== '' ? $label : $key)));
            if ($fingerprint === '' || isset($seen[$fingerprint])) continue;
            $seen[$fingerprint] = true;
            $normalized[] = ['key' => $key !== '' ? $key : $label, 'label' => $label !== '' ? $label : $key];
        }
        return $normalized;
    }

    private function selectedOptionsContainUnknown(array $options): bool
    {
        foreach ($options as $option) {
            $key = mb_strtolower((string)($option['key'] ?? ''));
            if (in_array($key, ['unknown', 'not_sure'], true)) return true;
        }
        return false;
    }

    private function normalizeAiAnswerOptions(array $answers, bool $appendDefaults = true): array
    {
        $normalized = [];
        $seen = [];
        $seenKeys = [];
        foreach ($answers as $answer) {
            if (is_array($answer)) {
                $value = trim((string)($answer['value'] ?? $answer['key'] ?? $answer['label'] ?? ''));
                $label = trim((string)($answer['label'] ?? $answer['value'] ?? $value));
            } else {
                $value = trim((string)$answer);
                $label = trim((string)$answer);
            }
            $fingerprint = mb_strtolower(trim(preg_replace('/[^\p{L}\p{N}\s]+/u', '', preg_replace('/\s+/u', ' ', $label) ?? $label) ?? $label));
            $keyFingerprint = mb_strtolower($value);
            if (($keyFingerprint === 'unknown' && isset($seenKeys['not_sure'])) || ($keyFingerprint === 'not_sure' && isset($seenKeys['unknown']))) continue;
            if ($value === '' || $label === '' || $fingerprint === '' || isset($seen[$fingerprint]) || isset($seenKeys[$keyFingerprint])) continue;
            $seen[$fingerprint] = true;
            $seenKeys[$keyFingerprint] = true;
            $normalized[] = ['value' => $value, 'label' => $label];
        }

        if ($appendDefaults) {
            foreach ([['value' => 'not_sure', 'label' => $this->t('idea/messages.sm_not_sure')], ['value' => 'custom', 'label' => $this->t('idea/messages.option_custom_answer')]] as $default) {
                $fingerprint = mb_strtolower($default['label']);
                if ($default['value'] === 'not_sure' && (isset($seenKeys['unknown']) || isset($seenKeys['not_sure']))) continue;
                if ($default['value'] === 'custom' && (isset($seen['другое']) || isset($seenKeys['custom']))) continue;
                if (!isset($seen[$fingerprint]) && !isset($seenKeys[$default['value']])) {
                    $seen[$fingerprint] = true;
                    $seenKeys[$default['value']] = true;
                    $normalized[] = $default;
                }
            }
        }

        return $normalized;
    }

    /**
     * GET /ideas/{id}/state — returns idea state with coverage, questions, available actions.
     * Spec section 11.3.
     */
    public function state(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $service = $this->container->get('service.idea');
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $pdo = $this->container->get('db.pdo');
        $this->ensureIdeaWorkflowTables($pdo);

        $status = (string)($idea['status'] ?? 'draft');
        $coverage = json_decode($idea['coverage_json'] ?? '{}', true) ?: [];
        $questions = $service->getQuestions((int)$idea['id'], $this->getCurrentCycleId((int)$idea['id']));
        if (!in_array($status, ['questioning', 'question_generation', 'answers_processing'], true)) {
            $questions = [];
        }
        $analyses = $service->getAnalyses((int)$idea['id']);

        $completedTypes = [];
        // Exclude answers_processing records from analyses list
        $rawAnalyses = [];
        foreach ($analyses as $a) {
            if ($a['analysis_type'] === 'answers_processing') continue;
            $r = $a['result_json'] ?? null;
            if (is_string($r)) { $r = json_decode($r, true); }
            // Try to unwrap wrapper format preview.summary
            if (is_array($r) && isset($r['ok']) && isset($r['result']['preview']['summary'])) {
                $summary = $r['result']['preview']['summary'];
                $parsed = json_decode($summary, true);
                if (is_array($parsed) && $parsed !== []) {
                    $r = $parsed;
                }
            }
            $isContentReady = is_array($r) && $r !== [] && !isset($r['ok']) && count($r) > 0;
            $rawAnalyses[] = [
                'analysis_type' => $a['analysis_type'],
                'status' => $a['status'],
                'is_ready' => $a['status'] === 'completed' && $isContentReady,
                'result_json' => $r,
                'error_message' => $a['status'] === 'failed' ? ($a['error_message'] ?? null) : null,
                'completed_at' => $a['completed_at'] ?? $a['created_at'],
            ];
            if ($a['status'] === 'completed' && $isContentReady) {
                $completedTypes[] = $a['analysis_type'];
            }
        }

        // Determine final_report readiness
        $frAnalysis = null;
        foreach ($rawAnalyses as $ra) {
            if ($ra['analysis_type'] === 'final_report') { $frAnalysis = $ra; break; }
        }
        $finalReportReady = $frAnalysis !== null && $frAnalysis['is_ready'];

        // Get recommended_next_action
        $coverage = json_decode($idea['coverage_json'] ?? '{}', true) ?: [];
        $recommendedAction = $this->getRecommendedNextAction((int)$idea['id'], $coverage);

        $actions = ['edit_idea'];
        if (in_array($status, ['draft', 'classification_pending'])) $actions[] = 'start_analysis';
        if ($status === 'questioning') $actions[] = 'answer_questions';
        if ($status === 'ready_for_analysis' || $finalReportReady) $actions[] = 'run_analysis';
        if ($recommendedAction === 'ask_questions' && $status !== 'analysis_ready') $actions[] = 'answer_questions';
        if ($status === 'analysis_ready' || $status === 'analysis_partially_ready') $actions[] = 'view_report';
        if ($finalReportReady) $actions[] = 'task_decomposition';
        if (in_array($status, ['tasks_created', 'tasks_partially_created'])) $actions[] = 'view_tasks';

        $stepsStmt = $pdo->prepare("SELECT step_key, step_order, status, error_message, completed_at FROM idea_analysis_steps WHERE idea_id = :iid ORDER BY step_order ASC");
        $stepsStmt->execute(['iid' => (int)$idea['id']]);
        $analysisSteps = $stepsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $visibleMode = 'initial';
        if ($status === 'questioning' || $status === 'question_generation' || count($questions) > 0) $visibleMode = 'questions';
        if ($status === 'ready_for_analysis') $visibleMode = 'ready_for_analysis';
        if ($status === 'analysis_pending' || $status === 'analysis_in_progress' || $status === 'analysis_partially_ready') $visibleMode = 'analysis_progress';
        if ($status === 'analysis_ready') $visibleMode = 'analysis_ready';
        if ($status === 'task_decomposition_pending' || $status === 'task_decomposition_ready' || $status === 'tasks_created') $visibleMode = 'task_decomposition';
        if ($status === 'failed') $visibleMode = 'error';

        $iid = (int)$idea['id'];
        $allQuestionsForHistory = $service->getQuestions($iid);
        $answeredCycles = [];
        $currentCycle = $this->getCurrentCycleId($iid);
        foreach ($allQuestionsForHistory as $q) {
            $cId = (int)($q['cycle_id'] ?? 1);
            if ($cId >= $currentCycle) continue; // skip current active cycle
            if (!isset($answeredCycles[$cId])) $answeredCycles[$cId] = ['cycle_number' => $cId, 'questions' => []];
            $answeredCycles[$cId]['questions'][] = [
                'id' => $q['id'],
                'question_text' => $q['question_text'],
                'last_answer' => $q['last_answer'] ?? null,
            ];
        }
        $answeredCyclesSummary = array_values($answeredCycles);

        return $this->success('IDEA_STATE', $this->t('common/messages.ok'), [
            'idea' => $idea,
            'current_status' => $status,
            'coverage' => $coverage,
            'recommended_next_action' => $recommendedAction,
            'active_question_cycle' => ['cycle_number' => $this->getCurrentCycleId((int)$idea['id'])],
            'active_questions' => $questions,
            'answered_cycles_summary' => $answeredCyclesSummary,
            'analyses' => $rawAnalyses,
            'analysis_steps' => $analysisSteps,
            'final_report' => [
                'status' => $frAnalysis['status'] ?? 'not_started',
                'is_ready' => $finalReportReady,
                'result_json' => $frAnalysis['result_json'] ?? null,
            ],
            'available_actions' => $actions,
            'visible_mode' => $visibleMode,
        ]);
    }

    /**
     * POST /ideas/{id}/answers — save answers independently from refine.
     * Spec section 11.2.
     */
    public function saveAnswers(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $input = $this->request()->allInput();
        $answers = $input['answers'] ?? [];

        if ($publicId === '' || !is_array($answers) || $answers === []) {
            return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);
        }

        $service = $this->container->get('service.idea');
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $service->saveAnswers((int)$idea['id'], $answers);
        $service->updateStatus((int)$idea['id'], 'questioning');

        return $this->success('ANSWERS_SAVED', $this->t('idea/messages.answers_saved'), ['saved' => count($answers)]);
    }

    /**
     * GET /ideas/{id}/tasks/drafts — return task drafts for an idea.
     * Spec section 10.3.
     */
    public function taskDrafts(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $service = $this->container->get('service.idea');
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $drafts = $service->getTaskDrafts((int)$idea['id']);
        return $this->success('TASK_DRAFTS_LIST', $this->t('common/messages.ok'), ['items' => $drafts]);
    }

    /**
     * PUT /ideas/{id}/tasks/drafts/{draftTaskId} — update a task draft.
     * Spec section 10.3.
     */
    public function updateTaskDraft(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $draftId = (string)($params['draftTaskId'] ?? '');
        $input = $this->request()->allInput();

        if ($publicId === '' || $draftId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $pdo = $this->container->get('db.pdo');
        $stmt = $pdo->prepare("SELECT itd.* FROM idea_task_drafts itd JOIN ideas d ON d.id = itd.idea_id WHERE d.public_id = :pid AND itd.public_id = :did");
        $stmt->execute(['pid' => $publicId, 'did' => $draftId]);
        $draft = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$draft) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $updates = [];
        if (isset($input['title'])) $updates['title'] = $input['title'];
        if (isset($input['description'])) $updates['description'] = $input['description'];
        if (isset($input['is_selected'])) $updates['is_selected'] = (int)$input['is_selected'];
        if (isset($input['priority'])) $updates['priority'] = $input['priority'];
        if (isset($input['stage'])) $updates['stage'] = $input['stage'];

        if ($updates !== []) {
            $setClauses = [];
            $params2 = [];
            foreach ($updates as $col => $val) { $setClauses[] = "{$col} = :{$col}"; $params2[$col] = $val; }
            $params2['did'] = $draft['id'];
            $pdo->prepare("UPDATE idea_task_drafts SET " . implode(', ', $setClauses) . " WHERE id = :did")->execute($params2);
        }

        return $this->success('TASK_DRAFT_UPDATED', $this->t('common/messages.updated'));
    }

    /**
     * POST /ideas/{id}/reset-analysis — clear broken/stale analysis records, reset to draft.
     */
    public function resetAnalysis(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $service = $this->container->get('service.idea');
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $ideaId = (int)$idea['id'];
        $pdo = $this->container->get('db.pdo');
        $this->ensureIdeaWorkflowTables($pdo);

        $deleted = [];
        $deleteFrom = function (string $table) use ($pdo, $ideaId, &$deleted): void {
            try {
                $stmt = $pdo->prepare("DELETE FROM {$table} WHERE idea_id = :iid");
                $stmt->execute(['iid' => $ideaId]);
                $deleted[$table] = $stmt->rowCount();
            } catch (\Throwable $e) {
                $deleted[$table] = 'skipped';
            }
        };

        try {
            $pdo->beginTransaction();
            // Archive legacy wrapper/empty analysis records when the legacy table exists.
            try {
                $stmt = $pdo->prepare("UPDATE idea_analyses SET status = 'failed_validation', error_message = 'Reset by user' WHERE idea_id = :iid");
                $stmt->execute(['iid' => $ideaId]);
                $deleted['idea_analyses_archived'] = $stmt->rowCount();
            } catch (\Throwable) {
                $deleted['idea_analyses_archived'] = 'skipped';
            }

            foreach ([
                'idea_answers',
                'idea_questions',
                'idea_question_cycles',
                'idea_analysis_steps',
                'idea_ai_iterations',
                'idea_understanding_cards',
                'idea_refined_cards',
                'idea_potential_scores',
                'idea_risk_reports',
                'idea_pitfalls_reports',
                'idea_implementation_plans',
                'idea_final_recommendations',
                'idea_suggested_tasks',
            ] as $table) {
                $deleteFrom($table);
            }

            // Clear stale AI data on the idea itself without changing the user-facing workflow status.
            $pdo->prepare("UPDATE ideas SET coverage_json = NULL, assumptions_json = NULL, known_facts_json = NULL, unknowns_json = NULL, ai_analysis = NULL, ai_analysis_at = NULL WHERE id = :id")
                ->execute(['id' => $ideaId]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            ai_diag_log("[IDEA_RESET_ANALYSIS_ERROR] " . $e->getMessage());
            return $this->error('RESET_FAILED', $this->t('idea/messages.analysis_reset_failed'), 500);
        }

        return $this->success('ANALYSIS_RESET', $this->t('idea/messages.analysis_reset'), [
            'status' => (string)($idea['status'] ?? 'new'),
            'message' => $this->t('idea/messages.analysis_reset_message'),
            'deleted' => $deleted,
        ]);
    }

    /**
     * POST /ideas/{id}/tasks/decompose — trigger AI task decomposition.
     * Spec section 10.3.
     */
    public function decomposeTasks(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $service = $this->container->get('service.idea');
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        // Get existing analyses for context
        $existingAnalyses = $service->getAnalyses((int)$idea['id']);
        $finalReport = [];
        $implPlan = [];
        foreach ($existingAnalyses as $ea) {
            $r = $ea['result_json'] ?? null;
            if (is_string($r)) { $r = json_decode($r, true); }
            if (!is_array($r)) continue;
            if ($ea['analysis_type'] === 'final_report') $finalReport = $r;
            if ($ea['analysis_type'] === 'implementation_plan') $implPlan = $r;
        }

        try {
            $user = $this->user()['user'] ?? [];
            $ai = $this->container->get('service.ai_action');
            $result = $ai->execute('idea_task_decomposition', [
                'title' => $this->stripTags($idea['title']),
                'description' => $this->stripTags($idea['description'] ?? ''),
                'final_report' => $finalReport,
                'implementation_plan' => $implPlan,
            ], $user);

            $tasks = $result['result']['structured']['tasks'] ?? $result['tasks'] ?? [];
            if (is_array($tasks) && $tasks !== []) {
                $service->saveTaskDrafts((int)$idea['id'], $tasks);
                $service->updateStatus((int)$idea['id'], 'task_decomposition_ready');
            }

            $structured = $this->extractStructuredResult($result);
            $saveData = $structured ?? $result;
            $this->saveAnalysis($this->container->get('db.pdo'), (int)$idea['id'], 'task_decomposition', $saveData);

            return $this->success('TASKS_DECOMPOSED', $this->t('idea/messages.decomposed'), ['tasks' => $tasks]);
        } catch (\Throwable $e) {
            return $this->error('AI_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * POST /ideas/{id}/questions/next — generate next cycle of questions.
     * Spec section 10.2.
     */
     public function questionsNext(array $params = []): JsonResponse
    {
        $this->requireFeatureEnabled();
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $service = $this->container->get('service.idea');
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $pdo = $this->container->get('db.pdo');
        $cycleStmt = $pdo->prepare("SELECT MAX(cycle_id) FROM idea_questions WHERE idea_id = :iid");
        $cycleStmt->execute(['iid' => $idea['id']]);
        $nextCycle = ((int)$cycleStmt->fetchColumn()) + 1;
        if ($nextCycle > 5) return $this->error('MAX_CYCLES', 'Maximum question cycles reached', 422);

        try {
            $user = $this->user()['user'] ?? [];
            $ai = $this->container->get('service.ai_action');

            $prevQuestions = $service->getQuestions((int)$idea['id']);
            $prevAnswers = [];
            foreach ($prevQuestions as $pq) {
                if (!empty($pq['last_answer'])) {
                    $prevAnswers[] = $pq['last_answer'];
                }
            }

            $result = $ai->execute('idea_questions', [
                'title' => $this->stripTags($idea['title']),
                'description' => $this->stripTags($idea['description'] ?? ''),
                'previous_answers' => $prevAnswers,
                'cycle' => $nextCycle,
                'include_options' => true,
            ], $user);

            $questions = $result['questions'] ?? $result['result']['questions'] ?? [];
            if ((!is_array($questions) || $questions === []) && is_array($result)) {
                $questions = $this->parseAiQuestions($result, $idea);
            }
            if (is_array($questions) && $questions !== []) {
                $service->saveQuestions((int)$idea['id'], $nextCycle, $questions);
                $service->updateStatus((int)$idea['id'], 'questioning');
            } else {
                $service->updateStatus((int)$idea['id'], 'ready_for_analysis');
            }

            $this->saveAnalysis($pdo, (int)$idea['id'], 'questions_cycle_' . $nextCycle, $result);

            return $this->success('QUESTIONS_NEXT', $this->t('idea/messages.questions_generated'), [
                'questions' => $questions, 'cycle' => $nextCycle,
                'ready_for_analysis' => !$questions || $questions === [],
            ]);
        } catch (\Throwable $e) {
            return $this->error('AI_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * POST /ideas/{id}/analysis/run — run main analysis with background blocks.
     * Spec section 10.2.
     */
    public function runAnalysis(array $params = []): JsonResponse
    {
        $this->requireFeatureEnabled();
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $service = $this->container->get('service.idea');
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);
        if (($idea['status'] ?? '') !== 'ready_for_analysis') {
            return $this->error('ANALYSIS_NOT_READY', $this->t('idea/messages.analysis_not_ready_status'), 422);
        }
        $pdo = $this->container->get('db.pdo');
        $ideaId = (int)$idea['id'];
        // Guard: must have no unanswered active questions
        $allCurrentQ = $service->getQuestions($ideaId, $this->getCurrentCycleId($ideaId));
        $unanswered = false;
        foreach ($allCurrentQ as $q) {
            if (empty($q['last_answer'])) { $unanswered = true; break; }
        }
        if ($unanswered) {
            return $this->error('QUESTIONS_NOT_COMPLETED', $this->t('idea/messages.answer_all_questions_first'), 422);
        }

        // SAFE MODE: instant demo analysis — clearly marked for dev/test only
        if ($this->isSafeModeEnabled()) {
            $answersSummary = [];
            $prevQuestions = $service->getQuestions($ideaId);
            foreach ($prevQuestions as $pq) {
                if (!empty($pq['last_answer'])) {
                    $ans = $pq['last_answer'];
                    $answersSummary[] = ['question' => $pq['question_text'] ?? '', 'answer_key' => $ans['selected_option_key'] ?? '', 'answer_text' => $ans['answer_text'] ?? '', 'label' => $ans['selected_option_label'] ?? ''];
                }
            }
            $blocks = $this->buildSafeModeAnalysisBlocks($idea, $answersSummary);
            foreach ($blocks as $type => $data) {
                $data['_demo_mode'] = true;
                $service->saveAnalysis($ideaId, $type, $data);
            }
            return $this->success('ANALYSIS_RUN', $this->t('idea/messages.demo_analysis'), ['status' => 'analysis_partially_ready', 'demo_mode' => true, 'message' => $this->t('idea/messages.demo_mode_message')]);
        }

        // NORMAL MODE: run ALL analysis steps with 50s time budget
        $this->ensureIdeaWorkflowTables($pdo);
        $stepKeys = $this->analysisStepKeys();
        $totalSteps = count($stepKeys);

        $existing = $pdo->prepare("SELECT COUNT(*) FROM idea_analysis_steps WHERE idea_id = :iid");
        $existing->execute(['iid' => $ideaId]);
        if ((int)$existing->fetchColumn() === 0) {
            foreach ($stepKeys as $idx => $stepKey) {
                $pdo->prepare("INSERT INTO idea_analysis_steps (idea_id, step_key, step_order, status, attempts, created_at, updated_at) VALUES (:iid, :k, :o, 'pending', 0, NOW(), NOW())")
                    ->execute(['iid' => $ideaId, 'k' => $stepKey, 'o' => $idx + 1]);
            }
        }

        $service->updateStatus($ideaId, 'analysis_in_progress');
        set_time_limit(55);
        $deadline = microtime(true) + 50;
        $completed = 0;

        while (microtime(true) < $deadline) {
            $next = $pdo->prepare("SELECT step_key FROM idea_analysis_steps WHERE idea_id = :iid AND status IN ('pending','failed') ORDER BY step_order ASC LIMIT 1");
            $next->execute(['iid' => $ideaId]);
            $stepKey = (string)($next->fetchColumn() ?: '');
            if ($stepKey === '') break;

            try {
                $this->runAnalysisStepInternal($idea, $stepKey);
                $completed++;
            } catch (\Throwable $e) {
                $pdo->prepare("UPDATE idea_analysis_steps SET status = 'failed', error_message = :err, updated_at = NOW() WHERE idea_id = :iid AND step_key = :k")
                    ->execute(['iid' => $ideaId, 'k' => $stepKey, 'err' => $e->getMessage()]);
                ai_diag_log("[ANALYSIS_STEP_FAILED][{$stepKey}] {$e->getMessage()}");
                break; // Stop on error
            }
        }

        $finalStatus = $completed >= $totalSteps ? 'analysis_ready' : 'analysis_partially_ready';
        $service->updateStatus($ideaId, $finalStatus);

        return $this->success('ANALYSIS_RUN', $this->t('idea/messages.analysis_completed_label'), [
            'status' => $finalStatus,
            'progress' => ['completed' => $completed, 'total' => $totalSteps],
            'message' => $completed >= $totalSteps ? $this->t('idea/messages.analysis_complete_full') : $this->t('idea/messages.analysis_complete_partial') . ' ' . $completed . ' ' . $this->t('idea/messages.analysis_complete_of') . ' ' . $totalSteps . ' ' . $this->t('idea/messages.analysis_complete_steps'),
        ]);
    }

    public function submitAnswers(array $params = []): JsonResponse
    {
        return $this->aiRefine($params);
    }

    public function runAnalysisStep(array $params = []): JsonResponse
    {
        $this->requireFeatureEnabled();
        $publicId = (string)($params['public_id'] ?? '');
        $stepKey = (string)($params['stepKey'] ?? '');
        if ($publicId === '' || $stepKey === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $service = $this->container->get('service.idea');
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);
        if (!in_array($stepKey, $this->analysisStepKeys(), true)) {
            return $this->error('INVALID_STEP', $this->t('idea/messages.unknown_step_key'), 422);
        }

        $result = $this->runAnalysisStepInternal($idea, $stepKey);
        return $this->success('ANALYSIS_STEP_DONE', $this->t('idea/messages.analysis_step_done'), [
            'step_key' => $stepKey,
            'step_status' => 'completed',
            'result' => $result,
        ]);
    }

    /** @return list<string> */
    private function analysisStepKeys(): array
    {
        return [
            'idea_summary','risks','opportunities','validation_plan','implementation_plan','final_report',
        ];
    }

    private function ensureIdeaWorkflowTables(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS idea_question_cycles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            idea_id INT NOT NULL,
            cycle_number INT NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            input_snapshot_json LONGTEXT NULL,
            ai_response_json LONGTEXT NULL,
            summary_for_user TEXT NULL,
            created_at DATETIME NOT NULL,
            completed_at DATETIME NULL
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS idea_analysis_steps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            idea_id INT NOT NULL,
            step_key VARCHAR(64) NOT NULL,
            step_order INT NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            input_snapshot_json LONGTEXT NULL,
            result_json LONGTEXT NULL,
            result_text LONGTEXT NULL,
            error_message TEXT NULL,
            attempts INT NOT NULL DEFAULT 0,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS idea_potential_scores (
            id INT AUTO_INCREMENT PRIMARY KEY,
            idea_id INT NOT NULL,
            potential_json LONGTEXT NULL,
            potential_score INT NOT NULL DEFAULT 0,
            potential_level VARCHAR(32) NOT NULL DEFAULT '',
            confidence_score FLOAT NOT NULL DEFAULT 0,
            completeness_score FLOAT NOT NULL DEFAULT 0,
            calculation_type VARCHAR(32) NOT NULL DEFAULT '',
            verdict TEXT NULL,
            ai_request_json LONGTEXT NULL,
            ai_response_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY (idea_id)
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS idea_understanding_cards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            idea_id INT NOT NULL,
            profile_json LONGTEXT NULL,
            summary TEXT NULL,
            idea_type VARCHAR(64) NULL,
            specificity_level VARCHAR(32) NULL,
            completeness_score DECIMAL(5,4) NOT NULL DEFAULT 0,
            confidence_score DECIMAL(5,4) NOT NULL DEFAULT 0,
            next_action VARCHAR(64) NULL,
            ai_request_json LONGTEXT NULL,
            ai_response_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY (idea_id)
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS idea_refined_cards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            idea_id INT NOT NULL,
            profile_json LONGTEXT NULL,
            summary TEXT NULL,
            idea_type VARCHAR(64) NULL,
            specificity_level VARCHAR(32) NULL,
            completeness_score DECIMAL(5,4) NOT NULL DEFAULT 0,
            confidence_score DECIMAL(5,4) NOT NULL DEFAULT 0,
            next_action VARCHAR(64) NULL,
            ai_request_json LONGTEXT NULL,
            ai_response_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY (idea_id)
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS idea_risk_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            idea_id INT NOT NULL,
            risk_report_json LONGTEXT NULL,
            overall_risk_score DECIMAL(5,2) NOT NULL DEFAULT 0,
            overall_risk_level VARCHAR(32) NULL,
            critical_risks_count INT NOT NULL DEFAULT 0,
            high_risks_count INT NOT NULL DEFAULT 0,
            medium_risks_count INT NOT NULL DEFAULT 0,
            low_risks_count INT NOT NULL DEFAULT 0,
            confidence_score DECIMAL(5,4) NOT NULL DEFAULT 0,
            ai_request_json LONGTEXT NULL,
            ai_response_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY (idea_id)
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS idea_pitfalls_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            idea_id INT NOT NULL,
            overall_hidden_complexity VARCHAR(32) NULL,
            overall_summary TEXT NULL,
            pitfalls_json LONGTEXT NULL,
            data_confidence DECIMAL(5,4) NOT NULL DEFAULT 0,
            ai_request_json LONGTEXT NULL,
            ai_response_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY (idea_id)
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS idea_implementation_plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            idea_id INT NOT NULL,
            plan_json LONGTEXT NULL,
            summary TEXT NULL,
            planning_horizon VARCHAR(64) NULL,
            plan_type VARCHAR(32) NULL,
            confidence_score DECIMAL(5,4) NOT NULL DEFAULT 0,
            ai_request_json LONGTEXT NULL,
            ai_response_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY (idea_id)
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS idea_final_recommendations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            idea_id INT NOT NULL,
            status VARCHAR(32) NULL,
            status_label VARCHAR(64) NULL,
            recommendation_score DECIMAL(5,2) NOT NULL DEFAULT 0,
            ai_recommendation_score DECIMAL(5,2) NOT NULL DEFAULT 0,
            calculated_recommendation_score DECIMAL(5,2) NOT NULL DEFAULT 0,
            potential_score DECIMAL(5,2) NOT NULL DEFAULT 0,
            feasibility_score DECIMAL(5,2) NOT NULL DEFAULT 0,
            risk_score DECIMAL(5,2) NOT NULL DEFAULT 0,
            data_completeness_score DECIMAL(5,2) NOT NULL DEFAULT 0,
            plan_quality_score DECIMAL(5,2) NOT NULL DEFAULT 0,
            blocker_score DECIMAL(5,2) NOT NULL DEFAULT 0,
            confidence_score DECIMAL(5,4) NOT NULL DEFAULT 0,
            recommendation_json LONGTEXT NULL,
            ai_request_json LONGTEXT NULL,
            ai_response_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY (idea_id)
        )");
    }

    /** @param array<string,mixed> $idea */
    private function runAnalysisStepInternal(array $idea, string $stepKey): array
    {
        $pdo = $this->container->get('db.pdo');
        $service = $this->container->get('service.idea');
        $ideaId = (int)$idea['id'];
        $this->ensureIdeaWorkflowTables($pdo);

        $pdo->prepare("UPDATE idea_analysis_steps SET status = 'running', attempts = attempts + 1, started_at = NOW(), updated_at = NOW() WHERE idea_id = :iid AND step_key = :k")
            ->execute(['iid' => $ideaId, 'k' => $stepKey]);

        $previousResults = [];
        $prevStmt = $pdo->prepare("SELECT step_key, result_json FROM idea_analysis_steps WHERE idea_id = :iid AND status = 'completed' ORDER BY step_order ASC");
        $prevStmt->execute(['iid' => $ideaId]);
        foreach ($prevStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $previousResults[(string)$row['step_key']] = json_decode((string)$row['result_json'], true) ?: [];
        }

        $questions = $service->getQuestions($ideaId);
        $answers = [];
        foreach ($questions as $q) {
            if (!empty($q['last_answer'])) {
                $answers[] = [
                    'question_id' => (int)$q['id'],
                    'question_text' => (string)($q['question_text'] ?? ''),
                    'answer' => $q['last_answer'],
                ];
            }
        }

        $context = [
            'title' => $this->stripTags((string)($idea['title'] ?? '')),
            'description' => $this->stripTags((string)($idea['description'] ?? '')),
            'current_date' => date('Y-m-d'),
            'idea_created_at' => (string)($idea['created_at'] ?? ''),
            'all_questions' => $questions,
            'all_answers' => $answers,
            'known_facts' => json_decode((string)($idea['known_facts_json'] ?? '[]'), true) ?: [],
            'unknowns' => json_decode((string)($idea['unknowns_json'] ?? '[]'), true) ?: [],
            'assumptions' => json_decode((string)($idea['assumptions_json'] ?? '[]'), true) ?: [],
            'coverage' => json_decode((string)($idea['coverage_json'] ?? '{}'), true) ?: [],
            'previous_analysis_results' => $previousResults,
            'step_key' => $stepKey,
        ];

        $actionType = match ($stepKey) {
            'final_report' => 'idea_final_report',
            'risks' => 'idea_risks',
            'pitfalls' => 'idea_pitfalls',
            'opportunities' => 'idea_opportunities',
            'validation_plan' => 'idea_validation_plan',
            'alternative_scenarios' => 'idea_alternative_scenarios',
            'implementation_plan' => 'idea_implementation_plan',
            default => 'idea_main_analysis',
        };
        $ai = $this->container->get('service.ai_action');
        try {
            $raw = $ai->execute($actionType, $context, $this->user()['user'] ?? []);
            $structured = $this->extractStructuredResult($raw) ?? (is_array($raw) ? $raw : []);
            if (!is_array($structured) || $structured === []) {
                throw new \RuntimeException($this->t('idea/messages.empty_analysis_result'));
            }

            $pdo->prepare("UPDATE idea_analysis_steps SET status = 'completed', input_snapshot_json = :inp, result_json = :res, completed_at = NOW(), updated_at = NOW() WHERE idea_id = :iid AND step_key = :k")
                ->execute([
                    'iid' => $ideaId,
                    'k' => $stepKey,
                    'inp' => json_encode($context, JSON_UNESCAPED_UNICODE),
                    'res' => json_encode($structured, JSON_UNESCAPED_UNICODE),
                ]);
            $service->saveAnalysis($ideaId, $stepKey, $structured);

            $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM idea_analysis_steps WHERE idea_id = :iid AND status IN ('pending','running','failed')");
            $pendingStmt->execute(['iid' => $ideaId]);
            if ((int)$pendingStmt->fetchColumn() === 0) {
                $service->updateStatus($ideaId, 'analysis_ready');
            } else {
                $service->updateStatus($ideaId, 'analysis_partially_ready');
            }
            return $structured;
        } catch (\Throwable $e) {
            $pdo->prepare("UPDATE idea_analysis_steps SET status = 'failed', error_message = :msg, updated_at = NOW() WHERE idea_id = :iid AND step_key = :k")
                ->execute(['iid' => $ideaId, 'k' => $stepKey, 'msg' => $e->getMessage()]);
            $service->updateStatus($ideaId, 'analysis_partially_ready');
            throw $e;
        }
    }

    /**
     * POST /ideas/{id}/analysis/{analysisType}/retry — retry a failed analysis block.
     * Spec section 10.2.
     */
    public function retryAnalysis(array $params = []): JsonResponse
    {
        $this->requireFeatureEnabled();
        $publicId = (string)($params['public_id'] ?? '');
        $analysisType = (string)($params['analysisType'] ?? '');
        if ($publicId === '' || $analysisType === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $service = $this->container->get('service.idea');
        $idea = $service->getByPublicId($publicId);
        if (!$idea) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        // Get existing completed analyses for context
        $existingAnalyses = $service->getAnalyses((int)$idea['id']);
        $structuredBlocks = [];
        foreach ($existingAnalyses as $ea) {
            $r = $ea['result_json'] ?? null;
            if (is_string($r)) { $r = json_decode($r, true); }
            if (is_array($r) && ($ea['status'] ?? '') === 'completed') {
                $structuredBlocks[$ea['analysis_type']] = $r;
            }
        }

        try {
            set_time_limit(300);
            $user = $this->user()['user'] ?? [];
            $ai = $this->container->get('service.ai_action');

            $knownFacts = json_decode($idea['known_facts_json'] ?? '[]', true) ?: [];
            $unknowns = json_decode($idea['unknowns_json'] ?? '[]', true) ?: [];

            $actionType = 'idea_' . $analysisType;
            $context = [
                'title' => $this->stripTags($idea['title']),
                'description' => $this->stripTags($idea['description'] ?? ''),
                'classification' => json_decode($idea['known_facts_json'] ?? '{}', true) ?: [],
                'known_facts' => $knownFacts,
                'unknowns' => $unknowns,
                'main_analysis' => $structuredBlocks['main_analysis'] ?? [],
            ];

            $result = $ai->execute($actionType, $context, $user);
            $structured = $this->extractStructuredResult($result);
            if ($structured !== null) {
                $service->saveAnalysis((int)$idea['id'], $analysisType, $structured);
            } else {
                $service->saveAnalysis((int)$idea['id'], $analysisType, $result);
            }
            return $this->success('ANALYSIS_RETRIED', $this->t('idea/messages.analysis_retried'), [
                'analysis_type' => $analysisType,
                'status' => 'completed',
            ]);
        } catch (\Throwable $e) {
            return $this->error('AI_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * @param array<string,mixed> $coverage
     */
    private function getRecommendedNextAction(int $ideaId, array $coverage): string
    {
        $pdo = $this->container->get('db.pdo');
        $stmt = $pdo->prepare("SELECT status FROM ideas WHERE id = :id");
        $stmt->execute(['id' => $ideaId]);
        $status = $stmt->fetchColumn();

        if ($status === 'draft') return 'start_analysis';
        if ($status === 'questioning' || $status === 'question_generation') return 'ask_questions';
        if (in_array($status, ['ready_for_analysis', 'analysis_ready', 'analysis_partially_ready', 'analysis_in_progress', 'analysis_pending'])) {
            return 'ready_for_analysis';
        }

        $stmt = $pdo->prepare("SELECT result_json FROM idea_analyses WHERE idea_id = :iid AND analysis_type = 'analysis_map' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute(['iid' => $ideaId]);
        $mapRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($mapRow) {
            $mapJson = json_decode($mapRow['result_json'] ?? '{}', true);
            if (is_array($mapJson) && isset($mapJson['recommended_next_action'])) {
                return $mapJson['recommended_next_action'];
            }
        }
        // Fallback: if coverage is very low, ask questions
        $nonZeroValues = 0;
        $totalValues = 0;
        foreach ($coverage as $v) {
            if (is_numeric($v)) { $totalValues++; if ((float)$v > 0) $nonZeroValues++; }
        }
        if ($totalValues === 0) return 'ask_questions';
        if ($nonZeroValues <= 3) return 'ask_questions';
        return 'ready_for_analysis';
    }

    /**
     * Extract structured data from AI response. Handles the new format with `structured` key.
     * @return array<string,mixed>|null
     */
    private function extractStructuredResult(mixed $aiResult): ?array
    {
        if (!is_array($aiResult)) return null;

        // New format: result.structured
        $structured = $aiResult['result']['structured'] ?? $aiResult['structured'] ?? null;
        if (is_array($structured) && $structured !== []) return $structured;

        // Legacy: result may directly contain the structured data
        $result = $aiResult['result'] ?? $aiResult;
        if (is_array($result)) {
            // Check if this looks like structured analysis data (not the generic wrapper)
            if (isset($result['summary']) || isset($result['executive_summary']) ||
                isset($result['questions']) || isset($result['risks']) ||
                isset($result['pitfalls']) || isset($result['opportunities'])) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $report
     * @return array<string,mixed>
     */
    private function deduplicateFinalReport(array $report, object $validator): array
    {
        if (!method_exists($validator, 'deduplicate')) return $report;

        $arrayFields = ['known_facts', 'unknowns', 'assumptions', 'strengths', 'weaknesses',
            'critical_findings', 'pitfalls', 'opportunities'];
        foreach ($arrayFields as $field) {
            if (isset($report[$field]) && is_array($report[$field])) {
                $maxItems = in_array($field, ['known_facts', 'unknowns', 'assumptions'], true) ? 6 : 5;
                $report[$field] = $validator->deduplicate($report[$field], $maxItems);
            }
        }
        return $report;
    }

    private function requireFeatureEnabled(): void
    {
        if (!$this->isFeatureEnabled()) {
            throw new \RuntimeException('AI ideas feature is disabled');
        }
    }

    /**
     * Build a minimum viable understanding card when the provider returns
     * malformed JSON. It uses only facts already available in the CRM.
     *
     * @param array<string,mixed> $idea
     * @param array<int,array<string,mixed>> $qaList
     * @return array<string,mixed>
     */
    private function buildFallbackUnderstandingCardData(array $idea, string $plainDesc, array $qaList, string $reason): array
    {
        $title = trim((string)($idea['title'] ?? ''));
        $summarySource = $plainDesc !== '' ? $plainDesc : $title;
        $summary = $summarySource !== ''
            ? mb_substr($summarySource, 0, 420)
            : $this->t('idea/messages.fallback_card_summary');

        $knownFacts = [];
        if ($title !== '') $knownFacts[] = $this->t('idea/messages.fallback_fact_title') . ' ' . $title;
        if ($plainDesc !== '') $knownFacts[] = $this->t('idea/messages.fallback_fact_description') . ' ' . mb_substr($plainDesc, 0, 700);
        foreach (['category' => $this->t('idea/messages.fallback_category'), 'product' => $this->t('idea/messages.fallback_product'), 'region' => $this->t('idea/messages.fallback_region'), 'target_date' => $this->t('idea/messages.fallback_target_date')] as $key => $label) {
            $value = trim((string)($idea[$key] ?? ''));
            if ($value !== '') $knownFacts[] = $label . ': ' . $value;
        }

        $userUnknowns = [];
        $missingFacts = [];
        foreach ($qaList as $item) {
            $question = trim((string)($item['question'] ?? ''));
            $answerValue = $item['answer'] ?? null;
            $answer = is_array($answerValue) ? (array)$answerValue : null;
            if ($question === '') continue;
            if (!$answer && trim((string)$answerValue) === '') {
                $missingFacts[] = $question;
                continue;
            }
            $selected = $answer ? trim((string)($answer['selected_option'] ?? '')) : '';
            $custom = $answer ? trim((string)($answer['custom_answer'] ?? '')) : '';
            if ($answer && !empty($answer['is_unknown'])) {
                $userUnknowns[] = $question;
                continue;
            }
            $answerText = $custom !== '' ? $custom : ($selected !== '' ? $selected : trim((string)$answerValue));
            if ($answerText !== '') {
                $knownFacts[] = $question . ': ' . $answerText;
            } else {
                $missingFacts[] = $question;
            }
        }

        $defaultMissing = [
            $this->t('idea/messages.fallback_missing_goal'),
            $this->t('idea/messages.fallback_missing_audience'),
            $this->t('idea/messages.fallback_missing_budget'),
            $this->t('idea/messages.fallback_missing_timeline'),
            $this->t('idea/messages.fallback_missing_team'),
            $this->t('idea/messages.fallback_missing_legal'),
            $this->t('idea/messages.fallback_missing_risks'),
        ];
        $missingFacts = array_values(array_unique(array_filter(array_merge($missingFacts, $defaultMissing))));
        $knownFacts = array_values(array_unique(array_filter($knownFacts)));
        $userUnknowns = array_values(array_unique(array_filter($userUnknowns)));

        $knownCount = count($knownFacts);
        $answeredCount = count(array_filter($qaList, static fn($item) => !empty($item['answer'])));
        $overall = min(0.75, max(0.2, ($knownCount * 0.08) + ($answeredCount * 0.05)));
        $specificity = $overall >= 0.58 ? 'medium' : 'low';

        $completeness = [
            'overall' => round($overall, 2),
            'goal' => $this->hasAnyText($knownFacts, ['цель', 'результат', 'успех']) ? 0.6 : 0.25,
            'product_or_service' => $this->hasAnyText($knownFacts, ['продукт', 'услуг', 'сервис', 'внедрение']) ? 0.6 : 0.3,
            'audience' => $this->hasAnyText($knownFacts, ['клиент', 'пользователь', 'аудитор', 'сотрудник']) ? 0.55 : 0.25,
            'region' => trim((string)($idea['region'] ?? '')) !== '' ? 0.7 : 0.25,
            'finance' => $this->hasAnyText($knownFacts, ['бюджет', 'стоим', 'цена', 'финанс']) ? 0.55 : 0.2,
            'timeline' => trim((string)($idea['target_date'] ?? '')) !== '' || $this->hasAnyText($knownFacts, ['срок', 'дата', 'месяц', 'недел']) ? 0.55 : 0.25,
            'operations' => $this->hasAnyText($knownFacts, ['процесс', 'операц', 'внедрение', 'производ']) ? 0.55 : 0.25,
            'team' => $this->hasAnyText($knownFacts, ['команда', 'ответствен', 'исполнитель']) ? 0.5 : 0.2,
            'market' => $this->hasAnyText($knownFacts, ['рынок', 'конкур', 'спрос', 'клиент']) ? 0.45 : 0.2,
            'legal' => $this->hasAnyText($knownFacts, ['закон', 'договор', 'персональн', '152', 'gdpr']) ? 0.45 : 0.2,
            'risks' => count($userUnknowns) > 0 ? 0.45 : 0.25,
        ];

        return [
            'idea_profile' => [
                'summary' => $summary,
                'idea_type' => $this->guessIdeaType($idea, $plainDesc),
                'specificity_level' => $specificity,
                'known_facts' => array_slice($knownFacts, 0, 12),
                'user_unknowns' => array_slice($userUnknowns, 0, 10),
                'missing_facts' => array_slice($missingFacts, 0, 10),
                'assumptions' => [],
                'constraints' => [],
                'early_risks' => [$this->t('idea/messages.fallback_early_risks')],
                'key_decision_factors' => [$this->t('idea/messages.fallback_factor_goal'), $this->t('idea/messages.fallback_factor_timeline'), $this->t('idea/messages.fallback_factor_budget'), $this->t('idea/messages.fallback_factor_responsible'), $this->t('idea/messages.fallback_factor_impl_risks'), $this->t('idea/messages.fallback_factor_business_effect')],
                'completeness' => $completeness,
                'confidence_score' => round(max(0.15, min(0.55, $overall - 0.1)), 2),
                '_fallback' => true,
                '_fallback_reason' => $reason,
            ],
            'next_step' => [
                'action' => 'start_analysis',
                'reason' => $this->t('idea/messages.fallback_next_step_reason'),
                'recommended_missing_topics' => array_slice($missingFacts, 0, 6),
                'can_continue_without_more_questions' => true,
            ],
        ];
    }

    /**
     * @param array<int,string> $items
     * @param array<int,string> $needles
     */
    private function hasAnyText(array $items, array $needles): bool
    {
        $text = mb_strtolower(implode(' ', $items));
        foreach ($needles as $needle) {
            if ($needle !== '' && mb_strpos($text, mb_strtolower($needle)) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $idea
     */
    private function guessIdeaType(array $idea, string $plainDesc): string
    {
        $text = mb_strtolower(implode(' ', [
            (string)($idea['category'] ?? ''),
            (string)($idea['product'] ?? ''),
            (string)($idea['title'] ?? ''),
            $plainDesc,
        ]));
        if (str_contains($text, 'услуг') || str_contains($text, 'сервис')) return 'service';
        if (str_contains($text, 'продукт') || str_contains($text, 'товар')) return 'product';
        if (str_contains($text, 'путеше') || str_contains($text, 'тур')) return 'travel';
        if (str_contains($text, 'инвест')) return 'investment';
        if (str_contains($text, 'мероприят') || str_contains($text, 'ивент')) return 'event';
        if (str_contains($text, 'бизнес') || str_contains($text, 'внедрение') || str_contains($text, 'crm') || str_contains($text, '1с')) return 'business';
        return 'other';
    }

    /**
     * @param array<string,mixed> $idea
     * @param array<string,mixed> $understandingCard
     * @param array<int,array<string,mixed>> $qaList
     * @return array<string,mixed>
     */
    private function buildFallbackPotentialData(array $idea, array $understandingCard, array $qaList, string $reason): array
    {
        $completeness = max(0.0, min(1.0, (float)($understandingCard['completeness'] ?? 0)));
        $confidence = max(0.15, min(0.55, (float)($understandingCard['confidence'] ?? 0.25)));
        $answeredCount = count(array_filter($qaList, static fn($item) => trim((string)($item['answer'] ?? '')) !== ''));
        $baseScore = 35 + (int)round($completeness * 25) + min(15, $answeredCount * 3);
        $score = max(20, min(70, $baseScore));
        $level = $score <= 20 ? 'very_low' : ($score <= 40 ? 'low' : ($score <= 60 ? 'medium' : 'high'));
        $title = trim((string)($idea['title'] ?? $this->t('idea/messages.default_idea_title')));

        return [
            'potential' => [
                'potential_score' => $score,
                'potential_level' => $level,
                'calculation_type' => 'preliminary',
                'verdict' => $this->t('idea/messages.fallback_potential_verdict'),
                'summary' => $this->t('idea/messages.fallback_potential_summary'),
                'confidence_score' => $confidence,
                'completeness_score' => $completeness,
                '_fallback' => true,
                '_fallback_reason' => $reason,
            ],
            'criteria' => [
                ['criterion_id' => 'clarity', 'title' => $this->t('idea/messages.fallback_criterion_clarity'), 'weight' => 25, 'score' => max(2, min(8, (int)round($completeness * 10))), 'weighted_score' => (int)round(25 * $completeness), 'reason' => $this->t('idea/messages.fallback_criterion_clarity_reason'), 'positive_factors' => [], 'negative_factors' => [], 'missing_data' => []],
                ['criterion_id' => 'business_relevance', 'title' => $this->t('idea/messages.fallback_criterion_business'), 'weight' => 25, 'score' => $title !== '' ? 6 : 4, 'weighted_score' => $title !== '' ? 15 : 10, 'reason' => $this->t('idea/messages.fallback_criterion_business_reason'), 'positive_factors' => [], 'negative_factors' => [], 'missing_data' => []],
                ['criterion_id' => 'data_quality', 'title' => $this->t('idea/messages.fallback_criterion_data'), 'weight' => 25, 'score' => min(8, 3 + $answeredCount), 'weighted_score' => min(20, (int)round((3 + $answeredCount) * 2.5)), 'reason' => $this->t('idea/messages.fallback_criterion_data_reason'), 'positive_factors' => [], 'negative_factors' => [], 'missing_data' => []],
                ['criterion_id' => 'implementation_readiness', 'title' => $this->t('idea/messages.fallback_criterion_readiness'), 'weight' => 25, 'score' => 5, 'weighted_score' => 13, 'reason' => $this->t('idea/messages.fallback_criterion_readiness_reason'), 'positive_factors' => [], 'negative_factors' => [], 'missing_data' => ['Риски', $this->t('idea/messages.plan_title'), 'Ограничения']],
            ],
            'strengths' => [$this->t('idea/messages.fallback_strengths')],
            'weaknesses' => [$this->t('idea/messages.fallback_weaknesses')],
            'growth_factors' => [$this->t('idea/messages.fallback_growth_factors')],
            'risk_factors' => [$this->t('idea/messages.fallback_risk_factors')],
            'missing_data' => [$this->t('idea/messages.fallback_missing_financial'), $this->t('idea/messages.fallback_missing_success'), $this->t('idea/messages.fallback_missing_main_risks'), $this->t('idea/messages.fallback_missing_impl_plan')],
            'assumptions' => [],
            'what_can_improve_score' => [$this->t('idea/messages.fallback_improve_score')],
            'what_can_reduce_score' => [$this->t('idea/messages.fallback_reduce_score')],
            'recommended_next_step' => ['action' => 'finalize', 'reason' => $this->t('idea/messages.fallback_next_action_reason')],
        ];
    }

    /**
     * @param array<string,mixed> $blocks
     * @return array<string,mixed>
     */
    private function buildFallbackFinalRecommendationData(array $blocks, string $reason): array
    {
        $potential = is_array($blocks['potential'] ?? null) ? (array)$blocks['potential'] : [];
        $risks = is_array($blocks['risks'] ?? null) ? (array)$blocks['risks'] : [];
        $plan = is_array($blocks['implementation_plan'] ?? null) ? (array)$blocks['implementation_plan'] : [];
        $card = is_array($blocks['understanding_card'] ?? null) ? (array)$blocks['understanding_card'] : [];

        $potentialScore = (float)($potential['score'] ?? 45);
        $riskScore = (float)($risks['overall_score'] ?? 45);
        $dataScore = (float)($card['completeness'] ?? 35);
        $planScore = !empty($plan['exists']) ? 50 : 30;
        $feasibility = max(20, min(75, 55 - ($riskScore * 0.2) + ($planScore * 0.25)));
        $blocker = $riskScore >= 75 ? 70 : 35;
        $confidence = 35;

        return [
            'final_recommendation' => [
                'status' => 'refine_first',
                'status_label' => $this->t('idea/messages.fallback_status_refine_first'),
                'recommendation_score' => 45,
                'potential_score' => max(0, min(100, $potentialScore)),
                'feasibility_score' => round($feasibility),
                'risk_score' => max(0, min(100, $riskScore)),
                'data_completeness_score' => max(0, min(100, $dataScore)),
                'plan_quality_score' => $planScore,
                'blocker_score' => $blocker,
                'confidence_score' => $confidence,
                'short_verdict' => $this->t('idea/messages.fallback_final_short_verdict'),
                'detailed_verdict' => $this->t('idea/messages.fallback_final_detailed_verdict'),
                'main_reasons' => [$this->t('idea/messages.fallback_final_reason1'), $this->t('idea/messages.fallback_final_reason2')],
                'positive_arguments' => [],
                'negative_arguments' => [$this->t('idea/messages.fallback_final_negative')],
                'critical_blockers' => [],
                'conditions_to_proceed' => [$this->t('idea/messages.fallback_final_condition1'), $this->t('idea/messages.fallback_final_condition2')],
                'what_to_validate_first' => [$this->t('idea/messages.fallback_validate_goal'), $this->t('idea/messages.fallback_validate_budget'), $this->t('idea/messages.fallback_validate_timeline'), $this->t('idea/messages.fallback_validate_risks'), $this->t('idea/messages.fallback_validate_responsible')],
                'next_best_actions' => [$this->t('idea/messages.fallback_action_check_card'), $this->t('idea/messages.fallback_action_clarify_data'), $this->t('idea/messages.fallback_action_regenerate')],
                'what_can_go_wrong' => [$this->t('idea/messages.fallback_wrong_decision')],
                'missing_data_that_affects_recommendation' => [$this->t('idea/messages.fallback_missing_ai_response')],
                'assumptions_used' => [],
                'user_friendly_summary' => $this->t('idea/messages.fallback_final_summary'),
                '_fallback' => true,
                '_fallback_reason' => $reason,
            ],
        ];
    }

    /**
     * Walk through text and replace { } inside JSON strings with safe alternatives
     * so that brace counting works correctly for extracting the JSON block.
     */
    private function sanitizeJsonBraces(string $text, int $start): array
    {
        $len = strlen($text);
        // First pass: replace { } inside strings with safe placeholders
        $inString = false;
        $prevBackslash = false;
        $sanitized = '';
        for ($i = 0; $i < $len; $i++) {
            $ch = $text[$i];
            if ($inString) {
                if ($ch === '\\' && !$prevBackslash) { $sanitized .= $ch; $prevBackslash = true; continue; }
                if ($ch === '"' && !$prevBackslash) { $inString = false; $sanitized .= $ch; $prevBackslash = false; continue; }
                if ($ch === '{') { $sanitized .= "\xE2\x80\xA2"; $prevBackslash = false; continue; }
                if ($ch === '}') { $sanitized .= "\xE2\x80\xA2"; $prevBackslash = false; continue; }
                $sanitized .= $ch; $prevBackslash = false;
            } else {
                if ($ch === '"') { $inString = true; $sanitized .= $ch; continue; }
                $sanitized .= $ch;
            }
        }
        // Now find proper brace matching using sanitized text
        $depth = 0; $foundEnd = -1;
        for ($i = $start; $i < strlen($sanitized); $i++) {
            $ch = $sanitized[$i];
            if ($ch === '{') $depth++;
            elseif ($ch === '}') { $depth--; if ($depth === 0) { $foundEnd = $i; break; } }
        }
        if ($foundEnd === -1) {
            return ['ok' => false, 'error' => 'no_matching_brace', 'text' => $text];
        }
        // Extract from original text using matched positions
        $extracted = substr($text, $start, $foundEnd - $start + 1);
        return ['ok' => true, 'text' => $extracted];
    }

    /**
     * Clean AI response: strip markdown fences, extract first {…} block, decode.
     * @return array{ok:bool,data:?array,error?:string}
     */
     private function extractAiJson(string $rawText): array
    {
        $trimmed = trim($rawText);
        if ($trimmed === '') {
            return ['ok' => false, 'data' => null, 'error' => 'empty'];
        }
        $start = strpos($trimmed, '{');
        if ($start === false) {
            return ['ok' => false, 'data' => null, 'error' => 'no_brace'];
        }

        // First pass: sanitize { } inside JSON strings to avoid brace-count pollution
        $sanitized = '';
        $inString = false; $prevBackslash = false;
        for ($i = 0; $i < strlen($trimmed); $i++) {
            $ch = $trimmed[$i];
            if ($inString) {
                if ($ch === '\\' && !$prevBackslash) { $sanitized .= $ch; $prevBackslash = true; continue; }
                if ($ch === '"' && !$prevBackslash) { $inString = false; $sanitized .= $ch; $prevBackslash = false; continue; }
                if ($ch === '{') { $sanitized .= "\xE2\x80\xA2"; $prevBackslash = false; continue; }
                if ($ch === '}') { $sanitized .= "\xE2\x80\xA2"; $prevBackslash = false; continue; }
                $sanitized .= $ch; $prevBackslash = false;
            } else {
                if ($ch === '"') { $inString = true; $sanitized .= $ch; continue; }
                $sanitized .= $ch;
            }
        }

        // Second pass: count braces on sanitized text from first { to find match or imbalanc
        $depth = 0; $foundEnd = -1;
        for ($i = $start; $i < strlen($sanitized); $i++) {
            $ch = $sanitized[$i];
            if ($ch === '{') $depth++;
            elseif ($ch === '}') { $depth--; if ($depth === 0) { $foundEnd = $i; break; } }
        }

        // Extract JSON block
        if ($foundEnd !== -1) {
            $jsonBlock = substr($trimmed, $start, $foundEnd - $start + 1);
        } else {
            $lastBrace = strrpos($trimmed, '}');
            if ($lastBrace !== false && $lastBrace > $start) {
                $jsonBlock = substr($trimmed, $start, $lastBrace - $start + 1);
            } else {
                $jsonBlock = substr($trimmed, $start);
            }
            if ($depth > 0) {
                $jsonBlock .= str_repeat('}', $depth);
            }
        }

        // Try direct parse
        $decoded = @json_decode($jsonBlock, true);
        if (is_array($decoded)) {
            return ['ok' => true, 'data' => $decoded];
        }

        // Regex extraction on original block before escape sanitization
        $broken = $this->extractBrokenJson($jsonBlock);
        if ($broken !== null) {
            return ['ok' => true, 'data' => $broken];
        }

        // Full sanitization: control chars + invalid escape sequences
        $validEscapes = ['"', '\\', '/', 'b', 'f', 'n', 'r', 't', 'u'];
        $cleanJson = '';
        $inString = false;
        for ($i = 0; $i < strlen($jsonBlock); $i++) {
            $ch = $jsonBlock[$i];
            if ($inString) {
                if ($ch === '\\') {
                    // Peek at next character
                    $next = $i + 1 < strlen($jsonBlock) ? $jsonBlock[$i + 1] : null;
                    if ($next !== null && !in_array($next, $validEscapes, true)) {
                        // Invalid escape \x → produce \\x (escaped backslash + literal x)
                        $cleanJson .= '\\\\' . $next;
                        $i++; // skip next char
                    } else {
                        $cleanJson .= '\\';
                    }
                    continue;
                }
                if ($ch === '"') { $inString = false; $cleanJson .= $ch; continue; }
                $o = ord($ch);
                if ($o < 32 && $o !== 9 && $o !== 10 && $o !== 13) continue;
                $cleanJson .= $ch;
            } else {
                if ($ch === '"') { $inString = true; $cleanJson .= $ch; continue; }
                $cleanJson .= $ch;
            }
        }
        $decoded = @json_decode($cleanJson, true);
        if (is_array($decoded)) {
            return ['ok' => true, 'data' => $decoded];
        }

        // Final fallback: progressive } truncation
        $repaired = $this->repairJsonString($cleanJson);
        $decoded = @json_decode($repaired, true);
        if (is_array($decoded)) {
            return ['ok' => true, 'data' => $decoded];
        }

        // Final fallback: regex-based minimum viable data extraction
        $broken = $this->extractBrokenJson($cleanJson);
        if ($broken !== null) {
            return ['ok' => true, 'data' => $broken];
        }

        return ['ok' => false, 'data' => null, 'error' => 'json_decode_failed:' . json_last_error_msg()];
    }

    /**
     * Regex-based extraction of minimum viable data from broken JSON.
     * Used as LAST RESORT when json_decode fails on all repair attempts.
     */
    private function extractBrokenJson(string $text): ?array
    {
        $data = [];
        // Try to extract simple key-value pairs: "key":"value" or "key":"some value"
        if (preg_match('/"risk_report"\s*:\s*\{/i', $text)) {
            $data['risk_report'] = [];
            if (preg_match('/"summary"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $text, $m))
                $data['risk_report']['summary'] = $m[1];
            if (preg_match('/"overall_risk_score"\s*:\s*(\d+)/i', $text, $m))
                $data['risk_report']['overall_risk_score'] = (int)$m[1];
            if (preg_match('/"overall_risk_level"\s*:\s*"(\w+)"/i', $text, $m))
                $data['risk_report']['overall_risk_level'] = $m[1];
            if (preg_match('/"confidence_score"\s*:\s*([\d.]+)/i', $text, $m))
                $data['risk_report']['confidence_score'] = (float)$m[1];
            // Try to extract individual risks via simpler patterns
            $riskBlocks = [];
            preg_match_all('/"risks"\s*:\s*\[(.*?)\]/s', $text, $riskMatches);
            if (!empty($riskMatches[1][0])) {
                $chunks = explode('"title"', $riskMatches[1][0]);
                foreach (array_slice($chunks, 1) as $chunk) {
                    $risk = [];
                    if (preg_match('/"((?:[^"\\\\]|\\\\.)*)"/s', $chunk, $tm)) $risk['title'] = $tm[1];
                    if (preg_match('/"category"\s*:\s*"(\w+)"/i', $chunk, $cm)) $risk['category'] = $cm[1];
                    $riskBlocks[] = $risk;
                }
                $data['risk_report']['risks'] = $riskBlocks;
            }
            return $data;
        }
        if (preg_match('/"pitfalls"\s*:\s*\[/i', $text)) {
            $data['pitfalls'] = [];
            $pitfallChunks = explode('"title"', $text);
            foreach (array_slice($pitfallChunks, 1) as $chunk) {
                $p = [];
                if (preg_match('/"((?:[^"\\\\]|\\\\.)*)"/s', $chunk, $tm)) $p['title'] = $tm[1];
                if (preg_match('/"description"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $chunk, $dm)) $p['description'] = $dm[1];
                if (preg_match('/"category"\s*:\s*"(\w+)"/i', $chunk, $cm)) $p['category'] = $cm[1];
                $data['pitfalls'][] = $p;
            }
            if (preg_match('/"overall_summary"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $text, $sm)) $data['overall_summary'] = $sm[1];
            if (preg_match('/"overall_hidden_complexity"\s*:\s*"(\w+)"/i', $text, $cm)) $data['overall_hidden_complexity'] = $cm[1];
            return $data;
        }
        if (preg_match('/"implementation_plan"\s*:\s*\{/i', $text)) {
            $plan = [];
            if (preg_match('/"summary"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $text, $m)) $plan['summary'] = $m[1];
            if (preg_match('/"recommended_next_action"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $text, $m)) $plan['recommended_next_action'] = $m[1];
            $data['implementation_plan'] = $plan;
            return $data;
        }
        if (preg_match('/"projects"\s*:\s*\[/i', $text) || preg_match('/"tasks"\s*:\s*\[/i', $text)) {
            if (preg_match('/"summary"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $text, $m)) $data['summary'] = $m[1];
            $taskChunks = explode('"title"', $text);
            $tasks = [];
            foreach (array_slice($taskChunks, 1) as $chunk) {
                $t = [];
                if (preg_match('/"((?:[^"\\\\]|\\\\.)*)"/s', $chunk, $tm)) $t['title'] = $tm[1];
                if (preg_match('/"description"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $chunk, $dm)) $t['description'] = $dm[1];
                if (preg_match('/"priority"\s*:\s*"(\w+)"/i', $chunk, $pm)) $t['priority'] = $pm[1];
                $tasks[] = $t;
            }
            if ($tasks) {
                $data['projects'] = [['id' => 'p1', 'title' => $this->t('idea/messages.plan_title'), 'description' => '', 'tasks' => $tasks]];
            }
            return $data;
        }
        return null;
    }

    /**
     * Attempt to repair malformed JSON from AI responses.
     */
    private function repairJsonString(string $json): string
    {
        $trimmed = trim($json);
        $start = strpos($trimmed, '{');
        if ($start === false) return $json;

        // Count braces outside strings
        $openCnt = 0; $closeCnt = 0; $inString = false; $prevBackslash = false;
        for ($i = 0; $i < strlen($trimmed); $i++) {
            $ch = $trimmed[$i];
            if ($inString) {
                if ($ch === '\\' && !$prevBackslash) { $prevBackslash = true; continue; }
                if ($ch === '"' && !$prevBackslash) { $inString = false; }
                $prevBackslash = false; continue;
            }
            if ($ch === '"') { $inString = true; continue; }
            if ($ch === '{') $openCnt++;
            elseif ($ch === '}') $closeCnt++;
        }
        // Balance missing braces
        $diff = $openCnt - $closeCnt;
        if ($diff > 0) {
            $balanced = $trimmed . str_repeat('}', $diff);
            $decoded = @json_decode($balanced, true);
            if (is_array($decoded)) return $balanced;
        }
        // Fallback: progressive } truncation
        $len = strlen($trimmed);
        $lastEnd = $len;
        while ($lastEnd > $start) {
            $candidate = substr($trimmed, $start, $lastEnd - $start);
            $decoded = @json_decode($candidate, true);
            if (is_array($decoded)) return $candidate;
            $prev = strrpos($trimmed, '}', $lastEnd - $len - 1);
            if ($prev === false || $prev >= $lastEnd) break;
            $lastEnd = $prev;
        }
        return $trimmed;
    }

    public function listComments(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $pdo = $this->container->get('db.pdo');
        $stmt = $pdo->prepare("SELECT c.*, u.full_name as author_name, u.login as author_login FROM comments c LEFT JOIN users u ON u.id = c.author_user_id WHERE c.entity_type = 'idea' AND c.entity_public_id = :pid ORDER BY c.created_at ASC");
        $stmt->execute(['pid' => $publicId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $this->success('COMMENTS_LIST', $this->t('common/messages.ok'), ['items' => $items]);
    }

    public function addComment(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $body = trim((string)($this->request()->allInput()['body'] ?? ''));
        if ($publicId === '' || $body === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $user = $this->user()['user'] ?? [];
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);

        $pdo = $this->container->get('db.pdo');
        $commentId = 'cmt_' . bin2hex(random_bytes(8));
        $pdo->prepare("INSERT INTO comments (public_id, entity_type, entity_public_id, author_user_id, body, created_at) VALUES (:pid, 'idea', :epid, :uid, :body, NOW())")
            ->execute(['pid' => $commentId, 'epid' => $publicId, 'uid' => $userId, 'body' => $body]);
        $pdo->prepare("UPDATE ideas SET comment_count = comment_count + 1 WHERE public_id = :pid")->execute(['pid' => $publicId]);

        return $this->success('COMMENT_ADDED', $this->t('common/messages.saved'), ['public_id' => $commentId], status: 201);
    }
}
