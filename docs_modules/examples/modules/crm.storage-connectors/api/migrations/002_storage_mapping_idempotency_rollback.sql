ALTER TABLE crm_storage_file_mappings
  DROP INDEX `uniq_mapping_org_file`;

-- Restore pre-migration duplicate mapping rows only after the unique key is
-- removed. Provider-side objects were never deleted and remain untouched.
INSERT IGNORE INTO crm_storage_file_mappings
SELECT * FROM crm_storage_file_mappings_dedup_archive;

DROP TABLE crm_storage_file_mappings_dedup_archive;
