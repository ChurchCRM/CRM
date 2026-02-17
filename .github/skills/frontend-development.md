# Skill: Frontend Development

## Context
This skill covers frontend patterns, UI components, notifications, internationalization, and asset management in ChurchCRM.

## Stack

- **Bootstrap 4.6.2** - AdminLTE v2 pattern for legacy pages (NEVER use Bootstrap 5)
- **React + TypeScript** - Modern components
- **Webpack** - Build system
- **Quill** - Rich text editor
- **i18next** - Frontend internationalization

## Bootstrap 4.6.2 (CRITICAL)

**ALWAYS use Bootstrap 4.6.2 CSS classes, never Bootstrap 5 classes:**

```php
// ✅ CORRECT - Bootstrap 4.6.2 classes
<div class="text-center align-top">Content</div>
<button class="btn btn-primary btn-block">Full Width Button</button>
<div class="btn-group btn-group-sm d-flex" role="group">
    <a class="btn btn-outline-primary flex-fill">Button 1</a>
    <a class="btn btn-outline-primary flex-fill">Button 2</a>
</div>

// ❌ WRONG - Bootstrap 5 classes (DO NOT USE!)
<button class="btn btn-primary w-100">Button</button>  // Use btn-block instead
<div class="d-flex flex-wrap gap-2">Content</div>      // gap- is Bootstrap 5 only
<div class="d-grid gap-3">Content</div>               // d-grid is Bootstrap 5 only
```

**Bootstrap 5 Classes to AVOID:**
- `w-100` on buttons (use `btn-block`)
- `gap-*` utilities (use margins/padding instead)
- `d-grid` (use `d-flex` or Bootstrap 4 grid)
- `text-decoration-*` (use existing classes)
- `fw-*` and `fs-*` font utilities
- `rounded-*` beyond Bootstrap 4 values
- `justify-content-*` with `gap-*` (gap is Bootstrap 5 only)
- `flex-wrap` with `gap-*` (use proper spacing classes instead)

**Always use Bootstrap 4.6.2 CSS classes, never deprecated HTML attributes:**

```php
// ✅ CORRECT - Bootstrap 4.6.2 classes
<div class="text-center align-top">Content</div>
<button style="margin-top: 12px;">Click</button>  // OK for custom values

// ❌ WRONG - Deprecated HTML attributes  
<div align="center" valign="top">Content</div>
```

## Asset Paths (SystemURLs)

**ALWAYS use SystemURLs::getRootPath() for asset references:**

```php
// ✅ CORRECT
<link rel="stylesheet" href="<?= SystemURLs::getRootPath() ?>/skin/v2/churchcrm.min.css">
<img src="<?= SystemURLs::getRootPath() ?>/images/logo.png">
<script src="<?= SystemURLs::getRootPath() ?>/skin/v2/churchcrm.min.js"></script>

// ❌ WRONG - Relative paths break in subdirectories
<link rel="stylesheet" href="/skin/v2/churchcrm.min.css">
```

**Asset locations:**
- **CSS/JS**: `src/skin/v2/` (compiled from Webpack)
- **Images**: `src/images/`
- **React components**: `react/`
- **Webpack entry points**: `webpack/`

## Notifications (CRITICAL)

**ALWAYS use window.CRM.notify() with i18n, NEVER alert():**

```javascript
// ✅ CORRECT - Use window.CRM.notify with i18next
window.CRM.notify(i18next.t('Operation completed'), {
    type: 'success',
    delay: 3000
});

window.CRM.notify(i18next.t('An error occurred'), {
    type: 'error'
});

// ❌ WRONG - Never use alert()
alert('Operation completed');
```

**Notification types:**
- `success` - Green, success operations
- `error` - Red, failures
- `warning` - Orange, warnings
- `info` - Blue, informational

**Options:**
- `delay` - Auto-dismiss time in milliseconds (default: 5000)
- `type` - Notification type (default: 'success')

## Confirmations (CRITICAL)

**ALWAYS use bootbox.confirm() for confirmations, NEVER confirm():**

```javascript
// ✅ CORRECT - Use bootbox.confirm
bootbox.confirm({
    title: i18next.t('Confirm Deletion'),
    message: i18next.t('Are you sure you want to delete this item?'),
    buttons: {
        confirm: {
            label: i18next.t('Yes'),
            className: 'btn-danger'
        },
        cancel: {
            label: i18next.t('No'),
            className: 'btn-default'
        }
    },
    callback: function(result) {
        if (result) {
            // User confirmed
            performDeletion();
        }
    }
});

// ❌ WRONG - Never use confirm()
if (confirm('Are you sure?')) {
    performDeletion();
}
```

## Modals (Bootstrap 4)

**For complex forms/modals, use Bootstrap 4 static modals:**

