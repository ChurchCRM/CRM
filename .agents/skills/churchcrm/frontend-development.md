---
title: "Frontend Development"
intent: "Guidance for frontend work, vanilla JS/TypeScript, and asset management"
tags: ["frontend","webpack","i18n"]
prereqs: ["webpack-typescript.md","i18n-localization.md"]
complexity: "intermediate"
---

# Skill: Frontend Development

## Context
This skill covers frontend patterns, UI components, notifications, internationalization, and asset management in ChurchCRM.

## Stack <!-- updated: 2026-03-22 -->

- **Tabler + Bootstrap 5.3.8** — Primary UI framework (migrated from AdminLTE/BS4)
- **Vanilla JS + TypeScript** — Frontend modules (React removed in 7.2.0)
- **Webpack** — Build system
- **ApexCharts** — Charting (replaced Chart.js)
- **i18next** — Frontend internationalization

**Verified versions in this repo (package.json):**
- `@tabler/core` ^1.4.0
- `@tabler/icons-webfont` ^3.40.0
- `bootstrap` ^5.3.8
- `apexcharts` ^5.10.4
- `typescript` ^5.9.3
- `webpack` ^5.105.4

**For detailed component reference**, see `tabler-components.md`.

## Badge / Pill Contrast (CRITICAL) <!-- learned: 2026-03-25 -->

Tabler overrides Bootstrap's semantic colors. `bg-info` alone **fails WCAG AA** (~2.9:1 with white text). `bg-warning` and `bg-light` also fail without explicit text overrides. Always check contrast before using any badge color.

**Rule: use Tabler `-lt` variants for semantic/label badges; explicit `text-*` for all others.**

```php
// ✅ CORRECT — Tabler -lt pattern: light tinted bg + colored text = always readable
<span class="badge bg-blue-lt text-blue">Person</span>
<span class="badge bg-teal-lt text-teal">Family</span>
<span class="badge bg-purple-lt text-purple">Group</span>
<span class="badge bg-green-lt text-green">Active</span>

// ✅ CORRECT — solid dark backgrounds (pass with white text)
<span class="badge bg-primary text-white">42</span>   // count on dark row
<span class="badge bg-light text-dark">0</span>        // zero / empty state

// ✅ CORRECT — warning always needs text-dark
<span class="badge bg-warning text-dark">Pending</span>

// ❌ WRONG — these fail WCAG AA in Tabler
<span class="badge bg-info">Person</span>             // ~2.9:1 — FAILS
<span class="badge bg-secondary">0</span>             // ~4.0:1 on gray rows — FAILS
<span class="badge bg-warning">Alert</span>           // yellow + white — FAILS
<span class="badge bg-light">Label</span>             // near-white bg + white text — FAILS
```

**Quick reference:**

| Use case | ✅ Class |
|---|---|
| Semantic label (Person/Family/Group/status) | `bg-{color}-lt text-{color}` |
| Count on light background | `bg-primary text-white` |
| Count on dark/active row | `bg-primary text-white` |
| Zero / empty state | `bg-light text-dark` |
| Warning | `bg-warning text-dark` |
| Success action | `bg-green-lt text-green` |

Tabler named colors available for `-lt`: `blue`, `azure`, `indigo`, `purple`, `pink`, `red`, `orange`, `yellow`, `lime`, `green`, `teal`, `cyan`.

## Bootstrap 5 / Tabler (CRITICAL) <!-- updated: 2026-03-22 -->

**Use Bootstrap 5 + Tabler CSS classes for all new and migrated code:**

```php
// ✅ CORRECT - Bootstrap 5 / Tabler classes
<div class="ms-3 me-2 ps-2 pe-1">Spacing</div>
<span class="badge bg-primary">Badge</span>
<div class="float-end text-end">Aligned</div>
<span class="fw-bold">Bold text</span>
<button class="btn-close" data-bs-dismiss="modal"></button>
<div class="visually-hidden">Screen reader only</div>
<div class="form-select">Select</div>
<div class="form-check">Check</div>

// ❌ WRONG - Bootstrap 4 classes (DO NOT USE in new code)
<div class="ml-3 mr-2 pl-2 pr-1">Spacing</div>    // Use ms-/me-/ps-/pe-
<span class="badge badge-primary">Badge</span>       // Use bg-primary
<div class="float-end text-right">Aligned</div>   // Use float-end/text-end
<span class="font-weight-bold">Bold text</span>     // Use fw-bold
<button class="close">&times;</button>               // Use btn-close
<div class="sr-only">Hidden</div>                    // Use visually-hidden
<div class="custom-select">Select</div>              // Use form-select
<div class="custom-control">Check</div>              // Use form-check
<div class="form-group">Group</div>                  // Use <div class="mb-3">
```

**Data attributes must use `data-bs-*` prefix:**
```php
// ✅ CORRECT
data-bs-toggle="modal" data-bs-target="#myModal" data-bs-dismiss="modal"

// ❌ WRONG
data-toggle="modal" data-target="#myModal" data-dismiss="modal"
```

**Always use CSS classes, never deprecated HTML attributes:**

```php
// ✅ CORRECT
<div class="text-center align-top">Content</div>

// ❌ WRONG - Deprecated HTML attributes
<div align="center" valign="top">Content</div>
```

## Table Design for User-Facing Lists <!-- learned: 2026-03-14 -->

**Keep visible columns focused on essential info (5-6 columns max for scannable views).**

For tables with many potential columns:
- Show quick-glance stats in cards above the table (enrollment, gender breakdown, activity metrics)
- Use modal popups for detailed information rather than expandable rows (better for print-friendly layouts)
- Include actionable columns: clickable links to profiles, phone/email for quick contact
- Use Bootstrap responsive grid: `col-12 col-md-6 col-lg-4` for mobile-first stacking

