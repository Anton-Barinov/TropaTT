-- Compatibility migration: last_request_at is part of the current baseline
-- (001_create_tables.sql). It must not be dropped when rolling back this
-- migration, otherwise an existing installation becomes incompatible with the
-- client. The migration is intentionally irreversible.
SELECT 1;
