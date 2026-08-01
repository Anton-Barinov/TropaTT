<?php
declare(strict_types=1);

require __DIR__ . '/../_live_http.php';

function runSecurityRecoveryCalendarFeatureLive(): void
{
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = gmdate('YmdHis') . '_' . bin2hex(random_bytes(3));

    $email = 'live.inv.' . strtolower($suffix) . '@crm.local';
    $invitationCreate = liveRequest('POST', 'api/v1/security/invitations', [
        'email' => $email,
    ], $rootHeaders);
    liveAssert($invitationCreate['status'] === 201, 'Invitation create must return 201');
    $invitationToken = (string)($invitationCreate['payload']['data']['accept_token'] ?? '');
    $invitationPublicId = (string)($invitationCreate['payload']['data']['invitation']['public_id'] ?? '');
    liveAssert($invitationToken !== '', 'Invitation accept_token is required');
    liveAssert($invitationPublicId !== '', 'Invitation public_id is required');

    $acceptLogin = 'live_accept_' . strtolower($suffix);
    $acceptPassword = 'LiveAccept123!';
    $accept = liveRequest('POST', 'api/v1/security/invitations/accept', [
        'invitation_token' => $invitationToken,
        'login' => $acceptLogin,
        'full_name' => 'Live Accept User',
        'password' => $acceptPassword,
        'locale' => 'ru-ru',
    ]);
    liveAssert($accept['status'] === 201, 'Invitation accept must return 201');
    $acceptedUserPublicId = (string)($accept['payload']['data']['user']['public_id'] ?? '');
    $acceptedUserToken = (string)($accept['payload']['data']['user_token'] ?? '');
    liveAssert($acceptedUserPublicId !== '', 'Accepted user public_id is required');
    liveAssert($acceptedUserToken !== '', 'Accepted user token is required');

    $resetRequest = liveRequest('POST', 'api/v1/security/password-reset', [
        'identifier' => $acceptLogin,
    ]);
    liveAssert($resetRequest['status'] === 200, 'Password reset request must return 200');
    $resetToken = (string)($resetRequest['payload']['data']['reset_token'] ?? '');
    liveAssert($resetToken !== '', 'Reset token is required');

    $newPassword = 'LiveReset123!';
    $resetConfirm = liveRequest('POST', 'api/v1/security/password-reset/confirm', [
        'reset_token' => $resetToken,
        'new_password' => $newPassword,
    ]);
    liveAssert($resetConfirm['status'] === 200, 'Password reset confirm must return 200');
    liveAssert((string)($resetConfirm['payload']['code'] ?? '') === 'PASSWORD_RESET_COMPLETED', 'Reset confirm code mismatch');

    $loginAfterReset = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $acceptLogin,
        'password' => $newPassword,
        'token' => $acceptedUserToken,
    ]);
    liveAssert($loginAfterReset['status'] === 200, 'Login with reset password must return 200');

    $calendarCreate = liveRequest('POST', 'api/v1/calendar/business', [
        'title' => 'Live Calendar ' . $suffix,
        'timezone' => 'Europe/Moscow',
    ], $rootHeaders);
    liveAssert($calendarCreate['status'] === 201, 'Business calendar create must return 201');
    $calendarPublicId = (string)($calendarCreate['payload']['data']['calendar']['public_id'] ?? '');
    liveAssert($calendarPublicId !== '', 'Calendar public_id is required');

    $invalidHoliday = liveRequest('POST', 'api/v1/calendar/holidays', [
        'calendar_public_id' => $calendarPublicId,
        'holiday_date' => '31-12-2026',
        'title' => 'Invalid Holiday',
    ], $rootHeaders);
    liveAssert($invalidHoliday['status'] === 422, 'Invalid holiday date must return 422');

    $holidayCreate = liveRequest('POST', 'api/v1/calendar/holidays', [
        'calendar_public_id' => $calendarPublicId,
        'holiday_date' => '2026-12-31',
        'title' => 'Live Holiday ' . $suffix,
    ], $rootHeaders);
    liveAssert($holidayCreate['status'] === 201, 'Holiday create must return 201');
    $holidayPublicId = (string)($holidayCreate['payload']['data']['holiday']['public_id'] ?? '');
    liveAssert($holidayPublicId !== '', 'Holiday public_id is required');

    $workingCreate = liveRequest('POST', 'api/v1/calendar/working-hours', [
        'calendar_public_id' => $calendarPublicId,
        'weekday' => 1,
        'start_time' => '09:00',
        'end_time' => '18:00',
    ], $rootHeaders);
    liveAssert($workingCreate['status'] === 201, 'Working-hours create must return 201');
    $workingPublicId = (string)($workingCreate['payload']['data']['working_hours']['public_id'] ?? '');
    liveAssert($workingPublicId !== '', 'Working-hours public_id is required');

    liveRequest('DELETE', 'api/v1/calendar/working-hours/' . $workingPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/calendar/holidays/' . $holidayPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/calendar/business/' . $calendarPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/users/' . $acceptedUserPublicId, [], $rootHeaders);
}

runSecurityRecoveryCalendarFeatureLive();
echo "[OK] security_recovery_calendar_feature_live\n";
