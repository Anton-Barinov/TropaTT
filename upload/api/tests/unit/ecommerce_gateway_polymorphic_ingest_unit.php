<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../web/system/Core/helpers.php';
require_once __DIR__ . '/../../system/library/support/Ulid.php';

putenv('APP_SECRET=test_app_secret_for_unit_tests_32_bytes_long!!');
$_ENV['APP_SECRET'] = 'test_app_secret_for_unit_tests_32_bytes_long!!';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Module\\Crm\\EcommerceGateway\\';
    $baseDir = __DIR__ . '/../../../modules/crm.ecommerce-gateway/api/';
    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});

use Module\Crm\EcommerceGateway\Service\AntiSpamService;
use Module\Crm\EcommerceGateway\Service\FieldMapperService;
use Module\Crm\EcommerceGateway\Service\RoutingMatrixService;
use Module\Crm\EcommerceGateway\Service\PayloadValidator;
use Module\Crm\EcommerceGateway\Service\IntakeComposer;

$passed = 0;
$total = 0;

function assertCheck(bool $condition, string $msg): void {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$msg}\n";
    } else {
        echo "  [FAIL] {$msg}\n";
    }
}

echo "Running E-Commerce Gateway Polymorphic Ingestion & Anti-Spam Test Suite (E-COM-11)...\n\n";

// =========================================================================
// 1. Polymorphic Schema Validation (callback, feedback, quick_order, quiz, custom forms)
// =========================================================================
echo "--- 1. Polymorphic Schema Validation (cki_MTYM0QHKB2401102EC9FC0A2) ---\n";
$validator = new PayloadValidator();

// 1.1 Callback
$callbackPayload = [
    'external_id' => 'cb_001',
    'payload' => [
        'topic' => 'Консультация по доставке',
        'customer' => ['phone' => '+7 (999) 111-22-33', 'full_name' => 'Иван Иванов'],
    ],
];
$cbRes = $validator->validate('callback', $callbackPayload);
assertCheck($cbRes['ok'], "Callback schema validates successfully");
assertCheck($cbRes['data']['payload']['topic'] === 'Консультация по доставке', "Callback topic normalized");
assertCheck($cbRes['data']['contact']['phone'] === '+79991112233', "Callback phone normalized to E.164");

// 1.2 Feedback
$feedbackPayload = [
    'external_id' => 'fb_001',
    'payload' => [
        'subject' => 'Отзыв о сервисе',
        'message' => 'Отличная скорость обработки заказов!',
        'customer' => ['email' => 'client@example.com', 'full_name' => 'Анна Смирнова'],
    ],
];
$fbRes = $validator->validate('feedback', $feedbackPayload);
assertCheck($fbRes['ok'], "Feedback schema validates successfully");
assertCheck($fbRes['data']['payload']['subject'] === 'Отзыв о сервисе', "Feedback subject preserved");

// 1.3 Quick Order
$quickOrderPayload = [
    'external_id' => 'qo_001',
    'payload' => [
        'product' => [
            'name' => 'Смартфон Alpha X',
            'sku' => 'AP-100',
            'price' => [
                'amount_minor' => 4999000,
                'currency' => 'RUB',
            ],
        ],
        'quantity' => 1,
        'customer' => ['phone' => '+7 (916) 555-44-33'],
    ],
];
$qoRes = $validator->validate('quick_order', $quickOrderPayload);
assertCheck($qoRes['ok'], "Quick order schema validates successfully");
assertCheck($qoRes['data']['payload']['product']['sku'] === 'AP-100', "Quick order SKU extracted");

// 1.4 Custom Form
$formPayload = [
    'external_id' => 'form_001',
    'payload' => [
        'form_id' => 'lead_calculator',
        'form_name' => 'Калькулятор стоимости ремонта',
        'form_data' => [
            'area_sqm' => '75',
            'rooms' => '3',
            'type' => 'Капитальный',
        ],
        'customer' => ['phone' => '+7 (926) 777-88-99', 'full_name' => 'Сергей'],
    ],
];
$formRes = $validator->validate('form', $formPayload);
assertCheck($formRes['ok'], "Custom form schema validates successfully");
assertCheck($formRes['data']['payload']['form_id'] === 'lead_calculator', "Form ID normalized");
assertCheck(isset($formRes['data']['payload']['form_data']['area_sqm']), "Dynamic form data retained");

