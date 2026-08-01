<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/language/LanguageManager.php';

use Api\System\Library\Language\LanguageManager;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $base = sys_get_temp_dir() . '/crm_api_lang_unit_' . bin2hex(random_bytes(4));
    $enDir = $base . '/en-gb';
    $ruDir = $base . '/ru-ru';

    if (!mkdir($enDir, 0777, true) && !is_dir($enDir)) {
        throw new RuntimeException('Cannot create en-gb temp dir');
    }
    if (!mkdir($ruDir, 0777, true) && !is_dir($ruDir)) {
        throw new RuntimeException('Cannot create ru-ru temp dir');
    }

    file_put_contents($enDir . '/auth.php', "<?php\nreturn [\n    'login_success' => 'Login success',\n    'logout_success' => 'Logout success',\n];\n");
    file_put_contents($ruDir . '/auth.php', "<?php\nreturn [\n    'login_success' => 'Вход выполнен',\n];\n");

    $lm = new LanguageManager($base, 'en-gb');
    $lm->setLocale('ru-ru');

    $login = $lm->get('auth.login_success');
    $logout = $lm->get('auth.logout_success');
    $fallback = $lm->get('auth.not_exists', 'default-text');

    unitAssert($login === 'Вход выполнен', 'Locale value must override fallback value');
    unitAssert($logout === 'Logout success', 'Missing locale key must fallback to en-gb value');
    unitAssert($fallback === 'default-text', 'Unknown key must return provided default');

    echo "[OK] language_manager_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] language_manager_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
