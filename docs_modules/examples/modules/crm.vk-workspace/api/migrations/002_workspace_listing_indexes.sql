ALTER TABLE module_vk_workspace_meetings
    ADD COLUMN provider_verified TINYINT(1) NOT NULL DEFAULT 0;

CREATE INDEX idx_vk_meetings_workspace_start
    ON module_vk_workspace_meetings (workspace_id, provider_verified, starts_at);
