CREATE TABLE IF NOT EXISTS module_vk_workspace_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workspace_id INT NOT NULL DEFAULT 1,
    domain VARCHAR(190) NULL,
    bot_token VARCHAR(255) NULL,
    api_token VARCHAR(255) NULL,
    auto_create_call_rooms TINYINT(1) NOT NULL DEFAULT 1,
    notify_vk_teams TINYINT(1) NOT NULL DEFAULT 1,
    calendar_sync_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vk_ws_workspace (workspace_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_vk_workspace_meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(64) NOT NULL UNIQUE,
    workspace_id INT NOT NULL DEFAULT 1,
    crm_calendar_event_public_id VARCHAR(64) NULL,
    crm_project_public_id VARCHAR(64) NULL,
    crm_task_public_id VARCHAR(64) NULL,
    crm_client_public_id VARCHAR(64) NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    call_room_url VARCHAR(512) NOT NULL,
    call_room_id VARCHAR(128) NOT NULL,
    call_pin VARCHAR(32) NULL,
    vk_teams_chat_id VARCHAR(128) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_vk_meeting_crm_event (crm_calendar_event_public_id),
    INDEX idx_vk_meeting_project (crm_project_public_id),
    INDEX idx_vk_meeting_task (crm_task_public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_vk_workspace_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    email VARCHAR(190) NOT NULL,
    name VARCHAR(190) NULL,
    vk_teams_user_id VARCHAR(128) NULL,
    rsvp_status VARCHAR(32) NOT NULL DEFAULT 'needs_action',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vk_participant_meeting (meeting_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
