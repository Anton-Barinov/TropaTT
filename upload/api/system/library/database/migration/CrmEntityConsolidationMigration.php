<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * CRM Entity Consolidation Migration
 *
 * Объединяет разрозненные сущности в логичную CRM-структуру:
 * 1. clients + companies → counterparties (единый справочник контрагентов)
 * 2. contacts → обновлены (counterparty_id + role вместо client_id + company_id)
 * 3. teams + departments → teams с иерархией (type + parent_id)
 */
final class CrmEntityConsolidationMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260518_000001_crm_entity_consolidation';
    }

    public function description(): string
    {
        return 'Consolidate clients+companies into counterparties, add contact roles, unify teams+departments with hierarchy';
    }

    public function up(PDO $pdo, string $driver): void
    {
        // 1. Создать таблицу counterparties
        $this->createCounterpartiesTable($pdo, $driver);

        // 2. Мигрировать данные из companies в counterparties
        $this->migrateCompaniesToCounterparties($pdo, $driver);

        // 3. Мигрировать данные из clients в counterparties
        $this->migrateClientsToCounterparties($pdo, $driver);

        // 4. Обновить contacts: добавить counterparty_id, role, is_primary
        $this->updateContactsTable($pdo, $driver);

        // 5. Мигрировать связи contacts (client_id, company_id → counterparty_id)
        $this->migrateContactRelations($pdo, $driver);

        // 6. Обновить teams: добавить type, parent_id, code
        $this->updateTeamsTable($pdo, $driver);

        // 7. Мигрировать departments в teams
        $this->migrateDepartmentsToTeams($pdo, $driver);

        // 8. Создать индексы
        $this->createIndexes($pdo, $driver);
    }

    private function createCounterpartiesTable(PDO $pdo, string $driver): void
    {
        if ($this->tableExists($pdo, $driver, 'counterparties')) {
            return;
        }

        $sql = match ($driver) {
            'mysql' => "CREATE TABLE counterparties (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                public_id VARCHAR(64) UNIQUE NOT NULL,
                created_by_user_id INTEGER NULL,
                title VARCHAR(255) NOT NULL,
                counterparty_type VARCHAR(32) NOT NULL DEFAULT 'organization',
                status VARCHAR(64) NOT NULL DEFAULT 'active',
                legal_name VARCHAR(255) NULL,
                person_last_name VARCHAR(120) NULL,
                person_first_name VARCHAR(120) NULL,
                person_middle_name VARCHAR(120) NULL,
                person_birth_date DATE NULL,
                tax_inn VARCHAR(12) NULL,
                tax_kpp VARCHAR(9) NULL,
                tax_ogrn VARCHAR(13) NULL,
                tax_ogrnip VARCHAR(15) NULL,
                bank_account VARCHAR(34) NULL,
                bank_name VARCHAR(255) NULL,
                bank_bik VARCHAR(9) NULL,
                bank_corr_account VARCHAR(34) NULL,
                website VARCHAR(2048) NULL,
                messenger VARCHAR(190) NULL,
                address_legal TEXT NULL,
                address_postal TEXT NULL,
                notes TEXT NULL,
                extra_attributes TEXT NULL,
                email VARCHAR(190) NULL,
                phone VARCHAR(64) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'pgsql' => "CREATE TABLE counterparties (
                id SERIAL PRIMARY KEY,
                public_id VARCHAR(64) UNIQUE NOT NULL,
                created_by_user_id INTEGER NULL,
                title VARCHAR(255) NOT NULL,
                counterparty_type VARCHAR(32) NOT NULL DEFAULT 'organization',
                status VARCHAR(64) NOT NULL DEFAULT 'active',
                legal_name VARCHAR(255) NULL,
                person_last_name VARCHAR(120) NULL,
                person_first_name VARCHAR(120) NULL,
                person_middle_name VARCHAR(120) NULL,
                person_birth_date DATE NULL,
                tax_inn VARCHAR(12) NULL,
                tax_kpp VARCHAR(9) NULL,
                tax_ogrn VARCHAR(13) NULL,
                tax_ogrnip VARCHAR(15) NULL,
                bank_account VARCHAR(34) NULL,
                bank_name VARCHAR(255) NULL,
                bank_bik VARCHAR(9) NULL,
                bank_corr_account VARCHAR(34) NULL,
                website VARCHAR(2048) NULL,
                messenger VARCHAR(190) NULL,
                address_legal TEXT NULL,
                address_postal TEXT NULL,
                notes TEXT NULL,
                extra_attributes TEXT NULL,
                email VARCHAR(190) NULL,
                phone VARCHAR(64) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )",
            'sqlsrv' => "CREATE TABLE counterparties (
                id INT IDENTITY(1,1) PRIMARY KEY,
                public_id VARCHAR(64) UNIQUE NOT NULL,
                created_by_user_id INT NULL,
                title VARCHAR(255) NOT NULL,
                counterparty_type VARCHAR(32) NOT NULL DEFAULT 'organization',
                status VARCHAR(64) NOT NULL DEFAULT 'active',
                legal_name VARCHAR(255) NULL,
                person_last_name VARCHAR(120) NULL,
                person_first_name VARCHAR(120) NULL,
                person_middle_name VARCHAR(120) NULL,
                person_birth_date DATE NULL,
                tax_inn VARCHAR(12) NULL,
                tax_kpp VARCHAR(9) NULL,
                tax_ogrn VARCHAR(13) NULL,
                tax_ogrnip VARCHAR(15) NULL,
                bank_account VARCHAR(34) NULL,
                bank_name VARCHAR(255) NULL,
                bank_bik VARCHAR(9) NULL,
                bank_corr_account VARCHAR(34) NULL,
                website VARCHAR(2048) NULL,
                messenger VARCHAR(190) NULL,
                address_legal TEXT NULL,
                address_postal TEXT NULL,
                notes TEXT NULL,
                extra_attributes TEXT NULL,
                email VARCHAR(190) NULL,
                phone VARCHAR(64) NULL,
                created_at DATETIME NOT NULL DEFAULT GETDATE(),
                updated_at DATETIME NOT NULL DEFAULT GETDATE()
            )",
            default => "CREATE TABLE counterparties (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                public_id VARCHAR(64) UNIQUE NOT NULL,
                created_by_user_id INTEGER NULL,
                title VARCHAR(255) NOT NULL,
                counterparty_type VARCHAR(32) NOT NULL DEFAULT 'organization',
                status VARCHAR(64) NOT NULL DEFAULT 'active',
                legal_name VARCHAR(255) NULL,
                person_last_name VARCHAR(120) NULL,
                person_first_name VARCHAR(120) NULL,
                person_middle_name VARCHAR(120) NULL,
                person_birth_date DATE NULL,
                tax_inn VARCHAR(12) NULL,
                tax_kpp VARCHAR(9) NULL,
                tax_ogrn VARCHAR(13) NULL,
                tax_ogrnip VARCHAR(15) NULL,
                bank_account VARCHAR(34) NULL,
                bank_name VARCHAR(255) NULL,
                bank_bik VARCHAR(9) NULL,
                bank_corr_account VARCHAR(34) NULL,
                website VARCHAR(2048) NULL,
                messenger VARCHAR(190) NULL,
                address_legal TEXT NULL,
                address_postal TEXT NULL,
                notes TEXT NULL,
                extra_attributes TEXT NULL,
                email VARCHAR(190) NULL,
                phone VARCHAR(64) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )",
        };

        $pdo->exec($sql);
    }

    private function migrateCompaniesToCounterparties(PDO $pdo, string $driver): void
    {
        if (!$this->tableExists($pdo, $driver, 'companies')) {
            return;
        }

        $stmt = $pdo->query('SELECT public_id, created_by_user_id, title, status, email, created_at, updated_at FROM companies');
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($companies)) {
            return;
        }

        $insertSql = $driver === 'mysql'
            ? "INSERT IGNORE INTO counterparties (
                public_id, created_by_user_id, title, counterparty_type, status,
                email, created_at, updated_at
            ) VALUES (
                :public_id, :created_by_user_id, :title, 'organization', COALESCE(:status, 'active'),
                :email, :created_at, :updated_at
            )"
            : "INSERT INTO counterparties (
                public_id, created_by_user_id, title, counterparty_type, status,
                email, created_at, updated_at
            ) SELECT :public_id, :created_by_user_id, :title, 'organization', COALESCE(:status, 'active'),
                :email, :created_at, :updated_at
            WHERE NOT EXISTS (SELECT 1 FROM counterparties WHERE public_id = :public_id_check)";

        $insertStmt = $pdo->prepare($insertSql);

        foreach ($companies as $company) {
            $params = [
                'public_id' => $company['public_id'],
                'created_by_user_id' => $company['created_by_user_id'] ?? null,
                'title' => $company['title'],
                'status' => $company['status'] ?? 'active',
                'email' => $company['email'] ?? null,
                'created_at' => $company['created_at'],
                'updated_at' => $company['updated_at'],
            ];
            if ($driver !== 'mysql') {
                $params['public_id_check'] = $company['public_id'];
            }
            $insertStmt->execute($params);
        }
    }

    private function migrateClientsToCounterparties(PDO $pdo, string $driver): void
    {
        if (!$this->tableExists($pdo, $driver, 'clients')) {
            return;
        }

        $stmt = $pdo->query('SELECT public_id, created_by_user_id, title, client_type, status, legal_name, person_last_name, person_first_name, person_middle_name, person_birth_date, tax_inn, tax_kpp, tax_ogrn, tax_ogrnip, bank_account, bank_name, bank_bik, bank_corr_account, website, messenger, address_legal, address_postal, notes, extra_attributes, email, phone, created_at, updated_at FROM clients');
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($clients)) {
            return;
        }

        $insertSql = $driver === 'mysql'
            ? "INSERT IGNORE INTO counterparties (
                public_id, created_by_user_id, title, counterparty_type, status,
                legal_name, person_last_name, person_first_name, person_middle_name, person_birth_date,
                tax_inn, tax_kpp, tax_ogrn, tax_ogrnip,
                bank_account, bank_name, bank_bik, bank_corr_account,
                website, messenger, address_legal, address_postal,
                notes, extra_attributes, email, phone,
                created_at, updated_at
            ) VALUES (
                :public_id, :created_by_user_id, :title, :counterparty_type, COALESCE(:status, 'active'),
                :legal_name, :person_last_name, :person_first_name, :person_middle_name, :person_birth_date,
                :tax_inn, :tax_kpp, :tax_ogrn, :tax_ogrnip,
                :bank_account, :bank_name, :bank_bik, :bank_corr_account,
                :website, :messenger, :address_legal, :address_postal,
                :notes, :extra_attributes, :email, :phone,
                :created_at, :updated_at
            )"
            : "INSERT INTO counterparties (
                public_id, created_by_user_id, title, counterparty_type, status,
                legal_name, person_last_name, person_first_name, person_middle_name, person_birth_date,
                tax_inn, tax_kpp, tax_ogrn, tax_ogrnip,
                bank_account, bank_name, bank_bik, bank_corr_account,
                website, messenger, address_legal, address_postal,
                notes, extra_attributes, email, phone,
                created_at, updated_at
            ) SELECT :public_id, :created_by_user_id, :title, :counterparty_type, COALESCE(:status, 'active'),
                :legal_name, :person_last_name, :person_first_name, :person_middle_name, :person_birth_date,
                :tax_inn, :tax_kpp, :tax_ogrn, :tax_ogrnip,
                :bank_account, :bank_name, :bank_bik, :bank_corr_account,
                :website, :messenger, :address_legal, :address_postal,
                :notes, :extra_attributes, :email, :phone,
                :created_at, :updated_at
            WHERE NOT EXISTS (SELECT 1 FROM counterparties WHERE public_id = :public_id_check)";

        $insertStmt = $pdo->prepare($insertSql);

        foreach ($clients as $client) {
            $clientType = $client['client_type'] ?? 'organization';
            $counterpartyType = match ($clientType) {
                'individual' => 'individual',
                'sole_proprietor' => 'sole_proprietor',
                default => 'organization',
            };

            $params = [
                'public_id' => $client['public_id'],
                'created_by_user_id' => $client['created_by_user_id'] ?? null,
                'title' => $client['title'],
                'counterparty_type' => $counterpartyType,
                'status' => $client['status'] ?? 'active',
                'legal_name' => $client['legal_name'] ?? null,
                'person_last_name' => $client['person_last_name'] ?? null,
                'person_first_name' => $client['person_first_name'] ?? null,
                'person_middle_name' => $client['person_middle_name'] ?? null,
                'person_birth_date' => $client['person_birth_date'] ?? null,
                'tax_inn' => $client['tax_inn'] ?? null,
                'tax_kpp' => $client['tax_kpp'] ?? null,
                'tax_ogrn' => $client['tax_ogrn'] ?? null,
                'tax_ogrnip' => $client['tax_ogrnip'] ?? null,
                'bank_account' => $client['bank_account'] ?? null,
                'bank_name' => $client['bank_name'] ?? null,
                'bank_bik' => $client['bank_bik'] ?? null,
                'bank_corr_account' => $client['bank_corr_account'] ?? null,
                'website' => $client['website'] ?? null,
                'messenger' => $client['messenger'] ?? null,
                'address_legal' => $client['address_legal'] ?? null,
                'address_postal' => $client['address_postal'] ?? null,
                'notes' => $client['notes'] ?? null,
                'extra_attributes' => $client['extra_attributes'] ?? null,
                'email' => $client['email'] ?? null,
                'phone' => $client['phone'] ?? null,
                'created_at' => $client['created_at'],
                'updated_at' => $client['updated_at'],
            ];
            if ($driver !== 'mysql') {
                $params['public_id_check'] = $client['public_id'];
            }
            $insertStmt->execute($params);
        }
    }

    private function updateContactsTable(PDO $pdo, string $driver): void
    {
        if (!$this->tableExists($pdo, $driver, 'contacts')) {
            return;
        }

        $this->ensureColumn($pdo, $driver, 'contacts', 'counterparty_id', 'INTEGER NULL');
        $this->ensureColumn($pdo, $driver, 'contacts', 'role', 'VARCHAR(64) NULL');
        $this->ensureColumn($pdo, $driver, 'contacts', 'is_primary', 'TINYINT(1) DEFAULT 0');
    }

    private function migrateContactRelations(PDO $pdo, string $driver): void
    {
        if (!$this->tableExists($pdo, $driver, 'contacts')) {
            return;
        }

        // Обновить contacts где есть client_id → counterparty_id
        $pdo->exec("
            UPDATE contacts c
            INNER JOIN clients cl ON c.client_id = cl.id
            SET c.counterparty_id = (
                SELECT cp.id FROM counterparties cp WHERE cp.public_id = cl.public_id LIMIT 1
            ),
            c.role = COALESCE(c.role, 'contact')
            WHERE c.client_id IS NOT NULL AND c.counterparty_id IS NULL
        ");

        // Обновить contacts где есть company_id → counterparty_id
        $pdo->exec("
            UPDATE contacts c
            INNER JOIN companies co ON c.company_id = co.id
            SET c.counterparty_id = (
                SELECT cp.id FROM counterparties cp WHERE cp.public_id = co.public_id LIMIT 1
            ),
            c.role = COALESCE(c.role, 'contact')
            WHERE c.company_id IS NOT NULL AND c.counterparty_id IS NULL
        ");
    }

    private function updateTeamsTable(PDO $pdo, string $driver): void
    {
        if (!$this->tableExists($pdo, $driver, 'teams')) {
            return;
        }

        $this->ensureColumn($pdo, $driver, 'teams', 'team_type', 'VARCHAR(32) NULL DEFAULT \'team\'');
        $this->ensureColumn($pdo, $driver, 'teams', 'parent_id', 'INTEGER NULL');
        $this->ensureColumn($pdo, $driver, 'teams', 'code', 'VARCHAR(64) NULL');
    }

    private function migrateDepartmentsToTeams(PDO $pdo, string $driver): void
    {
        if (!$this->tableExists($pdo, $driver, 'departments')) {
            return;
        }

        $stmt = $pdo->query('SELECT public_id, title, code, manager_user_id, created_by_user_id, created_at, updated_at FROM departments');
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($departments)) {
            return;
        }

        $insertSql = $driver === 'mysql'
            ? "INSERT IGNORE INTO teams (
                public_id, title, team_type, code, manager_user_id, created_by_user_id,
                member_user_ids, created_at, updated_at
            ) VALUES (
                :public_id, :title, 'department', :code, :manager_user_id, :created_by_user_id,
                NULL, :created_at, :updated_at
            )"
            : "INSERT INTO teams (
                public_id, title, team_type, code, manager_user_id, created_by_user_id,
                member_user_ids, created_at, updated_at
            ) SELECT :public_id, :title, 'department', :code, :manager_user_id, :created_by_user_id,
                NULL, :created_at, :updated_at
            WHERE NOT EXISTS (SELECT 1 FROM teams WHERE public_id = :public_id_check)";

        $insertStmt = $pdo->prepare($insertSql);

        foreach ($departments as $dept) {
            $params = [
                'public_id' => $dept['public_id'],
                'title' => $dept['title'],
                'code' => $dept['code'] ?? null,
                'manager_user_id' => $dept['manager_user_id'] ?? null,
                'created_by_user_id' => $dept['created_by_user_id'] ?? null,
                'created_at' => $dept['created_at'],
                'updated_at' => $dept['updated_at'],
            ];
            if ($driver !== 'mysql') {
                $params['public_id_check'] = $dept['public_id'];
            }
            $insertStmt->execute($params);
        }
    }

    private function createIndexes(PDO $pdo, string $driver): void
    {
        $indexes = [
            ['counterparties', 'idx_counterparties_type', 'counterparty_type'],
            ['counterparties', 'idx_counterparties_status', 'status'],
            ['counterparties', 'idx_counterparties_created_by', 'created_by_user_id'],
            ['counterparties', 'idx_counterparties_tax_inn', 'tax_inn'],
            ['contacts', 'idx_contacts_counterparty', 'counterparty_id'],
            ['teams', 'idx_teams_type', 'team_type'],
            ['teams', 'idx_teams_parent', 'parent_id'],
        ];

        foreach ($indexes as [$table, $index, $columns]) {
            IndexHelper::createIndexIfNotExists($pdo, $table, $index, $columns);
        }
    }

    private function ensureColumn(PDO $pdo, string $driver, string $table, string $column, string $definition): void
    {
        if ($this->columnExists($pdo, $driver, $table, $column)) {
            return;
        }

        $sql = match ($driver) {
            'mysql', 'pgsql', 'sqlite' => sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition),
            'sqlsrv' => sprintf('ALTER TABLE %s ADD %s %s', $table, $column, $definition),
            default => sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition),
        };

        $pdo->exec($sql);
    }

    private function columnExists(PDO $pdo, string $driver, string $table, string $column): bool
    {
        try {
            return match ($driver) {
                'mysql' => $this->mysqlColumnExists($pdo, $table, $column),
                'pgsql' => $this->pgsqlColumnExists($pdo, $table, $column),
                'sqlsrv' => $this->sqlsrvColumnExists($pdo, $table, $column),
                default => $this->sqliteColumnExists($pdo, $table, $column),
            };
        } catch (\Throwable $e) {
            error_log('[CrmEntityConsolidationMigration::columnExists] ' . $e->getMessage());
            return false;
        }
    }

    private function tableExists(PDO $pdo, string $driver, string $table): bool
    {
        try {
            return match ($driver) {
                'mysql' => $this->mysqlTableExists($pdo, $table),
                'pgsql' => $this->pgsqlTableExists($pdo, $table),
                'sqlsrv' => $this->sqlsrvTableExists($pdo, $table),
                default => $this->sqliteTableExists($pdo, $table),
            };
        } catch (\Throwable $e) {
            error_log('[CrmEntityConsolidationMigration::tableExists] ' . $e->getMessage());
            return false;
        }
    }

    private function mysqlTableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name LIMIT 1');
        $stmt->execute(['table_name' => $table]);
        return $stmt->fetchColumn() !== false;
    }

    private function pgsqlTableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = :table_name LIMIT 1');
        $stmt->execute(['table_name' => $table]);
        return $stmt->fetchColumn() !== false;
    }

    private function sqliteTableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name = :table_name LIMIT 1");
        $stmt->execute(['table_name' => $table]);
        return $stmt->fetchColumn() !== false;
    }

    private function sqlsrvTableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM sys.tables WHERE name = :table_name');
        $stmt->execute(['table_name' => $table]);
        return $stmt->fetchColumn() !== false;
    }

    private function mysqlColumnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name LIMIT 1');
        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);
        return $stmt->fetchColumn() !== false;
    }

    private function pgsqlColumnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = :table_name AND column_name = :column_name LIMIT 1');
        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);
        return $stmt->fetchColumn() !== false;
    }

    private function sqliteColumnExists(PDO $pdo, string $table, string $column): bool
    {
        $rows = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll() ?: [];
        foreach ($rows as $row) {
            if ((string)($row['name'] ?? '') === $column) {
                return true;
            }
        }
        return false;
    }

    private function sqlsrvColumnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID(:table_name) AND name = :column_name');
        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);
        return $stmt->fetchColumn() !== false;
    }
}
