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
    <div class="col-sm-6 col-lg-3">
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

## Files

**Compiled Assets:** `src/skin/v2/churchcrm.min.js`, `src/skin/v2/churchcrm.min.css`
**Webpack Entry Points:** `webpack/`
**Locale Files:** `locale/messages.json`, `locale/terms/messages.po`
**Build Config:** `webpack.config.js`, `package.json`
