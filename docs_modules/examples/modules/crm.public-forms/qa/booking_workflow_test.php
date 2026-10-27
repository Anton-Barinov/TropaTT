<?php

declare(strict_types=1);

use Module\Crm\PublicForms\Service\PublicFormSubmissionException;
use function PublicFormsQa\{check, countRows, database, fields, service, submission};

require_once __DIR__ . '/test_support.php';

function expectBookingCode(callable $operation, string $code, string $message): void
{
    try { $operation(); }
    catch (PublicFormSubmissionException $e) {
        PublicFormsQa\check($e->errorCode === $code, $message . ' (got ' . $e->errorCode . ')');
        return;
    }
    throw new RuntimeException('FAIL: ' . $message . ' (expected ' . $code . ')');
}

function createBookingForm(\Module\Crm\PublicForms\Service\PublicFormsService $forms, string $slug, string $date, string $user = 'usr_calendar_a'): string
{
    $schedule = [(int)(new DateTimeImmutable($date, new DateTimeZone('UTC')))->format('N') => [['start' => '09:00', 'end' => '11:00']]];
    $id = $forms->saveForm(10, $slug, 'Booking form', 'booking', fields(), 'I consent to booking.', null, null, $user, 30, 15, 'UTC', $schedule);
    check($forms->setFormPublished(10, $id, true), 'booking form should publish');
    return $id;
}

$db = database();
$db->exec("INSERT INTO users(id,public_id,full_name) VALUES (101,'usr_calendar_a','Calendar A'),(202,'usr_calendar_b','Calendar B')");
$db->exec('INSERT INTO organization_memberships(organization_id,user_id) VALUES (10,101),(20,101),(20,202)');
$date = (new DateTimeImmutable('+5 days', new DateTimeZone('UTC')))->format('Y-m-d');
$forms = service($db);
$bookingId = createBookingForm($forms, 'booking-atomic-form', $date);

$slots = $forms->getBookingSlots('booking-atomic-form', $date, $date, '203.0.113.19');
check(count($slots) > 0, 'configured future schedule exposes available slots');
$slot = $slots[0]['starts_at'];
check($slots[0]['ends_at'] > $slot, 'availability returns a positive duration in the configured timezone');

$slotUtc = (new DateTimeImmutable($slot))->setTimezone(new DateTimeZone('UTC'));
$db->prepare('INSERT INTO calendar_events(public_id,organization_id,owner_user_id,starts_at,ends_at) VALUES (?,?,?,?,?)')
    ->execute(['evt_other_workspace', 20, 101, $slotUtc->format('Y-m-d H:i:s'), $slotUtc->modify('+30 minutes')->format('Y-m-d H:i:s')]);
$otherWorkspaceSlots = $forms->getBookingSlots('booking-atomic-form', $date, $date, '203.0.113.20');
check(in_array($slot, array_column($otherWorkspaceSlots, 'starts_at'), true), 'calendar events from another workspace do not hide this workspace availability');

$db->prepare('INSERT INTO calendar_events(public_id,organization_id,owner_user_id,starts_at,ends_at) VALUES (?,?,?,?,?)')
    ->execute(['evt_same_workspace', 10, 101, $slotUtc->format('Y-m-d H:i:s'), $slotUtc->modify('+30 minutes')->format('Y-m-d H:i:s')]);
$conflictedSlots = $forms->getBookingSlots('booking-atomic-form', $date, $date, '203.0.113.21');
check(!in_array($slot, array_column($conflictedSlots, 'starts_at'), true), 'same-workspace event conflicts hide the slot including its configured buffer');
$db->exec("DELETE FROM calendar_events WHERE public_id='evt_same_workspace'");

$key = 'booking_atomic_key_0001';
$failingCreator = static function (array $input, array $actor, array $source) use ($db): array {
    $db->prepare('INSERT INTO calendar_events(public_id,organization_id,owner_user_id,starts_at,ends_at) VALUES (?,?,?,?,?)')
        ->execute(['evt_should_rollback', $actor['organization_id'], $actor['id'], $input['starts_at'], $input['ends_at']]);
    throw new RuntimeException('simulated calendar service failure');
};
$failingForms = service($db, null, $failingCreator);
try {
    $failingForms->submit('booking-atomic-form', submission('Ada', $key, ['_booking_slot' => $slot]), '203.0.113.19');
    throw new RuntimeException('FAIL: failed calendar creation should abort booking');
} catch (RuntimeException $e) {
    check($e->getMessage() !== 'FAIL: failed calendar creation should abort booking', 'calendar failure propagated safely');
}
check(countRows($db, 'crm_public_form_submissions') === 0, 'failed calendar event does not leave a committed form submission');
check((int)$db->query("SELECT COUNT(*) FROM calendar_events WHERE public_id='evt_should_rollback'")->fetchColumn() === 0, 'calendar event insert rolls back with the failed booking transaction');

