<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/support/Autoloader.php';

$autoloader = new Api\System\Library\Support\Autoloader(dirname(__DIR__, 2));
$autoloader->register();

use Api\System\Library\App;

function dispositionAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $app = new App(dirname(__DIR__, 2));
    $method = new ReflectionMethod(App::class, 'contentDispositionAttachment');

    $normal = (string)$method->invoke($app, 'normal.pdf');
    dispositionAssert($normal === 'attachment; filename="normal.pdf"; filename*=UTF-8\'\'normal.pdf', 'Normal filename disposition mismatch');

    $pathLike = (string)$method->invoke($app, '../../secret.txt');
    dispositionAssert(str_contains($pathLike, 'filename="secret.txt"'), 'Path-like filename must use basename');

    $headerLike = (string)$method->invoke($app, "bad\r\nX-Evil: 1.txt");
    dispositionAssert(!str_contains($headerLike, "\r") && !str_contains($headerLike, "\n"), 'Disposition must not contain CR/LF');
    dispositionAssert(str_contains($headerLike, 'badX-Evil: 1.txt'), 'Disposition must strip control chars');

    $quoted = (string)$method->invoke($app, 'bad"name.txt');
    dispositionAssert(str_contains($quoted, 'filename="bad_name.txt"'), 'ASCII filename must neutralize quotes');

    $backslashPath = (string)$method->invoke($app, 'dir\\nested.txt');
    dispositionAssert(str_contains($backslashPath, 'filename="nested.txt"'), 'Backslash path-like filename must use basename');

    $unicode = (string)$method->invoke($app, 'файл.txt');
    dispositionAssert((bool)preg_match('/filename="[_]+\\.txt"/', $unicode), 'Unicode ASCII fallback mismatch');
    dispositionAssert(str_contains($unicode, "filename*=UTF-8''%D1%84%D0%B0%D0%B9%D0%BB.txt"), 'Unicode filename* mismatch');

    echo "[OK] app_content_disposition_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] app_content_disposition_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