**Example:** Sunday School class view shows (Name, Age, Mobile, Email, Father, Mother) with info icon opening modal for address/parent details.

## DataTables: Always Inherit the User's Page-Length Preference <!-- learned: 2026-04-22 -->

Every user has a **"Rows per page"** preference (Edit Profile → Preferences → Tables). It's stored in the `ui.table.size` user setting and exposed globally as `window.CRM.plugin.dataTable` by [src/Include/Header.php](src/Include/Header.php#L85-L163). Every list-page DataTable MUST merge this global config so the user's choice wins.

**Correct pattern** — put any local defaults *first*, then extend with the global config so `window.CRM.plugin.dataTable.pageLength` overrides them:

```js
let dataTableConfig = {
  paging: true,
  pageLength: 10,        // fallback only — extend overwrites this
  columnDefs: [...],
};
$.extend(dataTableConfig, window.CRM.plugin.dataTable);
$("#mytable").DataTable(dataTableConfig);
```

**Anti-pattern** — setting `pageLength` *after* the extend silently ignores the user's preference:

```js
// ❌ WRONG — locks every user to 25 rows regardless of their setting
$.extend(dataTableConfig, window.CRM.plugin.dataTable);
dataTableConfig.pageLength = 25;
```

**Intentional exceptions** (do NOT copy these for list pages):

- Home dashboard widgets in [MainDashboard.js](src/skin/js/MainDashboard.js#L47-L57) set `paging: false` via `dataTableDashboardDefaults` — compact widgets, not paginated lists.
- Birthdays / Anniversaries dashboard widgets override back to `pageLength: 5` *after* the extend on purpose (tight widget constraint).

**Quick audit**: `grep -rEn "pageLength" src/ --include="*.js" --include="*.php"` — any `pageLength` line that appears *after* `$.extend(..., window.CRM.plugin.dataTable)` is a bug unless it's a dashboard widget.

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

## Modals (Bootstrap 5 / Tabler) <!-- updated: 2026-03-22 -->

**For complex forms/modals, use Bootstrap 5 data attributes:**

```html
<!-- Button trigger -->
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#myModal">
    <?= gettext('Open Modal') ?>
</button>

<!-- Modal -->
<div class="modal fade" id="myModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= gettext('Modal Title') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Form content -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
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

**Programmatic modal control (Bootstrap 5 API):**

```javascript
// Bootstrap 5 — use bootstrap.Modal class
const myModal = new bootstrap.Modal(document.getElementById('myModal'));
myModal.show();
myModal.hide();

// jQuery still works (BS5 auto-detects jQuery)
$('#myModal').modal('show');
$('#myModal').modal('hide');

// Modal events (same as BS4)
$('#myModal').on('shown.bs.modal', function() {
    // Modal is now visible
});

$('#myModal').on('hidden.bs.modal', function() {
    // Modal is now hidden
});
```

### Dynamic Content Modals (Single-Instance Pattern) <!-- learned: 2026-04-06 -->

When building modals with dynamically loaded/swapped content (e.g., loading spinner → form),
use **one Bootstrap Modal instance** and swap `innerHTML` of header/body/footer. Never
destroy and recreate the Modal instance — Bootstrap's transition callbacks fire on the
disposed element (`null.style` TypeError).

```javascript
// ✅ CORRECT — single modal, content swap
function createAndShowModal() {
  container.innerHTML = `<div class="modal fade" id="myModal">...</div>`;
  modalEl = document.getElementById("myModal");
  modal = new bootstrap.Modal(modalEl, { backdrop: "static" });
  modal.show();
}
function swapContent(html) {
  modalEl.querySelector(".modal-body").innerHTML = html;
}

// ❌ WRONG — dispose + recreate causes transition race
cleanup();          // dispose() mid-transition
buildNewModal();    // new Modal instance → TypeError
```

**Close/dismiss gotcha:** `data-bs-dismiss="modal"` does NOT reliably fire on
dynamically swapped buttons. Use explicit click handlers:

```javascript
// ✅ CORRECT — explicit close handler
function closeModal() {
  cleanup();   // dispose + remove element from DOM
  refreshData();
}
document.getElementById("cancelBtn").addEventListener("click", closeModal);

// ❌ WRONG — data-bs-dismiss silently fails on swapped content
<button data-bs-dismiss="modal">Cancel</button>
```

**Widget cleanup before swap:** Destroy TomSelect/Quill instances BEFORE replacing
innerHTML, otherwise they hold references to removed DOM nodes.

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

## System Settings Panel Component — Standard Pattern <!-- learned: 2026-03-23 -->

**Use the Finance Dashboard pattern (http://localhost/finance/) as the gold standard for all settings pages.**

The `system-settings-panel.js` component displays and edits SystemConfig settings with automatic API integration. 

**Standard Implementation (Finance Dashboard Pattern):**

```php
<div class="container-fluid">
    <!-- 1. Context Row with Settings Toggle (Admin Only) -->
    <div class="row mb-3">
        <div class="col-12 d-flex align-items-center">
            <p class="text-muted mb-0 flex-grow-1">
                <i class="fa-solid fa-icon me-1"></i>
                <?= gettext('Context Info') ?>: <strong><?= $value ?></strong>
            </p>
            <?php if ($isAdmin): ?>
            <button class="btn btn-sm btn-outline-secondary" type="button" 
                data-bs-toggle="collapse" data-bs-target="#mySettings">
                <i class="fa-solid fa-cog"></i> <?= gettext('Settings') ?>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. Collapsible Settings Container (Initially Hidden) -->
    <?php if ($isAdmin): ?>
    <div class="collapse mb-3" id="mySettings"></div>
    <?php endif; ?>

    <!-- 3. Main Content Cards -->
    <div class="card mb-3">
        <!-- content -->
    </div>
</div>

<!-- 4. Settings Panel Assets & Init (after main content) -->
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function() {
    window.CRM.settingsPanel.init({
        container: '#mySettings',
        title: i18next.t('Quick Settings'),  // Keep consistent title
        icon: 'fa-solid fa-sliders',  // Or theme icon
        headerClass: 'bg-info',  // Use semantic color: bg-primary/success/danger/warning/info
        settings: [ 'iFYMonth', 'sSetting2' ],  // Predefined config keys
        onSave: function() {
            // Reload to reflect changes, or custom logic
            setTimeout(() => window.location.reload(), 1500);
        }
    });
});
</script>
```

**Key Principles:**
- Settings are **hidden by default** (collapsible) to keep page clean
- **Toggle button in header row** lets admins access settings when needed
- Settings panel initializes with **consistent title "Quick Settings"** and icon
- Use **semantic header colors** (`bg-info`, `bg-primary`, etc.) to differentiate sections
- Include **context information** (fiscal year, status, etc.) in the header row before the toggle
- **No custom form building** — let the component handle all rendering and API logic

**Available Setting Types:** `boolean`, `number`, `text`, `choice`, `password`, `date`, `textarea`, `json`

**Applies To:** Finance dashboard, admin logs, admin users, system settings — all pages that need settings management.

### Display Current Setting as Stat Card <!-- learned: 2026-03-23 -->

For critical settings that users should see at a glance, display the current value as a stat card (not just in the collapsible panel). Update it in real-time when the user saves changes.

**Example: Log Level Display & Update (System Logs page)**

```php
<!-- PHP: Log Level Stat Card (always visible) -->
<div class="row mb-3">
    <div class="col-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-secondary text-white avatar rounded-circle">
                            <i class="fa-solid fa-sliders icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium" id="currentLogLevelDisplay"><?= $currentLevelLabel ?></div>
                        <div class="text-muted"><?= gettext('Log Level') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Other stat cards below -->
