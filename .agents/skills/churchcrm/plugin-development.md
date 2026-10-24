---
title: "Plugin Development"
intent: "Building a ChurchCRM plugin end-to-end — allowed/forbidden capabilities, hooks, sandboxed config, and plugin-local translations"
tags: ["plugins", "security", "i18n"]
prereqs: ["[[plugin-system]]", "[[plugin-security-scan]]"]
complexity: "intermediate"
---
# Skill: Plugin Development

> [!IMPORTANT] Start with the security scan
> Every community plugin passes [`plugin-security-scan.md`](./plugin-security-scan.md) before it is approved for the URL installer. Treat that checklist as the spec.
>
> A PR to `src/plugins/approved-plugins.json` carries:
>
> 1. A zip built from a tagged release, hosted at an immutable HTTPS URL, with a reproducible SHA-256
> 2. A capability inventory listing every tag from `ApprovedPluginRegistry::KNOWN_PERMISSIONS` the plugin uses
> 3. A `risk` level (`low` / `medium` / `high`) and a one-sentence plain-language `riskSummary`; admins read it verbatim on the install screen
> 4. A `plugin.json` whose `id`, `version`, and `type: "community"` match the registry entry
> 5. Only files that pass `PluginInstaller::assertSafeZipEntry` and `assertAllowedExtension`
> 6. A vulnerability disclosure policy (VDP) URL in the plugin homepage or README
>
> Run the scan's static-analysis commands against your plugin before opening the PR.

Plugin views must follow [Frontend Development](./frontend-development.md): use theme-aware Tabler classes/tokens (not fixed light-only classes such as `bg-light`, `alert-light` or `table-success`) and verify **light and dark** at **desktop, tablet and phone** sizes before submitting.

## Architecture

A WordPress-style plugin system adds functionality without touching core code.

- `src/ChurchCRM/Plugin/`: `PluginManager.php` (static class: discovery, loading, activation, route registration), `AbstractPlugin.php` (base class), `PluginInterface.php` (contract), `PluginMetadata.php` (plugin.json parsing), `Hooks.php` (hook constants)
- `src/ChurchCRM/Plugin/Hook/HookManager.php`: actions and filters

| Type | Path |
|------|------|
| Core | `src/plugins/core/{plugin-name}/` |
| Community | `src/plugins/community/{plugin-name}/` |
| Management UI | `src/plugins/routes/`, `src/plugins/views/` |

```
src/plugins/core/{plugin-name}/
├── plugin.json                  # Manifest (required)
├── src/{PluginName}Plugin.php   # Main class extending AbstractPlugin
├── routes/routes.php            # MVC and API routes (optional)
├── views/*.php                  # View templates (optional)
└── help.json                    # User documentation (optional)
```

Core plugins are the directories under `src/plugins/core/`; read each `plugin.json` for its description, routes, and settings.

## plugin.json Manifest

```json
{
    "id": "mailchimp",
    "name": "MailChimp Integration",
    "description": "Sync contacts with MailChimp mailing lists",
    "version": "1.0.0",
    "author": "ChurchCRM Team",
    "authorUrl": "https://churchcrm.io",
    "type": "core",
    "minimumCRMVersion": "7.0.0",
    "mainClass": "ChurchCRM\\Plugins\\MailChimp\\MailChimpPlugin",
    "dependencies": [],
    "settingsUrl": null,
    "routesFile": "routes/routes.php",
    "settings": [
        {
            "key": "apiKey",
            "label": "API Key",
            "type": "password",
            "required": true,
            "help": "Get from MailChimp settings"
        }
    ],
    "menuItems": [
        {
            "parent": "email",
            "label": "MailChimp Dashboard",
            "url": "/plugins/mailchimp/dashboard",
            "icon": "fa-brands fa-mailchimp",
            "permission": "bEmailMailto"
        }
    ],
    "hooks": ["person.created", "person.updated", "person.deleted"]
}
```

