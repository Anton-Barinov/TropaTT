-- Per-user Google OAuth credentials. Each CRM user connects their own
-- Google Cloud OAuth client; the client_id/client_secret are stored
-- encrypted (EncryptionService) and are never returned by the API.
CREATE TABLE IF NOT EXISTS google_calendar_credentials (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    public_id VARCHAR(64) NOT NULL UNIQUE,
    user_id INTEGER NOT NULL,
    client_id_encrypted TEXT NOT NULL,
    client_secret_encrypted TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_google_calendar_credentials_user (user_id)
);