</div>
```

```javascript
// JavaScript: Update display in real-time when settings change
var logLevelMap = {
    '100': 'DEBUG',
    '200': 'INFO',
    '250': 'NOTICE',
    '300': 'WARNING',
    '400': 'ERROR',
    '500': 'CRITICAL',
    '550': 'ALERT',
    '600': 'EMERGENCY',
};

$(document).ready(function() {
    window.CRM.settingsPanel.init({
        container: '#logSettings',
        settings: [ 'sLogLevel' ],
        onSave: function() {
            window.CRM.notify(i18next.t('Settings saved'), { type: 'success' });
            // Fetch updated value and refresh stat card
            $.ajax({
                url: window.CRM.path + 'api/system/config/sLogLevel',
                type: 'GET',
                success: function(data) {
                    var levelValue = data.value || '200';
                    var levelLabel = logLevelMap[levelValue] || 'INFO';
                    $('#currentLogLevelDisplay').text(levelLabel);
                }
            });
        }
    });
});
```

**Pattern:**
1. Create a **stat card with id for display element** (e.g., `#currentLogLevelDisplay`)
2. Store **setting value → label mapping** in JS object (e.g., `logLevelMap`)
3. On settings panel save, **fetch fresh value** via GET `/api/system/config/{key}`
4. **Update stat card text** with the new label
5. Users immediately see the change without page reload

### Settings Panel: Scripts Before Footer <!-- learned: 2026-03-30 -->

Settings panel CSS/JS **must** be loaded before `Include/Footer.php` — Footer closes `</body></html>`, so assets loaded after it produce invalid markup. Place the `<link>`, `<script>`, and init block between your page content and the Footer include. **Footer must be the last line of the file.**

```php
// ✅ CORRECT — settings panel before Footer; Footer is last line
<?php if ($isAdmin): ?>
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function () { window.CRM.settingsPanel.init({ ... }); });
</script>
<?php endif; ?>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>

// ❌ WRONG — scripts after Footer produce invalid markup (outside </html>)
<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
<link rel="stylesheet" ...>
<script ...></script>
```

### Settings Panel: Do Not Duplicate the Success Notification in onSave <!-- learned: 2026-03-30 -->

The component already fires `window.CRM.notify("Settings saved successfully")` internally after a successful save. Do **not** call `window.CRM.notify` again inside `onSave` — it will show two toasts.

```javascript
// ✅ CORRECT — component handles the notification; onSave just reloads
onSave: function () {
    setTimeout(function () { window.location.reload(); }, 1500);
}

// ❌ WRONG — double notification
onSave: function () {
    window.CRM.notify(i18next.t('Settings saved successfully'), { type: 'success' });
    setTimeout(function () { window.location.reload(); }, 1500);
}
```

Exception: if your `onSave` does something extra (e.g., "Refreshing upgrade info...") a custom message with distinct text is fine — users understand it's a follow-on action, not a duplicate.

### Settings Panel: Pass Full Config for Custom Settings <!-- learned: 2026-03-30 -->

For settings not in the built-in `SettingDefinitions` dictionary, pass full config objects instead of just setting names:

```javascript
settings: [{
    name: 'bAllowPrereleaseUpgrade',
    type: 'boolean',
    label: i18next.t('Allow Pre-release Upgrades'),
    tooltip: i18next.t("Description here")
}]
```

This avoids coupling page-specific settings into the generic `system-settings-panel.js`.

### TomSelect Boolean Dropdown: value="" Bug <!-- learned: 2026-03-30 -->