// 1.5 Quiz Polymorphic Form
$quizPayload = [
    'external_id' => 'quiz_001',
    'payload' => [
        'quiz_id' => 'solar_power_calculator',
        'quiz_title' => 'Подбор солнечной электростанции',
        'answers' => [
            'power_consumption' => '5 кВт/ч',
            'roof_type' => 'Двускатная черепица',
            'budget' => '300000',
        ],
        'customer' => ['email' => 'eco_client@example.org', 'phone' => '+79851234567'],
    ],
];
$quizRes = $validator->validate('quiz', $quizPayload);
assertCheck($quizRes['ok'], "Quiz envelope validates as polymorphic form");
assertCheck($quizRes['data']['payload']['form_id'] === 'solar_power_calculator', "Quiz ID mapped to form_id");
assertCheck($quizRes['data']['payload']['form_name'] === 'Подбор солнечной электростанции', "Quiz title mapped to form_name");
assertCheck(isset($quizRes['data']['payload']['form_data']['power_consumption']), "Quiz answers parsed into form_data");

// 1.6 Missing Contact Channel Rejection
$noContact = [
    'external_id' => 'nocontact_001',
    'payload' => [
        'topic' => 'Test',
        'customer' => ['full_name' => 'Безымянный без контактов'],
    ],
];
$noContactRes = $validator->validate('callback', $noContact);
assertCheck(!$noContactRes['ok'], "Rejects payload without contact channel");
assertCheck($noContactRes['code'] === 'INGESTION_CONTACT_REQUIRED', "Returns INGESTION_CONTACT_REQUIRED code");

// =========================================================================
// 2. Flexible Field Mapper & Markdown Table Fallback
// =========================================================================
echo "\n--- 2. Dynamic Field Mapper (cki_MTYM0RAC6FB8B5B653157EE4) ---\n";
$mapper = new FieldMapperService();

$rawFormData = [
    'custom_inn' => '7701234567',
    'custom_city' => 'Москва',
    'preferred_courier' => 'CDEK',
    'budget_limit' => '150000 руб',
    'additional_notes' => "Хочу доставку\nдо двери",
];

$rules = [
    'custom_inn' => 'tax_id',
    'custom_city' => 'delivery_city',
];

$mapRes = $mapper->map(1, $rawFormData, $rules);
assertCheck(isset($mapRes['mapped_custom_fields']['tax_id']) && $mapRes['mapped_custom_fields']['tax_id'] === '7701234567', "Mapped custom_inn to tax_id");
assertCheck(isset($mapRes['mapped_custom_fields']['delivery_city']) && $mapRes['mapped_custom_fields']['delivery_city'] === 'Москва', "Mapped custom_city to delivery_city");
assertCheck(count($mapRes['unmapped_fields']) === 3, "Unmapped fields count is 3");
assertCheck(isset($mapRes['unmapped_fields']['preferred_courier']), "preferred_courier in unmapped fields");
assertCheck(str_contains($mapRes['markdown_table'], '| Поле формы | Значение |'), "Markdown table header generated");
assertCheck(str_contains($mapRes['markdown_table'], '| preferred_courier | CDEK |'), "Markdown table contains preferred_courier row");
assertCheck(!str_contains($mapRes['markdown_table'], "\nдо двери"), "Newlines in unmapped values flattened into single line");

// =========================================================================
// 3. Routing Matrix, Priorities & SLA Timers
// =========================================================================
echo "\n--- 3. Routing Matrix & SLA Timers (cki_MTYM0S1GA57A89BB4EE6F1B0) ---\n";
$router = new RoutingMatrixService();

// 3.1 Default Fallback SLAs
$storeDefault = [
    'settings_json' => json_encode([
        'default_priority_code' => 'normal',
        'default_assignee_id' => 10,
    ]),
];

$routeCallback = $router->resolveRoute('callback', $storeDefault, []);
assertCheck($routeCallback['sla_minutes'] === 5, "Default callback SLA is 5 minutes");
assertCheck($routeCallback['priority'] === 'urgent', "Default callback priority is urgent");
assertCheck(strtotime($routeCallback['sla_deadline']) > time(), "SLA deadline is in future");

$routeOrder = $router->resolveRoute('order', $storeDefault, []);
assertCheck($routeOrder['sla_minutes'] === 15, "Default order SLA is 15 minutes");
assertCheck($routeOrder['priority'] === 'high', "Default order priority is high");

$routeQuickOrder = $router->resolveRoute('quick_order', $storeDefault, []);
assertCheck($routeQuickOrder['sla_minutes'] === 10, "Default quick_order SLA is 10 minutes");
assertCheck($routeQuickOrder['priority'] === 'urgent', "Default quick_order priority is urgent");

$routeFeedback = $router->resolveRoute('feedback', $storeDefault, []);
assertCheck($routeFeedback['sla_minutes'] === 120, "Default feedback SLA is 120 minutes (2 hours)");

