#!/usr/bin/env php
<?php
declare(strict_types=1);
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }


use Api\System\Library\Config;
use Api\System\Library\Container;
use Api\System\Library\Database\ConnectionManager;
use Api\System\Library\Service\RecurringProcessorService;

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

$config = new Config($basePath . '/config');
$config->load($basePath . '/config/database.php', 'database');
$connectionManager = new ConnectionManager($config);
$pdo = $connectionManager->connect();

$container = new Container();

$container->factory('db.pdo', static fn() => $pdo);

$container->factory('repository.recurring', static fn(Container $c) => new \Api\Model\Recurring\RecurringRepository($c->get('db.pdo')));
$container->factory('repository.task', static fn(Container $c) => new \Api\Model\Task\TaskRepository($c->get('db.pdo')));
$container->factory('repository.project', static fn(Container $c) => new \Api\Model\Project\ProjectRepository($c->get('db.pdo')));
$container->factory('repository.reminder', static fn(Container $c) => new \Api\Model\Reminder\ReminderRepository($c->get('db.pdo')));
$container->factory('repository.calendar_event', static fn(Container $c) => new \Api\Model\Calendar\CalendarEventRepository($c->get('db.pdo')));

$container->factory('service.recurring_processor', static fn(Container $c) => new RecurringProcessorService(
    $c->get('repository.recurring'),
    $c->get('repository.task'),
    $c->get('repository.project'),
    $c->get('repository.reminder'),
    $c->get('repository.calendar_event'),
));

$limit = 20;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = (int)substr($arg, strlen('--limit='));
    }
}
$limit = max(1, min(100, $limit));

/** @var RecurringProcessorService $processor */
$processor = $container->get('service.recurring_processor');
$result = $processor->process($limit);

$output = json_encode([
    'ok' => $result['errors'] === 0,
    'total' => $result['total'],
    'created' => $result['created'],
    'skipped' => $result['skipped'],
    'errors' => $result['errors'],
    'results' => $result['results'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

fwrite(STDOUT, $output . PHP_EOL);

exit($result['errors'] === 0 ? 0 : 1);
