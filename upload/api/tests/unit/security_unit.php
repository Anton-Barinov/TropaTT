<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/security/TokenManager.php';
require_once __DIR__ . '/../../system/library/security/PasswordHasher.php';

use Api\System\Library\Security\PasswordHasher;
use Api\System\Library\Security\TokenManager;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $tokens = new TokenManager();

    $tokenA = $tokens->generate(16);
    $tokenB = $tokens->generate(16);
    unitAssert($tokenA !== '', 'Generated token A must not be empty');
    unitAssert($tokenB !== '', 'Generated token B must not be empty');
    unitAssert($tokenA !== $tokenB, 'Generated tokens should be different');

    $hashA1 = $tokens->hash($tokenA);
    $hashA2 = $tokens->hash($tokenA);
    unitAssert($hashA1 === $hashA2, 'Token hash must be deterministic');
    unitAssert(strlen($hashA1) === 64, 'SHA-256 hash length must be 64 chars');

    $hasher = new PasswordHasher();
    $password = 'UnitTest#Pass123';
    $hash = $hasher->hash($password);

    unitAssert($hash !== '', 'Password hash must not be empty');
    unitAssert($hash !== $password, 'Password hash must not equal plaintext');
    unitAssert($hasher->verify($password, $hash) === true, 'Password verify must pass for valid password');
    unitAssert($hasher->verify('wrong-password', $hash) === false, 'Password verify must fail for invalid password');

    echo "[OK] security_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] security_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
