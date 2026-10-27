-- Worker fencing: expired running jobs can be reclaimed without duplicate active workers.
ALTER TABLE module_jira_jobs
    ADD COLUMN lease_token CHAR(64) NULL AFTER status,
    ADD COLUMN lease_until DATETIME NULL AFTER lease_token;
CREATE INDEX idx_jira_job_lease_queue ON module_jira_jobs (status, lease_until, created_at);
