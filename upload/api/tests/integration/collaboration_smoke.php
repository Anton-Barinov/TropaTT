<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $tasks = request('GET', '/api/v1/tasks?limit=1', [], $headers);
    assertTrue($tasks['status'] === 200, 'Tasks list status must be 200');
    $taskPublicId = (string)($tasks['payload']['data']['items'][0]['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id required');

    $commentAdd = request('POST', '/api/v1/tasks/' . $taskPublicId . '/comments', [
        'body' => 'collaboration smoke',
    ], $headers);
    assertTrue($commentAdd['status'] === 201, 'Comment add status must be 201');

    $commentList = request('GET', '/api/v1/tasks/' . $taskPublicId . '/comments?limit=1', [], $headers);
    assertTrue($commentList['status'] === 200, 'Comment list status must be 200');
    $commentPublicId = (string)($commentList['payload']['data']['items'][0]['public_id'] ?? '');
    assertTrue($commentPublicId !== '', 'Comment public_id required');

    $mentionAdd = request('POST', '/api/v1/mentions', [
        'entity_type' => 'comment',
        'entity_public_id' => $commentPublicId,
        'mentioned_user_public_id' => $auth['user_public_id'],
    ], $headers);
    assertTrue($mentionAdd['status'] === 201, 'Mention add status must be 201');
    $mentionPublicId = (string)($mentionAdd['payload']['data']['mention']['public_id'] ?? '');
    assertTrue($mentionPublicId !== '', 'Mention public_id required');

    $mentionList = request('GET', '/api/v1/mentions?entity_type=comment&entity_public_id=' . $commentPublicId, [], $headers);
    assertTrue($mentionList['status'] === 200, 'Mention list status must be 200');
    assertTrue(($mentionList['payload']['code'] ?? '') === 'MENTION_LIST', 'Mention list code mismatch');

    $mentionDelete = request('DELETE', '/api/v1/mentions/' . $mentionPublicId, [], $headers);
    assertTrue($mentionDelete['status'] === 200, 'Mention delete status must be 200');

    $reactionAdd = request('POST', '/api/v1/reactions', [
        'entity_type' => 'comment',
        'entity_public_id' => $commentPublicId,
        'reaction' => 'like',
    ], $headers);
    assertTrue($reactionAdd['status'] === 201, 'Reaction add status must be 201');
    $reactionPublicId = (string)($reactionAdd['payload']['data']['reaction']['public_id'] ?? '');
    assertTrue($reactionPublicId !== '', 'Reaction public_id required');

    $reactionList = request('GET', '/api/v1/reactions?entity_type=comment&entity_public_id=' . $commentPublicId, [], $headers);
    assertTrue($reactionList['status'] === 200, 'Reaction list status must be 200');

    $reactionDelete = request('DELETE', '/api/v1/reactions/' . $reactionPublicId, [], $headers);
    assertTrue($reactionDelete['status'] === 200, 'Reaction delete status must be 200');

    $subscriptionCreate = request('POST', '/api/v1/subscriptions', [
        'entity_type' => 'task',
        'entity_public_id' => $taskPublicId,
    ], $headers);
    assertTrue($subscriptionCreate['status'] === 201, 'Subscription create status must be 201');
    $subscriptionPublicId = (string)($subscriptionCreate['payload']['data']['subscription']['public_id'] ?? '');
    assertTrue($subscriptionPublicId !== '', 'Subscription public_id required');

    $subscriptionList = request('GET', '/api/v1/subscriptions?entity_type=task&entity_public_id=' . $taskPublicId, [], $headers);
    assertTrue($subscriptionList['status'] === 200, 'Subscription list status must be 200');

    $subscriptionDelete = request('DELETE', '/api/v1/subscriptions/' . $subscriptionPublicId, [], $headers);
    assertTrue($subscriptionDelete['status'] === 200, 'Subscription delete status must be 200');

    $unauthorized = request('GET', '/api/v1/mentions');
    assertTrue($unauthorized['status'] === 401, 'Mentions unauthorized status must be 401');

    echo "[OK] Collaboration smoke passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
