<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class ImportAiContextBuilder
{
    public function __construct(
        private readonly ImportService $imports,
        private readonly AiMaskingService $masking
    ) {
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|null
     */
    public function buildReviewContext(string $importJobPublicId, array $input, array $actor): ?array
    {
        $job = $this->imports->get($importJobPublicId, $actor);
        if (!is_array($job['job'] ?? null)) {
            return null;
        }

        $item = (array)$job['job'];
        $prompt = trim((string)($input['prompt'] ?? $input['input_text'] ?? ''));

        return [
            'import_job_public_id' => (string)($item['public_id'] ?? ''),
            'type' => (string)($item['type'] ?? ''),
            'status' => (string)($item['status'] ?? ''),
            'created_at' => (string)($item['created_at'] ?? ''),
            'updated_at' => (string)($item['updated_at'] ?? ''),
            'result' => $this->masking->maskSensitiveText((string)json_encode($item['result'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'prompt' => $this->masking->maskSensitiveText($prompt),
        ];
    }
}