```html
<!-- Button trigger -->
<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#myModal">
    <?= gettext('Open Modal') ?>
</button>

<!-- Modal -->
<div class="modal fade" id="myModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= gettext('Modal Title') ?></h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Form content -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <?= gettext('Close') ?>
                </button>
                <button type="button" class="btn btn-primary">
                    <?= gettext('Save') ?>
                </button>
            </div>
        </div>
    </div>
</div>
```

**Programmatic modal control:**

```javascript
// Show modal
$('#myModal').modal('show');

// Hide modal
$('#myModal').modal('hide');

// Modal events
$('#myModal').on('shown.bs.modal', function() {
    // Modal is now visible
});

$('#myModal').on('hidden.bs.modal', function() {
    // Modal is now hidden
});
```

## Internationalization (i18n)

**CRITICAL: Always wrap user-facing text for translation.**

### JavaScript (i18next)

```javascript
// ✅ CORRECT
window.CRM.notify(i18next.t('Operation completed'), {
    type: 'success',
    delay: 3000
});

var confirmMsg = i18next.t('Are you sure you want to delete this item?');
var title = i18next.t('Confirm Deletion');

// ❌ WRONG - Hardcoded English
window.CRM.notify('Operation completed', { type: 'success' });
```

### PHP (gettext)

```php
// ✅ CORRECT
echo gettext('Welcome to ChurchCRM');
<h1><?= gettext('User Management') ?></h1>
<button><?= gettext('Save') ?></button>

// ❌ WRONG - Hardcoded English
echo 'Welcome to ChurchCRM';
<h1>User Management</h1>
```

### i18n Term Consolidation (Reduce Translation Burden)

**To reduce translator burden across 45+ languages, consolidate compound terms into reusable components:**

**Delete Confirmation Pattern:**

```php
// ✅ CORRECT - Reduces 7 variants to 1 term + type names
$sPageTitle = gettext('Delete Confirmation') . ': ' . gettext('Note');
$sPageTitle = gettext('Delete Confirmation') . ': ' . gettext('Family');

// ❌ WRONG - Creates 7+ separate terms to translate
$sPageTitle = gettext('Note Delete Confirmation');
$sPageTitle = gettext('Family Delete Confirmation');
```

**Add New Pattern:**

```php
// ✅ CORRECT - Reduces 16+ variants to 1 "Add New" term
<input type="submit" value="<?= gettext('Add New') . ' ' . gettext('Fund') ?>" />
<h3><?= gettext('Add New') . ' ' . gettext('Group') ?></h3>

// ❌ WRONG - Creates separate term for each type
<input type="submit" value="<?= gettext('Add New Fund') ?>" />
<h3><?= gettext('Add New Group') ?></h3>
```

### Locale Build Workflow (CRITICAL)

**BEFORE committing new gettext() or i18next.t() strings:**

```bash
npm run locale:build   # Extract terms into messages.po
npm run build          # Rebuild frontend bundles
# Commit updated locale/terms/messages.po with your changes
```

## PHP Templates (Server-Side Rendering)

**Render initial UI state server-side to avoid JS-only initialization flashes:**

```php
// ✅ CORRECT - Server-side initial state
<div id="user-stats">
    <span class="badge"><?= $data['stats']['total'] ?></span>
</div>

<script>
// JavaScript only for dynamic updates
function refreshStats() {
    $.get('/admin/api/users/stats', function(data) {
        $('#user-stats .badge').text(data.total);
    });
}
</script>

// ❌ WRONG - Empty div filled by JS (causes flash)
<div id="user-stats"></div>

<script>
// Page loads with empty div, then JS fills it (visible delay)
$.get('/admin/api/users/stats', function(data) {
    $('#user-stats').html('<span class="badge">' + data.total + '</span>');
});
</script>
```

## File Inclusion (require vs include)

```php
// ✅ CORRECT - Use require for critical layout files
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';

// ❌ WRONG - include allows missing critical files
include SystemURLs::getDocumentRoot() . '/Include/Header.php';  // Silent failure
```

**Guidelines:**
- **Use `require`** for critical files: Header.php, Footer.php, core utilities
- **Use `include`** for optional content: plugins, supplementary files that gracefully degrade
- **Why**: `require` fails loudly (fatal error), `include` fails silently (warning)
- **Admin views** (`src/admin/views/*.php`): ALL must use `require` for Header/Footer

## Null Safety

```php
// ✅ CORRECT
echo $notification?->title ?? 'No Title';

// ❌ WRONG
echo $notification->title;  // TypeError if null
```

## Files

**Compiled Assets:** `src/skin/v2/churchcrm.min.js`, `src/skin/v2/churchcrm.min.css`
**React Components:** `react/`
**Webpack Entry Points:** `webpack/`
**Locale Files:** `locale/messages.json`, `locale/terms/messages.po`
**Build Config:** `webpack.config.js`, `package.json`
