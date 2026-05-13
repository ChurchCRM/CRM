# Skill: Plugin Development

> ## ⚠️ Before you write a single line of plugin code — read this <!-- learned: 2026-04-13 -->
>
> **Every community plugin must pass [`plugin-security-scan.md`](./plugin-security-scan.md) before it can be approved for the URL installer.** That checklist is not a post-hoc review — it's the spec you are building against. Read it first, then come back here.
>
> What it will expect from you, by the time you submit a PR to `src/plugins/approved-plugins.json`:
>
> 1. A zip built from a tagged release, hosted at an immutable HTTPS URL, with a SHA-256 you can reproduce.
> 2. A capability inventory that enumerates every tag from `ApprovedPluginRegistry::KNOWN_PERMISSIONS` the plugin exercises — `network.outbound`, `db.write`, `fs.write`, `secrets.store`, `hooks.person`, `hooks.financial`, etc.
> 3. A `risk` level (`low` / `medium` / `high`) and a one-sentence `riskSummary` written in plain language. Admins will see this verbatim in the install screen — write the sentence you want them to see.
> 4. A `plugin.json` whose `id`, `version`, and `type: "community"` match exactly what the registry entry declares.
> 5. Zero files that would trigger `PluginInstaller::assertSafeZipEntry` or `assertAllowedExtension` — no `.phar`, `.phtml`, `.exe`, `.sh`, symlinks, `..`, or hidden files other than `.editorconfig` / `.gitattributes`.
> 6. A Vulnerability Disclosure Program (VDP) URL in your plugin homepage or README. The 2026 EU plugin rules require one for commercial plugins and we apply the same bar to community submissions.
>
> **Run the scan against your own work before opening the PR.** Nothing is more frustrating than watching a week of development die because a reviewer finds `base64_decode(...)` in your vendor blob. It is *much* cheaper to delete the blob now.

## Context
This skill covers creating, managing, and extending ChurchCRM using the WordPress-style plugin architecture.

## Plugin Architecture

ChurchCRM uses a WordPress-style plugin architecture for extensibility. Plugins can add functionality without modifying core code.

### Core Files (`src/ChurchCRM/Plugin/`)

- **PluginManager.php** - Discovery, loading, activation, route registration (static class)
- **AbstractPlugin.php** - Base class with sensible defaults
- **PluginInterface.php** - Contract all plugins must implement
- **PluginMetadata.php** - Data class for plugin.json manifest parsing
- **Hooks.php** - Constants for available hook points

### Hook System (`src/ChurchCRM/Plugin/Hook/`)

- **HookManager.php** - WordPress-style actions & filters

## Plugin Location

| Type | Path | Description |
|------|------|-------------|
| Core | `src/plugins/core/{plugin-name}/` | Shipped with ChurchCRM |
| Community | `src/plugins/community/{plugin-name}/` | Third-party extensions |
| Management | `src/plugins/routes/`, `src/plugins/views/` | Admin UI for managing plugins |

## Plugin Structure

Each plugin requires this structure:

```
src/plugins/core/{plugin-name}/
├── plugin.json           # Manifest (required)
├── src/
│   └── {PluginName}Plugin.php  # Main class extending AbstractPlugin
├── routes/
│   └── routes.php        # MVC & API routes (optional)
├── views/
│   └── *.php             # View templates (optional)
└── help.json             # User documentation (optional)
```

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

## Creating a Plugin

### 1. Create plugin directory

`src/plugins/core/{plugin-name}/`

### 2. Create plugin.json

With required fields (see manifest example above)

