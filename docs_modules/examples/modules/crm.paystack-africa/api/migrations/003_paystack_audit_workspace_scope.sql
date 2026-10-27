-- Keep legacy audit history where its original workspace can be inferred
-- unambiguously. New audit entries always include workspace_id.
ALTER TABLE `module_paystack_audit_log`
    ADD COLUMN `workspace_id` INT NULL AFTER `id`;

UPDATE `module_paystack_audit_log`
SET `workspace_id` = (
    SELECT MIN(t.`workspace_id`)
    FROM `module_paystack_transactions` t
    WHERE t.`reference` = `module_paystack_audit_log`.`transaction_reference`
)
WHERE (
    SELECT COUNT(DISTINCT t.`workspace_id`)
    FROM `module_paystack_transactions` t
    WHERE t.`reference` = `module_paystack_audit_log`.`transaction_reference`
) = 1;

CREATE INDEX `idx_paystack_audit_workspace_ref`
    ON `module_paystack_audit_log` (`workspace_id`, `transaction_reference`);
