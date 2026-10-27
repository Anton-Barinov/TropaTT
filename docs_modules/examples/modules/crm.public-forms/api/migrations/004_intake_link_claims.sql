ALTER TABLE `crm_public_form_submissions`
    ADD COLUMN `intake_claimed_at` DATETIME NULL AFTER `intake_public_id`,
    ADD COLUMN `intake_claim_token` CHAR(32) NULL AFTER `intake_claimed_at`;
