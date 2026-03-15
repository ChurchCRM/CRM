# Configuration Management

Guide to managing SystemConfig, asset paths, settings panels, and configuration best practices.

---

## Overview

ChurchCRM uses a centralized `SystemConfig` class for:
- **Application settings** - Database configuration, session timeouts, permissions
- **Dynamic settings** - Admin-editable configuration values
- **Debug mode** - Error display control
- **Asset path resolution** - CSS/image references

---

## SystemConfig Basics

### Location

```
src/ChurchCRM/Utils/SystemConfig.php
```

### Common Methods

| Method | Purpose | Example |
|--------|---------|---------|
| `getValue($key)` | Get string value | `SystemConfig::getValue('churchName')` |
| `getBooleanValue($key)` | Get boolean (truthy/falsey) | `SystemConfig::getBooleanValue('enableEmails')` |
| `integerValue($key)` | Get integer | `SystemConfig::integerValue('sessionTimeout')` |
| `setValue($key, $value)` | Set value | `SystemConfig::setValue('churchName', 'My Church')` |
| `getSettingsConfig($keys)` | Get structured config | `SystemConfig::getSettingsConfig(['timeout', 'maxFailed'])` |
| `debugEnabled()` | Is debug mode on? | `SystemConfig::debugEnabled()` |
| `getAllSettings()` | Get all settings | `SystemConfig::getAllSettings()` |

### Basic Usage

```php
// ✅ CORRECT - Access settings through SystemConfig
$churchName = SystemConfig::getValue('churchName', 'Default Church');
$sessionTimeout = SystemConfig::integerValue('iSessionTimeout');

if (SystemConfig::getBooleanValue('bEnableLostPassword')) {
    // Show password recovery option
}

// ❌ WRONG - Direct $_SESSION or $_CONFIG access
$churchName = $_SESSION['churchName'];      // Bypasses config layer
$direct = Config::CHURCH_NAME;               // No permission/security checks
```

---

## Boolean Configuration

### Method: getBooleanValue()

Use `getBooleanValue()` for all boolean config checks:

```php
// ✅ CORRECT - Using getBooleanValue
if (SystemConfig::getBooleanValue('bEmailMailto')) {
    // Email functionality is enabled
}

if (!SystemConfig::getBooleanValue('bEnableRegistration')) {
    // Registration is disabled
}

// ❌ WRONG - Direct evaluation
if (SystemConfig::getValue('bEmailMailto')) {  // Returns string "0" or "1"
    // "0" is truthy in PHP!
}

// ❌ WRONG - empty() check
if (empty(SystemConfig::getValue('bEmailMailto'))) {
    // Fails for "0" value
}
```

### Key Naming Convention

Boolean settings use `b` prefix:

| Key | Purpose |
|-----|---------|
| `bEnableEmails` | Email system enabled |
| `bEmailMailto` | Mailto links enabled |
| `bEnableLostPassword` | Password recovery |
| `bEditSelf` | Users can edit own records |
| `bEditRecords` | Users can edit any record |
| `bDeleteRecords` | Users can delete records |
| `bAddRecords` | Users can add records |

---

## Asset Paths (SystemURLs)

Use `SystemURLs::getRootPath()` for all asset references:

### Location

```
src/ChurchCRM/dto/SystemURLs.php
```

### Common Methods

| Method | Returns | Example |
|--------|---------|---------|
| `getRootPath()` | Relative root URL | `/churchcrm/` or `/` |
| `getDocumentRoot()` | File system root | `/var/www/html/` |
| `getImagePath()` | Image URL | `/churchcrm/images/` |
| `getSkinPath()` | CSS/JS URL | `/churchcrm/skin/v2/` |

### Usage Pattern

```php
// ✅ CORRECT - CSS/image references with getRootPath()
?>
<link rel="stylesheet" href="<?= SystemURLs::getRootPath() ?>/skin/v2/churchcrm.min.css">
<img src="<?= SystemURLs::getRootPath() ?>/images/logo.png" alt="Logo">
<script src="<?= SystemURLs::getRootPath() ?>/skin/v2/app.min.js"></script>

<?php

// ✅ CORRECT - File system operations with getDocumentRoot()
$photoPath = SystemURLs::getDocumentRoot() . '/photos/person_' . $personId . '.jpg';
if (file_exists($photoPath)) {
    // Process photo
}

// ❌ WRONG - Hardcoded paths (breaks in subdirectories)
<link rel="stylesheet" href="/skin/v2/churchcrm.min.css">
// If installed in /churchcrm/, this breaks because path should be /churchcrm/skin/...

// ❌ WRONG - Relative paths
<script src="../skin/v2/app.js"></script>
// Fails in nested routes like /admin/system/debug
```

