-- Draw.io Module: Initial tables
-- MySQL-only migration

CREATE TABLE IF NOT EXISTS module_drawio_diagrams (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(64) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    page_public_id VARCHAR(64) NULL,
    xml_content LONGTEXT NOT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_drawio_page (page_public_id),
    INDEX idx_drawio_created_by (created_by_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