### Menu item permission

The navigation menu renders from the PHP `getMenuItems()`. The `menuItems` block in `plugin.json` is metadata; keep the two in sync.

`permission` is optional. When set, the item shows only to users for whom `User::isEnabledSecurity()` passes; administrators always pass. Valid names: `bAdmin`, `bAll`, `bAddRecords`, `bEditRecords`, `bDeleteRecords`, `bManageGroups`, `bFinance`, `bNotes`, or a per-user setting name such as `bEmailMailto`. An unrecognized name hides the item from non-admins. Without `permission`, every signed-in user sees it.

A hidden menu item is not access control. Give the plugin's routes the matching middleware from `ChurchCRM\Slim\Middleware\Request\Auth\*` (`AdminRoleAuthMiddleware`, `FinanceRoleAuthMiddleware`, `EmailRoleAuthMiddleware`, `ManageGroupRoleAuthMiddleware`, `EditRecordsRoleAuthMiddleware`, `DeleteRecordRoleAuthMiddleware`, `NotesRoleAuthMiddleware`).

```php
$app->get('/my-plugin/dashboard', $handler)->add(AdminRoleAuthMiddleware::class);
```

## Main Class

```php
<?php
namespace ChurchCRM\Plugins\MyPlugin;

use ChurchCRM\Plugin\AbstractPlugin;

class MyPluginPlugin extends AbstractPlugin
{
    private static ?MyPluginPlugin $instance = null;

    public function __construct(string $basePath = '')
    {
        parent::__construct($basePath);
        self::$instance = $this;
    }

    public static function getInstance(): ?MyPluginPlugin
    {
        return self::$instance;
    }

    public function getId(): string { return 'my-plugin'; }
    public function getName(): string { return 'My Plugin'; }
    public function getDescription(): string { return 'Description here'; }

    public function boot(): void
    {
        // Initialize services, register hooks
    }

    public function isConfigured(): bool
    {
        return !empty($this->getConfigValue('apiKey'));
    }

    public function getConfigurationError(): ?string
    {
        return $this->isConfigured() ? null : gettext('API Key is required');
    }

    public function getMenuItems(): array
    {
        return [
            [
                'parent' => 'admin',
                'label' => gettext('My Plugin'),
                'url' => 'plugins/my-plugin/dashboard',
                'icon' => 'fa-plug',
                'permission' => 'bAdmin', // menu visibility only; guard the route with AdminRoleAuthMiddleware
            ],
        ];
    }

    public function getSettingsSchema(): array
    {
        return [
            ['key' => 'apiKey', 'label' => gettext('API Key'), 'type' => 'password', 'required' => true],
        ];
    }
}
```

## Plugin Routes (routes/routes.php)

Routes load only while the plugin is active. Fetch the plugin through its singleton:

```php
<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugins\MyPlugin\MyPluginPlugin;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$plugin = MyPluginPlugin::getInstance();
if ($plugin === null) {
    return;
}

$app->get('/my-plugin/dashboard', function (Request $request, Response $response) use ($plugin): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    return $renderer->render($response, 'dashboard.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('My Plugin Dashboard'),
        'data' => $plugin->getData(),
    ]);
});

$app->group('/my-plugin/api', function (RouteCollectorProxy $group) use ($plugin): void {
    $group->get('/items', function (Request $request, Response $response) use ($plugin): Response {
        return SlimUtils::renderJSON($response, ['data' => $plugin->getItems()]);
    });
});
```

Plugin URLs: `/plugins/management` (admin UI), `/plugins/api/...` (management API, see `src/plugins/routes/api/management.php`), `/plugins/{plugin-name}/*` (plugin routes).

## Member Portal: pages, capabilities and nav

A plugin reaches the Member Portal only through portal capabilities, which the portal permissions model (`ChurchCRM\Portal\Permissions`, member sign-in design §8.5) decides for core and plugins alike.

