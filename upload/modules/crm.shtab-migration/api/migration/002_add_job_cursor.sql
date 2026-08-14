ALTER TABLE `module_shtab_jobs`
  ADD COLUMN `last_source_cursor` JSON NULL AFTER `summary_json`;
