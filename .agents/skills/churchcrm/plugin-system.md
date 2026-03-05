# Plugin System & Extensibility

Comprehensive guide to ChurchCRM's WordPress-style plugin architecture. Plugins enable core functionality extension without modifying source code.

---

## Overview

ChurchCRM uses a plugin system for:
- **Extensibility**: Add features without forking
- **Isolation**: Plugin code separate from core
- **Activation**: Enable/disable plugins dynamically
- **Settings**: Configuration per plugin
- **Hooks**: React to system events

---

## Architecture

### Core Components

| Component | Location | Purpose |
|-----------|----------|---------|
| **PluginManager** | `src/ChurchCRM/Plugin/PluginManager.php` | Discovery, loading, activation (static class) |
| **AbstractPlugin** | `src/ChurchCRM/Plugin/AbstractPlugin.php` | Base class for all plugins |
| **PluginInterface** | `src/ChurchCRM/Plugin/PluginInterface.php` | Plugin contract/interface |
| **HookManager** | `src/ChurchCRM/Plugin/Hook/HookManager.php` | WordPress-style actions & filters |
| **Hooks** | `src/ChurchCRM/Plugin/Hooks.php` | Hook point constants |

### Plugin Locations

| Type | Path | Scope |
|------|------|-------|
| **Core** | `src/plugins/core/{plugin-name}/` | Shipped with ChurchCRM |
| **Community** | `src/plugins/community/{plugin-name}/` | Third-party extensions (future) |
| **Management Routes** | `src/plugins/routes/` | Admin UI for managing plugins |
| **Management Views** | `src/plugins/views/` | Plugin list/settings templates |

---

## Plugin Structure

### Required Files

```
src/plugins/core/my-plugin/
├── plugin.json                           # Manifest (REQUIRED)
├── src/
│   └── MyPluginPlugin.php               # Main class extending AbstractPlugin
├── routes/
│   └── routes.php                       # MVC & API routes (optional)
├── views/
│   └── *.php                            # View templates (optional)
└── help.json                            # Documentation (optional)
```

### plugin.json Manifest

Complete manifest with all available options:

```json
{
    "id": "mailchimp",
    "name": "MailChimp Integration",
    "description": "Sync ChurchCRM contacts with MailChimp mailing lists",
    "version": "1.0.0",
    "author": "ChurchCRM Team",
    "authorUrl": "https://churchcrm.io",
    "type": "core",
    "minimumCRMVersion": "7.0.0",
    "mainClass": "ChurchCRM\\Plugins\\MailChimp\\MailChimpPlugin",
    "dependencies": [],
    "settingsUrl": "/plugins/mailchimp/settings",
    "routesFile": "routes/routes.php",
    "settings": [
        {
            "key": "apiKey",
            "label": "MailChimp API Key",
            "type": "password",
            "required": true,
            "help": "Get from MailChimp account settings"
        },
        {
            "key": "syncInterval",
            "label": "Sync Interval (hours)",
            "type": "number",
            "required": false,
            "default": "24",
            "help": "Automatic sync frequency"
        },
        {
            "key": "enableSync",
            "label": "Enable Auto Sync",
            "type": "checkbox",
            "required": false,
            "default": "false"
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
    "hooks": [
        "person.created",
        "person.updated",
        "person.deleted",
        "email.sent"
    ]
}
```

**Manifest Fields:**
- `id` - Unique slug (kebab-case)
- `name` - Display name
- `description` - Plugin purpose
- `version` - Semantic versioning
- `type` - `"core"` or `"community"`
- `minimumCRMVersion` - Minimum CRM version required
- `mainClass` - Full class name with namespace
- `settings` - Configuration schema (array of setting objects)
- `menuItems` - Navigation menu entries
- `hooks` - Hook points this plugin uses

---

## Plugin Class Structure

### Creating a Plugin

