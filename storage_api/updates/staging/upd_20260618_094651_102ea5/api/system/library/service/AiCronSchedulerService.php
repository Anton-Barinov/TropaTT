<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\System\Library\Logger\JsonLogger;

final class AiCronSchedulerService
{
    public function __construct(
        private readonly AiJobService $jobs,
        private readonly JsonLogger $logger
    ) {
    }

    /** @return list<string> */
    public function supportedJobCodes(): array
    {
        return $this->jobs->supportedJobCodes();
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array{ok:bool,code?:string,dry_run?:array<string,mixed>}
     */
    public function dryRun(string $jobCode, array $input, array $actor): array
    {
        return $this->jobs->dryRun($jobCode, $input, $actor);
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array{ok:bool,code?:string,job?:array<string,mixed>}
     */
    public function runOnce(string $jobCode, array $input, array $actor): array
    {
        $result = $this->jobs->runOnce($jobCode, $input, $actor);
        if ((bool)($result['ok'] ?? false)) {
            $this->logger->audit([
                'action' => 'ai_cron_run_once_scheduled',
                'actor_public_id' => (string)($actor['public_id'] ?? ''),
                'job_code' => $jobCode,
                'entity_type' => 'ai_job',
                'entity_public_id' => (string)($result['job']['public_id'] ?? ''),
            ]);
        }

        return $result;
    }

    /**
     * @param list<string> $jobCodes
     * @param array<string,mixed> $actor
     * @return array{items:array<int,array<string,mixed>>,ok_count:int,error_count:int}
     */
    public function runBatch(array $jobCodes, array $actor): array
    {
        $items = [];
        $okCount = 0;
        $errorCount = 0;
        foreach ($jobCodes as $jobCode) {
            try {
                $result = $this->runOnce((string)$jobCode, [], $actor);
            } catch (\Throwable $e) {
                $result = ['ok' => false, 'code' => 'AI_JOB_RUN_ONCE_FAILED'];
                $this->logger->error([
                    'action' => 'ai_cron_run_batch_item_failed',
                    'job_code' => (string)$jobCode,
                    'error_class' => get_class($e),
                    'error_message' => $e->getMessage(),
                ]);
            }
            $ok = (bool)($result['ok'] ?? false);
            $items[] = [
                'job_code' => (string)$jobCode,
                'ok' => $ok,
                'code' => (string)($result['code'] ?? ''),
                'job_public_id' => (string)($result['job']['public_id'] ?? ''),
            ];
            if ($ok) {
                $okCount++;
            } else {
                $errorCount++;
            }
        }

        return [
            'items' => $items,
            'ok_count' => $okCount,
            'error_count' => $errorCount,
        ];
    }
}
