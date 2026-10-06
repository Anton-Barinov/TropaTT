CREATE TABLE IF NOT EXISTS module_zoom_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workspace_id INT NOT NULL DEFAULT 1,
    account_id VARCHAR(128) NULL,
    client_id VARCHAR(128) NULL,
    client_secret TEXT NULL,
    webhook_secret_token VARCHAR(128) NULL,
    auto_record TINYINT(1) NOT NULL DEFAULT 0,
    sync_recordings TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_zoom_workspace (workspace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_zoom_meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(64) NOT NULL UNIQUE,
    workspace_id INT NOT NULL DEFAULT 1,
    crm_calendar_event_public_id VARCHAR(64) NULL,
    crm_project_public_id VARCHAR(64) NULL,
    crm_task_public_id VARCHAR(64) NULL,
    crm_client_public_id VARCHAR(64) NULL,
    zoom_meeting_id BIGINT NOT NULL,
    topic VARCHAR(255) NOT NULL,
    agenda TEXT NULL,
    start_time DATETIME NOT NULL,
    duration_minutes INT NOT NULL DEFAULT 40,
    timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Moscow',
    join_url VARCHAR(1024) NOT NULL,
    start_url TEXT NULL,
    passcode VARCHAR(64) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'waiting',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_zoom_crm_event (crm_calendar_event_public_id),
    INDEX idx_zoom_meeting_id (zoom_meeting_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_zoom_recordings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(64) NOT NULL UNIQUE,
    meeting_id INT NOT NULL,
    zoom_file_id VARCHAR(128) NOT NULL,
    file_type VARCHAR(32) NOT NULL DEFAULT 'MP4',
    file_size_bytes BIGINT DEFAULT 0,
    play_url VARCHAR(1024) NULL,
    download_url TEXT NULL,
    recording_start DATETIME NULL,
    recording_end DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zoom_rec_meeting (meeting_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