### 3. Create main class extending AbstractPlugin

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
        // Check if required settings have values
        return !empty($this->getConfigValue('apiKey'));
    }

    public function getConfigurationError(): ?string
    {
        if (!$this->isConfigured()) {
            return gettext('API Key is required');
        }
        return null;
    }

    public function getMenuItems(): array
    {
        return [
            [
                'parent' => 'admin',
                'label' => gettext('My Plugin'),
                'url' => 'plugins/my-plugin/dashboard',
                'icon' => 'fa-plug',
            ],
        ];
    }

    public function getSettingsSchema(): array
    {
        return [
            [
                'key' => 'apiKey',
                'label' => gettext('API Key'),
                'type' => 'password',
                'required' => true,
            ],
        ];
    }
}
```

## Plugin Routes (routes/routes.php)

Routes are only loaded when the plugin is active. **Use the singleton pattern:**

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
    return; // Safety check
}

// MVC Route (returns HTML)
$app->get('/my-plugin/dashboard', function (Request $request, Response $response) use ($plugin): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    return $renderer->render($response, 'dashboard.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('My Plugin Dashboard'),
        'data' => $plugin->getData(),
    ]);
});

// API Routes (return JSON)
$app->group('/my-plugin/api', function (RouteCollectorProxy $group) use ($plugin): void {
    $group->get('/items', function (Request $request, Response $response) use ($plugin): Response {
        return SlimUtils::renderJSON($response, ['data' => $plugin->getItems()]);
    });
});
```

## What a Plugin Can and Cannot Do <!-- learned: 2026-04-13 -->

This section is the quick-reference contract every ChurchCRM plugin is
held to. The capability tags match `ApprovedPluginRegistry::KNOWN_PERMISSIONS`
— if you do something on this list, you must declare the corresponding
tag in the approved registry entry. If you want to do something not on
this list, open an issue before shipping.

### ✅ Allowed (declare the matching tag)

| What the plugin does | Tag to declare |
|----------------------|----------------|
| Read its own config (`plugin.{id}.*`) via `$this->getConfigValue()` | *(no tag needed — sandboxed)* |
| Write its own config via `$this->setConfigValue()` | *(no tag needed — sandboxed)* |
| Call outbound HTTPS APIs | `network.outbound` |
| Expose new HTTP routes via `routes/routes.php` | `network.inbound` |
| Read rows via Propel queries (e.g. `PersonQuery::create()->find()`) | `db.read` |
| Write rows via Propel (`->save()`, `doUpdate`, `doDelete`) | `db.write` |
| Store an API key or secret in its own config | `secrets.store` |
| Inject HTML/JS/CSS into core pages via `getHeadContent()` / `getFooterContent()` | `ui.inject` |
| Register a cron handler on `Hooks::CRON_RUN` | `cron` |
| Subscribe to `PERSON_*` or `FAMILY_*` hooks | `hooks.person` / `hooks.family` |
| Subscribe to `DONATION_*` or `DEPOSIT_*` hooks | `hooks.financial` |
| Subscribe to `EMAIL_*` hooks | `hooks.email` |
| Send email through ChurchCRM's mailer | `email.send` |
| Send SMS through a configured gateway | `sms.send` |
| Ship plugin-local translations | *(no tag needed — see Localization section)* |
| Read/write files **inside your own plugin directory** | *(no tag needed — staging only, nothing user-facing)* |

### ❌ Forbidden

These are not reviewer preferences — `PluginInstaller` and the runtime
reject every item on this list. The plugin simply will not install or
run if it violates any of them.

- **Writing any PHP file outside your own plugin directory.** No
  self-updaters, no core patches, no `/tmp` shell helpers, no
  `file_put_contents()` outside `{pluginDir}`.
- **Reading another plugin's config** or tampering with
  `plugin.{other}.enabled`. The `AbstractPlugin` config helpers are
  scoped to your plugin id and cannot reach other keys.
- **Modifying the `messages` gettext domain** or submitting strings to
  the core `locale/messages.po`. Community plugins are out of scope
  for POeditor — use `dgettext('{pluginId}', ...)` and ship `.mo` /
  `.json` inside the plugin directory (see the Localization section).
- **Shipping `.phar`, `.phtml`, `.pht`, `.sh`, `.bat`, `.cmd`, `.exe`,
  `.so`, `.dll`** or any binary that isn't a documented image/font
  asset. `PluginInstaller::assertAllowedExtension()` rejects these.
