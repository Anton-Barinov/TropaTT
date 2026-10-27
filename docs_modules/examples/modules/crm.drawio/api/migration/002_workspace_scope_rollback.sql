ALTER TABLE module_drawio_diagrams
    DROP INDEX idx_drawio_workspace_owner,
    DROP COLUMN organization_id;
