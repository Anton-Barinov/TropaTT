<?php
declare(strict_types=1);
namespace Api\System\Library\Service;

/** Server-side capabilities and data boundary for chat AI, independent of its prompt. */
final class AiChatSafetyPolicy
{
    private const ACTIONS = [
        'crm_task' => ['list','get','get_by_key','board','list_subtasks','list_checklists','list_checklist_items','list_comments','list_dependencies','list_relations','list_tags','create','update','delete','create_subtask','update_subtask','create_checklist','create_checklist_item','update_checklist_item','add_comment'],
        'crm_project' => ['list','get','summary','workload','timeline','list_milestones','list_cycles','create','update'],
        'crm_people' => ['list_users','get_user','list_teams','list_departments'],
        'crm_crm' => ['list_clients','get_client','list_contacts','get_contact','list_companies','get_company','list_counterparties','get_counterparty','create_client','update_client','create_contact','update_contact'],
        'crm_time' => ['list','summary','create_worklog'],
        'crm_knowledge' => ['list_spaces','list_pages','get_space','get_page','search','entity_pages','list_links','create_page','update_page'],
    ];
    private const READ_TOOLS = ['crm_get_current_user','crm_get_profile','crm_search','crm_get_dashboard_summary','crm_get_dashboard_widgets','crm_list_notifications','crm_get_notification_counters'];

    public static function tools(array $tools): array
    {
        $result = [];
        foreach ($tools as $tool) {
            $name = (string)($tool['function']['name'] ?? '');
            if (!isset(self::ACTIONS[$name]) && !in_array($name, self::READ_TOOLS, true)) continue;
            $schema = &$tool['function']['parameters'];
            $schema = (array)$schema;
            $schema['properties'] = (array)($schema['properties'] ?? []);
            if (isset(self::ACTIONS[$name])) {
                $actions = array_values(array_intersect((array)($schema['properties']['action']['enum'] ?? []), self::ACTIONS[$name]));
                if ($actions === []) continue;
                $schema['properties']['action']['enum'] = $actions;
            }
            // Workspace is pinned by the caller; the model cannot select another one.
            unset($schema['properties']['organization_id'], $schema['properties']['organization_public_id']);
            $result[] = $tool;
            unset($schema);
        }
        return $result;
    }

    public static function allowed(string $name, array $args, array $tools): bool
    {
        $encoded = json_encode($args);
        if ($encoded === false || strlen($encoded) > 60000) return false;
        foreach ($tools as $tool) {
            if (($tool['function']['name'] ?? '') !== $name) continue;
            return self::matches($args, (array)$tool['function']['parameters']);
        }
        return false;
    }

    private static function matches(mixed $value, array $schema): bool
    {
        $type = $schema['type'] ?? null;
        if (is_array($type)) {
            foreach ($type as $one) if (self::matches($value, array_replace($schema, ['type' => $one]))) return true;
            return false;
        }
        $valid = match ($type) {
            'object' => is_array($value) && ($value === [] || !array_is_list($value)),
            'array' => is_array($value) && array_is_list($value),
            'string' => is_string($value), 'integer' => is_int($value),
            'number' => is_int($value) || is_float($value), 'boolean' => is_bool($value),
            'null' => $value === null, default => true,
        };
        if (!$valid || (isset($schema['enum']) && !in_array($value, $schema['enum'], true))) return false;
        if ($type === 'object') {
            foreach ($schema['required'] ?? [] as $key) if (!array_key_exists($key, $value)) return false;
            foreach ($value as $key => $item) {
                if (!isset($schema['properties'][$key])) { if (($schema['additionalProperties'] ?? false) === false) return false; }
                elseif (!self::matches($item, $schema['properties'][$key])) return false;
            }
        }
        if ($type === 'array') {
            if (count($value) > (int)($schema['maxItems'] ?? 200)) return false;
            foreach ($value as $item) if (isset($schema['items']) && !self::matches($item, $schema['items'])) return false;
        }
        return !is_string($value) || strlen($value) <= (int)($schema['maxLength'] ?? 60000);
    }

