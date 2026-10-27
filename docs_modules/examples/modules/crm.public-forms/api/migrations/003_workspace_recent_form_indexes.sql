ALTER TABLE `crm_public_forms`
    ADD INDEX `idx_pubform_org_id` (`organization_id`, `id`);

ALTER TABLE `crm_public_form_submissions`
    ADD INDEX `idx_pubsub_org_id` (`organization_id`, `id`);