### Why getRootPath() is Critical

ChurchCRM can be installed in different ways:

```
Installation 1: mysite.com/churchcrm/
Root path: /churchcrm/
Asset URL: /churchcrm/skin/v2/style.css

Installation 2: churchsite.com/ (root)
Root path: /
Asset URL: /skin/v2/style.css

Installation 3: mysite.com/ (subdirectory handler)
Root path: /
Asset URL: /skin/v2/style.css
```

Without `getRootPath()`, hardcoded paths like `/skin/v2/` break in installation #1.

---

## Settings Panels (getSettingsConfig)

### Purpose

Get structured configuration data for admin UI panels. Automatically generates forms/panels.

### Method Signature

```php
public static function getSettingsConfig(array $settingKeys): array
```

**Returns:**
```php
[
    [
        'category' => 'System',
        'name' => 'iSessionTimeout',
        'value' => '1800',
        'type' => 'number',
        'label' => 'Session Timeout (seconds)',
        'options' => null,
        'help' => 'Seconds before idle session expires'
    ],
    // ... more settings
]
```

### Usage in Service

Extract settings for admin UI:

```php
<?php
namespace ChurchCRM\Service;

class UserService
{
    public function getUserSettingsConfig(): array
    {
        // Request structured config for these keys
        $userSettingKeys = [
            'iSessionTimeout',
            'iMaxFailedLogins',
            'bEnableLostPassword',
            'bTwoFactorAuth'
        ];
        
        // Get settings with type, label, help text
        return SystemConfig::getSettingsConfig($userSettingKeys);
    }
}
```

### Usage in View

Render settings panel dynamically:

```php
<?php
// src/admin/views/user-settings.php
$settingsConfig = $service->getUserSettingsConfig();
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= gettext('User Settings') ?></h3>
    </div>
    <div class="card-body">
        <form id="settings-form">
            <?php foreach ($settingsConfig as $setting): ?>
                <div class="form-group">
                    <label><?= InputUtils::escapeHTML($setting['label']) ?></label>
                    
                    <?php if ($setting['type'] === 'number'): ?>
                        <input type="number" 
                               name="<?= InputUtils::escapeAttribute($setting['name']) ?>"
                               value="<?= InputUtils::escapeAttribute($setting['value']) ?>"
                               class="form-control">
                    <?php elseif ($setting['type'] === 'checkbox'): ?>
                        <input type="checkbox"
                               name="<?= InputUtils::escapeAttribute($setting['name']) ?>"
                               <?= $setting['value'] ? 'checked' : '' ?>>
                    <?php elseif ($setting['type'] === 'select' && !empty($setting['options'])): ?>
                        <select name="<?= InputUtils::escapeAttribute($setting['name']) ?>" class="form-control">
                            <?php foreach ($setting['options'] as $key => $label): ?>
                                <option value="<?= $key ?>"
                                    <?= $key === $setting['value'] ? 'selected' : '' ?>>
                                    <?= InputUtils::escapeHTML($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    
                    <?php if (!empty($setting['help'])): ?>
                        <small class="form-text text-muted">
                            <?= InputUtils::escapeHTML($setting['help']) ?>
                        </small>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <button type="submit" class="btn btn-primary"><?= gettext('Save Settings') ?></button>
        </form>
    </div>
</div>

<script>
document.getElementById('settings-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    fetch(window.CRM.root + '/admin/api/system/settings', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(() => {
        window.CRM.notify(i18next.t('Settings saved'), {type: 'success'});
    })
    .catch(error => {
        window.CRM.notify(i18next.t('Error saving settings'), {type: 'error'});
    });
});
</script>
```

### Benefits

- ✅ Single method call gets all structured data
- ✅ Type information for form validation
- ✅ Help text and labels included
- ✅ Dynamic form generation
- ✅ No hardcoding of settings in views

---

## Admin Settings Panel Pattern

### Service Layer

