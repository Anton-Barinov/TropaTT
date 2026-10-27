-- Preserve any existing duplicates before choosing one canonical mapping.
-- Remote objects are never deleted. The archive makes this data cleanup
-- reversible if the unique-index migration needs to be rolled back.
CREATE TABLE IF NOT EXISTS `crm_storage_file_mappings_dedup_archive` LIKE `crm_storage_file_mappings`;

INSERT IGNORE INTO `crm_storage_file_mappings_dedup_archive`
SELECT older.*
FROM crm_storage_file_mappings AS older
INNER JOIN crm_storage_file_mappings AS newer
  ON newer.organization_id = older.organization_id
 AND newer.file_public_id = older.file_public_id
 AND (
   (newer.sync_status = 'synced' AND older.sync_status <> 'synced')
   OR (newer.sync_status = older.sync_status AND newer.id < older.id)
 );

-- Keep a synced mapping when present, otherwise the oldest mapping.
DELETE older
FROM crm_storage_file_mappings AS older
INNER JOIN crm_storage_file_mappings AS newer
  ON newer.organization_id = older.organization_id
 AND newer.file_public_id = older.file_public_id
 AND (
   (newer.sync_status = 'synced' AND older.sync_status <> 'synced')
   OR (newer.sync_status = older.sync_status AND newer.id < older.id)
 );

ALTER TABLE crm_storage_file_mappings
  ADD UNIQUE KEY `uniq_mapping_org_file` (`organization_id`, `file_public_id`);
