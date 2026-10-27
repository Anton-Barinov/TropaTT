ALTER TABLE module_zoom_meetings
    ADD COLUMN provider_verified TINYINT(1) NOT NULL DEFAULT 0;

CREATE INDEX idx_zoom_meetings_workspace_start
    ON module_zoom_meetings (workspace_id, provider_verified, start_time);
