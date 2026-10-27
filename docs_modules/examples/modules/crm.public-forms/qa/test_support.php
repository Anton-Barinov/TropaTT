<?php

declare(strict_types=1);

namespace Api\Model\Calendar {
    /** Test double for the core calendar serialization hook used by booking. */
    final class CalendarEventRepository
    {
        public static array $lockedUsers = [];

        public function __construct(private readonly \PDO $db) {}

        public function lockConflictUsers(array $userIds): void
        {
            self::$lockedUsers[] = $userIds;
        }
    }
}

namespace Api\System\Library {
    final class Container
    {
        public function __construct(private array $values = []) {}
        public function has(string $key): bool { return array_key_exists($key, $this->values); }
        public function get(string $key): mixed { return $this->values[$key] ?? null; }
    }
}

namespace Api\System\Library\Module {
    final class ModuleExecutionContext
    {
        public function __construct(public int $organizationId, public ?string $actorPublicId = null) {}
    }
}

namespace Api\System\Library\Module\Mcp {
    interface ModuleMcpToolInterface
    {
        public function execute(array $arguments, \Api\System\Library\Module\ModuleExecutionContext $context, \Api\System\Library\Container $container): array;
    }
}

namespace PublicFormsQa {
    use PDO;
    use Module\Crm\PublicForms\Service\PublicFormsService;

    require_once __DIR__ . '/../api/Service/PublicFormSubmissionException.php';
    require_once __DIR__ . '/../api/Service/PublicFormsService.php';
    require_once __DIR__ . '/../api/mcp/ListFormsTool.php';

    function check(bool $condition, string $message): void
    {
        if (!$condition) throw new \RuntimeException('FAIL: ' . $message);
    }

    function database(): PDO
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('CREATE TABLE crm_public_forms (
            id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE NOT NULL, organization_id INTEGER NOT NULL,
            slug TEXT UNIQUE NOT NULL, title TEXT NOT NULL, description TEXT NULL, form_type TEXT NOT NULL,
            fields_schema_json TEXT NOT NULL, consent_text TEXT NOT NULL, consent_version INTEGER NOT NULL DEFAULT 1,
            target_project_public_id TEXT NULL, target_assignee_user_public_id TEXT NULL, calendar_user_public_id TEXT NULL,
            slot_duration_minutes INTEGER NOT NULL DEFAULT 30, buffer_minutes INTEGER NOT NULL DEFAULT 15,
            booking_timezone TEXT NOT NULL DEFAULT "UTC", booking_schedule_json TEXT NULL, is_published INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL, updated_at TEXT NOT NULL
        )');
        $db->exec('CREATE TABLE crm_public_form_submissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE NOT NULL, organization_id INTEGER NOT NULL,
            form_id INTEGER NOT NULL, intake_public_id TEXT NULL, intake_claimed_at TEXT NULL, intake_claim_token TEXT NULL,
            event_public_id TEXT NULL, booking_buffer_minutes INTEGER NOT NULL DEFAULT 0, submitter_ip TEXT NOT NULL,
            submitter_data_json TEXT NOT NULL, consent_agreed INTEGER NOT NULL DEFAULT 1, consent_version INTEGER NOT NULL DEFAULT 1,
            idempotency_key_hash TEXT NULL, idempotency_payload_hash TEXT NULL, created_at TEXT NOT NULL,
            UNIQUE(form_id, idempotency_key_hash)
        )');
        $db->exec('CREATE TABLE crm_public_form_slot_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT, form_id INTEGER NOT NULL, request_ip_hash TEXT NOT NULL, created_at TEXT NOT NULL
        )');
        $db->exec('CREATE TABLE projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, organization_id INTEGER, title TEXT,
            archived_at TEXT NULL, deleted_at TEXT NULL
        )');
        $db->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY, public_id TEXT UNIQUE, full_name TEXT, is_active INTEGER NOT NULL DEFAULT 1, deleted_at TEXT NULL
        )');
        $db->exec('CREATE TABLE organization_memberships (organization_id INTEGER NOT NULL, user_id INTEGER NOT NULL)');
        $db->exec('CREATE TABLE intake_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, organization_id INTEGER NOT NULL,
            external_source TEXT NOT NULL, external_id TEXT NOT NULL, deleted_at TEXT NULL,
            UNIQUE(organization_id, external_source, external_id)
        )');
        $db->exec('CREATE TABLE calendar_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, organization_id INTEGER NOT NULL,
            owner_user_id INTEGER NOT NULL, starts_at TEXT NOT NULL, ends_at TEXT NOT NULL
        )');
        $db->exec('CREATE TABLE calendar_event_attendees (event_id INTEGER NOT NULL, user_id INTEGER NOT NULL)');
        return $db;
    }

    function service(PDO $db, ?callable $intakeCreator = null, ?callable $eventCreator = null, ?callable $afterCommit = null): PublicFormsService
    {
        return new PublicFormsService($db, $intakeCreator, $eventCreator, $afterCommit);
    }

    function fields(): array
    {
        return [
            ['name' => 'full_name', 'label' => 'Имя', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => false],
        ];
    }

    function publishIntake(PublicFormsService $service, string $slug, ?string $project = null, ?string $assignee = null): string
    {
        $id = $service->saveForm(10, $slug, 'Request form', 'intake', fields(), 'I agree to data processing.', $project, $assignee);
        check($service->setFormPublished(10, $id, true), 'new intake form should publish in its owning workspace');
        return $id;
    }

    function submission(string $name, string $key, array $extra = []): array
    {
        return array_merge(['full_name' => $name, 'email' => 'person@example.test', 'consent_agreed' => true, '_idempotency_key' => $key], $extra);
    }

    function createIntakeCreator(PDO $db, ?callable $beforeCreate = null): callable
    {
        return static function (array $input, array $actor) use ($db, $beforeCreate): array {
            if ($beforeCreate !== null) $beforeCreate($input, $actor);
            $existing = $db->prepare('SELECT public_id FROM intake_items WHERE organization_id=:org AND external_source=:source AND external_id=:external LIMIT 1');
            $existing->execute([':org' => $actor['organization_id'], ':source' => $input['external_source'], ':external' => $input['external_id']]);
            $id = $existing->fetchColumn();
            if (is_string($id)) return ['public_id' => $id];
            $id = 'int_' . bin2hex(random_bytes(8));
            $insert = $db->prepare('INSERT INTO intake_items (public_id, organization_id, external_source, external_id) VALUES (:id,:org,:source,:external)');
            $insert->execute([':id' => $id, ':org' => $actor['organization_id'], ':source' => $input['external_source'], ':external' => $input['external_id']]);
            return ['public_id' => $id];
        };
    }

    function countRows(PDO $db, string $table): int
    {
        if (!in_array($table, ['crm_public_forms', 'crm_public_form_submissions', 'crm_public_form_slot_requests', 'intake_items', 'calendar_events'], true)) {
            throw new \InvalidArgumentException('Unknown fixture table.');
        }
        return (int)$db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }
}