```php
<?php
namespace ChurchCRM\Plugins\MailChimp;

use ChurchCRM\Plugin\AbstractPlugin;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;

class MailChimpPlugin extends AbstractPlugin
{
    private static ?MailChimpPlugin $instance = null;
    private ?MailChimpService $service = null;

    // Singleton pattern
    public function __construct(string $basePath = '')
    {
        parent::__construct($basePath);
        self::$instance = $this;
    }

    public static function getInstance(): ?MailChimpPlugin
    {
        return self::$instance;
    }

    // Plugin metadata (required methods)
    public function getId(): string
    {
        return 'mailchimp';
    }

    public function getName(): string
    {
        return 'MailChimp Integration';
    }

    public function getDescription(): string
    {
        return 'Sync ChurchCRM contacts with MailChimp mailing lists';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    // Initialization (called when plugin is loaded)
    public function boot(): void
    {
        // Lazy-load service
        $this->service = new MailChimpService($this);

        // Register event handlers
        HookManager::addAction(Hooks::PERSON_CREATED, [$this, 'onPersonCreated']);
        HookManager::addAction(Hooks::PERSON_UPDATED, [$this, 'onPersonUpdated']);
        HookManager::addAction(Hooks::PERSON_DELETED, [$this, 'onPersonDeleted']);
        HookManager::addAction(Hooks::EMAIL_SENT, [$this, 'onEmailSent']);
    }

    // Configuration checking
    public function isConfigured(): bool
    {
        // Check if required settings have values
        return !empty($this->getConfigValue('apiKey'));
    }

    public function getConfigurationError(): ?string
    {
        if (!$this->isConfigured()) {
            return gettext('MailChimp API Key is required for operation');
        }

        // Additional validation
        if (!$this->validateApiKey($this->getConfigValue('apiKey'))) {
            return gettext('Provided API Key is invalid or has expired');
        }

        return null;
    }

    // Menu items (appear in navigation)
    public function getMenuItems(): array
    {
        if (!$this->isActive()) {
            return [];
        }

        return [
            [
                'parent' => 'email',
                'label' => gettext('MailChimp Dashboard'),
                'url' => 'plugins/mailchimp/dashboard',
                'icon' => 'fa-brands fa-mailchimp',
                'permission' => 'bEmailMailto'
            ],
            [
                'parent' => 'email',
                'label' => gettext('Sync Settings'),
                'url' => 'plugins/mailchimp/settings',
                'icon' => 'fa-cog',
                'permission' => 'bEmailMailto'
            ]
        ];
    }

    // Settings schema for admin panel
    public function getSettingsSchema(): array
    {
        return [
            [
                'key' => 'apiKey',
                'label' => gettext('API Key'),
                'type' => 'password',
                'required' => true,
                'help' => gettext('Get from MailChimp account settings')
            ],
            [
                'key' => 'listId',
                'label' => gettext('Default List'),
                'type' => 'select',
                'options' => $this->getAvailableLists(),
                'required' => true
            ],
            [
                'key' => 'autoSync',
                'label' => gettext('Automatically sync contacts'),
                'type' => 'checkbox',
                'required' => false
            ]
        ];
    }

    // Hook handlers
    public function onPersonCreated($person): void
    {
        if (!$this->isActive() || !$this->isConfigured()) {
            return;
        }

        try {
            $this->service->syncPerson($person, 'create');
        } catch (\Exception $e) {
            error_log("MailChimp sync error: " . $e->getMessage());
        }
    }

    public function onPersonUpdated($person, array $oldData): void
    {
        if (!$this->isActive() || !$this->isConfigured()) {
            return;
        }

        // Only sync if relevant fields changed
        $relevantFields = ['FirstName', 'LastName', 'Email', 'Phone'];
        $changed = false;

        foreach ($relevantFields as $field) {
            if (($oldData[$field] ?? null) !== $person->{'get' . $field}()) {
                $changed = true;
                break;
            }
        }

        if ($changed) {
            $this->service->syncPerson($person, 'update');
        }
    }

    public function onPersonDeleted($person): void
    {
        if (!$this->isActive() || !$this->isConfigured()) {
            return;
        }

        $this->service->syncPerson($person, 'delete');
    }

    public function onEmailSent($email, $recipients): void
    {
        if (!$this->isActive() || !$this->isConfigured()) {
            return;
        }

        // Log email in MailChimp
        $this->service->logEmailActivity($email, $recipients);
    }
}
```

---

## Plugin Routes & Views

### Defining Routes

Routes are only loaded when plugin is active. Use singleton pattern:

```php
<?php
// src/plugins/core/mailchimp/routes/routes.php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugins\MailChimp\MailChimpPlugin;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

return function ($app): void {
    $plugin = MailChimpPlugin::getInstance();
    if ($plugin === null || !$plugin->isActive()) {
        return; // Plugin not active
    }

    // MVC Route (returns HTML)
    $app->get('/plugins/mailchimp/dashboard', function (Request $request, Response $response) use ($plugin): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        
        return $renderer->render($response, 'dashboard.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('MailChimp Dashboard'),
            'plugin' => $plugin,
            'stats' => $plugin->getService()->getDashboardStats(),
            'syncStatus' => $plugin->getService()->getSyncStatus()
        ]);
    });

    // Settings page
    $app->get('/plugins/mailchimp/settings', function (Request $request, Response $response) use ($plugin): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        
        return $renderer->render($response, 'settings.php', [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('MailChimp Settings'),
            'plugin' => $plugin,
            'settingsSchema' => $plugin->getSettingsSchema()
        ]);
    });

    // API route (JSON response)
    $app->post('/plugins/mailchimp/api/sync', function (Request $request, Response $response) use ($plugin): Response {
        try {
            $result = $plugin->getService()->triggerManualSync();
            return SlimUtils::renderJSON($response, ['data' => $result]);
        } catch (\Exception $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Sync failed'), [], 500, $e, $request);
        }
    });

    // API route for saving settings
    $app->post('/plugins/mailchimp/api/settings', function (Request $request, Response $response) use ($plugin): Response {
        try {
            $body = $request->getParsedBody();
            
            // Validate settings
            if (empty($body['apiKey'])) {
                return SlimUtils::renderErrorJSON($response, gettext('API Key is required'), [], 400);
            }

            // Save to plugin config
            foreach ($body as $key => $value) {
                $plugin->setConfigValue($key, $value);
            }

            return SlimUtils::renderJSON($response, ['data' => ['status' => 'saved']]);
        } catch (\Exception $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to save settings'), [], 500, $e, $request);
        }
    });
};
```

