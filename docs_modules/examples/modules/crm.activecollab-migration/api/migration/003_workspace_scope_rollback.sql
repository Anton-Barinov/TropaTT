-- Irreversible data-preserving rollback: retain organization_id columns and assignments to avoid losing isolation metadata. Disable this migration only after restoring a verified pre-migration backup.
SELECT 1;
