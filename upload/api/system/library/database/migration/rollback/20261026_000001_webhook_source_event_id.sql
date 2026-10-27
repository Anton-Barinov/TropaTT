DROP INDEX uq_webhook_source_event ON webhook_deliveries;
ALTER TABLE webhook_deliveries DROP COLUMN source_event_id;
