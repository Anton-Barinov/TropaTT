-- Rollback for 002_idempotency_response.sql.

ALTER TABLE `ecommerce_idempotency`
  DROP COLUMN `response_json`,
  DROP COLUMN `updated_at`;
