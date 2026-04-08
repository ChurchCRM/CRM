---
title: "Admin MVC Module Migration"
intent: "Patterns and steps to migrate legacy pages into the Admin MVC structure"
tags: ["admin","mvc","migration","slim"]
prereqs: ["service-layer.md","php-best-practices.md"]
complexity: "intermediate"
---
- **Routes**: `src/admin/routes/[feature].php` - Define route endpoints
- **APIs**: `src/admin/routes/api/[feature-api].php` - Admin API endpoints
- **Services**: `src/ChurchCRM/Service/[Feature]Service.php` - Business logic (shared with APIs)

## Routing Structure

### Admin System Pages (consolidated at `/admin/system/`)

- **Routes**: `src/admin/routes/system.php`
- **Views**: `src/admin/views/`
- **Examples**: `/admin/system/debug`, `/admin/system/backup`
- **Menu entries**: `src/ChurchCRM/Config/Menu/Menu.php`
- **Security**: AdminRoleAuthMiddleware

### Admin APIs (NEW - use this for admin APIs)

- **Location**: `src/admin/routes/api/` (NOT in `src/api/routes/system/`)
- **Example**: `orphaned-files.php` contains `/admin/api/orphaned-files/delete-all` endpoint
- **Routes prefixed**: `/admin/api/` when accessed from frontend
- **Naming**: Use kebab-case for endpoint names (e.g., `/delete-all`)
- **Security**: AdminRoleAuthMiddleware applied at router level

### Finance Module (consolidated at `/finance/`)

- **Entry point**: `src/finance/index.php` with Slim 4 app
- **Routes**: `src/finance/routes/` (dashboard.php, reports.php)
- **Views**: `src/finance/views/`
- **Examples**: `/finance/` (dashboard), `/finance/reports`
- **Security**: FinanceRoleAuthMiddleware (allows admin OR finance permission)
- **Menu entry**: `src/ChurchCRM/Config/Menu/Menu.php` under "Finance"

### Deprecated locations (DO NOT USE)

- ❌ `src/v2/routes/admin/` - REMOVED (admin routes consolidated to `/admin/system/`)
- ❌ `src/api/routes/system/` - Legacy admin APIs (no new files here)

## Key Migration Steps

1. **Extract business logic** from the legacy PHP file into a Service class
2. **Create views** in `src/admin/views/` to render the UI with initial state server-side
3. **Create routes** in `src/admin/routes/` that call the Service and pass data to views
4. **Create APIs** in `src/admin/routes/api/` if UI needs dynamic updates (optional)
5. **Update menu** entries in `src/ChurchCRM/Config/Menu/Menu.php` to point to new route

## Example: User Management Module Migration

### Legacy (mixed concerns)

```
src/UserList.php - Mixed HTML, SQL, business logic, hardcoded settings
```

### Modern (separated concerns)

```
src/ChurchCRM/Service/UserService.php
    - getUserStats() - Statistics + settings config
    - getUserSettingsConfig() - Dynamic settings from SystemConfig

src/admin/views/users.php
    - Dashboard with stats cards
    - User table
    - Settings panels from config

src/admin/routes/api/user-admin.php
    - User operations (reset password, delete, 2FA)
    - All use UserService methods
```

## Route Example

**src/admin/routes/system.php:**

```php
use ChurchCRM\dto\SystemURLs;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

use ChurchCRM\view\PageHeader;

$app->get('/admin/system/users', function (Request $request, Response $response): Response {
    $container = $this->getContainer();
    $userService = $container->get('UserService');

    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    return $renderer->render($response, 'users.php', [
        'sRootPath'          => SystemURLs::getRootPath(),
        'sPageTitle'         => gettext('System Users'),
        'sPageSubtitle'      => gettext('Manage system users, permissions, and 2FA'),
        'aBreadcrumbs'       => PageHeader::breadcrumbs([
            [gettext('Admin'), '/admin/'],
            [gettext('System Users')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Settings'), 'icon' => 'fa-cog', 'collapse' => '#userSettings'],
            ['label' => gettext('Add User'), 'url' => '/UserEditor.php', 'icon' => 'fa-user-plus'],
        ]),
        'sSettingsCollapseId' => 'userSettings',
        'stats'    => $userService->getUserStats(),
        'settings' => $userService->getUserSettingsConfig(),
    ]);
});
```

