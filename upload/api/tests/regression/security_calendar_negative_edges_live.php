<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $headers = ['Authorization' => 'Bearer ' . $root['token']];

    $unknownReset = liveRequest('POST', 'api/v1/security/password-reset', [
        'identifier' => 'missing-user-' . bin2hex(random_bytes(2)) . '@example.local',
    ]);
    liveAssert($unknownReset['status'] === 200, 'Password reset request for unknown identifier must return 200');
    liveAssert(($unknownReset['payload']['data']['issued'] ?? true) === false, 'Unknown identifier must not issue reset token');

    $invalidResetConfirm = liveRequest('POST', 'api/v1/security/password-reset/confirm', [
        'reset_token' => 'invalid-reset-token',
        'new_password' => 'NewPass123!',
    ]);
    liveAssert($invalidResetConfirm['status'] === 404, 'Invalid password reset token must return 404');
    liveAssert((string)($invalidResetConfirm['payload']['code'] ?? '') === 'PASSWORD_RESET_TOKEN_INVALID', 'Expected PASSWORD_RESET_TOKEN_INVALID');

    $invalidInvitationAccept = liveRequest('POST', 'api/v1/security/invitations/accept', [
        'invitation_token' => 'invalid-invitation-token',
        'login' => 'invalid-edge-' . bin2hex(random_bytes(2)),
        'full_name' => 'Invalid Invitation User',
        'password' => 'Pass12345!',
    ]);
    liveAssert($invalidInvitationAccept['status'] === 404, 'Invalid invitation token must return 404');
    liveAssert((string)($invalidInvitationAccept['payload']['code'] ?? '') === 'INVITATION_NOT_FOUND', 'Expected INVITATION_NOT_FOUND');

    $suffix = 'edge-' . date('YmdHis') . '-' . bin2hex(random_bytes(2));
    $createInvite = liveRequest('POST', 'api/v1/security/invitations', [
        'email' => 'edge.invite.' . $suffix . '@example.local',
    ], $headers);
    liveAssert($createInvite['status'] === 201, 'Invitation create must return 201');

    $token = (string)($createInvite['payload']['data']['accept_token'] ?? '');
    liveAssert($token !== '', 'Invitation token is required');

    $acceptOnce = liveRequest('POST', 'api/v1/security/invitations/accept', [
        'invitation_token' => $token,
        'login' => 'edge_user_' . bin2hex(random_bytes(2)),
        'full_name' => 'Edge Invite User',
        'password' => 'Pass12345!',
    ]);
    liveAssert($acceptOnce['status'] === 201, 'First invitation accept must return 201');

    $acceptAgain = liveRequest('POST', 'api/v1/security/invitations/accept', [
        'invitation_token' => $token,
        'login' => 'edge_user_' . bin2hex(random_bytes(2)),
        'full_name' => 'Edge Invite User Again',
        'password' => 'Pass12345!',
    ]);
    liveAssert($acceptAgain['status'] === 404, 'Second invitation accept for same token must return 404');
    liveAssert((string)($acceptAgain['payload']['code'] ?? '') === 'INVITATION_NOT_FOUND', 'Expected INVITATION_NOT_FOUND on second accept');

    $calendarCreate = liveRequest('POST', 'api/v1/calendar/business', [
        'title' => 'Calendar Edge ' . $suffix,
        'timezone' => 'Europe/Moscow',
    ], $headers);
    liveAssert($calendarCreate['status'] === 201, 'Business calendar create must return 201');
    $calendarPublicId = (string)($calendarCreate['payload']['data']['calendar']['public_id'] ?? '');
    liveAssert($calendarPublicId !== '', 'Business calendar public_id is required');

    $invalidWorkingWeekday = liveRequest('POST', 'api/v1/calendar/working-hours', [
        'calendar_public_id' => $calendarPublicId,
        'weekday' => 9,
        'start_time' => '09:00',
        'end_time' => '18:00',
    ], $headers);
    liveAssert($invalidWorkingWeekday['status'] === 422, 'weekday out of range must return 422');

    $invalidWorkingTime = liveRequest('POST', 'api/v1/calendar/working-hours', [
        'calendar_public_id' => $calendarPublicId,
        'weekday' => 3,
        'start_time' => '9-00',
        'end_time' => '18:00',
    ], $headers);
    liveAssert($invalidWorkingTime['status'] === 422, 'invalid time format must return 422');

    $invalidHolidayDate = liveRequest('POST', 'api/v1/calendar/holidays', [
        'calendar_public_id' => $calendarPublicId,
        'holiday_date' => '2026/12/31',
        'title' => 'Broken Date',
    ], $headers);
    liveAssert($invalidHolidayDate['status'] === 422, 'invalid holiday date format must return 422');

    $calendarDelete = liveRequest('DELETE', 'api/v1/calendar/business/' . $calendarPublicId, [], $headers);
    liveAssert($calendarDelete['status'] === 200, 'Business calendar delete must return 200');

    echo "[OK] security_calendar_negative_edges_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] security_calendar_negative_edges_live: ' . $e->getMessage() . "\n");
    exit(1);
}
