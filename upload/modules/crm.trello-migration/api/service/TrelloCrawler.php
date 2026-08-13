<?php
declare(strict_types=1);

namespace Module\Crm\TrelloMigration\Service;

use Module\Crm\TrelloMigration\Repository\TrelloMigrationRepository;

final class TrelloCrawler
{
    public function __construct(
        private readonly TrelloClient $client,
        private readonly TrelloMigrationRepository $repo,
    ) {
    }

    /** @return array{boards:int,lists:int,cards:int,members:int,labels:int,custom_fields:int,warnings:array<int,string>} */
    public function crawl(array $job, string $apiKey, string $token, ?callable $heartbeat = null): array
    {
        $scope = (array)($job['source_scope'] ?? []);
        $selected = array_values(array_filter(array_map('strval', (array)($scope['board_ids'] ?? []))));
        $limit = max(0, (int)($scope['max_cards'] ?? 0));
        $options = (array)($job['target_options'] ?? []);
        $includeArchived = !array_key_exists('include_archived', $options) || (bool)$options['include_archived'];
        $boards = $this->client->boards($apiKey, $token);
        if ($selected !== []) {
            $boards = array_values(array_filter($boards, static fn(array $b): bool => in_array((string)($b['id'] ?? ''), $selected, true)));
        }

        $stats = ['boards' => 0, 'lists' => 0, 'cards' => 0, 'members' => 0, 'labels' => 0, 'custom_fields' => 0, 'warnings' => []];
        $cardCount = 0;
        foreach ($boards as $board) {
            if ($heartbeat !== null && !$heartbeat()) throw new \RuntimeException('TRELLO_JOB_LEASE_LOST');
            $boardId = (string)($board['id'] ?? '');
            if ($boardId === '') continue;
            $stats['boards']++;
            $this->repo->upsertItem((int)$job['id'], 'board', $boardId, [
                'status' => 'pending', 'checksum' => $this->checksum($board),
                'payload_json' => $board,
            ]);
            try {
                foreach ($this->client->members($apiKey, $token, $boardId) as $member) {
                    if (!empty($member['id'])) {
                        $this->repo->upsertUserMapping((int)$job['connection_id'], $member);
                        $this->repo->upsertItem((int)$job['id'], 'member', (string)$member['id'], ['source_parent_id' => $boardId, 'status' => 'pending', 'checksum' => $this->checksum($member), 'payload_json' => $member]);
                        $stats['members']++;
                    }
                }
                foreach ($this->client->labels($apiKey, $token, $boardId) as $label) {
                    if (!empty($label['id'])) {
                        $this->repo->upsertItem((int)$job['id'], 'label', (string)$label['id'], ['source_parent_id' => $boardId, 'status' => 'pending', 'checksum' => $this->checksum($label), 'payload_json' => $label]);
                        $stats['labels']++;
                    }
                }
                $customFieldDefinitions = [];
                try {
                    $customFieldDefinitions = $this->client->customFields($apiKey, $token, $boardId);
                    $stats['custom_fields'] += count($customFieldDefinitions);
                } catch (\Throwable) {
                    $stats['warnings'][] = 'Board ' . $boardId . ' custom fields were not loaded.';
                }
                foreach ($this->client->lists($apiKey, $token, $boardId) as $list) {
                    if (!$includeArchived && !empty($list['closed'])) continue;
                    $listId = (string)($list['id'] ?? '');
                    if ($listId === '') continue;
                    $this->repo->upsertItem((int)$job['id'], 'list', $listId, ['source_parent_id' => $boardId, 'status' => 'pending', 'checksum' => $this->checksum($list), 'payload_json' => $list]);
                    $stats['lists']++;
                }
                foreach ($this->client->cards($apiKey, $token, $boardId) as $cardSummary) {
                    if ($heartbeat !== null && !$heartbeat()) throw new \RuntimeException('TRELLO_JOB_LEASE_LOST');
                    if (!$includeArchived && !empty($cardSummary['closed'])) continue;
                    if ($limit > 0 && $cardCount >= $limit) break 2;
                    $cardId = (string)($cardSummary['id'] ?? '');
                    if ($cardId === '') continue;
                    try {
                        $card = $this->client->card($apiKey, $token, $cardId);
                        $card['trello_actions'] = $this->client->actions($apiKey, $token, $cardId);
                        $card['trello_custom_field_definitions'] = $customFieldDefinitions;
                    } catch (\Throwable $e) {
                        $card = $cardSummary;
                        $stats['warnings'][] = 'Card ' . $cardId . ' details were not loaded.';
                    }
                    $this->repo->upsertItem((int)$job['id'], 'card', $cardId, [
                        'source_parent_id' => $boardId,
                        'status' => 'pending',
                        'checksum' => $this->checksum($card),
                        'source_updated_at' => $this->date((string)($card['dateLastActivity'] ?? '')),
                        'payload_json' => $card,
                    ]);
                    $stats['cards']++;
                    $cardCount++;
                }
            } catch (\Throwable $e) {
                if ($e->getMessage() === 'TRELLO_JOB_LEASE_LOST') throw $e;
                $stats['warnings'][] = 'Board ' . $boardId . ' discovery failed.';
                $this->repo->addLog((int)$job['id'], 'warning', 'crawl', 'Board discovery failed.', ['board_id' => $boardId]);
            }
        }
        return $stats;
    }

    private function checksum(array $data): string
    {
        return hash('sha256', (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function date(string $value): ?string
    {
        if ($value === '') return null;
        $time = strtotime($value);
        return $time === false ? null : gmdate('Y-m-d H:i:s', $time);
    }
}
