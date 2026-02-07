# ChurchCRM Plugin System

ChurchCRM features a WordPress-style plugin architecture that allows extending functionality without modifying core code.

## Plugin Structure

Each plugin lives in its own directory under either:
- `src/plugins/core/` - Core plugins shipped with ChurchCRM
- `src/plugins/community/` - Third-party community plugins (user-installed)

```
src/plugins/
├── core/
│   ├── mailchimp/
│   │   ├── plugin.json          # Plugin manifest
│   │   └── src/
│   │       └── MailChimpPlugin.php
│   ├── vonage/
│   ├── google-analytics/
│   ├── openlp/
│   └── gravatar/
└── community/
    └── my-custom-plugin/
        ├── plugin.json
        └── src/
            └── MyCustomPlugin.php
```

## Creating a Plugin

### 1. Create the plugin.json manifest

```json
{
    "id": "my-plugin",
    "name": "My Custom Plugin",
    "description": "A description of what the plugin does",
    "version": "1.0.0",
    "author": "Your Name",
    "authorUrl": "https://yourwebsite.com",
    "type": "community",
    "minimumCRMVersion": "5.0.0",
    "mainClass": "ChurchCRM\\Plugins\\MyPlugin\\MyPlugin",
    "dependencies": [],
    "settingsUrl": "/plugins/my-plugin/settings",
    "settings": [
        {
            "key": "apiKey",
            "label": "API Key",
            "type": "text",
            "required": true,
            "help": "Your API key"
        }
    ],
    "menuItems": [
        {
            "parent": "admin",
            "label": "My Plugin",
            "url": "/plugins/my-plugin/dashboard",
            "icon": "fa-puzzle-piece",
            "permission": "bAdmin"
        }
    ],
    "hooks": [
        "person.created",
        "person.updated"
    ]
}
```

### 2. Create the main plugin class

```php
<?php

namespace ChurchCRM\Plugins\MyPlugin;

use ChurchCRM\Plugin\AbstractPlugin;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;

class MyPlugin extends AbstractPlugin
{
    public function getId(): string
    {
        return 'my-plugin';
    }

    public function getName(): string
    {
        return 'My Custom Plugin';
    }

    public function getDescription(): string
    {
        return 'A description of what the plugin does';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function boot(): void
    {
        // Called when the plugin is loaded
        // Register hooks, initialize services, etc.
        HookManager::addAction(Hooks::PERSON_CREATED, [$this, 'onPersonCreated']);
    }

    public function activate(): void
    {
        // Called when the plugin is enabled
    }

    public function deactivate(): void
    {
        // Called when the plugin is disabled
    }

    public function uninstall(): void
    {
        // Called when the plugin is removed
        // Clean up database tables, files, etc.
    }

    public function isConfigured(): bool
    {
        // Return true if the plugin has all required settings
        return !empty(SystemConfig::getValue('sMyPluginApiKey'));
    }

    public function registerRoutes($routeCollector): void
    {
        // Register custom routes
        $routeCollector->group('/my-plugin', function ($group) {
            $group->get('/dashboard', [$this, 'handleDashboard']);
        });
    }

    public function getMenuItems(): array
    {
        // Return menu items for this plugin
        return [];
    }

    public function getSettingsSchema(): array
    {
        // Return settings schema for the settings UI
        return [];
    }

    // Hook handlers
    public function onPersonCreated($person): void
    {
        $this->log("Person created: " . $person->getFullName());
    }
}
```

## Hooks System

ChurchCRM uses a WordPress-style hooks system with **actions** and **filters**.

### Actions

Actions are triggered when something happens. Use them to run code in response to events.

```php
// Register an action
HookManager::addAction(Hooks::PERSON_CREATED, [$this, 'onPersonCreated'], 10, 1);

// Trigger an action (in core code)
HookManager::doAction(Hooks::PERSON_CREATED, $person);
```

### Filters

Filters allow modifying data before it's used. They receive data, modify it, and return it.

```php
// Register a filter
HookManager::addFilter(Hooks::MENU_BUILDING, [$this, 'modifyMenu'], 10, 1);

// Apply a filter (in core code)
$menuItems = HookManager::applyFilters(Hooks::MENU_BUILDING, $menuItems);
```

### Available Hooks

See `src/ChurchCRM/Plugin/Hooks.php` for all available hook constants:

**Person Hooks:**
- `person.pre_create` - Filter: Before person creation
- `person.created` - Action: After person created
- `person.pre_update` - Filter: Before person update
- `person.updated` - Action: After person updated
- `person.deleted` - Action: After person deleted
- `person.view.tabs` - Filter: Modify tabs on person view

**Family Hooks:**
- `family.pre_create`, `family.created`, etc.

**Financial Hooks:**
- `donation.received` - After donation recorded
- `deposit.closed` - After deposit slip closed

**Event Hooks:**
- `event.created`, `event.checkin`, `event.checkout`

**Group Hooks:**
- `group.member.added`, `group.member.removed`

**UI Hooks:**
- `menu.building` - Modify menu items
- `dashboard.widgets` - Add dashboard widgets
- `settings.panels` - Add settings panels

**System Hooks:**
- `system.init` - During initialization
- `system.upgraded` - After upgrade
- `cron.run` - During scheduled tasks

## Best Practices

1. **Use hooks instead of modifying core code** - This ensures your plugin survives updates.

2. **Store settings in SystemConfig** - Use existing config infrastructure.

3. **Namespace your code** - Use `ChurchCRM\Plugins\YourPlugin\` namespace.

4. **Handle errors gracefully** - Don't crash the system if your plugin fails.

5. **Log important actions** - Use `$this->log()` from AbstractPlugin.

6. **Check `isConfigured()`** - Don't run if required settings are missing.

7. **Clean up on uninstall** - Remove any database tables, files, etc.

## Core Plugins

ChurchCRM ships with these core plugins:

| Plugin | Description |
|--------|-------------|
| `mailchimp` | MailChimp email list integration |
| `vonage` | Vonage SMS notifications |
| `google-analytics` | Google Analytics tracking |
| `openlp` | OpenLP presentation software integration |
| `gravatar` | Gravatar profile photos |

## Admin UI

Plugins are managed through **Admin → Plugins** in the ChurchCRM menu.

From this page you can:
- View all installed plugins
- Enable/disable plugins
- Access plugin settings

## API

Plugin management API endpoints:

```
GET  /plugins/api/plugins              # List all plugins
GET  /plugins/api/plugins/{id}         # Get plugin details
POST /plugins/api/plugins/{id}/enable  # Enable a plugin
POST /plugins/api/plugins/{id}/disable # Disable a plugin
```

## Migration from Legacy Integrations

If you're migrating from the old integration system:

1. Legacy config values (e.g., `sMailChimpApiKey`) are still used
2. Plugins check these values for `isConfigured()` status
3. No data migration needed - plugins use existing config

## Questions?

For plugin development help, visit:
- ChurchCRM Forums: https://forum.churchcrm.io
- GitHub Discussions: https://github.com/ChurchCRM/CRM/discussions
