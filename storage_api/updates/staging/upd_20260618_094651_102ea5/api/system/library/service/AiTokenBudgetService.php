<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class AiTokenBudgetService
{
    private const DEFAULT_CONTEXT_BUDGET_TOKENS = 1200;
    private const MIN_CONTEXT_BUDGET_TOKENS = 128;
    private const MAX_CONTEXT_BUDGET_TOKENS = 8192;
    private const TOKEN_TO_CHAR_RATIO = 4;

    /**
     * @param array<string,mixed> $context
     * @return array{context:array<string,mixed>,meta:array<string,mixed>}
     */
    public function limitContext(array $context, int $budgetTokens = self::DEFAULT_CONTEXT_BUDGET_TOKENS): array
    {
        $normalizedBudget = max(self::MIN_CONTEXT_BUDGET_TOKENS, min(self::MAX_CONTEXT_BUDGET_TOKENS, $budgetTokens));
        $budgetChars = $normalizedBudget * self::TOKEN_TO_CHAR_RATIO;
        $initialChars = $this->estimateChars($context);

        if ($initialChars <= $budgetChars) {
            return [
                'context' => $context,
                'meta' => [
                    'budget_tokens' => $normalizedBudget,
                    'estimated_tokens' => $this->estimateTokensByChars($initialChars),
                    'estimated_chars' => $initialChars,
                    'truncated' => false,
                    'dropped_keys' => [],
                ],
            ];
        }

        $priorities = $this->priorityMap();
        $keys = array_keys($context);
        usort($keys, static function (string $left, string $right) use ($priorities): int {
            return ($priorities[$right] ?? 50) <=> ($priorities[$left] ?? 50);
        });

        $result = [];
        $remaining = $budgetChars;
        $dropped = [];
        foreach ($keys as $key) {
            $value = $context[$key];
            $valueChars = $this->estimateChars($value);
            if ($valueChars <= $remaining) {
                $result[$key] = $value;
                $remaining -= $valueChars;
                continue;
            }

            if ($remaining < 32) {
                $dropped[] = $key;
                continue;
            }

            $truncatedValue = $this->truncateValue($value, $remaining);
            if ($truncatedValue === null) {
                $dropped[] = $key;
                continue;
            }
            $result[$key] = $truncatedValue;
            $remaining = max(0, $remaining - $this->estimateChars($truncatedValue));
        }

        $finalChars = $this->estimateChars($result);
        if ($finalChars > $budgetChars) {
            $result = $this->forceCompactContext($result, $budgetChars);
            $finalChars = $this->estimateChars($result);
        }
        return [
            'context' => $result,
            'meta' => [
                'budget_tokens' => $normalizedBudget,
                'estimated_tokens' => $this->estimateTokensByChars($finalChars),
                'estimated_chars' => $finalChars,
                'truncated' => true,
                'dropped_keys' => array_values(array_unique($dropped)),
            ],
        ];
    }

    public function estimateTokens(string $text): int
    {
        return $this->estimateTokensByChars(mb_strlen($text));
    }

    /** @param mixed $value */
    private function estimateChars(mixed $value): int
    {
        if (is_string($value)) {
            return mb_strlen($value);
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            return 0;
        }

        return mb_strlen($encoded);
    }

    private function estimateTokensByChars(int $chars): int
    {
        return max(1, (int)ceil(max(0, $chars) / self::TOKEN_TO_CHAR_RATIO));
    }

    /** @param mixed $value */
    private function truncateValue(mixed $value, int $maxChars): mixed
    {
        if ($maxChars <= 0) {
            return null;
        }

        if (is_string($value)) {
            if (mb_strlen($value) <= $maxChars) {
                return $value;
            }
            $suffix = '...[truncated]';
            $allowed = max(0, $maxChars - mb_strlen($suffix));
            return mb_substr($value, 0, $allowed) . $suffix;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            return null;
        }
        if (mb_strlen($encoded) <= $maxChars) {
            return $value;
        }

        $suffix = '...[truncated]';
        $allowed = max(0, $maxChars - mb_strlen($suffix));
        return mb_substr($encoded, 0, $allowed) . $suffix;
    }

    /** @return array<string,int> */
    private function priorityMap(): array
    {
        return [
            'intent_code' => 120,
            'task_public_id' => 120,
            'project_public_id' => 120,
            'client_public_id' => 120,
            'import_job_public_id' => 120,
            'title' => 110,
            'status' => 110,
            'priority' => 110,
            'due_at' => 110,
            'date' => 110,
            'summary' => 110,
            'prompt' => 80,
            'description' => 40,
            'dashboard' => 35,
            'analytics' => 35,
            'candidate_tasks' => 30,
            'security_logs' => 30,
            'result' => 30,
            'details' => 25,
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function forceCompactContext(array $context, int $maxChars): array
    {
        if ($maxChars <= 24) {
            return ['_context' => '...[truncated]'];
        }

        $preserved = $this->extractPreservedKeys($context);
        if ($this->estimateChars($preserved) > $maxChars) {
            return $preserved;
        }

        $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            return $preserved !== [] ? $preserved : ['_context' => '...[truncated]'];
        }

        if (mb_strlen($encoded) <= $maxChars) {
            return $context;
        }

        $suffix = '...[truncated]';
        $allowed = max(0, $maxChars - $this->estimateChars($preserved) - mb_strlen($suffix) - 24);
        $preview = mb_substr($encoded, 0, $allowed) . $suffix;
        $result = $preserved;
        $result['_context'] = $preview;
        while ($this->estimateChars($result) > $maxChars && $allowed > 0) {
            $allowed -= 8;
            if ($allowed < 0) {
                $allowed = 0;
            }
            $preview = mb_substr($encoded, 0, $allowed) . $suffix;
            $result = $preserved;
            $result['_context'] = $preview;
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function extractPreservedKeys(array $context): array
    {
        $keep = [
            'intent_code',
            'task_public_id',
            'project_public_id',
            'client_public_id',
            'import_job_public_id',
            'title',
            'status',
            'priority',
            'due_at',
            'date',
            'summary',
            'prompt',
        ];

        $result = [];
        foreach ($keep as $key) {
            if (!array_key_exists($key, $context)) {
                continue;
            }
            $value = $context[$key];
            if (is_string($value) && mb_strlen($value) > 160) {
                $value = mb_substr($value, 0, 148) . '...[truncated]';
            }
            $result[$key] = $value;
        }

        return $result;
    }
}
