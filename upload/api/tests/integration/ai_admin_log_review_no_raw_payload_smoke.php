<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $builderPath = __DIR__ . '/../../system/library/service/AdminAiContextBuilder.php';
    assertTrue(is_file($builderPath), 'AdminAiContextBuilder file must exist');
    $source = (string)file_get_contents($builderPath);
    assertTrue($source !== '', 'AdminAiContextBuilder source must be readable');

    assertTrue(str_contains($source, "'security_logs' => \$sanitizedItems"), 'Log review context must use sanitized security log items');
    assertTrue(str_contains($source, "'details' => \$this->masking->maskSensitiveText"), 'Log review details must be masked before AI context');
    assertTrue(str_contains($source, "'payload_masked' => \$this->masking->maskSensitiveText"), 'Workflow payload must be masked before AI context');
    assertTrue(str_contains($source, "'error_masked' => \$this->masking->maskSensitiveText"), 'Workflow run error text must be masked before AI context');
    assertTrue(!str_contains($source, "'request_logs' =>"), 'Admin log review context must not include raw request logs payload');
    assertTrue(!str_contains($source, "'audit_logs' =>"), 'Admin log review context must not include raw audit logs payload');

    fwrite(STDOUT, "[OK] ai_admin_log_review_no_raw_payload_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_admin_log_review_no_raw_payload_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}

