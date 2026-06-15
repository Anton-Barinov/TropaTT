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
