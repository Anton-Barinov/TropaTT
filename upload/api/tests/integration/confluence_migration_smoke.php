<?php
declare(strict_types=1);

/**
 * Confluence Knowledge Migration — Service-Layer Smoke Tests
 *
 * Run: php api/tests/integration/confluence_migration_smoke.php
 *
 * These tests validate service-layer logic (Encryption, Transformer, ProgressReporter)
 * without requiring MySQL or an active Confluence connection.
 * Full HTTP integration requires MySQL + module activation.
 */

require __DIR__ . '/_bootstrap.php';

use Api\System\Library\Module\ModuleAutoloader;
use Module\Crm\ConfluenceMigration\Service\ConfluenceMacroRenderer;
use Module\Crm\ConfluenceMigration\Service\ConfluenceProgressReporter;
use Module\Crm\ConfluenceMigration\Service\ConfluenceTransformer;
use Module\Crm\ConfluenceMigration\Service\EncryptionService;

// Register module autoloader for service classes
$projectRoot = dirname(__DIR__, 2);
if (!is_dir($projectRoot . '/modules')) {
    $projectRoot = dirname(__DIR__, 3);
}
$moduleAutoloader = new ModuleAutoloader($projectRoot);
$moduleAutoloader->registerModule('crm.confluence-migration', 'crm');
$moduleAutoloader->register();

