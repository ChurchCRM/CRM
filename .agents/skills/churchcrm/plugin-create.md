---
title: "Plugin Create — Community Plugin Quickstart & Submission"
intent: "Step-by-step flow for building a new community plugin from scratch and getting it added to the approved allowlist"
tags: ["plugins","community","create","submission","security"]
prereqs: ["[[plugin-development]]","[[plugin-security-scan]]"]
complexity: "intermediate"
---

# Plugin Create — Community Plugin Quickstart

Scope: third-party plugins installed into `src/plugins/community/` through the URL installer. Core plugins in `src/plugins/core/` follow [`plugin-migration.md`](./plugin-migration.md).

This is the path from idea to an entry in `approved-plugins.json`. Deeper detail lives in the linked cards.

## 0. Read the contract first

1. [`plugin-development.md → What a Plugin Can and Cannot Do`](./plugin-development.md#what-a-plugin-can-and-cannot-do): the allow/deny list, with each capability mapped to a registry tag.
2. [`plugin-security-scan.md`](./plugin-security-scan.md): the review checklist every submission is measured against.

List the `KNOWN_PERMISSIONS` tags the plugin needs before coding. `hooks.financial` + `network.outbound`, or `fs.write` + `network.outbound`, classify the plugin `high` and need two maintainer reviews.

For plugin UI, follow [Frontend Development](./frontend-development.md): use Tabler theme tokens rather than fixed light-only classes (`bg-light`, `alert-light`, `table-success`), and check **light/dark** at **desktop, tablet and phone** before submitting.

## 1. Scaffold

```bash
php scripts/create-plugin.php my-plugin --author="Your Name"
```

This clones [ChurchCRM/community-plugin-hello-world](https://github.com/ChurchCRM/community-plugin-hello-world) into `src/plugins/community/my-plugin/` and rewrites namespace, id, and class name (forking that repo and renaming works too):

```
src/plugins/community/my-plugin/
├── plugin.json
├── README.md
├── src/MyPluginPlugin.php      # extends AbstractPlugin
└── locale/i18n/en_US.json
```

Optional additions: `routes/routes.php` (Slim routes under `/plugins/{id}/...`), `views/*.php`, `locale/textdomain/{locale}/LC_MESSAGES/{id}.mo`, `help.json` (settings-modal help). Ship few files; review time and attack surface scale with file count.

## 2. `plugin.json`

The manifest declares `type: "community"`; the installer rejects other types.

```json
{
    "id": "my-plugin",
    "name": "My Plugin",
    "description": "One sentence describing what the plugin does.",
    "version": "1.0.0",
    "author": "Your Name",
    "authorUrl": "https://yoursite.example.org",
    "type": "community",
    "minimumCRMVersion": "7.1.0",
    "mainClass": "ChurchCRM\\Plugins\\MyPlugin\\MyPluginPlugin",
    "dependencies": [],
    "settings": [
        {
            "key": "apiKey",
            "label": "API Key",
            "type": "password",
            "required": true,
            "help": "Your upstream API key. Stored encrypted at rest."
        }
    ],
    "menuItems": [],
    "hooks": [],
    "hasTest": false
}
```

- `id` equals the zip's top-level directory name.
- `version` equals the approved registry entry; the installer cross-checks both.
- Password settings are masked in the admin UI; `getClientConfig()` omits raw passwords.
- `minimumCRMVersion` is the oldest ChurchCRM release you test against; the installer enforces it.

Schema: [`plugin-development.md → plugin.json Manifest`](./plugin-development.md#pluginjson-manifest).

## 3. Main class

Extend `AbstractPlugin`, keep the class small, and register service classes from `boot()`.

```php
namespace ChurchCRM\Plugins\MyPlugin;

use ChurchCRM\Plugin\AbstractPlugin;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;

class MyPluginPlugin extends AbstractPlugin
{
    public function getId(): string
    {
        return 'my-plugin';
    }

    public function boot(): void
    {
        HookManager::addAction(Hooks::PERSON_CREATED, [$this, 'onPersonCreated']);
    }

    public function isConfigured(): bool
    {
        return $this->getConfigValue('apiKey') !== '';
    }

    public function onPersonCreated($person): void
    {
        // Every outbound call maps to the `network.outbound` tag in the registry entry.
        // Use one well-named HTTP client so the security scan stays simple.
    }
}
```

Rules:

- Read and write only `plugin.{id}.*` config through `$this->getConfigValue()` / `$this->setConfigValue()`.
- Keep `eval`, `create_function`, string `assert`, `passthru`, `shell_exec`, `proc_open`, `system`, `pcntl_exec`, `extract($_POST)`, `parse_str($_POST)`, and `unserialize` of attacker-reachable input out of plugin code; the scan flags each.
- Write files only inside the plugin directory.
- Ship code as source; the scan flags `base64_decode` of embedded blobs.

## 4. Routes (optional)

Add `routes/routes.php` and set `routesFile` in `plugin.json`. The file loads only while the plugin is active. Plugin routes inherit Auth, ChurchInfoRequired, and Version middleware from the plugin entry point.

```php
use ChurchCRM\Plugin\PluginManager;
use Slim\Routing\RouteCollectorProxy;

$plugin = PluginManager::getPlugin('my-plugin');
if ($plugin === null) { return; }

$app->group('/my-plugin/api', function (RouteCollectorProxy $group) use ($plugin): void {
    $group->get('/items', function ($request, $response) use ($plugin) {
        // ...
    });
});
```

Pattern: [`plugin-development.md → Plugin Routes`](./plugin-development.md#plugin-routes-routesroutesphp).

### Member Portal pages (optional)

A plugin can add pages, API and nav items to the Member Portal. Each names a portal capability, and only members who hold it see it.

```json
"portalRoutesFile": "portal/routes.php",
"portal": {
    "capabilities": [
        { "key": "plugin.my-plugin.roster.view", "label": "See a group's roster",
          "description": "See who is in a group they belong to.", "scopeType": "group" }
    ]
}
```

```php
// src/MyPluginPlugin.php: who holds it (a rule) and where it shows (a nav item)
public function getPortalRules(): array
{
    return [new CallbackPortalRule('plugin.my-plugin.members', fn (Person $person): array => [
        new PortalRuleGrant('plugin.my-plugin.roster.view', PortalScope::group(7), 'Member of Choir'),
    ])];
}

public function getPortalNavItems(): array
{
    return [['label' => 'Rosters', 'url' => '/portal/plugins/my-plugin/rosters',
             'icon' => 'fa-solid fa-list', 'capability' => 'plugin.my-plugin.roster.view']];
}
```

```php
// portal/routes.php: included for pages ($portalArea 'pages', under /portal/plugins/my-plugin)
// and for the API ($portalArea 'api', under /api/portal/plugins/my-plugin)
if ($portalArea === 'pages') {
    $group->get('/rosters/{groupId:[0-9]+}', function ($request, $response) {
        return PortalTwig::renderPluginPage($response, 'my-plugin', 'roster.html.twig', ['pageTitle' => 'Roster']);
    })->add(RequirePortalCapability::onRouteArgument('plugin.my-plugin.roster.view', 'groupId'));
}
```

Templates live in `portal/templates/` and extend the church's portal theme (`{% extends "layout.html.twig" %}`). Keys start with `plugin.<id>.`; an undeclared key is always refused. Turning the plugin off removes all of it; grants an administrator gave stay stored and are deleted on uninstall. Reference: [`plugin-development.md → Member Portal`](./plugin-development.md#member-portal-pages-capabilities-and-nav).

## 5. Translations (optional)

Community plugins keep translations inside the plugin directory.

- PHP: `locale/textdomain/{locale}/LC_MESSAGES/my-plugin.mo`, called with `dgettext('my-plugin', '...')`. Plain `gettext()` and `_()` read the core `messages` domain and miss plugin strings.
- JS: `locale/i18n/{locale}.json` as a flat `key => string` map (the loader drops nested JSON).
- Ship `en_US` in both formats as the fallback.
- Extract with `xgettext --keyword=dgettext:2`.

Guide: [`plugin-development.md → Plugin Localization`](./plugin-development.md#plugin-localization-independent-of-poeditor).

## 6. Self-scan

```bash
php scripts/plugin-scan.php src/plugins/community/my-plugin
php scripts/plugin-scan.php --json src/plugins/community/my-plugin | jq
```

The scan runs the maintainer checklist against your directory. Errors block; fix every one. Warnings need a one-sentence justification in the PR body (why the sink exists, which input it receives); delete what you cannot justify. Infos are the capability inventory for the PR body. Exit code 0 means no errors.

It checks:

- `plugin.json` exists, parses, has required fields, uses a kebab-case id, declares `type: "community"`, and `mainClass` and `routesFile` resolve on disk
- every file has an allowed extension (`.phar`, `.sh`, `.exe`, `.so`, `.dll`, `.phtml` are rejected) and hidden files are limited to `.editorconfig` and `.gitattributes`
- PHP and JS sources have no dangerous sinks (`eval`, `shell_exec`, `passthru`, `proc_open`, `system`, `pcntl_exec`, `extract($_POST)`, `parse_str($_POST)`, `preg_replace(.../e)`, `unserialize`, bundled `base64_decode`)
- translations use `dgettext()`
- every outbound hostname and every literal `file_put_contents()` target is listed, so you can confirm each is covered by `riskSummary` and `network.outbound`, and that writes stay inside the plugin directory

For the rest (runtime smoke test, dependency graph, VDP, capability inventory) follow [`plugin-security-scan.md`](./plugin-security-scan.md) sections 5 and 6.

## 7. Reproducible release zip

The registry pins the zip by SHA-256; changed bytes fail install.

```bash
git tag v1.0.0 && git push --tags

mkdir my-plugin-staging && cd my-plugin-staging
git clone --depth 1 --branch v1.0.0 https://github.com/you/my-plugin
mv my-plugin my-plugin-1.0.0-src
cd my-plugin-1.0.0-src && rm -rf .git .github tests docs-dev && cd ..
zip -r my-plugin-1.0.0.zip my-plugin-1.0.0-src
shasum -a 256 my-plugin-1.0.0.zip
```

The zip has exactly one top-level directory named after the plugin id (`PluginInstaller::assertSafeZipEntry()` enforces it). Host it at an immutable HTTPS URL you control (GitHub release asset, versioned S3 object, CDN).

## 8. Registry PR

Add the entry to the root `approved-plugins.json` on the `External` branch of ChurchCRM/CRM (`plugin-registry.md`):

```json
{
    "id": "my-plugin",
    "name": "My Plugin",
    "version": "1.0.0",
    "downloadUrl": "https://github.com/you/my-plugin/releases/download/v1.0.0/my-plugin-1.0.0.zip",
    "sha256": "<hex digest from step 7>",
    "risk": "medium",
    "riskSummary": "POSTs member email + name to api.example.org on Person create/update hooks. Stores the upstream API key in plugin config.",
    "permissions": ["network.outbound", "secrets.store", "hooks.person", "hooks.family"],
    "minimumCRMVersion": "7.1.0",
    "author": "Your Name",
    "homepage": "https://github.com/you/my-plugin",
    "reviewedAt": "YYYY-MM-DD",
    "notes": "Outbound HTTPS only to api.example.org. No DB writes outside plugin config."
}
```

The PR body carries:

1. The capability inventory table ([`plugin-security-scan.md → Permission / capability inventory`](./plugin-security-scan.md#4-permission--capability-inventory)), every cell with evidence.
2. The static-analysis summary from step 6.
3. Runtime smoke test results: install, enable, and exercise every menu item, hook, and route in a Docker CRM instance.
4. The Vulnerability Disclosure Policy URL on the plugin homepage (required).

A maintainer re-runs the scan and approves (one reviewer for low/medium risk, two for high), requests changes, or rejects. Reviews focus on the behaviour-to-tag mapping and the `riskSummary` wording, which end users read on the install screen.

## 9. Keeping a plugin approved

- Every version bump is reviewed from scratch.
- Update `sha256` in each release PR.
- Drop your registry entry yourself and notify the forum when a vulnerability is found.
- Publish a `SECURITY.md` with a disclosure contact and response SLA.

## 10. Where things live

- `src/plugins/community/`: runtime home for installed plugins (the installer owns it; delete `src/plugins/community/{id}/` before reinstalling, because the installer refuses to overwrite)
- `src/ChurchCRM/Plugin/PluginInstaller.php`: install-time enforcement
- `src/ChurchCRM/Plugin/ApprovedPluginRegistry.php`: loads and validates entries
- `src/ChurchCRM/Plugin/PluginLocalization.php`: plugin-local translations

Related: [Plugin Development](./plugin-development.md), [Plugin System](./plugin-system.md), [Plugin Registry](./plugin-registry.md), [Plugin Security Scan](./plugin-security-scan.md), [Plugin Compliance](./plugin-compliance.md).
