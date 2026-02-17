# Skill: Plugin Development

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

## Plugin Config Access (Sandboxed)

Plugins can only access their own config keys (prefixed with `plugin.{id}.`):

```php
// In your plugin class (extends AbstractPlugin)
$apiKey = $this->getConfigValue('apiKey');     // Gets plugin.my-plugin.apiKey
$enabled = $this->getBooleanConfigValue('enabled');
$this->setConfigValue('lastSync', date('c'));  // Sets plugin.my-plugin.lastSync
```

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

Plugin entry points create their own Slim app instance. **Configure error middleware properly:**

```php
// ✅ CORRECT - Config-driven error display
$displayErrors = SystemConfig::debugEnabled();
$app->addErrorMiddleware($displayErrors, true, true)
    ->setDefaultErrorHandler(function (Request $request, Throwable $exception) use ($app): Response {
        $response = $app->getResponseFactory()->createResponse();
        return SlimUtils::renderErrorJSON(
            $response, 
            gettext('An error occurred'), 
            [], 
            500, 
            $exception, 
            $request
        );
    });

// ❌ WRONG - Always exposes error details (security risk in production)
$app->addErrorMiddleware(true, true, true);  // ❌ Hardcoded true exposes exceptions

// ❌ WRONG - Throws exception which leaks info to client
throw new HttpNotFoundException($request);  // ❌ Use SlimUtils::renderErrorJSON instead
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
- `PERSON_PRE_CREATE`, `PERSON_CREATED`
- `PERSON_PRE_UPDATE`, `PERSON_UPDATED`
- `PERSON_DELETED`, `PERSON_VIEW_TABS`

**Family**
- `FAMILY_PRE_CREATE`, `FAMILY_CREATED`
- `FAMILY_PRE_UPDATE`, `FAMILY_UPDATED`
- `FAMILY_DELETED`, `FAMILY_VIEW_TABS`

**Financial**
- `DONATION_RECEIVED`, `DEPOSIT_CLOSED`

**Events**
- `EVENT_CREATED`, `EVENT_CHECKIN`, `EVENT_CHECKOUT`

**Groups**
- `GROUP_MEMBER_ADDED`, `GROUP_MEMBER_REMOVED`

**Email**
- `EMAIL_PRE_SEND`, `EMAIL_SENT`

**UI/Menu**
- `MENU_BUILDING`, `DASHBOARD_WIDGETS`, `SETTINGS_PANELS`, `ADMIN_PAGE`

**System**
- `SYSTEM_INIT`, `SYSTEM_UPGRADED`, `CRON_RUN`, `API_RESPONSE`

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
