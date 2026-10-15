---
title: "Configuration Management"
intent: "Guide to managing SystemConfig, asset paths, settings panels, and configuration best practices"
tags: ["configuration", "settings", "systemconfig", "admin"]
prereqs: ["[[php-best-practices]]"]
complexity: "beginner"
---
# Configuration Management

`SystemConfig` (`src/ChurchCRM/dto/SystemConfig.php`) holds admin-editable settings and the debug switch. `SystemURLs` (`src/ChurchCRM/dto/SystemURLs.php`) resolves paths and asset URLs.

## Where a New Setting Goes

Define the `ConfigItem` in `SystemConfig::buildConfigs()` (that persists the value), leave it out of `buildCategories()`, and surface it in its area admin hub (`/admin/people` is the reference). `/SystemSettings.php` lists only what `buildCategories()` names; internal state keys (e.g. `sTelemetryAskedVersion`) stay out of it too. Placement and hub recipe: [settings-placement.md](./settings-placement.md).

### Member Portal settings

`/admin/member-portal` (`src/admin/routes/member-portal.php`, `src/admin/views/member-portal.php`) hosts the portal's settings on its **Settings** tab through the Settings Panel component. Its `ConfigItem`s (`sMemberPortalTheme`, `bPortalDeveloperMode`, `bPortalShowCalendar`, `bPortalShowVolunteer`, `bPortalAllowBirthdayEdit`) are declared in `buildConfigs()`.

The booleans save through `POST /admin/api/system/config/{name}`. `sMemberPortalTheme` saves through `POST /admin/api/member-portal/theme`, which runs `ThemeManager::validate()` and answers 409 with the findings when the theme cannot render.

## SystemConfig

| Method | Purpose |
|--------|---------|
| `getValue($key)` | String value |
| `getBooleanValue($key)` | Boolean |
| `getIntValue($key)` | Integer |
| `setValue($key, $value)` | Set value |
| `getSettingsConfig($keys)` | Structured config for settings panels |
| `debugEnabled()` | Debug mode |
| `getValueForHtml/Attr/Js($key)` | Escaped getters for output |

```php
$churchName = SystemConfig::getValue('sChurchName');
$timeout = SystemConfig::getIntValue('iSessionTimeout');

if (SystemConfig::getBooleanValue('bEnableLostPassword')) {
    // show password recovery
}

echo SystemConfig::getValueForHtml('sChurchName');
```

Boolean keys use the `b` prefix (`bEmailMailto`, `bEnableLostPassword`). Always check them with `getBooleanValue()`: `getValue()` returns the string `"0"`, which is truthy and non-empty. Escape every config value you emit (`getValueForHtml()` or `InputUtils`).

### Removing a ConfigItem

`getValue()` returns the default (or `''`) for an unregistered key and does not throw, so leftover references read the default. Pair the removal with `DELETE FROM config_cfg WHERE cfg_name = '...'` in the upgrade migration (`db-schema-migration.md`).

### Resetting to the default

`ConfigItem::setValue()` deletes the `config_cfg` row when the value equals the default and leaves the in-memory value untouched. Within that request `getValue()` still returns the previous value, and a `POST /admin/api/system/config/{name}` response echoes it. Assert with a separate `GET`.

### Cross-checking `buildCategories()`

Every key in `buildCategories()` needs a matching `buildConfigs()` entry; a missing one raises a PHP error when the settings page iterates categories.

## Asset Paths (SystemURLs)

| Method | Returns |
|--------|---------|
| `getRootPath()` | Relative root URL (`/churchcrm` or empty) |
| `getDocumentRoot()` | Filesystem root of `src/` |
| `getImagesRoot()` | Filesystem path of the images directory |
| `assetVersioned($path)` | Cache-busted asset URL, e.g. `SystemURLs::assetVersioned('/skin/v2/app.min.js')` |
| `getCSPNonce()` | Nonce for inline `<script>` |

Build image and skin URLs from `getRootPath()`. `getImagesRoot()` is a filesystem path.

```php
<link rel="stylesheet" href="<?= SystemURLs::getRootPath() ?>/skin/v2/churchcrm.min.css">
<img src="<?= SystemURLs::getRootPath() ?>/images/logo.png" alt="Logo">

$photoPath = SystemURLs::getDocumentRoot() . '/photos/person_' . $personId . '.jpg';
```

ChurchCRM installs at a site root or in a subdirectory (`/churchcrm/`), and routes nest (`/admin/system/debug`), so root-relative literals like `/skin/v2/...` and relative paths like `../skin/...` resolve wrongly. `getRootPath()` makes every URL correct.

## Settings Panels

`SystemConfig::getSettingsConfig(array $keys)` returns one entry per key: `name`, `type` (`text`, `number`, `boolean`, `choice`, `password`), `label` (first line of the tooltip), `tooltip`, and `choices` for `choice` types. Services pass it to the view, and `window.CRM.SettingsPanel` renders and auto-saves each field to `POST /admin/api/system/config/{key}` (recipe in `settings-placement.md`).

```php
class AdminDashboardService
{
    public function getSecuritySettingsConfig(): array
    {
        return SystemConfig::getSettingsConfig(['bEnableLostPassword', 'bStrictSSL', 'iPasswordExpires']);
    }
}
```

```php
// src/admin/routes/<area>.php; paths are relative to the /admin base path
$app->get('/system/settings', function (Request $request, Response $response) {
    $service = new AdminDashboardService();
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'settings.php', [
        'sPageTitle' => gettext('System Settings'),
        'sRootPath' => SystemURLs::getRootPath(),
        'securitySettings' => $service->getSecuritySettingsConfig(),
    ]);
});
```

Call `getSettingsConfig()` once per request and reuse the result.

## Adding a Setting: Workflow

1. Add the `ConfigItem` to `buildConfigs()` with a key, type, default, and tooltip; leave it out of `buildCategories()`
2. Read it in a service: `SystemConfig::getValue('sSupportEmail')`
3. Add the key to the area hub section's `getSettingsConfig()` list
4. Render it with `SettingsPanel` in the hub view

## Debugging

```bash
mysql> SELECT cfg_name, cfg_value FROM config_cfg WHERE cfg_name = 'churchName';
```

In development, `SystemConfig::setValue('bEnableUpdate', true)` resets a value.

## Related Skills

- [Settings Placement](./settings-placement.md)
- [Routing & Project Architecture](./routing-architecture.md)
- [PHP Best Practices](./php-best-practices.md)
- [Security Best Practices](./security-best-practices.md)