TomSelect hides options with `value=""` (treats them as placeholder/clear state). For boolean `<select>` elements, use `value="0"` for False, not `value=""`. The save logic already handles this — any non-`"1"` value is treated as false.

### TomSelect dropdownParent for Cards <!-- learned: 2026-03-30 -->

When TomSelect is inside a card with `table-responsive` or constrained overflow, dropdowns get clipped. Fix: pass `dropdownParent: 'body'` to TomSelect init and add a `body > .ts-dropdown` SCSS rule in `_tabler-bridge.scss` to preserve Tabler styling.

### Uppy v5 XHRUpload: Parse `response.responseText` to Surface Server Errors <!-- learned: 2026-04-21 -->

Uppy v5 wraps **every** non-2xx HTTP response in a `NetworkError` whose `.message` is **hardcoded** to `"This looks like a network error, the endpoint might be blocked by an internet provider or a firewall."` — regardless of the actual status code or response body. The `getResponseError` option from v3/v4 is **not wired up in v5** (the option name appears in a comment in `@uppy/xhr-upload/lib/index.js` but is never read).

If your endpoint returns a clean JSON 400 like `{ message: "Your CSV has duplicate column names…" }`, the user will still see the useless "network error" banner unless you parse `response.responseText` yourself inside `upload-error`:

```js
uppy.on("upload-error", (_file, error, response) => {
  // response (3rd arg) is the raw xhr — read responseText for the JSON body.
  let msg = null;
  const responseText = response?.responseText;
  if (responseText) {
    try {
      const parsed = JSON.parse(responseText);
      if (parsed && typeof parsed.message === "string" && parsed.message.length > 0) {
        msg = parsed.message;
      }
    } catch (_e) {
      // not JSON — fall through
    }
  }
  // error.isNetworkError is Uppy's hardcoded wrapper — ignore it.
  if (!msg && error && !error.isNetworkError && typeof error.message === "string") {
    msg = error.message;
  }
  setStatus("error", msg || i18next.t("Upload failed. Please check the file and try again."));
});
```

**Do NOT** try the documented `getResponseError` option — it's silently ignored. Confirmed in `node_modules/@uppy/xhr-upload/lib/index.js`: `this.opts.getResponseError` is never read, and `buildResponseError()` wraps 4xx/5xx in `NetworkError` which has a fixed message string.

See `webpack/csv-import.js` for a working example.

### Reading TomSelect values: never use jQuery `option:selected` <!-- learned: 2026-04-09 -->

TomSelect does **not** reliably mirror its current selection back onto the underlying `<option selected>` attribute — `$("#mySelect option:selected").val()` may return `undefined` even when the user has clearly picked an item. Always read the value via the TomSelect instance API:

```js
const el = document.getElementById("targetRoleSelection");
const value = el.tomselect ? el.tomselect.getValue() : window.jQuery(el).val();
```

This bit `promptSelection` in `CRMJSOM.js` (issue #8570 — "Cart empty to group" sent `groupID` only because `RoleID` was undefined and `JSON.stringify` silently dropped it, yielding a confusing `Invalid request data` 400). When forwarding select values into a payload, **also validate before sending** — `JSON.stringify({ a: undefined })` becomes `"{}"`, so missing values become silent server-side errors.

### marked.parse() XSS Prevention <!-- learned: 2026-03-30 -->

When rendering user-supplied markdown (e.g., GitHub release notes) with `marked`, strip raw HTML to prevent XSS:

```javascript
marked.use({
  renderer: {
    html() { return ""; },
    link({ href, text }) {
      const safeHref = href && /^https?:\/\//i.test(href) ? href : "#";
      return `<a href="${safeHref}" target="_blank" rel="noopener noreferrer">${text}</a>`;
    },
  },
});
```

## Async Button Handlers with i18next <!-- learned: 2026-03-08 -->

For action buttons that call APIs (refresh, save, delete), implement handlers in webpack entry points with proper localization.

**Pattern: Async Button Handler**

```javascript
// webpack/people/family-view.js
import { fetchAPIJSON } from "../api-utils";

document.addEventListener("DOMContentLoaded", function () {
  // Initialize i18next translation function
  const t = window.i18next ? i18next.t.bind(i18next) : (s) => s;

  const refreshBtn = document.getElementById("refresh-coordinates-btn");
  if (!refreshBtn) return;

  const familyId = parseInt(refreshBtn.dataset.familyId || "0");
  if (familyId <= 0) return;

  refreshBtn.addEventListener("click", async function () {
    const btn = this;
    const originalText = btn.innerHTML;

    try {
      btn.disabled = true;
      btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i>${t("Refreshing...")}`;

      const result = await fetchAPIJSON(`family/${familyId}/geocode`, {
        method: "POST",
      });

      if (result.success) {
        btn.classList.remove("btn-outline-success");
        btn.classList.add("btn-outline-primary");
        btn.innerHTML = `<i class="fa-solid fa-check mr-1"></i>${t("Coordinates Updated")}`;
        setTimeout(() => location.reload(), 1500);
      } else {
        btn.classList.remove("btn-outline-success");
        btn.classList.add("btn-outline-danger");
        btn.innerHTML = `<i class="fa-solid fa-exclamation-triangle mr-1"></i>${t("Failed to geocode")}`;
        btn.disabled = false;
        setTimeout(() => {
          btn.classList.remove("btn-outline-danger");
          btn.classList.add("btn-outline-success");
          btn.innerHTML = originalText;
        }, 3000);
      }
    } catch (error) {
      btn.classList.remove("btn-outline-success");
      btn.classList.add("btn-outline-danger");
      btn.innerHTML = `<i class="fa-solid fa-network-wired"></i> ${t("Error")}`;
      btn.disabled = false;
      console.error("API error:", error);
      setTimeout(() => {
        btn.classList.remove("btn-outline-danger");
        btn.classList.add("btn-outline-success");
        btn.innerHTML = originalText;
      }, 3000);
    }
  });
});
```

**Key Points:**
- Use `fetchAPIJSON()` from api-utils (includes error handling, type-safe)
- Initialize `i18next.t.bind()` once; reuse for all strings in that scope
- Fallback to identity function if i18next not loaded: `window.i18next ? i18next.t.bind(i18next) : (s) => s`
- All visible text wrapped with `${t("text")}` for translation support
- Button state transitions: loading → success/error → recovery
- Use template literals for HTML string interpolation: `` `<i class="..."></i>${t("text")}` ``

## Webpack Bundle Conditional Loading Bug <!-- learned: 2026-03-07 -->

**Never load a JS bundle inside a PHP conditional that hides the UI element it controls.**

A classic bug: the "Refresh Coordinates" button is shown when a family has no coordinates, but the bundle containing its click handler was only loaded inside the `hasLatitudeAndLongitude()` block — so the handler never registered when the button was visible.

```php
// ❌ WRONG — bundle only loads when map is shown; button handler never runs when button is visible
<?php if ($family->hasLatitudeAndLongitude()) : ?>
    <div id="map1"></div>
    <script src=".../people-family-view.min.js"></script>