## View Example

**src/admin/views/users.php:**

```php
<?php
use ChurchCRM\dto\SystemURLs;

// Header
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="row">
    <div class="col-lg-3 col-md-6">
        <div class="small-box bg-aqua">
            <div class="inner">
                <h3><?= $data['stats']['total'] ?></h3>
                <p><?= gettext('Total Users') ?></p>
            </div>
        </div>
    </div>
    <!-- More stats cards -->
</div>

<div class="box">
    <div class="box-header">
        <h3 class="box-title"><?= gettext('Users') ?></h3>
    </div>
    <div class="box-body">
        <!-- User table/content -->
    </div>
</div>

<?php
// Footer
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
?>
```

## API Example

**src/admin/routes/api/user-admin.php:**

```php
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/admin/api/users', function (RouteCollectorProxy $group): void {
    $group->post('/{userId}/reset-password', function (Request $request, Response $response, array $args): Response {
        try {
            $container = $this->getContainer();
            $userService = $container->get('UserService');
            
            $userId = (int)$args['userId'];
            $result = $userService->resetPassword($userId);
            
            return SlimUtils::renderJSON($response, ['data' => $result]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to reset password'), [], 500, $e, $request);
        }
    });
});
```

## SystemConfig for Settings Panels

**Use dynamic settings configuration:**

```php
// In Service
public function getUserSettingsConfig(): array 
{
    $userSettings = [
        'iSessionTimeout',
        'iMaxFailedLogins',
        'bEnableLostPassword'
    ];
    return SystemConfig::getSettingsConfig($userSettings);
}

// In View (example rendering)
<?php foreach ($data['settings'] as $setting): ?>
    <div class="form-group">
        <label><?= $setting['name'] ?></label>
        <input type="<?= $setting['type'] ?>" 
               value="<?= $setting['value'] ?>" 
               name="<?= $setting['category'] ?>" />
    </div>
<?php endforeach; ?>
```

## Migrating Multi-Form Pages — Action-Switch vs Split Routes <!-- learned: 2026-04-08 -->

Legacy pages often contain several `<form>` tags that all post to the same URL (Save Name,
Save Time, Add Count, Delete Count, etc.). When migrating to MVC, choose between two
patterns based on form complexity:

1. **Single POST handler with Action switch** — one `POST /resource/{id}` route that
   dispatches on `$body['Action']` (or a hidden `Action` form field). Keeps URLs cleaner
   and matches the legacy page 1:1, reducing diff risk.
2. **Split into multiple POST routes** — one route per action:
   `POST /resource/{id}/name`, `POST /resource/{id}/time`, `POST /resource/{id}/counts`.
   More REST-conformant and easier to test individually.

Both work. Prefer the Action-switch approach when migrating a legacy multi-form page
verbatim (all forms share layout/validation); prefer split routes when forms are
independent, when one form needs different auth, or when you're adding new actions.

```php
// Action-switch example (src/event/routes/event-types.php)
$app->post('/event/types/{id}', function (Request $req, Response $res, array $args): Response {
    $body = (array) $req->getParsedBody();
    $action = $body['Action'] ?? '';
    return match (true) {
        $action === 'ADD'          => handleAdd($args['id'], $body),
        $action === 'NAME'         => handleName($args['id'], $body),
        $action === 'TIME'         => handleTime($args['id'], $body),
        $action === 'SAVE'         => handleSaveCounts($args['id'], $body),
        str_starts_with($action, 'DELETE_') => handleDelete($args['id'], $action),
        default                    => RedirectUtils::redirect('/event/types/' . (int) $args['id']),
    };
});
```

## Middleware Order (CRITICAL) <!-- learned: 2026-04-07 -->

> **Full reference:** [`slim-4-best-practices.md` → Middleware Order](./slim-4-best-practices.md)