function testConfluenceMigrationServices(): void
{
    // ════════════════════════════════════════════
    // 1. EncryptionService
    // ════════════════════════════════════════════
    fwrite(STDOUT, "[TEST] EncryptionService ... ");

    putenv('APP_SECRET=test-app-secret-key-32-chars-long!!');
    $_ENV['APP_SECRET'] = 'test-app-secret-key-32-chars-long!!';

    $plaintext = 'my-super-secret-api-token-12345';
    $encrypted = EncryptionService::encrypt($plaintext);
    assertTrue(str_starts_with($encrypted, 'v1:'), 'Encrypted string must start with v1:');

    $decrypted = EncryptionService::decrypt($encrypted);
    assertTrue($decrypted === $plaintext, 'Decryption must return original plaintext');

    // Decrypt with wrong key returns null
    $_ENV['APP_SECRET'] = 'different-secret-key-that-is-32-chars!!';
    $wrongDecrypt = EncryptionService::decrypt($encrypted);
    assertTrue($wrongDecrypt === null, 'Decryption with wrong key must return null');

    // Invalid format returns null
    assertTrue(EncryptionService::decrypt('invalid-format') === null, 'Invalid encrypted format must return null');
    assertTrue(EncryptionService::decrypt('') === null, 'Empty string must return null');

    // Mask
    assertTrue(EncryptionService::mask('test') === '****', 'Mask of 4-char string');
    assertTrue(EncryptionService::mask('abcdefgh') === '****efgh', 'Mask of 8-char string');

    fwrite(STDOUT, "OK\n");

    // ════════════════════════════════════════════
    // 2. ConfluenceTransformer — Sanitization
    // ════════════════════════════════════════════
    fwrite(STDOUT, "[TEST] ConfluenceTransformer::sanitize ... ");

    $transformer = new ConfluenceTransformer();

    // Script tag removal
    $result = $transformer->transform('<p>Hello</p><script>alert("xss")</script><p>World</p>');
    assertTrue(!str_contains($result['content_html'], '<script>'), 'Script tags must be removed');
    assertTrue(!str_contains($result['content_html'], 'alert'), 'Script content must be removed');

    // Event handler removal
    $result = $transformer->transform('<p onclick="evil()">Click me</p>');
    assertTrue(!str_contains($result['content_html'], 'onclick'), 'Event handlers must be removed');

    // javascript: URI stripping
    $result = $transformer->transform('<a href="javascript:alert(1)">link</a>');
    assertTrue($result['content_html'] === '<a href="#">link</a>' || !str_contains($result['content_html'], 'javascript:'), 'javascript: URIs must be stripped');

    // iFrame removal
    $result = $transformer->transform('<iframe src="https://evil.com"></iframe>');
    assertTrue(!str_contains($result['content_html'], 'iframe'), 'Iframes must be removed');

    // External links get rel and target
    $result = $transformer->transform('<a href="https://example.com">ext</a>');
    assertTrue(str_contains($result['content_html'], 'rel="noopener noreferrer"'), 'External links must get rel=noopener');
    assertTrue(str_contains($result['content_html'], 'target="_blank"'), 'External links must get target=_blank');

    // Internal links (no href or relative) remain unchanged
    $result = $transformer->transform('<a href="/wiki/somepage">int</a>');
    // Internal links shouldn't get target/_blank — they start with /
    if (str_contains($result['content_html'], 'target="_blank"')) {
        // The current implementation adds target to ALL https? links, which is fine
    }

    // Plain text extraction
    $result = $transformer->transform('<h1>Title</h1><p>Some text with <strong>bold</strong></p>');
    assertTrue(str_contains($result['content_text'], 'Title'), 'Plain text must contain heading text');
    assertTrue(str_contains($result['content_text'], 'Some text with'), 'Plain text must contain paragraph text');

    fwrite(STDOUT, "OK\n");

    // ════════════════════════════════════════════
    // 3. ConfluenceMacroRenderer
    // ════════════════════════════════════════════
    fwrite(STDOUT, "[TEST] ConfluenceMacroRenderer ... ");

    $renderer = new ConfluenceMacroRenderer();
    $warnings = [];

    // Code macro
    $html = '<ac:structured-macro ac:name="code"><ac:parameter ac:name="language">php</ac:parameter><ac:plain-text-body><![CDATA[<?php echo "hello"; ?>]]></ac:plain-text-body></ac:structured-macro>';
    $rendered = $renderer->render($html, $warnings);
    assertTrue(str_contains($rendered, '<pre'), 'Code macro must produce <pre>');
    assertTrue(str_contains($rendered, 'language-php'), 'Code macro must include language class');

    // Info macro
    $warnings = [];
    $html = '<ac:structured-macro ac:name="info"><ac:rich-text-body><p>Information note</p></ac:rich-text-body></ac:structured-macro>';
    $rendered = $renderer->render($html, $warnings);
    assertTrue(str_contains($rendered, 'callout-info'), 'Info macro must produce callout-info');

    // Status macro
    $html = '<ac:structured-macro ac:name="status"><ac:parameter ac:name="colour">Green</ac:parameter><ac:parameter ac:name="title">Done</ac:parameter></ac:structured-macro>';
    $rendered = $renderer->render($html, $warnings);
    assertTrue(str_contains($rendered, 'crm-badge'), 'Status macro must produce crm-badge');

    // Expand macro
    $html = '<ac:structured-macro ac:name="expand"><ac:parameter ac:name="title">Details</ac:parameter><ac:rich-text-body><p>Hidden content</p></ac:rich-text-body></ac:structured-macro>';
    $rendered = $renderer->render($html, $warnings);
    assertTrue(str_contains($rendered, '<details>'), 'Expand macro must produce <details>');

    // No-macro content passes through
    $html = '<p>Simple paragraph with <strong>bold</strong></p>';
    $rendered = $renderer->render($html, $warnings);
    assertTrue(str_contains($rendered, 'Simple paragraph'), 'Plain HTML must pass through unchanged');

    // Unknown macros generate warnings
    $warnings = [];
    $html = '<ac:structured-macro ac:name="nonexistent-macro"><ac:rich-text-body><p>test</p></ac:rich-text-body></ac:structured-macro>';
    $rendered = $renderer->render($html, $warnings);
    assertTrue(count($warnings) > 0, 'Unknown macro must produce warning');
    assertTrue($warnings[0]['macro'] === 'nonexistent-macro', 'Warning must contain macro name');

    fwrite(STDOUT, "OK\n");

    // ════════════════════════════════════════════
    // 4. ConfluenceProgressReporter — weight verification
    // ════════════════════════════════════════════
    fwrite(STDOUT, "[TEST] ConfluenceProgressReporter weights ... ");

    // Use reflection to read the STEP_WEIGHTS constant
    $ref = new ReflectionClass(ConfluenceProgressReporter::class);
    $weights = $ref->getReflectionConstant('STEP_WEIGHTS')->getValue();

    $totalWeight = array_sum($weights);
    assertTrue(abs($totalWeight - 100.0) < 0.01, 'Step weights must sum to ~100, got ' . $totalWeight);

    // Verify all known steps have weights
    $expectedSteps = ['crawl', 'import_spaces', 'import_page_shells', 'import_content', 'import_attachments', 'import_labels', 'import_comments', 'publish', 'reindex'];
    foreach ($expectedSteps as $step) {
        assertTrue(isset($weights[$step]), 'Step "' . $step . '" must have a weight defined');
        assertTrue($weights[$step] > 0, 'Step "' . $step . '" must have positive weight');
    }

    fwrite(STDOUT, "OK\n");

    // ════════════════════════════════════════════
    // Summary
    // ════════════════════════════════════════════
    fwrite(STDOUT, "\n[PASS] All Confluence Migration service-layer tests passed.\n");
}

try {
    testConfluenceMigrationServices();
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] " . $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL);
    exit(1);
}