- **Shipping symlinks, hidden files (except `.editorconfig` /
  `.gitattributes`), or entries containing `..` / absolute paths.**
  `PluginInstaller::assertSafeZipEntry()` rejects these.
- **Calling `eval()`, `create_function()`, `assert()` with string
  arguments, `preg_replace(..., '/e')`, `include $userInput`,
  `passthru`, `shell_exec`, `proc_open`, `system`, `pcntl_exec`,
  `extract($_POST)`, `parse_str($_POST)`, or `unserialize` on any
  attacker-reachable input.** These are the sinks `plugin-security-scan.md`
  greps for, and every hit must have a documented reason or the
  plugin is rejected.
- **Running `base64_decode(...)` on a bundled blob at runtime** to
  reconstitute code. If you need to hide a string, you are doing
  something we do not want to approve.
- **Bypassing the route middleware stack.** Every plugin route inherits
  `AuthMiddleware`, `ChurchInfoRequiredMiddleware`, and
  `VersionMiddleware` from the plugins entry point. Do not try to
  mount raw PHP files under `src/plugins/community/{id}/` and call
  them directly — the installer allows them on disk but they are not
  reachable through the router.
- **Storing secrets anywhere other than `plugin.{id}.{key}` SystemConfig
  entries.** No env files, no JSON in the plugin directory, no
  hardcoded constants. Password-type settings are masked in the admin
  UI automatically.
- **Exposing unauthenticated write routes.** Plugin routes that mutate
  state must honour ChurchCRM's auth middleware. Public GET endpoints
  are fine for status/webhooks; public POST endpoints are not.

### When in doubt

1. Re-read [`plugin-security-scan.md`](./plugin-security-scan.md) — that
   is the checklist every plugin is reviewed against.
2. Run the ripgrep static-analysis commands from section 3 of that
   skill against your own plugin **before** opening a PR. It is much
   faster to delete questionable code now than to argue about it in
   review.
3. If the behaviour you need isn't on the allowed list, open an issue
   on the CRM repo describing the use case. New capability tags are
   added by PR, not by convention.

---

## Plugin Config Access (Sandboxed)

Plugins can only access their own config keys (prefixed with `plugin.{id}.`):

```php
// In your plugin class (extends AbstractPlugin)
$apiKey = $this->getConfigValue('apiKey');     // Gets plugin.my-plugin.apiKey
$enabled = $this->getBooleanConfigValue('enabled');
$this->setConfigValue('lastSync', date('c'));  // Sets plugin.my-plugin.lastSync
```

## Plugin Localization (Independent of POeditor) <!-- learned: 2026-04-13 -->

Community plugins are **never** added to the ChurchCRM POeditor project. If
your plugin needs translated strings you ship them inside the plugin
directory and ChurchCRM picks them up automatically.

### Directory layout

```
plugins/community/my-plugin/
├── plugin.json
├── src/
│   └── MyPluginPlugin.php
└── locale/
    ├── textdomain/              # PHP gettext (.mo) files
    │   ├── en_US/LC_MESSAGES/my-plugin.mo
    │   ├── de_DE/LC_MESSAGES/my-plugin.mo
    │   └── es_ES/LC_MESSAGES/my-plugin.mo
    └── i18n/                    # JavaScript i18next (flat key/value JSON)
        ├── en_US.json
        ├── de_DE.json
        └── es_ES.json
```

Neither subdirectory is required. Plugins with no translations simply omit
`locale/` and everything still works.

### PHP strings (gettext)

At boot, `PluginLocalization::bindPhpDomains()` calls
`bindtextdomain('{your-plugin-id}', '.../locale/textdomain')` for every
active plugin that ships a textdomain. The domain name matches your plugin
id, which keeps your strings isolated from the core `messages` domain.

In your plugin code, use `dgettext()` — **do not** call plain `gettext()`
or `_()`, because those go to the `messages` domain and your strings will
not be found:

