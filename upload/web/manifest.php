<?php
declare(strict_types=1);

/**
 * PWA web app manifest, served dynamically so the install dialog and the app
 * shortcut labels follow the CRM locale the user actually chose (the
 * crm_locale cookie — the same one the service worker uses for its fallback
 * page). A static manifest.json can only ever contain one language, and the
 * CRM is multilingual, so this small endpoint renders the manifest per request.
 *
 * All URLs inside are relative, which keeps the manifest valid on any hosting
 * sub-path (shared hosting, no server configuration required).
 *
 * Test hook: set the CRM_MANIFEST_LOCALE env var (e.g. CRM_MANIFEST_LOCALE=ru
 * php upload/web/manifest.php) to force the locale.
 */

const CRM_MANIFEST_LOCALES = [
    'ru' => [
        'description' => 'CRM: задачи, проекты, канбан, календарь и командная работа.',
        'tasks' => 'Задачи',
        'kanban' => 'Канбан',
        'calendar' => 'Календарь',
    ],
    'en' => [
        'description' => 'CRM: tasks, projects, kanban, calendar and team work.',
        'tasks' => 'Tasks',
        'kanban' => 'Kanban',
        'calendar' => 'Calendar',
    ],
    'de' => [
        'description' => 'CRM: Aufgaben, Projekte, Kanban, Kalender und Teamarbeit.',
        'tasks' => 'Aufgaben',
        'kanban' => 'Kanban',
        'calendar' => 'Kalender',
    ],
    'es' => [
        'description' => 'CRM: tareas, proyectos, kanban, calendario y trabajo en equipo.',
        'tasks' => 'Tareas',
        'kanban' => 'Kanban',
        'calendar' => 'Calendario',
    ],
    'fr' => [
        'description' => 'CRM : tâches, projets, kanban, calendrier et travail d’équipe.',
        'tasks' => 'Tâches',
        'kanban' => 'Kanban',
        'calendar' => 'Calendrier',
    ],
    'pt' => [
        'description' => 'CRM: tarefas, projetos, kanban, calendário e trabalho em equipe.',
        'tasks' => 'Tarefas',
        'kanban' => 'Kanban',
        'calendar' => 'Calendário',
    ],
    'zh' => [
        'description' => 'CRM：任务、项目、看板、日历和团队协作。',
        'tasks' => '任务',
        'kanban' => '看板',
        'calendar' => '日历',
    ],
];

function crmManifestPickLocale(): string
{
    $forced = trim((string)getenv('CRM_MANIFEST_LOCALE'));
    if ($forced !== '') {
        $lang = strtolower(explode('-', $forced, 2)[0]);
        if (isset(CRM_MANIFEST_LOCALES[$lang])) {
            return $lang;
        }
    }

    // 1) the user's explicitly chosen CRM locale (cookie set by api.js / the
    // login language switch) wins over the browser's Accept-Language.
    $cookie = isset($_COOKIE['crm_locale']) ? strtolower((string)$_COOKIE['crm_locale']) : '';
    $lang = strtolower(explode('-', $cookie, 2)[0]);
    if (isset(CRM_MANIFEST_LOCALES[$lang])) {
        return $lang;
    }

    // 2) fallback: the browser's Accept-Language, honouring q-priority so that
    // "ru,en;q=0.8" resolves to 'ru' while "en-US,en;q=0.9,ru;q=0.8" → 'en'.
    $accept = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? (string)$_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
    $best = 'en';
    $bestQ = -1.0;
    foreach (explode(',', $accept) as $part) {
        $tokens = array_map('trim', explode(';', $part));
        $tag = strtolower($tokens[0] ?? '');
        if ($tag === '') {
            continue;
        }
        $q = 1.0;
        if (isset($tokens[1]) && preg_match('/q\s*=\s*([0-9.]+)/', $tokens[1], $m)) {
            $q = (float)$m[1];
            if (!is_finite($q) || $q < 0) {
                $q = 0.0;
            }
        }
        if ($q > $bestQ) {
            $bestQ = $q;
            $best = $tag;
        }
    }
    $lang = strtolower(explode('-', $best, 2)[0]);
    return isset(CRM_MANIFEST_LOCALES[$lang]) ? $lang : 'en';
}

$locale = crmManifestPickLocale();
$t = CRM_MANIFEST_LOCALES[$locale];

$icon192 = ['src' => 'assets/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'];

$manifest = [
    'id' => './',
    'name' => 'TropaTT',
    'short_name' => 'TropaTT',
    'description' => $t['description'],
    'lang' => $locale,
    'start_url' => './',
    'scope' => './',
    'display' => 'standalone',
    'orientation' => 'any',
    'background_color' => '#ffffff',
    'theme_color' => '#0f8f72',
    'icons' => [
        $icon192,
        ['src' => 'assets/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => 'assets/icons/icon-512-maskable.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
    'shortcuts' => [
        ['name' => $t['tasks'], 'short_name' => $t['tasks'], 'url' => './index.php?route=tasks', 'icons' => [$icon192]],
        ['name' => $t['kanban'], 'short_name' => $t['kanban'], 'url' => './index.php?route=kanban', 'icons' => [$icon192]],
        ['name' => $t['calendar'], 'short_name' => $t['calendar'], 'url' => './index.php?route=calendar', 'icons' => [$icon192]],
    ],
];

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
