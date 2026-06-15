<?php
declare(strict_types=1);

namespace Api\Controller\Knowledge;

use Api\Controller\Common\BaseController;
use Api\Model\Knowledge\KnowledgeRepository;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Service\AiSemanticIndexService;
use Api\System\Library\Service\AiProviderService;
use Api\System\Library\Service\FeatureFlagService;

final class KnowledgeAiController extends BaseController
{
    private function repo(): KnowledgeRepository
    {
        return new KnowledgeRepository($this->container->get('db.pdo'));
    }

    private function actor(): array
    {
        $auth = $this->user();
        return is_array($auth['user'] ?? null) ? $auth['user'] : [];
    }

    private function actorUserId(): int
    {
        $actor = $this->actor();
        $id = (int)($actor['id'] ?? 0);
        if ($id > 0) {
            return $id;
        }
        $publicId = trim((string)($actor['public_id'] ?? ''));
        if ($publicId === '') {
            return 0;
        }
        $stmt = $this->container->get('db.pdo')->prepare('SELECT id FROM users WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    private function checkAiEnabled(): ?JsonResponse
    {
        /** @var FeatureFlagService $flags */
        $flags = $this->container->get('service.feature_flag');
        if (!$flags->isEnabled('ai.enabled')) {
            return $this->error('AI_DISABLED', $this->t('ai/messages.action_failed', 'AI features are disabled'), 409, [
                'ai' => ['AI_DISABLED'],
            ]);
        }
        if (!$flags->isEnabled('ai.knowledge')) {
            return $this->error('AI_FEATURE_DISABLED', $this->t('ai/messages.action_failed', 'Knowledge AI features are disabled'), 409, [
                'ai' => ['AI_FEATURE_DISABLED'],
            ]);
        }
        return null;
    }

    /**
     * Generate AI summary of a knowledge page.
     * POST /api/v1/knowledge/pages/{public_id}/ai/summary
     */
    public function summary(array $params): JsonResponse
    {
        $disabled = $this->checkAiEnabled();
        if ($disabled !== null) {
            return $disabled;
        }

        $page = $this->repo()->page((string)$params['public_id'], $this->actor());
        if (!$page) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }

        $title = trim((string)($page['title'] ?? ''));
        $contentText = trim((string)($page['content_text'] ?? ''));
        $contentHtml = trim((string)($page['content_html'] ?? ''));

        if ($contentText === '' && $contentHtml === '') {
            return $this->success('KNOWLEDGE_AI_SUMMARY', $this->t('knowledge/messages.ai_summary', 'AI summary'), [
                'summary' => $this->t('knowledge/messages.ai_no_content', 'Page has no content to summarize'),
                'mode' => 'empty',
            ]);
        }

        $textForSummary = $contentText !== '' ? $contentText : strip_tags($contentHtml);
        $textForSummary = mb_substr($textForSummary, 0, 4000);

        try {
            $result = $this->callLlmForSummary($title, $textForSummary);
            return $this->success('KNOWLEDGE_AI_SUMMARY', $this->t('knowledge/messages.ai_summary', 'AI summary'), [
                'summary' => $result['summary'] ?? '',
                'mode' => $result['mode'] ?? 'llm',
            ]);
        } catch (\Throwable $e) {
            return $this->error('AI_SUMMARY_FAILED', $this->t('knowledge/messages.ai_summary_failed', 'Failed to generate summary'), 500);
        }
    }

    /**
     * Simplify a knowledge page (explain simply).
     * POST /api/v1/knowledge/pages/{public_id}/ai/explain
     */
    public function explain(array $params): JsonResponse
    {
        $disabled = $this->checkAiEnabled();
        if ($disabled !== null) {
            return $disabled;
        }

        $page = $this->repo()->page((string)$params['public_id'], $this->actor());
        if (!$page) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }

        $title = trim((string)($page['title'] ?? ''));
        $contentText = trim((string)($page['content_text'] ?? ''));
        $contentHtml = trim((string)($page['content_html'] ?? ''));

        if ($contentText === '' && $contentHtml === '') {
            return $this->success('KNOWLEDGE_AI_EXPLAIN', $this->t('knowledge/messages.ai_explain', 'AI explanation'), [
                'explanation' => $this->t('knowledge/messages.ai_no_content', 'Page has no content to explain'),
                'mode' => 'empty',
            ]);
        }

        $textForExplain = $contentText !== '' ? $contentText : strip_tags($contentHtml);
        $textForExplain = mb_substr($textForExplain, 0, 4000);

        try {
            $result = $this->callLlmForExplain($title, $textForExplain);
            return $this->success('KNOWLEDGE_AI_EXPLAIN', $this->t('knowledge/messages.ai_explain', 'AI explanation'), [
                'explanation' => $result['explanation'] ?? '',
                'mode' => $result['mode'] ?? 'llm',
            ]);
        } catch (\Throwable $e) {
            return $this->error('AI_EXPLAIN_FAILED', $this->t('knowledge/messages.ai_explain_failed', 'Failed to generate explanation'), 500);
        }
    }