    public static function isRead(string $name, array $args): bool
    {
        if (in_array($name, self::READ_TOOLS, true)) return true;
        return isset(self::ACTIONS[$name]) && in_array($args['action'] ?? '', self::ACTIONS[$name], true)
            && (preg_match('/^(get|list|search)(_|$)/', (string)$args['action']) || in_array($args['action'], ['board','summary','workload','timeline','entity_pages'], true));
    }

    /** Defense in depth. Explicitly excluded knowledge is never included in provider data. */
    public static function minimize(mixed $value, string $key = ''): mixed
    {
        if (preg_match('/password|passwd|secret|token|credential|authorization|cookie|backup_codes|private_key|api_key|access_key/i', $key)) return '[redacted]';
        if (in_array(strtolower($key), ['email', 'phone', 'address_legal', 'address_postal', 'bank_account', 'tax_inn'], true)) return '[personal data omitted]';
        if (is_array($value)) {
            $metadata = $value['content_json'] ?? [];
            if (is_string($metadata)) $metadata = json_decode($metadata, true) ?: [];
            if (!empty($value['ai_excluded']) || (is_array($metadata) && !empty($metadata['ai_excluded']))
                || preg_match('/\[no-ai\]|\[confidential\]/iu', (string)($value['title'] ?? ''))) return ['excluded_from_ai' => true];
            foreach ($value as $childKey => &$item) $item = self::minimize($item, (string)$childKey);
            unset($item);
            return $value;
        }
        if (!is_string($value)) return $value;
        $decoded = json_decode($value, true);
        if (is_array($decoded)) return json_encode(self::minimize($decoded), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        // Rich-text KB content may wrap the actual value in <strong>/<code>.
        // Providers receive plain text; markup must not split a secret label/value.
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('/<\/?[a-z][^>]*>/i', $value)) {
            $value = preg_replace('/<br\s*\/?>|<\/(?:p|div|li|tr|h[1-6])>/i', "\n", $value) ?? $value;
            $value = strip_tags($value);
        }
        $value = preg_replace('/-----BEGIN [^-]*(?:PRIVATE KEY|CERTIFICATE)-----[\s\S]*?-----END [^-]+-----/u', '[redacted]', $value) ?? $value;
        $value = preg_replace('/\b(?:apk_|sk-proj-|sk-)[A-Za-z0-9_-]{16,}|\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+/', '[redacted]', $value) ?? $value;
        // HTTP authorization credentials pasted verbatim into a message.
        $value = preg_replace('/(?<![\p{L}\p{N}])Bearer\s+[\p{L}\p{N}._~+\/=-]{12,}/iu', 'Bearer [redacted]', $value) ?? $value;
        // Labelled secrets. \b treats "_" as a word character, so compound names such as
        // client_secret, password_hash or my-token never matched it; letter/number
        // boundaries do, while "secretary"/"tokenizer" stay intact.
        $value = preg_replace('/((?<![\p{L}\p{N}])(?:password|passwd|secret|credential|token|api[_ -]?key|api[_ -]?secret|access[_ -]?token|refresh[_ -]?token|authorization|пароль|токен|секрет)(?![\p{L}\p{N}])["\'*`\s]*[:=]["\'*`\s]*)([^\r\n<"]+)/iu', '$1[redacted]', $value) ?? $value;
        // "key:"/"ключ:" alone is a weak label, so it is only redacted when the value is an
        // opaque token; prose such as "the key: we must ship tomorrow" is preserved.
        $value = (string)preg_replace_callback(
            '/(?<![\p{L}\p{N}_\-])(?:key|ключ)(?![\p{L}\p{N}_\-])["\'*`\s]*[:=]["\'*`\s]*([^\s\r\n<"]{8,})/iu',
            static function (array $match): string {
                $token = $match[1];
                if (!preg_match('/[0-9_\-.\/+=]/', $token)) {
                    return $match[0];
                }
                return substr($match[0], 0, strlen($match[0]) - strlen($token)) . '[redacted]';
            },
            $value
        );
        $value = preg_replace('~(https?://)[^/\s:@]+:[^/\s@]+@~i', '$1[redacted]@', $value) ?? $value;
        return $value;
    }
}
