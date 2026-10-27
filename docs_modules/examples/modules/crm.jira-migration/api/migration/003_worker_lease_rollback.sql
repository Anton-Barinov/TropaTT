-- A downgrade must not leave jobs in `running`: the pre-lease worker cannot reclaim them.
-- Quiesce cron and wait for in-flight PHP workers before running this migration rollback.
UPDATE module_jira_jobs
SET status = 'paused', lease_token = NULL, lease_until = NULL, updated_at = UTC_TIMESTAMP()
WHERE status = 'running';

DROP INDEX idx_jira_job_lease_queue ON module_jira_jobs;
ALTER TABLE module_jira_jobs
    DROP COLUMN lease_until,
    DROP COLUMN lease_token;