<?php endif; ?>
<button id="refresh-coordinates-btn">Refresh</button>  <!-- shown when no coords -->

// ✅ CORRECT — always load the bundle; PHP conditional only controls the map div and config
<?php if ($family->hasLatitudeAndLongitude()) : ?>
    <div id="map1"></div>
    <script>window.CRM.familyMapConfig = ...;</script>
<?php endif; ?>
<script src=".../leaflet.js"></script>
<script src=".../people-family-view.min.js"></script>  <!-- always loaded -->
```

**Rule:** JS bundles that contain event handlers must always be loaded. Use `if (!config) return;` guards inside the JS, not PHP conditionals wrapping the `<script>` tag.

## importDemoData.js — Demo Data Trigger <!-- learned: 2026-03-19 -->

`src/skin/js/importDemoData.js` listens on these selectors and shows the import overlay:
- `#importDemoData`
- `#importDemoDataQuickBtn`
- `#importDemoDataV2`

The handler calls `e.preventDefault()`, so **use `<a href="#">`** as the trigger element, never a `<button>` with Bootstrap utility classes like `p-0 border-0` — those override padding on any wrapping card and break layout.

```html
<!-- ✅ CORRECT — <a> element, no Bootstrap utility conflicts -->
<a href="#" id="importDemoDataV2" class="gs-card gs-card--green">
    ...card content...
</a>

<!-- ❌ WRONG — Bootstrap p-0/border-0 strips padding from the card -->
<button id="importDemoDataV2" class="p-0 border-0 gs-card gs-card--green">
    ...
</button>
```

Include the script after your page content:
```php
<script src="<?= SystemURLs::assetVersioned('/skin/js/importDemoData.js') ?>"></script>
```

## Print Support (Native Browser Print) <!-- learned: 2026-03-28 -->

ChurchCRM uses native `window.print()` instead of separate print pages. Tabler/Bootstrap 5 handles the heavy lifting via `d-print-none` classes already on the navbar, sidebar, page-header breadcrumbs, and footer.

### Global Print CSS (`_utility-classes.scss`)

The `@media print` block in `src/skin/scss/_utility-classes.scss` hides interactive elements globally:

```scss
@media print {
  button.btn, a.btn, input.btn,  // narrowed — not bare .btn (preserves non-interactive styled spans)
  .dropdown, .modal, .fab-container,
  input, select, textarea, .form-control, .form-select,
  .dataTables_filter, .dataTables_length, .ts-wrapper,
  .nav-pills, .nav-tabs { display: none !important; }

  .tab-pane { display: block !important; opacity: 1 !important; }  // show ALL tab content
}
```

### Adding a Print Button to a Page

1. Add a `<button>` with a unique `id` (no `onclick` — CSP blocks inline handlers):
   ```php
   <button class="btn btn-ghost-secondary" id="printMyPage" title="<?= gettext('Print') ?>">
       <i class="fa-solid fa-print me-1"></i><?= gettext('Print') ?>
   </button>
   ```
2. Bind `window.print()` in the page's JS file:
   ```js
   $("#printMyPage").on("click", function () { window.print(); });
   ```
3. Mark the toolbar `d-print-none` so it hides when printing.
4. Any extra elements to hide: add `d-print-none` class (e.g., property assignment forms).

### Key Rules

- **Never use `onclick="window.print()"`** — CSP enforcement blocks inline scripts.
- **Never create separate print pages** — use `window.print()` from the existing page.
- Use `d-print-none` for page-specific elements not covered by the global rules.
- The page title (`<h2 class="page-title">`) is visible on print — breadcrumbs/buttons row is hidden.

### Pages with Print Buttons

| Page | Button ID | JS File |
|------|-----------|---------|
| PersonView | `#printPerson` | `skin/js/PersonView.js` |
| FamilyView | `#printFamily` | `skin/js/FamilyView.js` |
| GroupView | `#printGroup` | `skin/js/GroupView.js` |
| SS ClassView | `#printClass` | `skin/js/sundayschool-actions.js` |

---

## Forms — No Nested `<form>` Elements <!-- learned: 2026-03-29 -->

HTML does not allow nesting `<form>` inside another `<form>`. Nested forms cause unpredictable submission behaviour in browsers. A common mistake is wrapping a GET link in a POST form wrapper.

