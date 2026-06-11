<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use PDO;

final class IdeaService
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return array<string,mixed>|null */
    public function getByPublicId(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT i.*, u.full_name AS author_name, u.login AS author_login, u.public_id AS author_public_id,
                (SELECT COUNT(*) FROM comments c WHERE c.entity_type = 'idea' AND c.entity_public_id = i.public_id) AS comment_count
             FROM ideas i
             LEFT JOIN users u ON u.id = i.author_user_id
             WHERE i.public_id = :pid"
        );
        $stmt->execute(['pid' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    public function get(string $publicId): ?array
    {
        return $this->getByPublicId($publicId);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ideas WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array{items: array<int,array<string,mixed>>, meta: array{pagination: array{total:int,limit:int,offset:int}}} */
    public function list(array $filters): array
    {
        $status = (string)($filters['status'] ?? '');
        $limit = min(50, max(1, (int)($filters['limit'] ?? 20)));
        $offset = max(0, (int)($filters['offset'] ?? 0));

        $where = [];
        $params = [];
        if ($status !== '') { $where[] = 'status = :status'; $params['status'] = $status; }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->pdo->prepare("SELECT i.*, u.full_name as author_name, u.login as author_login FROM ideas i LEFT JOIN users u ON u.id = i.author_user_id {$whereSql} ORDER BY i.vote_count DESC, i.created_at DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM ideas {$whereSql}");
        foreach ($params as $k => $v) $countStmt->bindValue($k, $v);
        $countStmt->execute();

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'meta' => ['pagination' => ['total' => (int)$countStmt->fetchColumn(), 'limit' => $limit, 'offset' => $offset]],
        ];
    }

    public function create(array $input, int $userId): array
    {
        $publicId = 'idea_' . bin2hex(random_bytes(12));
        $this->pdo->prepare("INSERT INTO ideas (public_id, title, description, author_user_id, status, category, created_at) VALUES (:pid, :title, :desc, :uid, 'draft', :cat, NOW())")
            ->execute(['pid' => $publicId, 'title' => $input['title'], 'desc' => $input['description'] ?? '', 'uid' => $userId, 'cat' => $input['category'] ?? '']);
        return $this->getByPublicId($publicId);
    }

    public function update(string $publicId, array $input): ?array
    {
        $idea = $this->getByPublicId($publicId);
        if (!$idea) return null;
        $this->pdo->prepare("UPDATE ideas SET title = :t, description = :d WHERE public_id = :pid")
            ->execute(['t' => $input['title'] ?? $idea['title'], 'd' => $input['description'] ?? $idea['description'], 'pid' => $publicId]);
        return $this->getByPublicId($publicId);
    }

    public function updateStatus(int $ideaId, string $status): void
    {
        $this->pdo->prepare("UPDATE ideas SET status = :s WHERE id = :id")->execute(['s' => $status, 'id' => $ideaId]);
    }

    public function saveClassification(int $ideaId, array $classifyResult): void
    {
        $this->pdo->prepare("UPDATE ideas SET type = :t, domain = :d, maturity = :m, known_facts_json = :kf, unknowns_json = :un, ai_analysis_at = NOW() WHERE id = :id")
            ->execute([
                't' => $classifyResult['idea_type'] ?? null,
                'd' => $classifyResult['domain'] ?? null,
                'm' => $classifyResult['maturity'] ?? null,
                'kf' => json_encode($classifyResult['known_facts'] ?? [], JSON_UNESCAPED_UNICODE),
                'un' => json_encode($classifyResult['unknowns'] ?? [], JSON_UNESCAPED_UNICODE),
                'id' => $ideaId,
            ]);
    }

    public function saveAnalysisMap(int $ideaId, array $mapResult): void
    {
        $coverage = $mapResult['coverage'] ?? [];
        if (is_array($coverage)) {
            foreach ($coverage as $key => $value) {
                if (is_numeric($value)) {
                    $floatVal = (float)$value;
                    if ($floatVal > 0 && $floatVal <= 1) {
                        $coverage[$key] = (int)round($floatVal * 100);
                    } elseif ($floatVal > 1 && $floatVal <= 100) {
                        $coverage[$key] = (int)round($floatVal);
                    }
                }
            }
        }
        $this->pdo->prepare("UPDATE ideas SET coverage_json = :c, assumptions_json = :a WHERE id = :id")
            ->execute(['c' => json_encode($coverage, JSON_UNESCAPED_UNICODE), 'a' => json_encode($mapResult['critical_gaps'] ?? [], JSON_UNESCAPED_UNICODE), 'id' => $ideaId]);
    }

    public function saveQuestions(int $ideaId, int $cycleId, array $questions): void
    {
        $this->pdo->prepare("DELETE FROM idea_questions WHERE idea_id = :iid AND cycle_id = :cycle")->execute(['iid' => $ideaId, 'cycle' => $cycleId]);
        foreach ($questions as $idx => $q) {
            $qId = 'iq_' . bin2hex(random_bytes(8));
            $options = $q['options'] ?? $q['suggested_answers'] ?? [];
            $questionType = $q['question_type'] ?? 'multiple_choice';
            $allowUnknown = (int)($q['allow_unknown'] ?? 1);

            $normalizedOptions = [];
            $seenOptions = [];
            $seenKeys = [];
            foreach ($options as $opt) {
                if (is_array($opt)) {
                    $key = trim((string)($opt['key'] ?? $opt['value'] ?? $opt['label'] ?? ''));
                    $label = trim((string)($opt['label'] ?? $opt['value'] ?? $key));
                    $fingerprint = $this->optionFingerprint($label !== '' ? $label : $key);
                    $keyFingerprint = mb_strtolower($key);
                    if (($keyFingerprint === 'unknown' && isset($seenKeys['not_sure'])) || ($keyFingerprint === 'not_sure' && isset($seenKeys['unknown']))) continue;
                    if ($key === '' || $label === '' || $fingerprint === '' || isset($seenOptions[$fingerprint]) || isset($seenKeys[$keyFingerprint])) continue;
                    $seenOptions[$fingerprint] = true;
                    $seenKeys[$keyFingerprint] = true;
                    $normalizedOptions[] = ['key' => (string)$key, 'label' => (string)$label, 'description' => $opt['description'] ?? null];
                } else {
                    $key = trim((string)$opt);
                    $label = trim((string)$opt);
                    $fingerprint = $this->optionFingerprint($label);
                    $keyFingerprint = mb_strtolower($key);
                    if (($keyFingerprint === 'unknown' && isset($seenKeys['not_sure'])) || ($keyFingerprint === 'not_sure' && isset($seenKeys['unknown']))) continue;
                    if ($key === '' || $label === '' || $fingerprint === '' || isset($seenOptions[$fingerprint]) || isset($seenKeys[$keyFingerprint])) continue;
                    $seenOptions[$fingerprint] = true;
                    $seenKeys[$keyFingerprint] = true;
                    $normalizedOptions[] = ['key' => $key, 'label' => $label, 'description' => null];
                }
            }

            $needsOptions = in_array($questionType, ['single_choice', 'multiple_choice'], true);
            if ($needsOptions && count($normalizedOptions) < 2) {
                $questionType = 'text';
            }

            if ($allowUnknown && !isset($seenKeys['unknown']) && !isset($seenKeys['not_sure']) && !isset($seenOptions[$this->optionFingerprint('Пока не знаю')])) {
                $normalizedOptions[] = ['key' => 'unknown', 'label' => 'Пока не знаю', 'description' => null];
            }

            $allowCustom = (int)($q['allow_custom_answer'] ?? $q['allow_custom'] ?? 1);
            $required = (int)($q['required'] ?? 1);
            $this->pdo->prepare("INSERT INTO idea_questions (public_id, idea_id, cycle_id, question_text, reason, question_type, options_json, allow_custom, allow_unknown, required, dimension, impact, sort_order, created_at) VALUES (:pid, :iid, :cycle, :qt, :reason, :type, :opts, :ac, :au, :req, :dim, :impact, :sort, NOW())")
                ->execute([
                    'pid' => $qId, 'iid' => $ideaId, 'cycle' => $cycleId,
                    'qt' => $q['question_text'] ?? $q['question'] ?? '',
                    'reason' => $q['reason'] ?? '',
                    'type' => $questionType,
                    'opts' => json_encode($normalizedOptions, JSON_UNESCAPED_UNICODE),
                    'ac' => $allowCustom,
                    'au' => $allowUnknown,
                    'req' => $required,
                    'dim' => $q['dimension'] ?? null,
                    'impact' => $q['impact'] ?? 'medium',
                    'sort' => $idx,
                ]);
        }
    }

    private function optionFingerprint(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', '', $value) ?? $value;
        return trim($value);
    }

    public function saveAnswers(int $ideaId, array $answers): void
    {
        foreach ($answers as $ans) {
            $this->pdo->prepare("INSERT INTO idea_answers (idea_id, question_id, answer_text, selected_option_key, selected_option_label, selected_options_json, is_custom, is_unknown, created_at) VALUES (:iid, :qid, :txt, :key, :lbl, :opts, :custom, :unk, NOW())")
                ->execute([
                    'iid' => $ideaId,
                    'qid' => (int)($ans['question_id'] ?? 0),
                    'txt' => $ans['answer_text'] ?? null,
                    'key' => $ans['selected_option_key'] ?? null,
                    'lbl' => $ans['selected_option_label'] ?? null,
                    'opts' => json_encode($ans['selected_options'] ?? [], JSON_UNESCAPED_UNICODE),
                    'custom' => (int)($ans['is_custom'] ?? 0),
                    'unk' => (int)($ans['is_unknown'] ?? 0),
                ]);
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function getQuestions(int $ideaId, ?int $cycleId = null): array
    {
        $sql = "SELECT iq.* FROM idea_questions iq WHERE iq.idea_id = :iid";
        $params = ['iid' => $ideaId];
        if ($cycleId !== null) { $sql .= ' AND iq.cycle_id = :cycle'; $params['cycle'] = $cycleId; }
        $sql .= ' ORDER BY iq.sort_order ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($items as &$item) {
            $item['options_json'] = json_decode($item['options_json'] ?? '[]', true);
            if (!is_array($item['options_json'])) $item['options_json'] = [];
            $item['options'] = $item['options_json'];
            $ansStmt = $this->pdo->prepare("SELECT * FROM idea_answers WHERE question_id = :qid ORDER BY created_at DESC LIMIT 1");
            $ansStmt->execute(['qid' => $item['id']]);
            $item['last_answer'] = $ansStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        return $items;
    }

    public function saveAnalysis(int $ideaId, string $type, mixed $result): void
    {
        $inputHash = md5(json_encode($result));
        $promptV = '1.0.0';
        $schemaV = '1.0.0';

        $existing = $this->pdo->prepare("SELECT id FROM idea_analyses WHERE idea_id = :iid AND analysis_type = :type AND input_hash = :hash LIMIT 1");
        $existing->execute(['iid' => $ideaId, 'type' => $type, 'hash' => $inputHash]);
        if ($existing->fetchColumn()) {
            return; // Idempotent: skip duplicate
        }

        $pid = 'ia_' . bin2hex(random_bytes(8));
        $json = is_array($result) ? json_encode($result, JSON_UNESCAPED_UNICODE) : (string)$result;
        $this->pdo->prepare("INSERT INTO idea_analyses (public_id, idea_id, analysis_type, status, result_json, input_hash, prompt_version, schema_version, completed_at, created_at) VALUES (:pid, :iid, :type, 'completed', :json, :hash, :pv, :sv, NOW(), NOW())")
            ->execute(['pid' => $pid, 'iid' => $ideaId, 'type' => $type, 'json' => $json, 'hash' => $inputHash, 'pv' => $promptV, 'sv' => $schemaV]);
    }

    /** @return array<int,array<string,mixed>> */
    public function getAnalyses(int $ideaId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM idea_analyses WHERE idea_id = :iid ORDER BY created_at DESC");
        $stmt->execute(['iid' => $ideaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int,array<string,mixed>> */
    public function getTaskDrafts(int $ideaId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM idea_task_drafts WHERE idea_id = :iid ORDER BY sort_order ASC");
        $stmt->execute(['iid' => $ideaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveTaskDrafts(int $ideaId, array $tasks): void
    {
        $this->pdo->prepare("DELETE FROM idea_task_drafts WHERE idea_id = :iid")->execute(['iid' => $ideaId]);
        $idMap = [];
        foreach ($tasks as $idx => $task) {
            $pid = 'itd_' . bin2hex(random_bytes(8));
            $this->pdo->prepare("INSERT INTO idea_task_drafts (public_id, idea_id, parent_id, title, description, type, stage, priority, acceptance_criteria_json, estimated_duration, sort_order, created_at) VALUES (:pid, :iid, NULL, :title, :desc, :type, :stage, :pri, :ac, :dur, :sort, NOW())")
                ->execute([
                    'pid' => $pid, 'iid' => $ideaId, 'title' => $task['title'] ?? '', 'desc' => $task['description'] ?? '',
                    'type' => $task['type'] ?? 'other', 'stage' => $task['stage'] ?? 'clarification', 'pri' => $task['priority'] ?? 'normal',
                    'ac' => json_encode($task['acceptance_criteria'] ?? [], JSON_UNESCAPED_UNICODE),
                    'dur' => $task['estimated_duration'] ?? null, 'sort' => $idx,
                ]);
            $idMap[$task['temp_id'] ?? (string)$idx] = (int)$this->pdo->lastInsertId();
        }
        // Second pass: set parent_id
        foreach ($tasks as $task) {
            if (!empty($task['parent_temp_id']) && isset($idMap[$task['parent_temp_id']])) {
                $childId = $idMap[$task['temp_id'] ?? ''] ?? null;
                if ($childId) {
                    $this->pdo->prepare("UPDATE idea_task_drafts SET parent_id = :p WHERE id = :id")->execute(['p' => $idMap[$task['parent_temp_id']], 'id' => $childId]);
                }
            }
        }
    }
}