| Piece | Where | Notes |
|-------|-------|-------|
| Capabilities | `plugin.json` -> `portal.capabilities[]`: `key`, `label`, `description`, `scopeType` | `key` = `plugin.<id>.<name>`; `scopeType` is `none`, `family`, `team`, `ministry`, `group` or the plugin's own word. An undeclared key is always refused |
| Rules | `getPortalRules(): PortalRule[]` (`CallbackPortalRule` for a closure) | evaluated live per request; return `PortalRuleGrant(capability, scope, label)` |
| Pages and API | `plugin.json` -> `portalRoutesFile` | included twice, with `$group` and `$portalArea` (`'pages'` under `/portal/plugins/<id>`, `'api'` under `/api/portal/plugins/<id>`), behind the portal session. Each route adds `RequirePortalCapability` |
| Templates | `portal/templates/*.html.twig`, rendered by `PortalTwig::renderPluginPage()` | extend `layout.html.twig`; `portal_can()` hides sections |
| Nav | `getPortalNavItems(): [{label, url, icon, capability, id?}]` | shown when `scopesFor(capability)` is not empty, before My Family |

Turning the plugin off removes its capabilities from every check, and its rules, pages and nav. Given grants stay stored and work again when it is turned back on; `PluginInstaller::uninstall()` deletes them. Defaults in `AbstractPlugin` return empty lists. The plugin is trusted code; the model gives it one way to decide, not a sandbox.

## What a Plugin Can and Cannot Do

Every plugin is held to this contract. Capability tags match `ApprovedPluginRegistry::KNOWN_PERMISSIONS`; declare each tag the plugin exercises in its approved-registry entry. For a behavior outside this list, open an issue before shipping. New tags are added by PR.

### Allowed (declare the matching tag)

| What the plugin does | Tag |
|----------------------|-----|
| Read/write its own config (`plugin.{id}.*`) via `getConfigValue()` / `setConfigValue()` | none (sandboxed) |
| Call outbound HTTPS APIs | `network.outbound` |
| Expose HTTP routes via `routes/routes.php` | `network.inbound` |
| Read rows via Propel queries | `db.read` |
| Write rows via Propel (`->save()`, `doUpdate`, `doDelete`) | `db.write` |
| Store an API key or secret in its own config | `secrets.store` |
| Inject HTML/JS/CSS via `getHeadContent()` / `getFooterContent()` | `ui.inject` |
| Register a handler on `Hooks::CRON_RUN` | `cron` |
| Subscribe to `PERSON_*` / `FAMILY_*` hooks | `hooks.person` / `hooks.family` |
| Subscribe to `EVENT_*` hooks | `hooks.event` |
| Subscribe to `DONATION_*` / `DEPOSIT_*` hooks | `hooks.financial` |
| Subscribe to `EMAIL_*` hooks | `hooks.email` |
| Send email through ChurchCRM's mailer | `email.send` |
| Send SMS through a configured gateway | `sms.send` |
| Contribute system calendars via `SYSTEM_CALENDARS_REGISTER` | `calendar.register` |
| Ship plugin-local translations | none |
| Read/write files inside its own plugin directory | none |

### Forbidden

`PluginInstaller` and the runtime reject these; the plugin does not install or run.

