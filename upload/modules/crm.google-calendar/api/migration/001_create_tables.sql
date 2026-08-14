CREATE TABLE IF NOT EXISTS google_calendar_connections (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    public_id VARCHAR(64) NOT NULL UNIQUE,
    user_id INTEGER NOT NULL,
    google_account_email VARCHAR(190) NULL,
    refresh_token_encrypted TEXT NOT NULL,
    access_token_encrypted TEXT NULL,
    access_token_expires_at DATETIME NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    last_error TEXT NULL,
    last_sync_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_google_calendar_connection_user (user_id)
);

CREATE TABLE IF NOT EXISTS google_calendar_sources (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    public_id VARCHAR(64) NOT NULL UNIQUE,
    connection_id INTEGER NOT NULL,
    calendar_id VARCHAR(512) NOT NULL,
    summary VARCHAR(255) NULL,
    timezone VARCHAR(128) NULL,
    direction VARCHAR(32) NOT NULL DEFAULT 'google_to_crm',
    is_enabled INTEGER NOT NULL DEFAULT 1,
    is_primary INTEGER NOT NULL DEFAULT 0,
    sync_token TEXT NULL,
    watch_channel_id VARCHAR(128) NULL,
    watch_resource_id VARCHAR(255) NULL,
    watch_expiration BIGINT NULL,
    last_sync_at DATETIME NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_google_calendar_source (connection_id, calendar_id)
);

CREATE TABLE IF NOT EXISTS google_calendar_events (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    public_id VARCHAR(64) NOT NULL UNIQUE,
    source_id INTEGER NOT NULL,
    google_event_id VARCHAR(512) NOT NULL,
    crm_event_public_id VARCHAR(64) NULL,
    recurring_event_id VARCHAR(512) NULL,
    etag VARCHAR(255) NULL,
    google_updated_at DATETIME NULL,
    is_all_day INTEGER NOT NULL DEFAULT 0,
    all_day_start DATE NULL,
    all_day_end DATE NULL,
    last_synced_at DATETIME NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    last_error TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_google_calendar_event (source_id, google_event_id)
);
