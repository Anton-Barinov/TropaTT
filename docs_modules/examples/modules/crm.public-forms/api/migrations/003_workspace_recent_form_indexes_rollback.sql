ALTER TABLE `crm_public_form_submissions`
    DROP INDEX `idx_pubsub_org_id`;

ALTER TABLE `crm_public_forms`
    DROP INDEX `idx_pubform_org_id`;