For MVC modules, use `MvcAppFactory::create()` which handles all ordering automatically:

```php
$app = MvcAppFactory::create('/admin', [
    'dashboardUrl'  => '/admin/',
    'dashboardText' => gettext('Return to Admin Dashboard'),
    'roleMiddleware' => AdminRoleAuthMiddleware::class,
]);
```

## Entry Point Error Handling

> **Full reference:** [`slim-4-best-practices.md` → Error Handler Architecture](./slim-4-best-practices.md)

For MVC modules use `MvcAppFactory`. For non-MVC entry points (API, external, session),
add error middleware manually — always AFTER `addRoutingMiddleware()`.

## Admin Dashboard Setup Checklist Pattern <!-- learned: 2026-03-19 -->

The admin dashboard (`/admin/`) includes a **Setup Progress checklist** computed in the route and passed to the view. Pattern for adding a new checklist step:

**Route** (`src/admin/routes/dashboard.php`): compute a `$hasX` boolean, add it to `$completedSteps`, and add an entry to `$setupChecklist`.

```php
// Call PluginManager::init() before any plugin state checks — see plugin-system.md
PluginManager::init(SystemURLs::getDocumentRoot() . '/plugins');
$hasPlugins = PluginManager::hasAnyActivePlugin();

$setupChecklist[] = [
    'done'  => $hasPlugins,
    'label' => gettext('Enable Plugins'),
    'desc'  => gettext('Extend ChurchCRM with MailChimp, backups, and more'),
    'link'  => SystemURLs::getRootPath() . '/plugins/management',
    'icon'  => 'fa-plug',
];
```

The checklist card auto-hides when `$allDone === true`. The Quick Start shortcuts mirror the checklist steps in the same order.

## Get Started Wizard — /admin/get-started <!-- learned: 2026-03-19 -->

A dedicated onboarding wizard for new installs with 4 data-import paths:

| Card | Link | Notes |
|------|------|-------|
| Explore with Demo Data | `#importDemoDataV2` (JS trigger) | `<a role="button">` — never `<button>` (breaks card padding) |
| Import from a Spreadsheet | `CSVImport.php` | |
| Enter Data Manually | `/admin/get-started/manual` | guided intro page (see below) |
| Restore a Backup | `/admin/system/restore?context=onboarding` | |

**Files:** View: `src/admin/views/get-started.php`. Route: `GET /get-started` in `src/admin/routes/dashboard.php`. Webpack: `webpack/get-started.js` + `webpack/get-started.css`.

Uses `.gs-card` with top-border accents (`.gs-card--green`, `--blue`, `--teal`, `--orange`) inside `.gs-wrap` (max-width: 900px). Grid: `col-sm-6` (2×2).

## Start Fresh Guided Page — /admin/get-started/manual <!-- learned: 2026-03-19 -->

Guided manual-entry intro explaining the Family → Person recommended order. Key content:

- Numbered steps: Add a Family first (shared address/phone) → then add People to it
- Quick Tips including: **donations are tracked at the family level** — individuals who live alone need a single-person family to record giving against
- Sidebar: Family vs Person concept explainer + "Have Existing Data?" CSV shortcut

**Files:** View: `src/admin/views/get-started-manual.php`. Route: `GET /get-started/manual` in `src/admin/routes/get-started.php` (registered in `src/admin/index.php` after `dashboard.php`). No webpack entry — inherits admin styles.

**Important:** `GET /get-started` (landing) lives in `dashboard.php`. `routes/get-started.php` only registers `/manual` — do not add a `$group->get('', ...)` handler there or Slim will silently register the same route twice (first wins).

## Tabler Bridge File — TEMPORARY DO NOT USE FOR UX FIXES <!-- learned: 2026-03-21 -->

**CRITICAL:** `src/skin/scss/_tabler-bridge.scss` is a **temporary bridge** that should be removed once entire app is native Tabler.

❌ **DON'T** add CSS to bridge file during UX fixes or feature work
- Bridge file is meant to be temporary (eventually deleted)
- UX fixes should use **native Tabler classes** in HTML, not custom CSS
- Adding custom CSS to bridge perpetuates technical debt

