<?php
declare(strict_types=1);

namespace Api\System\Library\Support;

/**
 * Canonical links to the project's public documentation.
 *
 * Agents (Claude Code and other MCP clients) otherwise start a fresh session
 * with no idea where the API/MCP references live and spend a large amount of
 * context re-reading the source tree to reconstruct the contract. The MCP
 * `initialize` response and the public REST `/version` endpoint surface these
 * links so an agent can read the authoritative documentation directly.
 *
 * The default points at the open-source repository. A fork or a private
 * deployment can override the base with the TROPATT_DOCS_URL environment
 * variable (a plain base URL such as https://example.com/docs).
 */
final class Documentation
{
    /** @var array<string,string> language code => path inside the repository */
    private const FILES = [
        'api' => [
            'en' => 'docs_api/api_en.md',
            'ru' => 'docs_api/api_ru.md',
            'zh' => 'docs_api/api_zh.md',
        ],
        'mcp' => [
            'en' => 'docs_mcp/mcp_en.md',
            'ru' => 'docs_mcp/mcp_ru.md',
            'zh' => 'docs_mcp/mcp_zh.md',
        ],
        'modules' => [
            'en' => 'docs_modules/modules_en.md',
            'ru' => 'docs_modules/modules_ru.md',
            'zh' => 'docs_modules/modules_zh.md',
        ],
    ];

    public static function baseUrl(): string
    {
        $override = trim((string)(getenv('TROPATT_DOCS_URL') ?: ''));
        return rtrim($override !== '' ? $override : 'https://github.com/Anton-Barinov/TropaTT', '/');
    }

    private static function isGithub(): bool
    {
        return str_starts_with(self::baseUrl(), 'https://github.com/');
    }

    /**
     * HTML (human) URL for one documentation file.
     */
    public static function htmlUrl(string $topic, string $language = 'en'): string
    {
        $path = self::FILES[$topic][$language] ?? self::FILES[$topic]['en'] ?? '';
        if ($path === '') {
            return self::baseUrl();
        }
        return self::isGithub() ? self::baseUrl() . '/blob/main/' . $path : self::baseUrl() . '/' . $path;
    }

    /**
     * Raw (machine-readable) URL for one documentation file — the form an
     * agent can fetch directly as plain text.
     */
    public static function rawUrl(string $topic, string $language = 'en'): string
    {
        $path = self::FILES[$topic][$language] ?? self::FILES[$topic]['en'] ?? '';
        if ($path === '') {
            return self::baseUrl();
        }
        if (self::isGithub()) {
            return str_replace('https://github.com/', 'https://raw.githubusercontent.com/', self::baseUrl())
                . '/main/' . $path;
        }
        return self::baseUrl() . '/' . $path;
    }

    /**
     * Machine-readable catalogue for the REST `/version` payload and the MCP
     * `tropatt://server/docs` resource.
     *
     * @return array<string,array<string,array{html:string,raw:string}>>
     */
    public static function links(): array
    {
        $out = [];
        foreach (self::FILES as $topic => $languages) {
            foreach (array_keys($languages) as $language) {
                $out[$topic][$language] = [
                    'html' => self::htmlUrl($topic, $language),
                    'raw' => self::rawUrl($topic, $language),
                ];
            }
        }
        return $out;
    }

    /**
     * The `instructions` string returned by the MCP `initialize` response.
     *
     * Kept short on purpose: it is loaded into every agent session. It tells
     * the model where the documentation is and how to use the server, so it
     * does not have to reverse-engineer the API/MCP from the source tree.
     */
    public static function mcpInstructions(string $endpoint = ''): string
    {
        $endpointLine = $endpoint !== ''
            ? "\nEndpoint: POST {$endpoint}\n"
            : '';
        $rawMcp = self::rawUrl('mcp');
        $rawApi = self::rawUrl('api');
        $rawModules = self::rawUrl('modules');
        $htmlDocs = self::isGithub()
            ? self::baseUrl() . '/tree/main/docs_mcp'
            : self::baseUrl();

        return <<<TXT
TropaTT CRM — Model Context Protocol server.$endpointLine
Tools act as the authenticated CRM user with the same RBAC as the web UI.

START WITH THE DOCUMENTATION (do not scan the source tree to learn the contract):
- MCP reference: {$rawMcp}
- REST API reference: {$rawApi}
- Modules SDK: {$rawModules}
- Browsable docs: {$htmlDocs}

Recommended flow:
1. `tools/list` — the default `core` profile returns a small curated set of mega-tools. For one domain append `?toolset=<tasks|projects|kb|people|time|admin|all>` to the endpoint URL, or pass `"params":{"toolset":"<name>"}`.
2. `resources/read` on `tropatt://server/about` for the full usage guide, `tropatt://server/api-map` for the REST capability map and `tropatt://server/api-endpoints` for the route inventory.
3. Use the mega-tools (`crm_task`, `crm_project`, `crm_people`, `crm_crm`, `crm_time`, `crm_knowledge`, `crm_ai`, `crm_admin`) with an `action` argument.
4. Identifiers are public ids: task `tsk_...`, project `prj_...`, user `usr_...`. Confirm destructive actions before running them.
TXT;
    }
}
