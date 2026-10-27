DROP TABLE IF EXISTS crm_public_form_slot_requests;
ALTER TABLE crm_public_forms DROP INDEX idx_public_form_booking_user, DROP COLUMN booking_schedule_json, DROP COLUMN booking_timezone;
ALTER TABLE crm_public_form_submissions DROP COLUMN booking_buffer_minutes;