    /**
     * Find similar knowledge pages using semantic index.
     * POST /api/v1/knowledge/pages/{public_id}/ai/similar
     */
    public function similar(array $params): JsonResponse
    {
        $disabled = $this->checkAiEnabled();
        if ($disabled !== null) {
            return $disabled;
        }

        $page = $this->repo()->page((string)$params['public_id'], $this->actor());
        if (!$page) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }

        $title = trim((string)($page['title'] ?? ''));
        $contentText = trim((string)($page['content_text'] ?? ''));
        $contentHtml = trim((string)($page['content_html'] ?? ''));
        $text = $contentText !== '' ? $title . ' ' . $contentText : $title . ' ' . strip_tags($contentHtml);
        $text = mb_substr($text, 0, 2000);

        if (trim($text) === '') {
            return $this->success('KNOWLEDGE_AI_SIMILAR', $this->t('knowledge/messages.ai_similar', 'Similar pages'), [
                'items' => [],
                'mode' => 'empty',
            ]);
        }

        try {
            $limit = max(1, min(20, (int)$this->request()->input('limit', 10)));
            /** @var AiSemanticIndexService $semanticIndex */
            $semanticIndex = $this->container->get('service.ai_semantic_index');
            $result = $semanticIndex->search($text, $limit * 3);
            $items = [];
            $currentPublicId = (string)$params['public_id'];
            $actor = $this->actor();
            foreach ((array)($result['items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $meta = is_array($item['meta'] ?? null) ? (array)$item['meta'] : [];
                $entityType = (string)($meta['entity_type'] ?? '');
                $entityPublicId = (string)($meta['entity_public_id'] ?? '');
                if ($entityType !== 'knowledge' || $entityPublicId === '' || $entityPublicId === $currentPublicId) {
                    continue;
                }
                // Check access to the found page
                $foundPage = $this->repo()->page($entityPublicId, $actor);
                if (!$foundPage) {
                    continue;
                }
                $items[] = [
                    'public_id' => $entityPublicId,
                    'title' => trim((string)($foundPage['title'] ?? '')),
                    'page_type' => trim((string)($foundPage['page_type'] ?? 'article')),
                    'status' => trim((string)($foundPage['status'] ?? 'draft')),
                    'space_title' => trim((string)($foundPage['space_title'] ?? '')),
                    'excerpt' => trim((string)($foundPage['excerpt'] ?? '')),
                    'score' => (float)($item['score'] ?? 0.0),
                ];
                if (count($items) >= $limit) {
                    break;
                }
            }
            return $this->success('KNOWLEDGE_AI_SIMILAR', $this->t('knowledge/messages.ai_similar', 'Similar pages'), [
                'items' => $items,
                'mode' => count($items) > 0 ? 'semantic' : 'empty',
            ]);
        } catch (\Throwable $e) {
            return $this->error('AI_SIMILAR_FAILED', $this->t('knowledge/messages.ai_similar_failed', 'Failed to find similar pages'), 500);
        }
    }

    /**
     * @return array{summary:string,mode:string}
     */
    private function callLlmForSummary(string $title, string $text): array
    {
        /** @var AiProviderService $aiProvider */
        $aiProvider = $this->container->get('service.ai_provider');
        $provider = $this->resolveAiProvider($aiProvider);
        if ($provider === null) {
            return [
                'summary' => $this->buildFallbackSummary($title, $text),
                'mode' => 'fallback',
            ];
        }

        $prompt = 'You are a professional knowledge base assistant. Summarize the following page concisely in the same language as the content. Return ONLY the summary text, no extra formatting.';
        $userPrompt = "Title: {$title}\n\nContent:\n{$text}\n\nWrite a concise summary (3-5 sentences) of this page:";

        $llmResult = $aiProvider->completeText((string)($provider['public_id'] ?? ''), [
            'intent_code' => 'knowledge_summary',
            'system_prompt' => $prompt,
            'user_prompt' => $userPrompt,
            'context' => [],
            'model' => (string)($provider['default_model'] ?? ''),
        ]);

        $text = trim((string)($llmResult['text'] ?? ''));
        if ($text === '') {
            return [
                'summary' => $this->buildFallbackSummary($title, $text),
                'mode' => 'fallback',
            ];
        }
        // Remove any markdown code fences if present
        $text = preg_replace('/^```\w*\s*\n?|```\s*$/m', '', $text) ?? $text;

        return [
            'summary' => trim($text),
            'mode' => 'llm',
        ];
    }

    /**
     * @return array{explanation:string,mode:string}
     */
    private function callLlmForExplain(string $title, string $text): array
    {
        /** @var AiProviderService $aiProvider */
        $aiProvider = $this->container->get('service.ai_provider');
        $provider = $this->resolveAiProvider($aiProvider);
        if ($provider === null) {
            return [
                'explanation' => $this->buildFallbackExplanation($title, $text),
                'mode' => 'fallback',
            ];
        }

        $prompt = 'You are a professional knowledge base assistant. Explain the following page in simple terms that anyone can understand. Use plain language, avoid jargon. Return ONLY the explanation text, no extra formatting.';
        $userPrompt = "Title: {$title}\n\nContent:\n{$text}\n\nExplain this page in simple terms:";

        $llmResult = $aiProvider->completeText((string)($provider['public_id'] ?? ''), [
            'intent_code' => 'knowledge_simplify',
            'system_prompt' => $prompt,
            'user_prompt' => $userPrompt,
            'context' => [],
            'model' => (string)($provider['default_model'] ?? ''),
        ]);

        $text = trim((string)($llmResult['text'] ?? ''));
        if ($text === '') {
            return [
                'explanation' => $this->buildFallbackExplanation($title, $text),
                'mode' => 'fallback',
            ];
        }
        $text = preg_replace('/^```\w*\s*\n?|```\s*$/m', '', $text) ?? $text;

        return [
            'explanation' => trim($text),
            'mode' => 'llm',
        ];
    }

    private function buildFallbackSummary(string $title, string $text): string
    {
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $excerpt = is_array($words) ? implode(' ', array_slice($words, 0, 60)) : $text;
        if (mb_strlen($excerpt) > 400) {
            $excerpt = mb_substr($excerpt, 0, 397) . '...';
        }
        return $excerpt;
    }

    private function buildFallbackExplanation(string $title, string $text): string
    {
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $excerpt = is_array($words) ? implode(' ', array_slice($words, 0, 80)) : $text;
        if (mb_strlen($excerpt) > 500) {
            $excerpt = mb_substr($excerpt, 0, 497) . '...';
        }
        return $excerpt;
    }

    /**
     * Generate a checklist from page content.
     * POST /api/v1/knowledge/pages/{public_id}/ai/checklist
     */
    public function checklist(array $params): JsonResponse
    {
        $disabled = $this->checkAiEnabled();
        if ($disabled !== null) {
            return $disabled;
        }

        $page = $this->repo()->page((string)$params['public_id'], $this->actor());
        if (!$page) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }

        $contentText = trim((string)($page['content_text'] ?? ''));
        $contentHtml = trim((string)($page['content_html'] ?? ''));
        if ($contentText === '' && $contentHtml === '') {
            return $this->success('KNOWLEDGE_AI_CHECKLIST', $this->t('knowledge/messages.ai_checklist', 'AI checklist'), [
                'items' => [],
                'mode' => 'empty',
            ]);
        }

        $text = $contentText !== '' ? $contentText : strip_tags($contentHtml);
        $text = mb_substr($text, 0, 4000);

        try {
            $result = $this->callLlmForChecklist((string)($page['title'] ?? ''), $text);
            return $this->success('KNOWLEDGE_AI_CHECKLIST', $this->t('knowledge/messages.ai_checklist', 'AI checklist'), $result);
        } catch (\Throwable $e) {
            return $this->error('AI_CHECKLIST_FAILED', $this->t('knowledge/messages.ai_checklist_failed', 'Failed to generate checklist'), 500);
        }
    }

