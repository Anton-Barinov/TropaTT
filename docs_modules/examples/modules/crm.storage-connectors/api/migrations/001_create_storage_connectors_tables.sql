-- 001_create_storage_connectors_tables.sql
CREATE TABLE IF NOT EXISTS `crm_storage_configs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `provider_type` varchar(32) NOT NULL, -- 's3', 'nextcloud_webdav'
  `endpoint_url` varchar(512) NOT NULL,
  `bucket_or_path` varchar(255) NOT NULL,
  `region` varchar(64) DEFAULT 'us-east-1',
  `auth_credentials_encrypted` text NOT NULL, -- KeyGuard encrypted JSON (access_key, secret_key or username, app_password)
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sync_policy` varchar(32) NOT NULL DEFAULT 'new_files', -- 'all', 'new_files', 'manual'
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  UNIQUE KEY `idx_storage_config_org` (`organization_id`, `provider_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crm_storage_file_mappings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `file_public_id` varchar(64) NOT NULL,
  `provider_type` varchar(32) NOT NULL,
  `remote_object_key` varchar(512) NOT NULL,
  `file_size_bytes` bigint(20) unsigned NOT NULL,
  `sha256_checksum` varchar(64) NOT NULL,
  `sync_status` varchar(32) NOT NULL DEFAULT 'synced', -- 'pending', 'synced', 'failed'
  `last_error` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_id` (`public_id`),
  KEY `idx_mapping_file` (`file_public_id`),
  KEY `idx_mapping_org` (`organization_id`, `provider_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