✅ **DO** solve UX problems using:
- **Native Tabler classes** (`.navbar-brand`, sizing utilities, spacing classes)
- **Inline CSS** in specific templates (for auth pages)
- **Layout adjustments** in HTML structure (flexbox, grid, alignment)
- **Tabler variables** (colors, spacing, breakpoints) if available

**Pattern:** If native Tabler doesn't have a class you need, add it inline to that specific template, NOT to the bridge file.

---

## Authentication & Login Pages — Modern Tabler Styling

**When redesigning public-facing authentication pages (login, password reset, 2FA, etc.):**

### Approach: Inline CSS Only

✅ **DO:** Use `<style>` blocks with **inline CSS** directly in the template file
- Keeps form pages self-contained and isolated
- Authentication pages are simple (no complex interactions)
- Avoids polluting global bridge stylesheets
- Easy to maintain and iterate (all code in one file)

❌ **DON'T:** Add CSS to `_tabler-bridge.scss` or other global files
- Bridge is temporary and should be deleted once app is fully native Tabler
- Page-specific styling should stay in templates, not shared infrastructure
- Keep infrastructure CSS confined to bridge-only systemic changes

### Architecture

**File:** `src/session/templates/[page].php`
- Single PHP file with embedded `<style>` block
- Use CSS classes for structure (`.login-hero`, `.btn-sign-in`, etc.)
- Leverage Tabler colors: `#667eea` (primary), `#764ba2` (dark)
- Gradient backgrounds: `linear-gradient(135deg, #667eea 0%, #764ba2 100%)`

### Pattern Example

```php
<?php
use ChurchCRM\dto\SystemURLs;
$sPageTitle = gettext('Login');
require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';
?>

<style>
  body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .login-wrapper {
    display: flex;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    background: white;
  }

  .login-hero {
    flex: 1;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 60px 40px;
    color: white;
  }

  .btn-sign-in {
    width: 100%;
    padding: 12px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
  }

  .btn-sign-in:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
  }

  /* Responsive */
  @media (max-width: 768px) {
    .login-wrapper {
      flex-direction: column;
    }
  }
</style>

<div class="login-container">
  <div class="login-wrapper">
    <!-- Hero and form sections -->
  </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php'; ?>
```

### Key CSS Patterns for Auth Pages

| Element | Pattern | Notes |
|---------|---------|-------|
| **Background** | `linear-gradient(135deg, #667eea 0%, #764ba2 100%)` | Full-screen gradient |
| **Card** | `border-radius: 12px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);` | Modern elevation |
| **Primary Button** | Gradient fill + hover lift (`transform: translateY(-2px)`) | Focus action |
| **Secondary Button** | White bg + border + hover background | Alternative action |
| **Focus State** | `box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);` | Accessibility |
| **Responsive** | `@media (max-width: 768px)` with `flex-direction: column` | Stack on mobile |
| **Text Colors** | White on gradient (`rgba(255, 255, 255, 0.x)`) | high opacity for text, lower for borders |

### Reusable CSS Utilities for Auth Forms

Once pattern is established, these can be safely copied to new auth pages:

```css
/* Form group structure */
.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 500;
  color: #333;
  font-size: 14px;
}

.form-group input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #ddd;
  border-radius: 6px;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.form-group input:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}
```

### Testing & Validation

- Test responsive on mobile (480px, 768px breakpoints)
- Verify all form inputs have focus states
- Check button hover effects
- Ensure logo and images don't have unwanted filters
- Run `npm run build && npm run lint` before commit

## Files

**Views:** `src/admin/views/`, `src/finance/views/`
**Routes:** `src/admin/routes/`, `src/finance/routes/`
**APIs:** `src/admin/routes/api/`
**Services:** `src/ChurchCRM/Service/`
**Menu:** `src/ChurchCRM/Config/Menu/Menu.php`
**Middleware:** `src/ChurchCRM/Slim/Middleware/AdminRoleAuthMiddleware.php`