```html
<!-- ❌ WRONG — nested form inside outer form, has no effect and is invalid HTML -->
<form name="FilterForm" method="POST" action="ListEvents.php">
  ...
  <form method="POST" action="ListEvents.php" class="d-inline">
    <a href="ListEvents.php">Clear Filter</a>
  </form>
</form>

<!-- ✅ CORRECT — link is its own element, no form wrapper needed -->
<form name="FilterForm" method="POST" action="ListEvents.php">
  ...
  <a href="ListEvents.php" class="btn btn-sm btn-ghost-secondary">Clear Filter</a>
</form>
```

**Rule:** If an action is a plain link (GET navigation), use `<a>` directly — no `<form>` wrapper needed.

---

### Avatar Click-to-View Lightbox <!-- learned: 2026-03-30 -->

Clicking an avatar with an uploaded photo opens a lightbox overlay. **avatar-loader.ts is the single source of truth** for adding click classes — PHP templates should NOT add `.view-person-photo` / `.view-family-photo` manually.

**How it works:**
1. `avatar-loader.ts` fetches `/api/{type}/{id}/avatar` → gets `hasPhoto`
2. If `hasPhoto === true`, `loadUploadedPhoto.onload` adds `.view-person-photo` (or `.view-family-photo`) + `data-person-id` (or `data-family-id`) + `cursor: pointer`
3. Images inside `#uploadImageButton` or `#uploadImageTrigger` are skipped (profile upload buttons)
4. Initials and failed photo loads never get click classes
5. **`avatar-loader.ts` itself registers a single global delegated click handler** for `.view-person-photo` / `.view-family-photo` that calls `window.CRM.showPhotoLightbox()`. Per-page handlers are forbidden — they cause double-lightbox bugs.

**Rules:**
- Never add `.view-person-photo` / `.view-family-photo` in PHP templates that use avatar-loader
- **Never register a `.view-person-photo` / `.view-family-photo` click handler in any other file** — `avatar-loader.ts` owns it
- Dashboard handles its own click classes via `generatePhotoImg()` (sets `src` directly, avatar-loader skips it) but the global handler in avatar-loader still routes the click

**Exception:** Pages that render photos inline (not via avatar-loader) — like `verify-family-info.php` — must check `hasUploadedPhoto()` in PHP before adding click classes.

---

### Never Use `form-switch` for Functional Toggles <!-- learned: 2026-03-31 -->

`form-check form-switch` (Bootstrap 5 toggle/pill) must **not** be used when the checkbox functionally changes other form fields (like hiding/showing time pickers). Use Tabler's `form-selectgroup-pills` pattern instead.

**Why:** The switch style is confusing — it looks like a power toggle, not a data choice. When it drives side-effects (e.g. "All Day" stripping time), users expect a radio group or checkbox, not a toggle.

```html
<!-- ✅ CORRECT — Tabler selectgroup pills for mutually exclusive options -->
<div class="form-selectgroup form-selectgroup-pills">
  <label class="form-selectgroup-item">
    <input type="radio" name="eventDayType" value="timed" class="form-selectgroup-input" checked>
    <span class="form-selectgroup-label"><i class="fa-regular fa-clock me-1"></i>Timed</span>
  </label>
  <label class="form-selectgroup-item">
    <input type="radio" name="eventDayType" value="allday" class="form-selectgroup-input">
    <span class="form-selectgroup-label"><i class="fa-regular fa-sun me-1"></i>All Day</span>
  </label>
</div>
```

**Rule:** Reserve `form-switch` for pure settings toggles (enable/disable a feature). Use `form-selectgroup-pills` or plain `form-check` for any control that conditionally shows/hides or modifies other fields.

---

### Modal Edit Header Pattern <!-- learned: 2026-03-31 -->

When a modal's title is an editable input (create/edit forms), use a borderless underline input with a helper label above — not a plain `form-control` boxed input.

```html
<!-- ✅ CORRECT — breathing room, visual hierarchy, no box border noise -->
<div class="modal-header pb-0 border-bottom-0">
  <div class="w-100 me-3 pt-1">
    <label class="form-label text-muted small mb-1">Event Title</label>
    <input name="Title"
      class="form-control form-control-lg fw-bold border-0 border-bottom rounded-0 px-0"
      style="box-shadow:none" placeholder="e.g. Sunday Service">
  </div>
  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body pt-3" style="overflow:visible">
```

**Key classes:** `border-bottom-0` on header removes the divider line; `border-0 border-bottom rounded-0 px-0` on input gives an underline-only field; `pb-0` removes header bottom padding; `pt-3` on body adds breathing room.

---

### Calendar Badge Contrast Pattern <!-- learned: 2026-03-31 -->

When displaying coloured calendar name badges with user-defined `BackgroundColor`/`ForegroundColor`, always add a coloured dot + `border` class for contrast on light backgrounds.

```tsx
<span
  className="badge border"
  style={{
    backgroundColor: `#${calendar.BackgroundColor}`,
    color: `#${calendar.ForegroundColor}`,
    borderColor: `#${calendar.BackgroundColor}`,
  }}
>
  <span
    className="d-inline-block rounded-circle me-1"
    style={{ width: "8px", height: "8px", backgroundColor: `#${calendar.ForegroundColor}`, opacity: 0.7 }}
  />
  {calendar.Name}
