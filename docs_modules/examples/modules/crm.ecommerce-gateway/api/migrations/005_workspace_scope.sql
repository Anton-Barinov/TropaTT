-- Legacy rows intentionally remain unassigned until an administrator maps
-- them to a workspace. Never guess tenant ownership during an upgrade.
ALTER TABLE `ecommerce_stores`
  ADD COLUMN `organization_id` INT UNSIGNED NULL AFTER `public_id`,
  ADD KEY `idx_ecommerce_stores_org_status` (`organization_id`, `status`, `deleted_at`);