    /**
     * Create FAQ from page comments.
     * POST /api/v1/knowledge/pages/{public_id}/ai/faq-from-comments
     */
    public function faqFromComments(array $params): JsonResponse
    {
        $disabled = $this->checkAiEnabled();
        if ($disabled !== null) {
            return $disabled;
        }

        $page = $this->repo()->page((string)$params['public_id'], $this->actor());
        if (!$page) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }

        $comments = $this->repo()->comments((string)$params['public_id'], 0, 100);
        $items = [];
        foreach (($comments['items'] ?? $comments) as $c) {
            $body = trim((string)($c['body'] ?? $c['comment'] ?? ''));
            if ($body !== '') {
                $items[] = $body;
            }
        }

        if (empty($items)) {
            return $this->success('KNOWLEDGE_AI_FAQ', $this->t('knowledge/messages.ai_faq_from_comments', 'FAQ from comments'), [
                'items' => [],
                'mode' => 'empty',
                'comments_count' => 0,
            ]);
        }

        try {
            $result = $this->callLlmForFaq((string)($page['title'] ?? ''), $items);
            return $this->success('KNOWLEDGE_AI_FAQ', $this->t('knowledge/messages.ai_faq_from_comments', 'FAQ from comments'), $result);
        } catch (\Throwable $e) {
            return $this->error('AI_FAQ_FAILED', $this->t('knowledge/messages.ai_faq_failed', 'Failed to generate FAQ from comments'), 500);
        }
    }

    /**
     * Suggest related knowledge pages for a task.
     * POST /api/v1/knowledge/ai/suggest-for-task/{task_public_id}
     */
    public function suggestForTask(array $params): JsonResponse
    {
        $disabled = $this->checkAiEnabled();
        if ($disabled !== null) {
            return $disabled;
        }

        $taskPublicId = (string)($params['task_public_id'] ?? '');
        if ($taskPublicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error', 'Validation error'), 422);
        }

        $task = $this->container->get('service.task')->get($taskPublicId, $this->actor());
        if (!$task) {
            return $this->error('TASK_NOT_FOUND', $this->t('knowledge/messages.task_not_found', 'Task not found'), 404);
        }

        $taskTitle = trim((string)($task['title'] ?? ''));
        $taskDescription = trim((string)($task['description'] ?? ''));
        $query = $taskTitle . ' ' . $taskDescription;
        if (trim($query) === '') {
            return $this->success('KNOWLEDGE_AI_SUGGEST', $this->t('knowledge/messages.ai_suggest', 'AI suggestions'), [
                'items' => [],
                'mode' => 'empty',
            ]);
        }

        try {
            $limit = max(1, min(20, (int)$this->request()->input('limit', 10)));
            /** @var AiSemanticIndexService $semanticIndex */
            $semanticIndex = $this->container->get('service.ai_semantic_index');
            $result = $semanticIndex->search($query, $limit * 3);
            $items = [];
            $actor = $this->actor();
            foreach ((array)($result['items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $meta = is_array($item['meta'] ?? null) ? (array)$item['meta'] : [];
                $entityType = (string)($meta['entity_type'] ?? '');
                $entityPublicId = (string)($meta['entity_public_id'] ?? '');
                if ($entityType !== 'knowledge' || $entityPublicId === '') {
                    continue;
                }
                $foundPage = $this->repo()->page($entityPublicId, $actor);
                if (!$foundPage) {
                    continue;
                }
                $items[] = [
                    'public_id' => $entityPublicId,
                    'title' => trim((string)($foundPage['title'] ?? '')),
                    'page_type' => trim((string)($foundPage['page_type'] ?? 'article')),
                    'status' => trim((string)($foundPage['status'] ?? 'draft')),
                    'space_title' => trim((string)($foundPage['space_title'] ?? '')),
                    'excerpt' => trim((string)($foundPage['excerpt'] ?? '')),
                    'score' => (float)($item['score'] ?? 0.0),
                ];
                if (count($items) >= $limit) {
                    break;
                }
            }
            return $this->success('KNOWLEDGE_AI_SUGGEST', $this->t('knowledge/messages.ai_suggest', 'AI suggestions'), [
                'items' => $items,
                'mode' => count($items) > 0 ? 'semantic' : 'empty',
            ]);
        } catch (\Throwable $e) {
            return $this->error('AI_SUGGEST_FAILED', $this->t('knowledge/messages.ai_suggest_failed', 'Failed to suggest pages'), 500);
        }
    }

    // --- Admin AI features ---

    /**
     * Find duplicate pages by title similarity.
     * POST /api/v1/knowledge/ai/admin/find-duplicates
     */
    public function findDuplicates(array $params): JsonResponse
    {
        $disabled = $this->checkAiEnabled();
        if ($disabled !== null) {
            return $disabled;
        }

        $actor = $this->actor();
        $pdo = $this->container->get('db.pdo');
        $threshold = max(0.3, min(1.0, (float)$this->request()->input('threshold', 0.75)));

        $stmt = $pdo->prepare('
            SELECT p1.public_id AS public_id_1, p1.title AS title_1,
                   p2.public_id AS public_id_2, p2.title AS title_2,
                   p1.space_id, s.title AS space_title
            FROM knowledge_pages p1
            JOIN knowledge_pages p2 ON p1.id < p2.id AND p1.deleted_at IS NULL AND p2.deleted_at IS NULL
            JOIN knowledge_spaces s ON p1.space_id = s.id
            WHERE p1.deleted_at IS NULL AND p2.deleted_at IS NULL
              AND p1.status = ? AND p2.status = ?
              AND p1.space_id = p2.space_id
              AND (SOUNDEX(p1.title) = SOUNDEX(p2.title) OR LOCATE(SUBSTRING(p1.title, 1, 20), p2.title) > 0)
            ORDER BY p1.title
            LIMIT 50
        ');
        $stmt->execute(['published', 'published']);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $duplicates = [];
        foreach ($rows as $row) {
            $page1 = $this->repo()->page($row['public_id_1'], $actor);
            $page2 = $this->repo()->page($row['public_id_2'], $actor);
            if (!$page1 || !$page2) {
                continue;
            }
            $duplicates[] = [
                'page_1' => [
                    'public_id' => $row['public_id_1'],
                    'title' => $row['title_1'],
                ],
                'page_2' => [
                    'public_id' => $row['public_id_2'],
                    'title' => $row['title_2'],
                ],
                'space_title' => $row['space_title'],
                'similarity' => 1.0,
            ];
        }

        // Also check via semantic index for content-level duplicates
        /** @var AiSemanticIndexService $semanticIndex */
        $semanticIndex = $this->container->get('service.ai_semantic_index');
        $allIndexed = $semanticIndex->search('', 1000);
        $known = [];
        foreach ((array)($allIndexed['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $meta = is_array($item['meta'] ?? null) ? (array)$item['meta'] : [];
            if ((string)($meta['entity_type'] ?? '') !== 'knowledge') {
                continue;
            }
            $known[] = $item;
        }

        for ($i = 0; $i < count($known); ++$i) {
            for ($j = $i + 1; $j < count($known); ++$j) {
                $score = (float)($known[$j]['score'] ?? 0.0);
                if ($score < $threshold) {
                    continue;
                }
                $id1 = (string)($known[$i]['meta']['entity_public_id'] ?? '');
                $id2 = (string)($known[$j]['meta']['entity_public_id'] ?? '');
                if ($id1 === '' || $id2 === '' || $id1 === $id2) {
                    continue;
                }
                $exists = false;
                foreach ($duplicates as $d) {
                    if (($d['page_1']['public_id'] === $id1 && $d['page_2']['public_id'] === $id2)
                        || ($d['page_1']['public_id'] === $id2 && $d['page_2']['public_id'] === $id1)) {
                        $exists = true;
                        break;
                    }
                }
                if ($exists) {
                    continue;
                }
                $p1 = $this->repo()->page($id1, $actor);
                $p2 = $this->repo()->page($id2, $actor);
                if (!$p1 || !$p2) {
                    continue;
                }
                $duplicates[] = [
                    'page_1' => ['public_id' => $id1, 'title' => trim((string)($p1['title'] ?? ''))],
                    'page_2' => ['public_id' => $id2, 'title' => trim((string)($p2['title'] ?? ''))],
                    'space_title' => trim((string)($p1['space_title'] ?? '')),
                    'similarity' => $score,
                ];
                if (count($duplicates) >= 50) {
                    break 2;
                }
            }
        }

        usort($duplicates, static function (array $a, array $b): int {
            return ($b['similarity'] ?? 0) <=> ($a['similarity'] ?? 0);
        });

        return $this->success('KNOWLEDGE_AI_DUPLICATES', $this->t('knowledge/messages.ai_duplicates', 'Duplicates found'), [
            'items' => array_slice($duplicates, 0, 50),
            'mode' => count($duplicates) > 0 ? 'hybrid' : 'empty',
        ]);
    }

    /**
     * Find pages without an owner.
     * GET /api/v1/knowledge/ai/admin/find-orphans
     */
    public function findOrphans(array $params): JsonResponse
    {
        $disabled = $this->checkAiEnabled();
        if ($disabled !== null) {
            return $disabled;
        }

        $actor = $this->actor();
        $pdo = $this->container->get('db.pdo');

        $stmt = $pdo->prepare('
            SELECT p.public_id, p.title, p.status, p.page_type, p.created_at,
                   s.title AS space_title
            FROM knowledge_pages p
            JOIN knowledge_spaces s ON p.space_id = s.id
            WHERE p.deleted_at IS NULL
              AND (p.owner_user_id IS NULL OR p.owner_user_id = 0)
            ORDER BY p.created_at DESC
            LIMIT 100
        ');
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $items = [];
        foreach ($rows as $row) {
            $canView = $this->repo()->page($row['public_id'], $actor);
            if (!$canView) {
                continue;
            }
            $items[] = [
                'public_id' => $row['public_id'],
                'title' => $row['title'],
                'status' => $row['status'],
                'page_type' => $row['page_type'],
                'space_title' => $row['space_title'],
                'created_at' => $row['created_at'],
            ];
        }

        return $this->success('KNOWLEDGE_AI_ORPHANS', $this->t('knowledge/messages.ai_orphans', 'Orphan pages'), [
            'items' => $items,
            'total' => count($items),
        ]);
    }

    /**
     * Suggest space structure based on page clustering.
     * POST /api/v1/knowledge/ai/admin/suggest-structure
     */
    public function suggestStructure(array $params): JsonResponse
    {
        $disabled = $this->checkAiEnabled();
        if ($disabled !== null) {
            return $disabled;
        }

        $spacePublicId = (string)($params['public_id'] ?? '');
        if ($spacePublicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error', 'Validation error'), 422, [
                'public_id' => ['Space public_id is required'],
            ]);
        }

        $actor = $this->actor();
        $space = $this->repo()->space($spacePublicId, $actor);
        if (!$space) {
            return $this->error('SPACE_NOT_FOUND', $this->t('knowledge/messages.space_not_found', 'Space not found'), 404);
        }

        $pdo = $this->container->get('db.pdo');
        $spaceId = (int)($space['id'] ?? 0);
        $stmt = $pdo->prepare('
            SELECT public_id, title, page_type, status, content_text
            FROM knowledge_pages
            WHERE space_id = ? AND deleted_at IS NULL AND status = ?
            ORDER BY title
            LIMIT 200
        ');
        $stmt->execute([$spaceId, 'published']);
        $pages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($pages) < 3) {
            return $this->success('KNOWLEDGE_AI_STRUCTURE', $this->t('knowledge/messages.ai_structure', 'Structure suggestion'), [
                'suggestion' => $this->t('knowledge/messages.ai_structure_too_few', 'Add at least 3 pages to get a structure suggestion'),
                'mode' => 'too_few',
            ]);
        }

        // Group pages by page_type as a simple heuristic
        $groups = [];
        foreach ($pages as $p) {
            $type = trim((string)($p['page_type'] ?? 'article'));
            if (!isset($groups[$type])) {
                $groups[$type] = [];
            }
            $groups[$type][] = $p['title'];
        }

        $suggestion = [];
        foreach ($groups as $type => $titles) {
            if (count($titles) >= 2) {
                $suggestion[] = [
                    'group' => $type,
                    'title' => $this->pageTypeLabel($type),
                    'pages' => $titles,
                    'count' => count($titles),
                ];
            }
        }

        // If no good grouping, suggest by title prefix/keyword
        if (empty($suggestion)) {
            $keywords = [];
            foreach ($pages as $p) {
                $title = (string)($p['title'] ?? '');
                $words = preg_split('/[\s\-–—]+/u', $title, -1, PREG_SPLIT_NO_EMPTY);
                if (!is_array($words)) {
                    continue;
                }
                foreach (array_slice($words, 0, 2) as $w) {
                    $w = mb_strtolower(trim($w));
                    if (mb_strlen($w) < 4 || in_array($w, ['the', 'and', 'for', 'how', 'what', 'why', 'when'], true)) {
                        continue;
                    }
                    if (!isset($keywords[$w])) {
                        $keywords[$w] = [];
                    }
                    $keywords[$w][] = $title;
                }
            }
            foreach ($keywords as $word => $titles) {
                if (count($titles) >= 2) {
                    $suggestion[] = [
                        'group' => $word,
                        'title' => ucfirst($word),
                        'pages' => $titles,
                        'count' => count($titles),
                    ];
                }
            }
        }

        usort($suggestion, static function (array $a, array $b): int {
            return $b['count'] <=> $a['count'];
        });

        return $this->success('KNOWLEDGE_AI_STRUCTURE', $this->t('knowledge/messages.ai_structure', 'Structure suggestion'), [
            'suggestion' => $suggestion,
            'mode' => 'heuristic',
            'space_title' => $space['title'] ?? '',
        ]);
    }

    // --- Private helpers ---

    /**
     * @return array{items:array<int,string>,mode:string}
     */
    private function callLlmForChecklist(string $title, string $text): array
    {
        /** @var AiProviderService $aiProvider */
        $aiProvider = $this->container->get('service.ai_provider');
        $provider = $this->resolveAiProvider($aiProvider);
        if ($provider !== null) {
            $prompt = 'You are a professional knowledge base assistant. Extract action items from the following page as a checklist. Return ONLY a JSON array of strings, each string is one checklist item. Example: ["Item 1", "Item 2"]';
            $userPrompt = "Title: {$title}\n\nContent:\n{$text}\n\nExtract checklist items:";

            $llmResult = $aiProvider->completeText((string)($provider['public_id'] ?? ''), [
                'intent_code' => 'knowledge_summary',
                'system_prompt' => $prompt,
                'user_prompt' => $userPrompt,
                'context' => [],
                'model' => (string)($provider['default_model'] ?? ''),
            ]);

            $responseText = trim((string)($llmResult['text'] ?? ''));
            if ($responseText !== '') {
                $responseText = preg_replace('/^```json\s*|```\s*$/m', '', $responseText) ?? $responseText;
                $decoded = json_decode($responseText, true);
                if (is_array($decoded)) {
                    $items = [];
                    foreach ($decoded as $item) {
                        if (is_string($item)) {
                            $items[] = $item;
                        }
                    }
                    if (!empty($items)) {
                        return ['items' => $items, 'mode' => 'llm'];
                    }
                }
            }
        }

        // Fallback: extract sentences with action verbs
        $sentences = preg_split('/[.!?]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $items = [];
        $actionVerbs = ['ensure', 'check', 'verify', 'make', 'create', 'update', 'review', 'submit', 'send', 'confirm', 'prepare', 'implement', 'configure', 'install', 'run', 'test', 'validate', 'document', 'train', 'approve', 'complete', 'follow', 'use', 'add', 'remove', 'set'];
        if (is_array($sentences)) {
            foreach ($sentences as $s) {
                $s = trim($s);
                if ($s === '') {
                    continue;
                }
                $lower = mb_strtolower($s);
                foreach ($actionVerbs as $verb) {
                    if (preg_match('/\b' . preg_quote($verb, '/') . '\b/ui', $lower) === 1) {
                        $items[] = mb_substr($s, 0, 200);
                        break;
                    }
                }
                if (count($items) >= 15) {
                    break;
                }
            }
        }

        if (empty($items)) {
            // Just use bullet points from HTML
            if (preg_match_all('/<li[^>]*>([^<]+)<\/li>/iu', $text, $matches) > 0) {
                $items = array_slice($matches[1], 0, 20);
            }
        }

        return [
            'items' => $items,
            'mode' => 'fallback',
        ];
    }

    /**
     * @param array<int,string> $comments
     * @return array{items:array<int,array<string,string>>,mode:string}
     */
    private function callLlmForFaq(string $pageTitle, array $comments): array
    {
        /** @var AiProviderService $aiProvider */
        $aiProvider = $this->container->get('service.ai_provider');
        $provider = $this->resolveAiProvider($aiProvider);
        if ($provider !== null) {
            $commentsText = implode("\n- ", $comments);
            $prompt = 'You are a professional knowledge base assistant. Based on the page title and reader comments, generate FAQ entries. Return ONLY a JSON array of objects with "question" and "answer" fields. Example: [{"question": "Q1?", "answer": "A1."}]';
            $userPrompt = "Page: {$pageTitle}\n\nComments:\n- {$commentsText}\n\nGenerate FAQ entries:";

            $llmResult = $aiProvider->completeText((string)($provider['public_id'] ?? ''), [
                'intent_code' => 'knowledge_summary',
                'system_prompt' => $prompt,
                'user_prompt' => $userPrompt,
                'context' => [],
                'model' => (string)($provider['default_model'] ?? ''),
            ]);

            $responseText = trim((string)($llmResult['text'] ?? ''));
            if ($responseText !== '') {
                $responseText = preg_replace('/^```json\s*|```\s*$/m', '', $responseText) ?? $responseText;
                $decoded = json_decode($responseText, true);
                if (is_array($decoded)) {
                    $items = [];
                    foreach ($decoded as $item) {
                        if (is_array($item) && isset($item['question'], $item['answer'])) {
                            $items[] = [
                                'question' => trim((string)$item['question']),
                                'answer' => trim((string)$item['answer']),
                            ];
                        }
                    }
                    if (!empty($items)) {
                        return ['items' => $items, 'mode' => 'llm', 'comments_count' => count($comments)];
                    }
                }
            }
        }

        // Fallback: treat each comment as a potential FAQ item
        $items = [];
        foreach ($comments as $c) {
            if (str_contains($c, '?')) {
                $parts = explode('?', $c, 2);
                $items[] = [
                    'question' => trim($parts[0]) . '?',
                    'answer' => isset($parts[1]) ? trim(mb_substr($parts[1], 0, 300)) : '',
                ];
            }
            if (count($items) >= 20) {
                break;
            }
        }

        return [
            'items' => $items,
            'mode' => 'fallback',
            'comments_count' => count($comments),
        ];
    }

    private function pageTypeLabel(string $type): string
    {
        $labels = [
            'article' => 'Articles',
            'regulation' => 'Regulations',
            'instruction' => 'Instructions',
            'checklist' => 'Checklists',
            'faq' => 'FAQs',
            'runbook' => 'Runbooks',
            'decision' => 'Decisions',
            'meeting_note' => 'Meeting Notes',
            'client_note' => 'Client Notes',
            'project_note' => 'Project Notes',
            'onboarding' => 'Onboarding',
        ];
        return $labels[$type] ?? ucfirst($type);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function resolveAiProvider(AiProviderService $aiProvider): ?array
    {
        $providersResult = $aiProvider->list([]);
        $items = (array)($providersResult['items'] ?? []);
        $defaultProvider = null;
        foreach ($items as $p) {
            if (is_array($p) && (bool)($p['is_active'] ?? false)) {
                if ((bool)($p['is_default'] ?? false)) {
                    return $p;
                }
                if ($defaultProvider === null) {
                    $defaultProvider = $p;
                }
            }
        }
        return $defaultProvider;
    }
}