</span>
```

---

## Comparing IANA Timezones in JS — Canonicalize Both Sides <!-- learned: 2026-04-22 -->

Never compare a server-supplied timezone string (`sTimeZone`) to
`Intl.DateTimeFormat().resolvedOptions().timeZone` with `===`. The browser
returns canonical IANA names (`America/New_York`, `UTC`) but the configured
value may be an alias (`US/Eastern`, `Etc/UTC`, `US/Pacific`) — all equivalent
zones, but a strict string compare fires a false "timezone mismatch" warning.

**Rule:** run the configured zone through
`Intl.DateTimeFormat(undefined, { timeZone: configured }).resolvedOptions().timeZone`
before comparing. The browser resolves the alias to its canonical form, and both
sides are then directly comparable. Wrap in `try/catch` — an invalid/unknown
zone throws `RangeError`.

```js
// ❌ WRONG — fires false warning for US/Eastern vs America/New_York
if (browser === configured) { /* show mismatch warning */ }

// ✅ CORRECT — canonicalize configured via Intl, then compare
let browser, canonicalConfigured;
try {
    browser = Intl.DateTimeFormat().resolvedOptions().timeZone;
    canonicalConfigured = Intl.DateTimeFormat(undefined, { timeZone: configured })
        .resolvedOptions().timeZone;
} catch (e) {
    return; // unknown zone — fail closed, no warning
}
if (browser === canonicalConfigured) return; // equivalent zones
```

See `src/event/views/calendar.php` for the calendar-badge mismatch warning.
Cypress stubs for this check must **pass through** when the caller supplies
an explicit `timeZone` option — see `cypress-testing.md` → "Stubbing the
Browser Timezone for UI Tests" for the stub gotcha.

---

## Collapse triggers must be `<button>`, not `<h4 data-bs-toggle>` <!-- learned: 2026-04-21 -->

Bootstrap 5 lets you put `data-bs-toggle="collapse"` on any element, but
headings are not keyboard-focusable — keyboard-only users can't expand or
collapse the section. Use a `<button>` (or add `role="button"`, `tabindex="0"`,
and Enter/Space key handlers) so the control is reachable.

```html
<!-- ❌ WRONG — <h4> is not focusable; keyboard users cannot toggle -->
<div class="card-header">
    <h4 data-bs-toggle="collapse" data-bs-target="#collapseDebug"
        aria-expanded="false" aria-controls="collapseDebug"
        style="cursor: pointer;">
        <i class="fa fa-terminal me-2"></i> SMTP Debug Log
    </h4>
</div>

<!-- ✅ CORRECT — <button> is focusable and gets Enter/Space for free -->
<div class="card-header p-0">
    <button type="button"
            class="btn btn-link w-100 text-start text-decoration-none text-reset p-3 m-0"
            data-bs-toggle="collapse" data-bs-target="#collapseDebug"
            aria-expanded="false" aria-controls="collapseDebug">
        <span class="h4 mb-0 d-flex align-items-center">
            <i class="fa fa-terminal me-2"></i> SMTP Debug Log
            <i class="fa fa-chevron-down ms-auto"></i>
        </span>
    </button>
</div>
```

Keep the `h4 mb-0` typography on the inner `<span>` so visual weight is
preserved. The card-header's `p-0` + the button's `p-3` keeps the hit target
filling the whole header, so clicking the icon still toggles.

---

## Safe hash lookup: `getElementById(hash.slice(1))`, not `querySelector(hash)` <!-- learned: 2026-04-21 -->

`document.querySelector(window.location.hash)` parses the hash as a CSS
selector — any fragment with chars that aren't valid in a selector (pasted
URLs, `.` inside an id, etc.) throws `DOMException` and kills the rest of
the page initializer. Use `getElementById` with the leading `#` stripped —
no selector parsing, never throws:

```js
// ❌ WRONG — throws DOMException on malformed hashes
var target = document.querySelector(window.location.hash);

// ✅ CORRECT — always safe; returns null for unknown ids
if (!window.location.hash) return;
var target = document.getElementById(window.location.hash.slice(1));
if (!target) return;
```

Apply this anywhere a page does deep-link / scroll-to-anchor handling.
Example site: `src/admin/views/debug.php` (the init code that opens a
tab-pane or collapse when `location.hash` matches its id).

---

## Files

**Compiled Assets:** `src/skin/v2/churchcrm.min.js`, `src/skin/v2/churchcrm.min.css`
**Webpack Entry Points:** `webpack/`
**Locale Files:** `locale/messages.json`, `locale/terms/messages.po`
**Build Config:** `webpack.config.js`, `package.json`

### Quill 2 — Toolbar is a Sibling, Not a Child <!-- learned: 2026-04-22 -->

Unlike Quill 1, Quill 2 inserts `.ql-toolbar` as a **previous sibling** of
the container element, not a child. Three gotchas:

1. **Double-border seam**: toolbar and container each render a full border,
   showing a 2px seam where they meet. Fix by removing the toolbar's
   bottom border and squaring the container's top corners so they read as
   one unified control.

   ```scss
   .ql-toolbar.ql-snow {
     border: 1px solid var(--tblr-border-color) !important;
     border-bottom-width: 0 !important;
     border-top-left-radius: var(--tblr-border-radius, 4px);
     border-top-right-radius: var(--tblr-border-radius, 4px);
   }
   .quill-editor-container.ql-container {
     border-top-left-radius: 0;
     border-top-right-radius: 0;
   }
   ```

2. **Double-init guard fails**: `container.querySelector('.ql-toolbar')` in
   the init function returns `null` because the toolbar isn't a descendant.
   Check the previous sibling instead: `container.previousElementSibling?.classList.contains('ql-toolbar')`.

3. **Height: 100% stretches to flex parent**: Quill's default
   `.ql-container { height: 100% }` makes the editor fill any flex-column
   ancestor, pushing sibling rows off the page. Override in SCSS:

   ```scss
   .quill-editor-container.ql-container,
   .ql-container.ql-snow {
     height: auto !important;
   }
   .quill-editor-container {
     min-height: 200px;
     max-height: 400px;   // cap even if content overflows — editor scrolls internally
     overflow: hidden;    // scrollbar lives inside .ql-editor
   }
   .quill-editor-container .ql-editor {
     min-height: inherit;
     max-height: inherit;
     overflow-y: auto;
   }
   ```

