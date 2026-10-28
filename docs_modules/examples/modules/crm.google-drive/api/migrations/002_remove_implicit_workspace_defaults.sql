-- Existing installations already applied 001. Remove workspace and active-state
-- defaults so future rows cannot silently attach to workspace 1 or auto-enable.
ALTER TABLE `module_google_drive_settings`
    MODIFY `workspace_id` INT NOT NULL,
    MODIFY `auto_create_project_folders` TINYINT(1) NOT NULL DEFAULT 0,
    MODIFY `is_active` TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE `module_google_drive_files`
    MODIFY `workspace_id` INT NOT NULL,
    MODIFY `status` VARCHAR(32) NOT NULL DEFAULT 'not_configured';