// 3.2 Routing Matrix Overrides
$storeWithMatrix = [
    'settings_json' => json_encode([
        'default_priority_code' => 'normal',
        'default_assignee_id' => 10,
        'routing_matrix' => [
            'callback' => [
                'priority' => 'critical',
                'sla_minutes' => 3,
                'assignee_id' => 42,
            ],
            'form:vip_quote' => [
                'priority' => 'critical',
                'sla_minutes' => 7,
                'assignee_id' => 99,
            ],
        ],
    ]),
];

$routeVipForm = $router->resolveRoute('form', $storeWithMatrix, ['form_id' => 'vip_quote']);
assertCheck($routeVipForm['priority'] === 'critical', "VIP form matched form:vip_quote priority");
assertCheck($routeVipForm['sla_minutes'] === 7, "VIP form SLA is 7 minutes");
assertCheck($routeVipForm['assignee_user_id'] === 99, "VIP form routed to assignee 99");

$routeCustomCb = $router->resolveRoute('callback', $storeWithMatrix, []);
assertCheck($routeCustomCb['sla_minutes'] === 3, "Custom callback SLA is 3 minutes");
assertCheck($routeCustomCb['assignee_user_id'] === 42, "Custom callback routed to assignee 42");

// =========================================================================
// 4. Multi-layer Anti-Spam
// =========================================================================
echo "\n--- 4. Multi-layer Anti-Spam (cki_MTYM0SRI071DF03C06CA7F2A) ---\n";
$antiSpam = new AntiSpamService();

// 4.1 Honeypot Detection
$hpPayload = [
    'payload' => [
        'topic' => 'Вопрос',
        'website_hp' => 'http://spam-bot.com', // Bot filled hidden field
        'customer' => ['phone' => '+79990001122'],
    ],
];
$hpCheck = $antiSpam->check($hpPayload, ['phone' => '+79990001122'], '198.51.100.1');
assertCheck($hpCheck['is_spam'], "Honeypot trap detects non-empty website_hp");
assertCheck($hpCheck['rule'] === 'honeypot', "Rule identified as honeypot");

$hpNestedPayload = [
    'payload' => [
        'form_data' => [
            'hp_field' => 'bot-value',
            'name' => 'Spammer',
        ],
    ],
];
$hpNestedCheck = $antiSpam->check($hpNestedPayload, [], '198.51.100.2');
assertCheck($hpNestedCheck['is_spam'], "Honeypot trap detects nested hp_field in form_data");

// 4.2 Stop-words Analysis
$spamWordPayload = [
    'payload' => [
        'message' => 'Лучшее онлайн казино вулкан для вас! Быстрый доход тут.',
    ],
];
$swCheck = $antiSpam->check($spamWordPayload, ['phone' => '+79991112233'], '198.51.100.3');
assertCheck($swCheck['is_spam'], "Stop-words filter detects 'казино'");
assertCheck($swCheck['rule'] === 'stop_words', "Rule identified as stop_words");

$cleanPayload = [
    'payload' => [
        'message' => 'Здравствуйте, подскажите наличие товара на складе.',
        'topic' => 'Наличие',
    ],
];
$cleanCheck = $antiSpam->check($cleanPayload, ['phone' => '+79991112233'], '198.51.100.4');
assertCheck(!$cleanCheck['is_spam'], "Legitimate customer message passes spam check");

// 4.3 Bot Score / Captcha verification
$lowScorePayload = [
    'payload' => [
        'topic' => 'Тест',
    ],
    'meta' => [
        'captcha_score' => 0.2, // Below 0.5 threshold
    ],
];
$lowScoreCheck = $antiSpam->check($lowScorePayload, ['phone' => '+79991112233'], '198.51.100.5');
assertCheck($lowScoreCheck['is_spam'], "Low captcha score (<0.5) identified as bot");
assertCheck($lowScoreCheck['rule'] === 'captcha_score', "Rule identified as captcha_score");

$goodScorePayload = [
    'payload' => [
        'topic' => 'Тест',
    ],
    'meta' => [
        'turnstile_score' => 0.9,
    ],
];
$goodScoreCheck = $antiSpam->check($goodScorePayload, ['phone' => '+79991112233'], '198.51.100.6');
assertCheck(!$goodScoreCheck['is_spam'], "High Turnstile score (0.9) passes check");

echo "\n=======================================================\n";
echo "Tests Passed: {$passed}/{$total}\n";
if ($passed === $total) {
    echo "ALL E-COM-11 TESTS PASSED!\n";
    exit(0);
} else {
    echo "SOME TESTS FAILED!\n";
    exit(1);
}