### Quill Container Sizing — `data-editor-size` Attribute <!-- learned: 2026-04-22 -->

The Quill container uses a `data-editor-size` data attribute for
compact / default / tall sizing instead of inline `style="min-height:..."`.
`QuillEditorHelper::getQuillEditorContainer()` accepts a named size and
auto-translates legacy pixel strings (e.g. `"100px"` → `"compact"`) for
back-compat:

```scss
.quill-editor-container {
  min-height: 200px; max-height: 400px;  // default
  &[data-editor-size="compact"] { min-height: 120px; max-height: 240px; }
  &[data-editor-size="tall"]    { min-height: 300px; max-height: 520px; }
}
```

Call sites ask for `'compact' | 'default' | 'tall'`, never inline px.

### Shared Form Renderer for Modal + Page Surfaces <!-- learned: 2026-04-22 -->

When the same form lives in two surfaces (e.g. a Bootstrap modal AND a
full-page editor), extract it into a **standalone ES module** that
exports a renderer returning a controller object. Both surfaces pass a
container element + callbacks; they own their own chrome (modal header/
footer vs page header/footer buttons):

```js
// webpack/event-form.js
export function renderEventEditor(container, event, calendars, eventTypes, options = {}) {
  const { titleHost = null, onValidityChange = null, groups = [] } = options;
  container.innerHTML = fieldsMarkup;
  // ... wire TomSelect/Quill/datetime/radios — all the same
  return {
    getEvent: () => event,
    validate,
    destroy: () => { /* tear down TomSelect/Quill */ },
  };
}
```

```js
// webpack/calendar-event-editor.js — modal caller
formController = renderEventEditor(modalBody, event, cals, types, {
  titleHost: modalHeader,
  groups,
  onValidityChange: (valid) => { saveBtn.disabled = !valid; },
});

// webpack/event-editor.js — full-page caller
const controller = renderEventEditor(mount, event, cals, types, {
  titleHost: inlineTitleDiv, groups,
  onValidityChange: (valid) => { if (saveBtn) saveBtn.disabled = !valid; },
});
```

The modal wraps it in Bootstrap chrome + `#eventSaveBtn`; the page wraps
it in a card + redirect-on-success. Net effect: **zero field-set drift
possible by construction** because there's one renderer. Both save paths
go to `POST /api/events` via a shared `saveEvent()` helper in the module.

### `titleHost` Option for Header-Embedded Title Input <!-- learned: 2026-04-22 -->

The modal renders the event title as a big bold input inside the
`.modal-header`, while the full-page renders it inline in the card body.
Same renderer, two placements: pass an optional `titleHost` element in
the options; if set, the title markup goes there; if not, it's prepended
to the container body. Keeps the split cleanly separated.

### New Events Need Default Start/End or Save Stays Disabled <!-- learned: 2026-04-22 -->

The unified event form validates `event.Start != null && event.End != null`.
The calendar modal gets these pre-filled from the clicked calendar day,
but the full-page surface has no click context — without a seed, Save
stays disabled forever:

```js
// webpack/event-editor.js — page mount
const defaultStart = new Date();
defaultStart.setMinutes(0, 0, 0);
defaultStart.setHours(defaultStart.getHours() + 1);
const defaultEnd = new Date(defaultStart);
defaultEnd.setHours(defaultEnd.getHours() + 1);
const event = { Id: 0, Title: '', Type: 0, PinnedCalendars: [], Start: defaultStart, End: defaultEnd, ... };
```

Always seed a sensible default time range when bootstrapping a form with
validation that depends on it.

### PinnedCalendars is Optional — Surface Empty State via Hint <!-- learned: 2026-04-22 -->

The data model allows events without pinned calendars (they just don't
appear on calendar views — matches legacy editor behaviour). Don't make
it hard-required in `validate()`; instead surface the empty state with
a warning hint that auto-shows/hides via TomSelect's change event:

```js
function updateCalendarsEmptyHint() {
  const hint = document.getElementById("calendarsEmptyHint");
  if (!hint) return;
  const empty = !event.PinnedCalendars || event.PinnedCalendars.length === 0;
  hint.classList.toggle("d-none", !empty);
}
tsCalendars.on("change", () => {
  event.PinnedCalendars = tsCalendars.getValue().map((v) => parseInt(v, 10));
  updateCalendarsEmptyHint();
  fireValidity();
});
updateCalendarsEmptyHint(); // initial sync
```

Hint markup:
```html
<div class="form-text text-warning d-none" id="calendarsEmptyHint">
  <i class="ti ti-info-circle me-1"></i>No calendar selected — this event will be saved but won't appear on any calendar view.
</div>
```

### Title Required-Feedback: Don't Flash on Mount <!-- learned: 2026-04-22 -->

Showing "This field is required" immediately on mount for an empty Title
input is visual noise — the user hasn't had a chance to type yet. Track
a `titleTouched` flag and only surface the error after the first `input`
or `blur` event:

```js
let titleTouched = Boolean(event.Title && event.Title.length > 0);
titleInput.addEventListener("input", () => {
  event.Title = titleInput.value;
  titleTouched = true;
  fireValidity();
});
titleInput.addEventListener("blur", () => {
  titleTouched = true;
  updateTitleFeedback();
});
function updateTitleFeedback() {
  // Only show when the user has interacted AND the title is empty.
  const show = titleTouched && event.Title !== undefined && event.Title.length === 0;
  titleFb.style.display = show ? "block" : "none";
}
```