```php
// ✅ CORRECT — translates from plugin.my-plugin.mo
echo dgettext('my-plugin', 'Welcome to My Plugin');

// ❌ WRONG — looks up the string in the core messages domain
echo gettext('Welcome to My Plugin');
```

Build the `.mo` files with the standard gettext toolchain
(`xgettext` → `msgfmt`). The `.po` source lives wherever you want inside
the plugin repo — only the compiled `.mo` needs to ship in the zip.

### JavaScript strings (i18next, no core changes needed)

Ship a flat `key → string` JSON file per locale at
`locale/i18n/{locale}.json`. The loader:

1. Rejects files larger than 512 KB.
2. Falls back to `en_US.json` when the user's locale file is missing.
3. Embeds the resulting map in `window.CRM.plugins.{pluginId}.i18n` via
   `PluginManager::getPluginsClientConfig()`.

Your plugin JS then reads strings from that object. No changes to
`locale-loader.js` are required.

```json
// locale/i18n/de_DE.json
{
  "myplugin.welcome": "Willkommen bei My Plugin",
  "myplugin.save": "Speichern",
  "myplugin.cancel": "Abbrechen"
}
```

```js
// Inside your plugin's frontend code
const t = (key) =>
  window.CRM?.plugins?.["my-plugin"]?.i18n?.[key] ?? key;

document.getElementById("title").textContent = t("myplugin.welcome");
```

If you already use i18next elsewhere in the page, register the plugin map
as a namespace once at boot:

```js
i18next.addResourceBundle(
  window.CRM.shortLocale,          // e.g. "de_DE"
  "my-plugin",                     // namespace
  window.CRM.plugins["my-plugin"].i18n || {},
  true,                            // deep
  true                             // overwrite
);

// Then in your code:
i18next.t("myplugin.welcome", { ns: "my-plugin" });
```

### Rules of the road

- **Namespace your keys.** Prefix every JSON key with your plugin id
  (`myplugin.foo`) so you never collide with another plugin.
- **Only flat key/value string maps are accepted.** Nested JSON objects
  are silently dropped by the loader.
- **Fallback must exist.** Always ship `en_US.json` and an `en_US` .mo so
  users on unsupported locales see something usable.
- **Plugins never go through POeditor.** Do not add your strings to the
  main `locale/messages.po` or open a PR to `locale/i18n/*.json` — those
  are the core ChurchCRM domain. Your strings live in your plugin.
- **Audit your extractor.** `xgettext --keyword=dgettext:2` picks up
  plugin strings; the default settings do not.

### Useful code references

- `src/ChurchCRM/Plugin/PluginLocalization.php` — the loader.
- `src/ChurchCRM/Plugin/PluginManager.php::loadActivePlugins` — where PHP
  textdomains are bound.
- `src/ChurchCRM/Plugin/PluginManager.php::getPluginsClientConfig` — where
  JS resources are injected into `window.CRM.plugins.{id}.i18n`.

## Using PluginManager (Static Methods)

```php
use ChurchCRM\Plugin\PluginManager;

// Initialize (done once in src/plugins/index.php)
PluginManager::init($pluginsPath);

// Check plugin status
$isActive = PluginManager::isPluginActive('mailchimp');

// Get plugin instance
$plugin = PluginManager::getPlugin('mailchimp');
if ($plugin !== null && $plugin->isConfigured()) {
    $result = $plugin->doSomething();
}

// Get all plugins for admin UI
$plugins = PluginManager::getAllPlugins();

// Enable/disable plugins
PluginManager::enablePlugin('mailchimp');
PluginManager::disablePlugin('mailchimp');
```

**CRITICAL**: `PluginManager` is a static class. **Never call `PluginManager::getInstance()`** - it doesn't exist.

## Slim Entry Point Configuration (plugins/index.php)

Plugin entry points create their own Slim app instance.

> **Full reference:** [`slim-4-best-practices.md` → Middleware Order](./slim-4-best-practices.md)

