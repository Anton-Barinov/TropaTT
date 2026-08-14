CREATE TABLE IF NOT EXISTS yandex_calendar_connections (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    public_id VARCHAR(64) NOT NULL UNIQUE,
    user_id INTEGER NOT NULL,
    account_email VARCHAR(190) NOT NULL,
    caldav_username VARCHAR(190) NOT NULL,
    credential_encrypted TEXT NOT NULL,
    auth_mode VARCHAR(32) NOT NULL DEFAULT 'app_password',
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    last_error TEXT NULL,
    last_sync_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_yandex_calendar_connection_user (user_id)
);

CREATE TABLE IF NOT EXISTS yandex_calendar_sources (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    public_id VARCHAR(64) NOT NULL UNIQUE,
    connection_id INTEGER NOT NULL,
    -- Keep the unique composite index below within InnoDB utf8mb4 limits.
    calendar_href VARCHAR(512) NOT NULL,
    display_name VARCHAR(255) NULL,
    timezone VARCHAR(128) NULL,
    direction VARCHAR(32) NOT NULL DEFAULT 'yandex_to_crm',
    is_enabled INTEGER NOT NULL DEFAULT 1,
    is_primary INTEGER NOT NULL DEFAULT 0,
    ctag VARCHAR(255) NULL,
    last_sync_at DATETIME NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_yandex_calendar_source (connection_id, calendar_href)
);

CREATE TABLE IF NOT EXISTS yandex_calendar_events (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    public_id VARCHAR(64) NOT NULL UNIQUE,
    source_id INTEGER NOT NULL,
    external_uid VARCHAR(512) NOT NULL,
    recurrence_id VARCHAR(128) NULL,
    event_href VARCHAR(1024) NULL,
    etag VARCHAR(255) NULL,
    recurrence_rule TEXT NULL,
    event_start DATETIME NULL,
    event_end DATETIME NULL,
    is_all_day INTEGER NOT NULL DEFAULT 0,
    all_day_start DATE NULL,
    all_day_end DATE NULL,
    crm_event_public_id VARCHAR(64) NULL,
    last_synced_at DATETIME NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    last_error TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_yandex_calendar_event (source_id, external_uid, recurrence_id)
);
