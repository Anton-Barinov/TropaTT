-- Rollback for 003_paystack_audit_workspace_scope.sql. The added workspace attribution was introduced by this migration.
ALTER TABLE `module_paystack_audit_log`
    DROP INDEX `idx_paystack_audit_workspace_ref`,
    DROP COLUMN `workspace_id`;
