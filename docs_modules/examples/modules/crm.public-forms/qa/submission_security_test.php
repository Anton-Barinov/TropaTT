<?php

declare(strict_types=1);

use Module\Crm\PublicForms\Service\PublicFormSubmissionException;
use function PublicFormsQa\{check, countRows, createIntakeCreator, database, fields, publishIntake, service, submission};

require_once __DIR__ . '/test_support.php';

function expectCode(callable $operation, string $code, string $message): void
{
    try { $operation(); }
    catch (PublicFormSubmissionException $e) {
        PublicFormsQa\check($e->errorCode === $code, $message . ' (got ' . $e->errorCode . ')');
        return;
    }
    throw new RuntimeException('FAIL: ' . $message . ' (expected ' . $code . ')');
}

$db = database();
$intakeCalls = 0;
$intake = createIntakeCreator($db, static function (array $input, array $actor) use (&$intakeCalls): void {
    $intakeCalls++;
    PublicFormsQa\check($actor['organization_id'] === 10, 'intake callback receives the source workspace');
    PublicFormsQa\check(($input['external_source'] ?? null) === 'public_form' && !empty($input['external_id']), 'intake uses a stable idempotent external identity');
});
$forms = service($db, $intake);
$formId = publishIntake($forms, 'security-submission-form');

expectCode(static fn() => $forms->submit('security-submission-form', ['full_name' => 'Ada', '_idempotency_key' => 'consent_missing_0001'], '192.0.2.1'), 'CONSENT_REQUIRED', 'submission without consent is rejected');
expectCode(static fn() => $forms->submit('security-submission-form', submission('Ada', 'unexpected_field_0001', ['is_root' => '1']), '192.0.2.1'), 'UNEXPECTED_FIELD', 'unknown field injection is rejected');
expectCode(static fn() => $forms->submit('security-submission-form', submission('Ada', 'invalid_value_type_001', ['email' => ['attacker@example.test']]), '192.0.2.1'), 'INVALID_FIELD_VALUE', 'nested field values are rejected');
expectCode(static fn() => $forms->submit('security-submission-form', submission('', 'required_field_empty_001'), '192.0.2.1'), 'REQUIRED_FIELD_MISSING', 'empty required field is rejected');
expectCode(static fn() => $forms->submit('security-submission-form', submission('Ada', 'invalid_key_format_001', ['_idempotency_key' => 'short']), '192.0.2.1'), 'IDEMPOTENCY_KEY_REQUIRED', 'short idempotency key is rejected');
expectCode(static fn() => $forms->submit('security-submission-form', submission(str_repeat('x', 33000), 'large_payload_key_0001'), '192.0.2.1'), 'PAYLOAD_TOO_LARGE', 'oversized decoded payload is rejected');

$honeypot = $forms->submit('security-submission-form', submission('Bot', 'honeypot_key_00000001', ['_hp_website' => 'filled by bot']), '192.0.2.1');
check($honeypot['submission_id'] === 'spm_dropped' && countRows($db, 'crm_public_form_submissions') === 0, 'honeypot success response stores no submission or PII');

$key = 'stable_submission_key_0001';
$first = $forms->submit('security-submission-form', submission('Ada Lovelace', $key), '192.0.2.1');
check($first['ok'] && !$first['duplicate'] && $first['intake_public_id'] !== '', 'valid consented submission creates a CRM intake record');
check(countRows($db, 'crm_public_form_submissions') === 1 && countRows($db, 'intake_items') === 1, 'submission and exactly one intake record are persisted');
$replay = $forms->submit('security-submission-form', submission('Ada Lovelace', $key), '192.0.2.1');
check($replay['duplicate'] && $replay['submission_id'] === $first['submission_id'] && $replay['intake_public_id'] === $first['intake_public_id'], 'same idempotency key and payload replay the original result');
check($intakeCalls === 1 && countRows($db, 'intake_items') === 1, 'idempotent replay does not duplicate Intake side effects');
expectCode(static fn() => $forms->submit('security-submission-form', submission('Grace Hopper', $key), '192.0.2.1'), 'IDEMPOTENCY_CONFLICT', 'same key with different payload is rejected');

$stored = $forms->listSubmissions(10, 25);
check(count($stored) === 1, 'submissions are listed only within the owning workspace');
check(($stored[0]['field_labels']['full_name'] ?? '') === 'Имя', 'submission report preserves field labels');
check(($stored[0]['consent_evidence']['agreed'] ?? false) === true && ($stored[0]['consent_evidence']['text'] ?? '') === 'I agree to data processing.', 'submission report preserves the consent evidence snapshot');
check(!isset($stored[0]['data']['__public_form_snapshot']), 'private form snapshot is not exposed as user-submitted data');
check($forms->listSubmissions(20) === [], 'another workspace cannot list this form response');

$rateDb = database();
$rateForms = service($rateDb, createIntakeCreator($rateDb));
publishIntake($rateForms, 'rate-limit-form');
for ($i = 1; $i <= 5; $i++) {
    $rateForms->submit('rate-limit-form', submission('Contact ' . $i, 'rate_limit_key_' . str_pad((string)$i, 8, '0', STR_PAD_LEFT)), '198.51.100.22');
}
expectCode(static fn() => $rateForms->submit('rate-limit-form', submission('Contact 6', 'rate_limit_key_00000006'), '198.51.100.22'), 'RATE_LIMITED', 'sixth submission from one IP within the window is rejected');
check(countRows($rateDb, 'crm_public_form_submissions') === 5, 'rate-limited submission is not persisted');

echo "Public Forms submission consent, allowlist, payload, idempotency, Intake, and rate-limit checks passed\n";
