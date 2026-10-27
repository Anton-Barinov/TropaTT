-- crm.ecommerce-gateway: idempotency response snapshot.
--
-- On an idempotent repeat the store must receive exactly the ids the first
-- request produced (E-COM-01 §8.2: "та же data.external_id,
-- intake_item_public_id"). Re-deriving them from linked rows would be fragile
-- once outbound sync (E-COM-04/10) starts writing its own records, so the
-- ingestion result of the first request is snapshotted here.

ALTER TABLE `ecommerce_idempotency`
  ADD COLUMN `response_json` MEDIUMTEXT NULL AFTER `entity_public_id`,
  ADD COLUMN `updated_at` DATETIME NULL AFTER `created_at`;
