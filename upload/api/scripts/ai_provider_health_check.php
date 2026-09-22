<?php
declare(strict_types=1);
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }
$argv ??= $_SERVER['argv'] ?? [];

use Api\Model\Ai\AiProviderRepository;
use Api\Model\Common\UserRepository;
use Api\Model\Feature_flag\FeatureFlagRepository;
use Api\Model\Notification\NotificationRepository;
use Api\Model\Setting\SettingRepository;
use Api\System\Library\Config;
use Api\System\Library\Container;
use Api\System\Library\Database\ConnectionManager;
use Api\System\Library\Http\Request;
use Api\System\Library\Language\LanguageManager;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Service\AiHealthMonitorService;
use Api\System\Library\Service\AiProviderClientFactory;
use Api\System\Library\Service\AiProviderService;
use Api\System\Library\Service\CustomHttpProviderClient;
use Api\System\Library\Service\FeatureFlagService;
use Api\System\Library\Service\MockAiProviderClient;
use Api\System\Library\Service\NotificationService;
use Api\System\Library\Service\OpenAiCompatibleProviderClient;
use Api\System\Library\Service\SettingService;
use Api\System\Library\Support\AppLog;

/**
 * Scheduled AI provider health sweep (TROPATTCRM-623).
 *
 * Probes every active AI provider with a cheap completion, keeps the incident
 * state on the provider row and notifies administrators once per incident and
 * once on recovery. It is a standalone CLI (not the token-authenticated
 * `ai_cron.php` endpoint) on purpose: the monitor has to be able to report that
 * AI is broken even when the web layer is the thing that is broken.
 *
 *   *​/10 * * * * cd /path/to/site && php api/scripts/ai_provider_health_check.php --summary >> storage_api/logs/ai-health.log 2>&1
 *
 * Options:
 *   --force     run even when the ai.health.monitor feature flag is off
 *   --summary   also print the rolling AI usage summary for the window
 *   --hours=N   window for --summary (default 24)
 *   --json      machine-readable output
 *   --dry-run   list what would be probed and exit without probing, notifying
 *               or writing anything (safe smoke check after a deploy)
 */

require_once __DIR__ . '/../system/library/support/Autoloader.php';

$basePath = dirname(__DIR__);
$projectRoot = dirname($basePath);

$autoloader = new Api\System\Library\Support\Autoloader($basePath);
$autoloader->register();

if (class_exists(Api\System\Library\Support\EnvLoader::class)) {
    Api\System\Library\Support\EnvLoader::loadFiles([
        $projectRoot . '/.env',
        $basePath . '/.env',
        $projectRoot . '/.env.local',
        $basePath . '/.env.local',
    ]);
}

$asJson = in_array('--json', $argv, true);
$force = in_array('--force', $argv, true);
$withSummary = in_array('--summary', $argv, true);
$dryRun = in_array('--dry-run', $argv, true);
$hours = 24;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--hours=')) {
        $hours = (int)substr($arg, strlen('--hours='));
    }
}
$hours = max(1, min(720, $hours));

