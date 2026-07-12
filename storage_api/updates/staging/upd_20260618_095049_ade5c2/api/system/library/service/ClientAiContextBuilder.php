<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class ClientAiContextBuilder
{
    public function __construct(
        private readonly ClientService $clients,
        private readonly ProjectService $projects,
        private readonly TaskService $tasks,
        private readonly CalendarService $calendar,
        private readonly AiMaskingService $masking
    ) {
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|null
     */
    public function buildSummaryContext(string $clientPublicId, array $input, array $actor): ?array
    {
        $client = $this->clients->get($clientPublicId, $actor);
        if (!$client) {
            return null;
        }

        $inputPrompt = trim((string)($input['prompt'] ?? $input['input_text'] ?? ''));
        $taxInn = trim((string)($client['tax_inn'] ?? ''));
        $taxKpp = trim((string)($client['tax_kpp'] ?? ''));
        $taxOgrn = trim((string)($client['tax_ogrn'] ?? ''));
        $taxOgrnip = trim((string)($client['tax_ogrnip'] ?? ''));
        $bankAccount = trim((string)($client['bank_account'] ?? ''));
        $bankBik = trim((string)($client['bank_bik'] ?? ''));
        $bankCorrAccount = trim((string)($client['bank_corr_account'] ?? ''));

        $clientType = trim((string)($client['client_type'] ?? ''));

        return [
            'client_public_id' => (string)($client['public_id'] ?? ''),
            'title' => $this->maskClientTitleByPolicy(trim((string)($client['title'] ?? '')), $clientType),
            'status' => trim((string)($client['status'] ?? '')),
            'client_type' => $clientType,
            'notes' => $this->masking->maskSensitiveText(trim((string)($client['notes'] ?? ''))),
            'email' => $this->masking->maskSensitiveText(trim((string)($client['email'] ?? ''))),
            'phone' => $this->masking->maskSensitiveText(trim((string)($client['phone'] ?? ''))),
            'tax_inn' => $this->masking->maskSensitiveText($taxInn),
            'tax_kpp' => $this->masking->maskSensitiveText($taxKpp),
            'tax_ogrn' => $this->masking->maskSensitiveText($taxOgrn),
            'tax_ogrnip' => $this->masking->maskSensitiveText($taxOgrnip),
            'bank_account' => $this->masking->maskSensitiveText($bankAccount),
            'bank_bik' => $this->masking->maskSensitiveText($bankBik),
            'bank_corr_account' => $this->masking->maskSensitiveText($bankCorrAccount),
            'bank_name' => $this->masking->maskSensitiveText(trim((string)($client['bank_name'] ?? ''))),
            'website' => trim((string)($client['website'] ?? '')),
            'address_legal' => $this->masking->maskSensitiveText(trim((string)($client['address_legal'] ?? ''))),
            'address_postal' => $this->masking->maskSensitiveText(trim((string)($client['address_postal'] ?? ''))),
            'quality_profile' => [
                'email_present' => trim((string)($client['email'] ?? '')) !== '',
                'phone_present' => trim((string)($client['phone'] ?? '')) !== '',
                'website_present' => trim((string)($client['website'] ?? '')) !== '',
                'tax_inn_digits' => $this->digitLength($taxInn),
                'tax_kpp_digits' => $this->digitLength($taxKpp),
                'tax_ogrn_digits' => $this->digitLength($taxOgrn),
                'tax_ogrnip_digits' => $this->digitLength($taxOgrnip),
                'bank_account_digits' => $this->digitLength($bankAccount),
                'bank_bik_digits' => $this->digitLength($bankBik),
                'bank_corr_account_digits' => $this->digitLength($bankCorrAccount),
            ],
            'prompt' => $this->masking->maskSensitiveText($inputPrompt),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|null
     */
    public function buildMeetingPrepContext(string $clientPublicId, array $input, array $actor): ?array
    {
        $base = $this->buildSummaryContext($clientPublicId, $input, $actor);
        if ($base === null) {
            return null;
        }

        $projectsResult = $this->projects->list([
            'limit' => 100,
            'sort' => 'updated_at',
            'order' => 'DESC',
        ], $actor);
        $projects = array_values(array_filter((array)($projectsResult['items'] ?? []), function (array $project) use ($clientPublicId): bool {
            return (string)($project['client_public_id'] ?? '') === $clientPublicId;
        }));
        $projectPublicIds = array_values(array_filter(array_map(static fn(array $project): string => (string)($project['public_id'] ?? ''), $projects), static fn(string $id): bool => $id !== ''));
        $projectIdMap = array_fill_keys($projectPublicIds, true);

        $openTasks = [];
        foreach (array_slice($projectPublicIds, 0, 12) as $projectPublicId) {
            $tasksResult = $this->tasks->list([
                'project_public_id' => $projectPublicId,
                'limit' => 30,
                'sort' => 'updated_at',
                'order' => 'DESC',
            ], $actor);
            foreach ((array)($tasksResult['items'] ?? []) as $task) {
                $status = strtolower(trim((string)($task['status_code'] ?? '')));
                if ($status === 'done' || $status === 'completed' || $status === 'cancelled') {
                    continue;
                }
                $openTasks[] = [
                    'task_public_id' => (string)($task['public_id'] ?? ''),
                    'title' => $this->masking->maskSensitiveText(trim((string)($task['title'] ?? ''))),
                    'status' => (string)($task['status_code'] ?? ''),
                    'priority' => (string)($task['priority_code'] ?? ''),
                    'due_at' => (string)($task['due_at'] ?? ''),
                    'project_public_id' => (string)($task['project_public_id'] ?? ''),
                    'project_title' => $this->masking->maskSensitiveText(trim((string)($task['project_title'] ?? ''))),
                ];
                if (count($openTasks) >= 25) {
                    break 2;
                }
            }
        }

        $from = gmdate('Y-m-d H:i:s');
        $to = gmdate('Y-m-d H:i:s', time() + 14 * 24 * 60 * 60);
        $eventsResult = $this->calendar->listEvents([
            'from' => $from,
            'to' => $to,
            'limit' => 200,
        ], $actor);
        $upcomingEvents = [];
        foreach ((array)($eventsResult['items'] ?? []) as $event) {
            $eventProjectId = (string)($event['project_public_id'] ?? '');
            $eventTaskProjectId = '';
            if ($eventProjectId === '') {
                $eventTaskProjectId = (string)($event['task_project_public_id'] ?? '');
            }
            if ($eventProjectId !== '' && isset($projectIdMap[$eventProjectId]) !== true) {
                continue;
            }
            if ($eventProjectId === '' && $eventTaskProjectId !== '' && isset($projectIdMap[$eventTaskProjectId]) !== true) {
                continue;
            }
            if ($eventProjectId === '' && $eventTaskProjectId === '' && $projectIdMap !== []) {
                continue;
            }
            $upcomingEvents[] = [
                'event_public_id' => (string)($event['public_id'] ?? ''),
                'title' => $this->masking->maskSensitiveText(trim((string)($event['title'] ?? ''))),
                'starts_at' => (string)($event['starts_at'] ?? ''),
                'ends_at' => (string)($event['ends_at'] ?? ''),
                'project_public_id' => $eventProjectId,
                'project_title' => $this->masking->maskSensitiveText(trim((string)($event['project_title'] ?? ''))),
                'task_public_id' => (string)($event['task_public_id'] ?? ''),
                'task_title' => $this->masking->maskSensitiveText(trim((string)($event['task_title'] ?? ''))),
            ];
            if (count($upcomingEvents) >= 12) {
                break;
            }
        }

        $recentProjects = array_map(function (array $project): array {
            return [
                'project_public_id' => (string)($project['public_id'] ?? ''),
                'title' => $this->masking->maskSensitiveText(trim((string)($project['title'] ?? ''))),
                'status' => (string)($project['status_code'] ?? ''),
                'priority' => (string)($project['priority_code'] ?? ''),
                'updated_at' => (string)($project['updated_at'] ?? ''),
            ];
        }, array_slice($projects, 0, 10));

        return $base + [
            'upcoming_events' => $upcomingEvents,
            'open_tasks' => $openTasks,
            'recent_projects' => $recentProjects,
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|null
     */
    public function buildDataQualityContext(string $clientPublicId, array $input, array $actor): ?array
    {
        return $this->buildSummaryContext($clientPublicId, $input, $actor);
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|null
     */
    public function buildClientSafeReportContext(string $clientPublicId, array $input, array $actor): ?array
    {
        return $this->buildMeetingPrepContext($clientPublicId, $input, $actor);
    }

    private function digitLength(string $value): int
    {
        $digits = preg_replace('/\D+/', '', $value);
        return is_string($digits) ? strlen($digits) : 0;
    }

    private function maskClientTitleByPolicy(string $title, string $clientType): string
    {
        $normalizedType = strtolower(trim($clientType));
        if ($title === '') {
            return '';
        }

        if ($normalizedType === 'individual' || $normalizedType === 'sole_proprietor') {
            return '[masked]';
        }

        return $title;
    }
}