**TL;DR:** `addErrorMiddleware()` MUST be called AFTER `addRoutingMiddleware()`. Wrong order → raw 500 on 404s.

```php
// ✅ CORRECT
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(SystemConfig::debugEnabled(), true, true);
$errorMiddleware->setDefaultErrorHandler(function (Request $request, Throwable $exception) use ($app): Response {
    $response = $app->getResponseFactory()->createResponse();
    return SlimUtils::renderErrorJSON($response, gettext('An error occurred'), [], 500, $exception, $request);
});
```

**Guidelines:**
- **Use `SystemConfig::debugEnabled()`** to control `displayErrorDetails` parameter
- **Set custom error handler** that uses `SlimUtils::renderErrorJSON()` for sanitized responses
- **Never throw HTTP exceptions in API routes** - always catch and return sanitized JSON errors

## Plugin URL Structure

| URL Pattern | Purpose |
|-------------|---------|
| `/plugins/management` | Admin UI for managing plugins |
| `/plugins/management/{pluginId}` | Redirects to management with plugin expanded |
| `/plugins/api/plugins` | API: List all plugins |
| `/plugins/api/plugins/{id}/enable` | API: Enable plugin |
| `/plugins/api/plugins/{id}/disable` | API: Disable plugin |
| `/plugins/api/plugins/{id}/settings` | API: Update settings |
| `/plugins/{plugin-name}/*` | Plugin-specific routes |

## Available Hooks

Defined in `src/ChurchCRM/Plugin/Hooks.php`:

**Person**
- `PERSON_CREATED`, `PERSON_UPDATED`, `PERSON_DELETED`

**Family**
- `FAMILY_CREATED`, `FAMILY_UPDATED`, `FAMILY_DELETED`

**Financial**
- `DONATION_RECEIVED`, `DEPOSIT_CLOSED`

**Events**
- `EVENT_CREATED`, `EVENT_CHECKIN`, `EVENT_CHECKOUT`, `SYSTEM_CALENDARS_REGISTER`

**Groups**
- `GROUP_MEMBER_ADDED`, `GROUP_MEMBER_REMOVED`

**UI/Menu**
- `MENU_BUILDING`

**System**
- `CRON_RUN`

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
    // Handle person update
}
```

### Hook Initialization Requirement <!-- learned: 2026-04-24 -->

Hooks registered in `boot()` only fire if `PluginManager::init()` has run **before** the ORM save that triggers the Propel lifecycle hook. For MVC routes, initialization happens automatically via `PageInit.php` before the body parser. Legacy `src/*.php` pages also run `PageInit.php` before form processing. If writing a CLI script, unit test, or custom entry point, call `PluginManager::init()` explicitly before any ORM operation:

```php
\ChurchCRM\Plugin\PluginManager::init(SystemURLs::getDocumentRoot() . '/plugins');
$person->setFirstName('Test')->save(); // Now hooks fire
```

## Core Plugins Reference

| Plugin | Description | Has Routes | Has Views |
|--------|-------------|-----------|-----------|
| `custom-links` | Custom external links in navigation menu | ✅ | ✅ |
| `external-backup` | WebDAV cloud backup (NextCloud, ownCloud, etc.) | ✅ | ✅ |
| `mailchimp` | MailChimp email list integration | ✅ | ✅ |
| `gravatar` | Gravatar profile photos | ❌ | ❌ |
| `google-analytics` | GA4 tracking code injection | ❌ | ❌ |
| `openlp` | OpenLP projector integration | ❌ | ❌ |
| `vonage` | Vonage SMS notifications | ❌ | ❌ |

## Files

**Plugin System:** `src/ChurchCRM/Plugin/`
**Core Plugins:** `src/plugins/core/`
**Community Plugins:** `src/plugins/community/`
**Plugin Routes:** `src/plugins/routes/`
**Plugin Views:** `src/plugins/views/`
**Hook Manager:** `src/ChurchCRM/Plugin/Hook/HookManager.php`
**Available Hooks:** `src/ChurchCRM/Plugin/Hooks.php`