- Writing PHP files outside the plugin directory (self-updaters, core patches, `file_put_contents()` outside `{pluginDir}`)
- Reading another plugin's config or changing `plugin.{other}.enabled` (the `AbstractPlugin` config helpers are scoped to the plugin id)
- Modifying the `messages` gettext domain or submitting strings to core `locale/messages.po`
- Shipping `.phar`, `.phtml`, `.pht`, `.sh`, `.bat`, `.cmd`, `.exe`, `.so`, `.dll`, or any binary that is not a documented image/font asset (`assertAllowedExtension()`)
- Shipping symlinks, hidden files (except `.editorconfig` / `.gitattributes`), or entries with `..` / absolute paths (`assertSafeZipEntry()`)
- `eval()`, `create_function()`, `assert()` with string arguments, `preg_replace(..., '/e')`, `include $userInput`, `passthru`, `shell_exec`, `proc_open`, `system`, `pcntl_exec`, `extract($_POST)`, `parse_str($_POST)`, `unserialize` on attacker-reachable input. `plugin-security-scan.md` greps for these sinks; each hit needs a documented reason
- `base64_decode()` of a bundled blob to rebuild code at runtime
- Unauthenticated write routes. Every plugin route inherits `AuthMiddleware`, `ChurchInfoRequiredMiddleware`, and `VersionMiddleware` from the plugins entry point; mutating routes stay behind them. Public GET endpoints (status, webhooks) are fine
- Secrets outside `plugin.{id}.{key}` SystemConfig entries (no env files, JSON files, or hardcoded constants). Password-type settings are masked in the admin UI automatically

When in doubt, re-read `plugin-security-scan.md` and run its ripgrep commands against the plugin before opening a PR.

## Plugin Config Access (Sandboxed)

Config helpers read and write keys prefixed `plugin.{id}.`:

```php
$apiKey  = $this->getConfigValue('apiKey');            // plugin.my-plugin.apiKey
$enabled = $this->getBooleanConfigValue('enabled');
$this->setConfigValue('lastSync', date('c'));
```

## Plugin Localization (Independent of POeditor)

Plugin strings ship inside the plugin directory and ChurchCRM loads them automatically. The core POeditor project holds core strings only.

```
plugins/community/my-plugin/
├── plugin.json
├── src/MyPluginPlugin.php
└── locale/
    ├── textdomain/              # PHP gettext (.mo)
    │   ├── en_US/LC_MESSAGES/my-plugin.mo
    │   └── de_DE/LC_MESSAGES/my-plugin.mo
    └── i18n/                    # JavaScript i18next (flat key/value JSON)
        ├── en_US.json
        └── de_DE.json
```

Both subdirectories are optional.

### PHP strings

At boot, `PluginLocalization::bindPhpDomains()` calls `bindtextdomain('{plugin-id}', '.../locale/textdomain')` for each active plugin that ships a textdomain. Use `dgettext()` with the plugin id as the domain (plain `gettext()` / `_()` read the core `messages` domain):

```php
echo dgettext('my-plugin', 'Welcome to My Plugin');
```

Build `.mo` files with `xgettext --keyword=dgettext:2` and `msgfmt`. Ship only the compiled `.mo`.

### JavaScript strings

Ship a flat `key → string` JSON file per locale at `locale/i18n/{locale}.json`. The loader rejects files over 512 KB, falls back to `en_US.json` when the user's locale is missing, and exposes the map as `window.CRM.plugins.{pluginId}.i18n` through `PluginManager::getPluginsClientConfig()`.

```json
{
  "myplugin.welcome": "Willkommen bei My Plugin",
  "myplugin.save": "Speichern"
}
```

```js
const t = (key) => window.CRM?.plugins?.["my-plugin"]?.i18n?.[key] ?? key;
document.getElementById("title").textContent = t("myplugin.welcome");
```

With i18next on the page, register the map as a namespace once at boot:

```js
i18next.addResourceBundle(
  window.CRM.shortLocale,
  "my-plugin",
  window.CRM.plugins["my-plugin"].i18n || {},
  true,
  true
);
i18next.t("myplugin.welcome", { ns: "my-plugin" });
```

Rules:

- Prefix every key with the plugin id (`myplugin.foo`)
- Use flat key/value maps; the loader drops nested objects
- Ship `en_US.json` and an `en_US` `.mo` as the fallback

Code: `src/ChurchCRM/Plugin/PluginLocalization.php`; `PluginManager::loadActivePlugins` (binds textdomains); `PluginManager::getPluginsClientConfig` (injects JS resources).

