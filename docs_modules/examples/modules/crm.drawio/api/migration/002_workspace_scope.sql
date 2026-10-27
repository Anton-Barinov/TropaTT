-- Legacy draw.io diagrams were global in module 1.0.0. Preserve them in the
-- existing default workspace; they had no reliable workspace ownership metadata.
SET @drawio_default_workspace_id := (SELECT id FROM organizations ORDER BY id ASC LIMIT 1);

ALTER TABLE module_drawio_diagrams
    ADD COLUMN organization_id BIGINT UNSIGNED NULL AFTER public_id;

UPDATE module_drawio_diagrams
SET organization_id = @drawio_default_workspace_id
WHERE organization_id IS NULL;

ALTER TABLE module_drawio_diagrams
    MODIFY COLUMN organization_id BIGINT UNSIGNED NOT NULL,
    ADD INDEX idx_drawio_workspace_owner (organization_id, created_by_user_id);