try {
    $config = new Config();
    $config->load($basePath . '/config/default.php', 'default');
    $config->load($basePath . '/config/database.php', 'database');
    $config->load($basePath . '/config/feature_flags.php', 'feature_flags');
    AppLog::bootFromConfig($config, $basePath . '/config/logging.php');

    // Cron has no X-Locale header, so notifications go out in the installation
    // language, exactly like a web request that does not ask for another one.
    $locale = (string)$config->get('default.locale.default', 'en-gb');
    $lang = new LanguageManager($basePath . '/language', (string)$config->get('default.locale.fallback', 'en-gb'));
    $lang->setLocale($locale);

    $logger = new JsonLogger(
        (array)$config->get('logging.channels', []),
        (array)$config->get('logging.mask_keys', [])
    );

    $pdo = (new ConnectionManager($config))->connect();

    $container = new Container();
    $container->set('logger', $logger);
    $container->set('db.pdo', $pdo);

    $container->factory('service.setting', fn(Container $c) => new SettingService(new SettingRepository($c->get('db.pdo'))));

    $container->factory('service.notification', fn(Container $c) => new NotificationService(
        new NotificationRepository($c->get('db.pdo')),
        new UserRepository($c->get('db.pdo')),
        $c->get('logger'),
        null,
        null,
        $lang,
        $c->get('service.setting')
    ));

    $container->factory('service.feature_flag', fn(Container $c) => new FeatureFlagService(
        new FeatureFlagRepository($c->get('db.pdo')),
        $c->get('logger'),
        (array)$config->get('feature_flags.feature_flags', [])
    ));

    // The probe is the same AiProviderService an administrator's manual "check
    // connection" button uses, so the sweep exercises the production code path
    // (secret decryption, URL safety, payload snapshot) rather than a copy of it.
    $container->factory('service.ai_provider', fn(Container $c) => new AiProviderService(
        new AiProviderRepository($c->get('db.pdo')),
        $c->get('service.setting'),
        $c->get('logger'),
        $config,
        new AiProviderClientFactory(
            new OpenAiCompatibleProviderClient(),
            new MockAiProviderClient(),
            new CustomHttpProviderClient()
        ),
        new Request(
            'POST',
            '/cli/ai-provider-health-check',
            '/cli/ai-provider-health-check',
            [],
            [],
            [],
            [],
            [],
            [],
            '',
            'ai-health-cli',
            'ai-health-cli',
            $locale
        )
    ));

    $container->factory('service.ai_health_monitor', fn(Container $c) => new AiHealthMonitorService(
        new AiProviderRepository($c->get('db.pdo')),
        new UserRepository($c->get('db.pdo')),
        $c->get('service.notification'),
        $c->get('logger'),
        $c->get('db.pdo'),
        $c->get('service.feature_flag')
    ));

    /** @var AiHealthMonitorService $monitor */
    $monitor = $container->get('service.ai_health_monitor');

    if (!$monitor->isEnabled() && !$force) {
        $output = ['ok' => true, 'skipped' => 'feature_flag_disabled', 'flag' => AiHealthMonitorService::FLAG_CODE];
        fwrite(STDOUT, $asJson
            ? json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
            : "AI health monitor is disabled ({$output['flag']}); use --force to run anyway\n");
        exit(0);
    }

    if ($dryRun) {
        $planned = [];
        foreach ($monitor->activeProviders() as $provider) {
            $payload = json_decode((string)($provider['provider_payload'] ?? '{}'), true);
            $planned[] = [
                'public_id' => (string)($provider['public_id'] ?? ''),
                'title' => (string)($provider['title'] ?? $provider['provider_code'] ?? ''),
                'last_checked_at' => (string)($payload['health']['last_checked_at'] ?? ''),
            ];
        }

        $output = ['ok' => true, 'dry_run' => true, 'enabled' => $monitor->isEnabled(), 'providers' => $planned];
        fwrite(STDOUT, $asJson
            ? json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
            : sprintf("AI health sweep dry run: monitor %s, %d provider(s) would be probed\n", $output['enabled'] ? 'enabled' : 'disabled', count($planned)));
        exit(0);
    }

    /** @var AiProviderService $providerService */
    $providerService = $container->get('service.ai_provider');
    $serviceActor = ['id' => 0, 'public_id' => 'cron.ai_health', 'is_root' => false];

    $summary = $monitor->runProviderChecks(
        static function (array $provider) use ($providerService, $serviceActor): array {
            $result = $providerService->testConnection((string)($provider['public_id'] ?? ''), $serviceActor);

            return (bool)($result['ok'] ?? false)
                ? ['ok' => true, 'code' => '']
                : ['ok' => false, 'code' => (string)($result['code'] ?? 'AI_PROVIDER_PROBE_FAILED')];
        }
    );

    if ($withSummary) {
        $summary['usage_summary'] = $monitor->usageSummary($hours);
    }

    if ($asJson) {
        fwrite(STDOUT, json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    } else {
        fwrite(STDOUT, sprintf(
            "AI health sweep: %d provider(s) probed, %d failed, %d incident(s), %d recovery(ies), %d notification(s)\n",
            (int)$summary['probed'],
            (int)$summary['failed_probes'],
            count($summary['incidents']),
            count($summary['recoveries']),
            (int)$summary['notified']
        ));

        foreach ($summary['incidents'] as $incident) {
            fwrite(STDOUT, sprintf(
                "  incident: %s %s (error rate %.1f%% of %d calls)\n",
                (string)($incident['provider_public_id'] ?? ''),
                (string)($incident['code'] ?? ''),
                ((float)($incident['error_rate'] ?? 0)) * 100,
                (int)($incident['error_samples'] ?? 0)
            ));
        }

        foreach ($summary['recoveries'] as $recovery) {
            fwrite(STDOUT, sprintf("  recovered: %s\n", (string)($recovery['provider_public_id'] ?? '')));
        }

        if ($withSummary) {
            $usage = (array)($summary['usage_summary'] ?? []);
            fwrite(STDOUT, sprintf(
                "AI usage (last %dh): %d call(s), success rate %.1f%%, avg %d ms, max %d ms\n",
                (int)($usage['window_hours'] ?? $hours),
                (int)($usage['total'] ?? 0),
                ((float)($usage['success_rate'] ?? 1.0)) * 100,
                (int)($usage['avg_latency_ms'] ?? 0),
                (int)($usage['max_latency_ms'] ?? 0)
            ));

            foreach ((array)($usage['top_errors'] ?? []) as $error) {
                fwrite(STDOUT, sprintf(
                    "  errors: %s x%d\n",
                    (string)($error['error_code'] ?? ''),
                    (int)($error['occurrences'] ?? 0)
                ));
            }
        }
    }

    exit(0);
} catch (Throwable $e) {
    // A broken monitor must be visible to cron (non-zero exit) but must never
    // escalate into anything else: nothing here is allowed to take the
    // installation down.
    fwrite(STDERR, '[FAIL] ai_provider_health_check: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