$eventCount = 0;
$afterCommitCount = 0;
$eventCreator = static function (array $input, array $actor, array $source) use ($db, &$eventCount): array {
    $eventCount++;
    $publicId = 'evt_booking_' . $eventCount;
    $db->prepare('INSERT INTO calendar_events(public_id,organization_id,owner_user_id,starts_at,ends_at) VALUES (?,?,?,?,?)')
        ->execute([$publicId, $actor['organization_id'], $actor['id'], $input['starts_at'], $input['ends_at']]);
    return ['public_id' => $publicId];
};
$afterCommit = static function (array $event, array $actor) use (&$afterCommitCount): void { $afterCommitCount++; };
$bookingForms = service($db, null, $eventCreator, $afterCommit);
$booked = $bookingForms->submit('booking-atomic-form', submission('Ada', $key, ['_booking_slot' => $slot]), '203.0.113.19');
check($booked['ok'] && !$booked['duplicate'] && $booked['event_public_id'] !== '', 'available slot creates and links one calendar event');
check(countRows($db, 'crm_public_form_submissions') === 1 && countRows($db, 'calendar_events') === 2, 'booking commits the response and calendar event together');
check($afterCommitCount === 1, 'calendar notification dispatch runs after successful commit');
$replay = $bookingForms->submit('booking-atomic-form', submission('Ada', $key, ['_booking_slot' => $slot]), '203.0.113.19');
check($replay['duplicate'] && $replay['event_public_id'] === $booked['event_public_id'], 'booking replay returns the same committed event');
check($eventCount === 1 && $afterCommitCount === 1, 'booking replay does not create another event or dispatch notification');
expectBookingCode(static fn() => $bookingForms->submit('booking-atomic-form', submission('Grace', $key, ['_booking_slot' => $slot]), '203.0.113.19'), 'IDEMPOTENCY_CONFLICT', 'reusing booking key for changed data is rejected');

$conflictForms = service($db, null, static fn(): array => ['public_id' => 'evt_never_created']);
$newKey = 'booking_conflict_key_01';
$secondSlot = $otherWorkspaceSlots[1]['starts_at'] ?? null;
check(is_string($secondSlot), 'fixture exposes a second booking slot');
$secondStartUtc = (new DateTimeImmutable($secondSlot))->setTimezone(new DateTimeZone('UTC'));
$db->prepare('INSERT INTO calendar_events(public_id,organization_id,owner_user_id,starts_at,ends_at) VALUES (?,?,?,?,?)')
    ->execute(['evt_conflict_before_submit', 10, 101, $secondStartUtc->format('Y-m-d H:i:s'), $secondStartUtc->modify('+30 minutes')->format('Y-m-d H:i:s')]);
expectBookingCode(static fn() => $conflictForms->submit('booking-atomic-form', submission('Contact', $newKey, ['_booking_slot' => $secondSlot]), '203.0.113.22'), 'BOOKING_SLOT_UNAVAILABLE', 'slot that becomes occupied before submit is rejected');
check(countRows($db, 'crm_public_form_submissions') === 1, 'conflicted booking creates no new submission');

$rateDb = database();
$rateDb->exec("INSERT INTO users(id,public_id,full_name) VALUES (301,'usr_booking_rate','Rate user')");
$rateDb->exec('INSERT INTO organization_memberships(organization_id,user_id) VALUES (10,301)');
$rateForms = service($rateDb);
createBookingForm($rateForms, 'booking-rate-limit-form', $date, 'usr_booking_rate');
for ($i = 0; $i < 30; $i++) $rateForms->getBookingSlots('booking-rate-limit-form', $date, $date, '203.0.113.88');
expectBookingCode(static fn() => $rateForms->getBookingSlots('booking-rate-limit-form', $date, $date, '203.0.113.88'), 'RATE_LIMITED', 'availability endpoint limits repeated slot scraping per form/IP');
check(countRows($rateDb, 'crm_public_form_slot_requests') === 30, 'rate-limited availability query is not counted as an accepted request');

echo "Public Forms booking availability, workspace conflict, idempotency, atomicity, and rate-limit checks passed\n";