## Using PluginManager

`PluginManager` is a static class.

```php
use ChurchCRM\Plugin\PluginManager;

PluginManager::init($pluginsPath);                 // once, in src/plugins/index.php

$isActive = PluginManager::isPluginActive('mailchimp');
$plugin = PluginManager::getPlugin('mailchimp');
if ($plugin !== null && $plugin->isConfigured()) {
    $result = $plugin->doSomething();
}

$plugins = PluginManager::getAllPlugins();
PluginManager::enablePlugin('mailchimp');
PluginManager::disablePlugin('mailchimp');
```

## Slim Entry Point (plugins/index.php)

The plugin entry point creates its own Slim app. Call `addErrorMiddleware()` after `addRoutingMiddleware()` (`slim-4-best-practices.md` → Middleware Order). Pass `SystemConfig::debugEnabled()` as `displayErrorDetails`, set a default handler that returns `SlimUtils::renderErrorJSON()`, and catch exceptions in API routes to return sanitized JSON.

```php
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(SystemConfig::debugEnabled(), true, true);
$errorMiddleware->setDefaultErrorHandler(function (Request $request, Throwable $exception) use ($app): Response {
    $response = $app->getResponseFactory()->createResponse();
    return SlimUtils::renderErrorJSON($response, gettext('An error occurred'), [], 500, $exception, $request);
});
```

## Available Hooks

Constants live in `src/ChurchCRM/Plugin/Hooks.php`; each has a docblock with its payload. Entity hooks dispatch from the model lifecycle methods, so they fire on every code path that saves or deletes the record.

| Hook | Receives | Dispatched from |
|------|----------|-----------------|
| `PERSON_CREATED` | `Person $person` | `Person::postInsert()` |
| `PERSON_UPDATED` | `Person $person, array $oldData` | `Person::postUpdate()` |
| `PERSON_DELETED` | `int $personId, array $personData` | `Person::postDelete()` |
| `FAMILY_CREATED` / `_UPDATED` / `_DELETED` | same shapes as Person | `Family::post*()` |
| `EVENT_CREATED` | `Event $event` | bulk creation paths included |
| `EVENT_UPDATED` | `Event $event, array $oldData` | `Event::postUpdate()` |
| `EVENT_DELETED` | `int $eventId, array $eventData` | `Event::postDelete()` |

The `array` payloads are keyed by Propel phpName (`FirstName`, `Email`). The old-data snapshot is taken only when a listener is registered.

Other hooks: `DONATION_RECEIVED`, `DEPOSIT_CLOSED`, `EVENT_CHECKIN`, `EVENT_CHECKOUT`, `SYSTEM_CALENDARS_REGISTER`, `GROUP_MEMBER_ADDED`, `GROUP_MEMBER_REMOVED`, `MENU_BUILDING`, `CRON_RUN`.

## Registering Hooks

```php
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;

public function boot(): void
{
    HookManager::addAction(Hooks::PERSON_UPDATED, [$this, 'onPersonUpdated']);
    HookManager::addAction(Hooks::GROUP_MEMBER_ADDED, [$this, 'onGroupMemberAdded']);
}

public function onPersonUpdated($person, array $oldData): void
{
    if (!$this->isActive()) {
        return;
    }
}
```

### Hook Initialization

Hooks registered in `boot()` fire only after `PluginManager::init()` has run. `PageInit.php` does this for MVC routes and `src/*.php` pages. A CLI script, unit test, or custom entry point calls it before any ORM save:

```php
\ChurchCRM\Plugin\PluginManager::init(SystemURLs::getDocumentRoot() . '/plugins');
$person->setFirstName('Test')->save();
```

## Core Plugins Reference

Core plugins live in `src/plugins/core/`. Each directory's `plugin.json` states its settings, hooks, and `routesFile`; `ls src/plugins/core` lists them.