### Plugin View Example

```php
<?php
// src/plugins/core/mailchimp/views/dashboard.php
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div>
    <h1><?= InputUtils::escapeHTML($sPageTitle) ?></h1>

    <?php if ($plugin->getConfigurationError()): ?>
        <div class="alert alert-warning">
            <i class="fa fa-warning"></i>
            <?= InputUtils::escapeHTML($plugin->getConfigurationError()) ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-3">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3><?= $stats['totalContacts'] ?></h3>
                    <p><?= gettext('Synced Contacts') ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= gettext('Sync Status') ?></h3>
        </div>
        <div class="card-body">
            <p><?= gettext('Last Sync') ?>: <?= $syncStatus['lastSync']?->format('Y-m-d H:i') ?? gettext('Never') ?></p>
            <button id="sync-button" class="btn btn-primary">
                <i class="fa fa-sync"></i> <?= gettext('Sync Now') ?>
            </button>
        </div>
    </div>
</div>

<script>
document.getElementById('sync-button').addEventListener('click', function() {
    fetch(window.CRM.root + '/plugins/mailchimp/api/sync', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        window.CRM.notify(i18next.t('Sync completed'), {type: 'success'});
    })
    .catch(error => {
        window.CRM.notify(i18next.t('Sync failed'), {type: 'error'});
    });
});
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
```

---

## Configuration Management

### Accessing Settings

Plugins can ONLY access their own config (prefixed with `plugin.{id}.`):

```php
// Inside plugin class
$apiKey = $this->getConfigValue('apiKey');          // Gets plugin.mailchimp.apiKey
$enabled = $this->getBooleanConfigValue('autoSync'); // Gets boolean value
$this->setConfigValue('lastSync', date('c'));        // Sets plugin.mailchimp.lastSync
```

**Configuration Sandbox:**
- Plugins cannot read/write `SystemConfig` keys directly
- All plugin settings stored under `plugin.{plugin_id}.{setting_key}`
- Automatic prefixing prevents conflicts between plugins

---

## Hook System

### Available Hook Points

**Person Lifecycle:**
- `PERSON_PRE_CREATE` - Before person is created (pre-save, can prevent)
- `PERSON_CREATED` - After person is created
- `PERSON_PRE_UPDATE` - Before person is updated
- `PERSON_UPDATED` - After person is updated
- `PERSON_DELETED` - After person is deleted
- `PERSON_VIEW_TABS` - Add tabs to person profile

**Family Lifecycle:**
- `FAMILY_PRE_CREATE`, `FAMILY_CREATED`, `FAMILY_PRE_UPDATE`, `FAMILY_UPDATED`, `FAMILY_DELETED`, `FAMILY_VIEW_TABS`

**Financial:**
- `DONATION_RECEIVED` - When donation is recorded
- `DEPOSIT_CLOSED` - When deposit is finalized

**Events:**
- `EVENT_CREATED` - When event is created
- `EVENT_CHECKIN` - When person checks in
- `EVENT_CHECKOUT` - When person checks out

**Groups:**
- `GROUP_MEMBER_ADDED` - When person added to group
- `GROUP_MEMBER_REMOVED` - When person removed from group

**Email:**
- `EMAIL_PRE_SEND` - Before email is sent (can modify)
- `EMAIL_SENT` - After email is sent

**UI/Menu:**
- `MENU_BUILDING` - Building navigation menu
- `DASHBOARD_WIDGETS` - Adding dashboard widgets
- `SETTINGS_PANELS` - Adding settings to admin panel
- `ADMIN_PAGE` - Rendering admin pages

**System:**
- `SYSTEM_INIT` - System initialization
- `SYSTEM_UPGRADED` - After version upgrade
- `CRON_RUN` - Periodic task execution
- `API_RESPONSE` - Before API response sent

### Registering Hooks

