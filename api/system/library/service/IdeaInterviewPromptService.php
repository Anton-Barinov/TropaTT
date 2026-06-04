<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class IdeaInterviewPromptService
{
    public function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You conduct a deep diagnostic interview about a user's idea.

Goal: collect MAXIMUM information for comprehensive later analysis.
Cover all aspects: motivation, budget, timeline, resources, risks, alternatives, people, place, experience, legal, and any other relevant details.

Forbidden: analyze, evaluate, advise, draw conclusions.

Core rule: never repeat closed topics.
A topic is closed if: it was already asked, info exists in idea/answers, or it's in already_covered_topics/do_not_ask_again_topics.
Even "I don't know" closes the topic.

Use idea.category to identify idea type and focus on relevant aspects.

Base aspects for any idea:
- Motivation & goal (why, what problem, success/failure criteria, cost of inaction)
- Expectations (concrete result, ideal vs minimum, deadline)
- Resources (budget, time, effort, what exists, what's needed)
- Time & stages (start date, duration, blockers, seasonality)
- Experience (skills, learning needed, who can advise)
- People (who's involved, supporters/opponents, helpers needed)
- Place (where, already available, constraints, accessibility)
- Risks (what could go wrong, external/personal, dependencies, plan B)
- Alternatives (other options, why this one chosen)

Dig deep. For each aspect ask: do I know specific numbers, dates, names, places? Are risks, alternatives, dependencies clear? If not — ask.

Use ALL remaining_question_count, max 15 per batch.
should_ask_questions=false only if remaining=0 OR everything is fully explored.

Each question requires: question (MANDATORY), semantic_key, dimension, priority, why_needed, type, multiple, options (3-7 + not_sure + custom), allow_custom_answer=true.

Format: valid JSON only, no markdown, no comments.

Template: {"should_ask_questions":true,"idea_diagnostics":{"idea_type":"","specificity_level":"","clarity_score":0.0,"can_start_analysis":false,"main_reason":"","known_facts":[],"missing_facts":[],"blocked_topics":[]},"questions":[{"question":"text","semantic_key":"key","dimension":"dim","priority":100,"why_needed":"reason","type":"single_choice","multiple":false,"options":[{"value":"opt1","label":"Option 1"},{"value":"not_sure","label":"Not sure"},{"value":"custom","label":"Other"}],"allow_custom_answer":true}]}

Before final answer: remove questions with closed topics, semantic duplicates, or those giving no new information.

You are an interviewer. Collect data. Don't judge. Use the full limit.

PROMPT;
    }

    public function buildUserPrompt(array $data): string
    {
        $idea = $data['idea'] ?? [];
        $limits = $data['question_limits'] ?? [];
        $asked = $data['already_asked_questions'] ?? [];
        $coveredTopics = $data['already_covered_topics'] ?? [];
        $doNotAskTopics = $data['do_not_ask_again_topics'] ?? [];

        $desc = $idea['description'] ?? '';
        $plainDesc = strip_tags($desc);

        return json_encode([
            'idea' => [
                'title' => $idea['title'] ?? '',
                'short_description' => mb_substr($plainDesc, 0, 200),
                'description_plain_text' => $plainDesc,
                'description_html' => $desc,
                'category' => $idea['category'] ?? '',
                'product' => $idea['product'] ?? '',
                'region' => $idea['region'] ?? '',
                'target_date' => $idea['target_date'] ?? null,
                'current_date' => date('Y-m-d'),
            ],
            'question_limits' => [
                'total_question_limit' => (int)($limits['total'] ?? 25),
                'already_asked_count' => (int)($limits['asked'] ?? 0),
                'remaining_question_count' => max(0, 25 - (int)($limits['asked'] ?? 0)),
                'current_batch_limit' => max(0, 25 - (int)($limits['asked'] ?? 0)),
            ],
            'already_asked_questions' => $asked,
            'already_covered_topics' => array_values($coveredTopics),
            'do_not_ask_again_topics' => array_values($doNotAskTopics),
            'is_first_interview' => (bool)($data['is_first_interview'] ?? false),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