```php
<?php
namespace ChurchCRM\Service;

class AdminDashboardService
{
    public function getSystemSettingsConfig(): array
    {
        $systemSettings = [
            'sSoftwareTitle',
            'iSessionTimeout',
            'iMaxFailedLogins',
            'bEnableUpdate'
        ];
        
        return SystemConfig::getSettingsConfig($systemSettings);
    }

    public function getSecuritySettingsConfig(): array
    {
        $securitySettings = [
            'bEnableLostPassword',
            'bStrictSSL',
            'iPasswordExpires',
            'bTwoFactorAuth'
        ];
        
        return SystemConfig::getSettingsConfig($securitySettings);
    }

    public function getEmailSettingsConfig(): array
    {
        $emailSettings = [
            'sFromEmail',
            'sFromName',
            'bEmailMailto',
            'bEnableEmails'
        ];
        
        return SystemConfig::getSettingsConfig($emailSettings);
    }
}
```

### Route Layer

```php
<?php
// src/admin/routes/system.php

$app->get('/admin/system/settings', function (Request $request, Response $response) use ($container) {
    $service = $container->get('AdminDashboardService');
    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    
    return $renderer->render($response, 'settings.php', [
        'sPageTitle' => gettext('System Settings'),
        'sRootPath' => SystemURLs::getRootPath(),
        'systemSettings' => $service->getSystemSettingsConfig(),
        'securitySettings' => $service->getSecuritySettingsConfig(),
        'emailSettings' => $service->getEmailSettingsConfig()
    ]);
});
```

---

## Setting Types

### Common Setting Types

| Type | Example | Display |
|------|---------|---------|
| `text` | `'churchName'` | `<input type="text">` |
| `email` | `'sFromEmail'` | `<input type="email">` |
| `number` | `'iSessionTimeout'` | `<input type="number">` |
| `checkbox` | `'bEnableUpdate'` | `<input type="checkbox">` |
| `select` | `'sDefaultFamily'` | `<select>` with options |
| `textarea` | `'sChurchDescription'` | `<textarea>` |
| `password` | `'sBackupPassword'` | `<input type="password">` masked |

---

## Best Practices

### ✅ DO

- Use `SystemConfig::getBooleanValue()` for all boolean checks (prevents "0" string truthy bug)
- Call `SystemConfig::getSettingsConfig()` once per page load, cache the result
- Use `SystemURLs::getRootPath()` for all asset paths
- Create service methods to encapsulate setting logic
- Group related settings into logical panels
- Always escape `SystemConfig` values in templates with `InputUtils`

### ❌ DON'T

- Directly evaluate `SystemConfig::getValue()` as boolean
- Hardcode paths like `/skin/v2/` (breaks in subdirectories)
- Create separate `SystemConfig` calls in loops (N+1 performance issue)
- Store config values in instance variables (query `SystemConfig` directly)
- Assume `SystemConfig` values are safe for output (always escape)

---

## Configuration Workflow Example

### Scenario: Add "Support Email" Setting

**Step 1** - Add to database/SystemConfig
```php
// Handled by ChurchCRM installation, not your code
INSERT INTO config_cfg VALUES ('sSupportEmail', 'support@church.com');
```

**Step 2** - Use in Service
```php
<?php
class EmailService
{
    public function getSupportEmail(): string
    {
        return SystemConfig::getValue('sSupportEmail', 'support@church.com');
    }
}
```

**Step 3** - Add to Admin Settings Panel
```php
<?php
class AdminService
{
    public function getEmailSettingsConfig(): array
    {
        return SystemConfig::getSettingsConfig([
            'sSupportEmail',
            'sFromEmail',
            'bEnableEmails'
        ]);
    }
}
```

**Step 4** - Render in View
```php
<!-- View renders setings from service -->
<?php foreach ($emailSettings as $setting): ?>
    <input type="email" 
           name="<?= InputUtils::escapeAttribute($setting['name']) ?>"
           value="<?= InputUtils::escapeAttribute($setting['value']) ?>">
<?php endforeach; ?>
```

---

## Debugging Configuration Issues

### Check Current Values

```bash
# Via PHP CLI
php -r "require 'src/Include/Config.php'; echo SystemConfig::getValue('churchName');"

# Via MySQL
mysql> SELECT cfg_name, cfg_value FROM config_cfg WHERE cfg_name = 'churchName';
```

### Reset to Defaults

```php
// Only in development
SystemConfig::setValue('churchName', 'Default Church');
SystemConfig::setValue('bEnableUpdate', true);
```

---

## Related Skills

- [Routing & Project Architecture](./routing-architecture.md) - SystemConfig in admin pages
- [PHP Best Practices](./php-best-practices.md) - Configuration patterns
- [Security Best Practices](./security-best-practices.md) - Escaping config values

---

Last updated: February 16, 2026