```php
// In plugin's boot() method
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;

public function boot(): void
{
    // Action hook (no return value needed)
    HookManager::addAction(Hooks::PERSON_UPDATED, [$this, 'onPersonUpdated']);

    // Filter hook (return value required)
    HookManager::addFilter(Hooks::EMAIL_PRE_SEND, [$this, 'filterEmailBody']);
}

// Handler methods
public function onPersonUpdated($person, array $oldData): void
{
    // Handle person update
}

public function filterEmailBody($emailBody, $recipient): string
{
    // Modify email body before sending
    return str_replace('{{placeholders}}', 'values', $emailBody);
}
```

---

## Using PluginManager

PluginManager is a **static class** (NOT a singleton - no getInstance() method).

```php
use ChurchCRM\Plugin\PluginManager;

// Initialize plugins (done once in src/plugins/index.php)
PluginManager::init($pluginsPath);

// Check if plugin is active
if (PluginManager::isPluginActive('mailchimp')) {
    // do something
}

// Get plugin instance
$plugin = PluginManager::getPlugin('mailchimp');
if ($plugin !== null && $plugin->isConfigured()) {
    $result = $plugin->getService()->doSomething();
}

// Get all active plugins
$plugins = PluginManager::getAllPlugins();
foreach ($plugins as $plugin) {
    if ($plugin->isActive()) {
        // do something
    }
}

// Enable/disable plugins
PluginManager::enablePlugin('mailchimp');
PluginManager::disablePlugin('mailchimp');

// Uninstall (removes plugin files)
PluginManager::uninstallPlugin('mailchimp');
```

---

## Error Handling (Plugin Entry Points)

When plugins have their own Slim app entry point, handle errors properly:

```php
<?php
// src/plugins/core/my-plugin/index.php
use Slim\Factory\AppFactory;

$app = AppFactory::create();

// CORRECT - Config-driven error display
$displayErrors = \ChurchCRM\Utils\SystemConfig::debugEnabled();
$app->addErrorMiddleware($displayErrors, true, true)
    ->setDefaultErrorHandler(function ($request, $exception) use ($app): Response {
        $response = $app->getResponseFactory()->createResponse();
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('Plugin error occurred'),
            [],
            500,
            $exception,
            $request
        );
    });

// Load routes
$app->group('', require __DIR__ . '/routes/routes.php');

$app->run();
```

**Key Points:**
- Use `SystemConfig::debugEnabled()` to control error detail display
- Always use `SlimUtils::renderErrorJSON()` for consistent error responses
- Never throw exceptions directly in API routes

---

## Core Plugins Reference

| Plugin | Description | Enabled | Has Routes |
|--------|-------------|---------|-----------|
| `custom-links` | Custom external links in menu | By default | ✅ |
| `external-backup` | WebDAV cloud backup | Optional | ✅ |
| `mailchimp` | MailChimp list sync | Optional | ✅ |
| `gravatar` | Gravatar profile photos | Optional | ❌ |
| `google-analytics` | GA4 tracking injection | Optional | ❌ |
| `openlp` | OpenLP projector control | Optional | ❌ |
| `vonage` | Vonage SMS messaging | Optional | ❌ |

---

## Plugin URL Structure

| URL | Purpose |
|-----|---------|
| `/plugins/management` | Admin UI lists all plugins |
| `/plugins/management/{pluginId}` | Redirect with plugin expanded |
| `/plugins/api/plugins` | API: List all plugins |
| `/plugins/api/plugins/{id}/enable` | API: Enable plugin |
| `/plugins/api/plugins/{id}/disable` | API: Disable plugin |
| `/plugins/api/plugins/{id}/settings` | API: Update plugin settings |
| `/plugins/{plugin-name}/*` | Plugin-specific routes |

---

## Best Practices

### Configuration
- ✅ Check `isConfigured()` before operations
- ✅ Return clear error messages from `getConfigurationError()`
- ✅ Use settings schema for admin UI generation
- ✅ Validate API keys/credentials before saving

### Hooks
- ✅ Check `isActive()` in hook handlers before processing
- ✅ Use try/catch in hooks to prevent breaking core functionality
- ✅ Log errors but don't throw exceptions from hooks
- ✅ Keep hook handlers fast and focused

### Routes
- ✅ Always check plugin instance exists before using
- ✅ Use render functions for HTML, JSON for APIs
- ✅ Return appropriate HTTP status codes (201 for create, etc.)
- ✅ Use sanitization (InputUtils) for user input

### Performance
- ✅ Lazy-load services only when needed
- ✅ Cache expensive operations in plugin config
- ✅ Use database queries efficiently (avoid N+1)
- ✅ Uninstall cleanup - remove temporary data

---

**Related Skills:**
- [Routing & Project Architecture](./routing-architecture.md) - Plugin route patterns
- [Slim 4 Best Practices](./slim-4-best-practices.md) - Entry point configuration
- [Security Best Practices](./security-best-practices.md) - Plugin input validation

---

Last updated: February 16, 2026
