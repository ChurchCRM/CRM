# Church Information Configuration Page - Implementation Guide

**GitHub Issue**: [#8190](https://github.com/ChurchCRM/CRM/issues/8190)  
**Created**: March 5, 2026  
**Timeline**: 5-7 development days

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Current State Analysis](#current-state-analysis)
3. [Target Architecture](#target-architecture)
4. [Implementation Phases](#implementation-phases)
5. [File Changes Matrix](#file-changes-matrix)
6. [Testing Strategy](#testing-strategy)
7. [Documentation Updates](#documentation-updates)
8. [Launch Checklist](#launch-checklist)

---

## Executive Summary

### Problem
When a new ChurchCRM installation is configured, admins must navigate the **Edit General Settings** page where church information (name, address, logo, etc.) is mixed with technical system settings (timezones, email configs, advanced options). This creates:
- **Cognitive overload** for new administrators
- **Hidden critical data** buried in many tabs
- **Poor onboarding flow** with no enforcement of essential fields
- **Mixed concerns** in code architecture

### Solution
Create a dedicated `/admin/church-info` page that:
- **Organizes** church metadata into logical tabs
- **Enforces** critical field completion on first run
- **Improves** UX with focused, grouped forms
- **Documents** the new workflow for admins

### Value Add
- ✅ New users don't get overwhelmed by 20+ system settings
- ✅ Admins quickly access church-specific config
- ✅ System ensures church is configured before use begins
- ✅ Professional first-run flow (like modern SaaS)

---

## Current State Analysis

### Existing Fields (SystemConfig)

All church information fields already exist in the `system_config` table:

| Field | Name | Type | ID | Required |
|-------|------|------|----|----|
| sChurchName | Church Name | text | 1003 | **CRITICAL** |
| sChurchAddress | Church Address | text | 1004 | no |
| sChurchCity | Church City | text | 1005 | no |
| sChurchState | Church State | text | 1006 | no |
| sChurchZip | Church Zip | text | 1007 | no |
| sChurchCountry | Church Country | choice | 1047 | no |
| sChurchPhone | Church Phone | text | 1008 | no |
| sChurchEmail | Church Email | text | 1009 | no |
| iChurchLatitude | Church Latitude | number | 1010 | no |
| iChurchLongitude | Church Longitude | number | 1011 | no |
| sTimeZone | Time Zone | choice | 65 | no |
| sChurchWebSite | Church Website | text | (see ConfigItem) | no |

**Source**: [src/ChurchCRM/dto/SystemConfig.php](src/ChurchCRM/dto/SystemConfig.php) line 145+

### Current Display Location

**File**: [src/SystemSettings.php](src/SystemSettings.php)
- Displayed in "Church Information" tab
- Mixed with Email Setup, Map Settings, Report Settings, Localization tabs
- Accessed via `/SystemSettings.php` (legacy PHP MVC)
- Admin-only via role check

### Existing First-Run Check

**File**: [src/finance/views/dashboard.php](src/finance/views/dashboard.php#L241)
```php
$hasChurchInfo = !empty(SystemConfig::getValue('sChurchName')) && 
                 !empty(SystemConfig::getValue('sChurchAddress'));
```
- Shows ✓/✗ badge on Finance Dashboard
- Links to SystemSettings.php
- **Will need to update** to link to new `/admin/church-info`

### Admin Architecture

**Existing Admin Routes**: [src/admin/routes/](src/admin/routes/)
- Uses Slim 4 framework
- Routing patterns: `/admin/system`, `/admin/dashboard`
- Views use PhpRenderer at [src/admin/views/](src/admin/views/)
- Auth: AdminRoleAuthMiddleware required

**Example Route Pattern** (from [src/admin/routes/system.php](src/admin/routes/system.php)):
```php
$app->group('/system', function (RouteCollectorProxy $group): void {
    $group->get('/backup', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Backup Database'),
        ];
        return $renderer->render($response, 'backup.php', $pageArgs);
    });
    // ... more routes
});
```

---

## Target Architecture

### Route Definition

**File to Create/Modify**: `src/admin/routes/system.php`

```php
// Add to the /system route group:
$group->get('/church-info', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    
    // Fetch all church info values
    $churchInfo = [
        'sChurchName' => SystemConfig::getValue('sChurchName'),
        'sChurchAddress' => SystemConfig::getValue('sChurchAddress'),
        'sChurchCity' => SystemConfig::getValue('sChurchCity'),
        'sChurchState' => SystemConfig::getValue('sChurchState'),
        'sChurchZip' => SystemConfig::getValue('sChurchZip'),
        'sChurchCountry' => SystemConfig::getValue('sChurchCountry'),
        'sChurchPhone' => SystemConfig::getValue('sChurchPhone'),
        'sChurchEmail' => SystemConfig::getValue('sChurchEmail'),
        'iChurchLatitude' => SystemConfig::getValue('iChurchLatitude'),
        'iChurchLongitude' => SystemConfig::getValue('iChurchLongitude'),
        'sTimeZone' => SystemConfig::getValue('sTimeZone'),
        'sChurchWebSite' => SystemConfig::getValue('sChurchWebSite'),
    ];
    
    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Church Information'),
        'churchInfo' => $churchInfo,
        'countries' => Countries::getNames(),
        'timezones' => timezone_identifiers_list(),
    ];
    
    return $renderer->render($response, 'church-info.php', $pageArgs);
});

// POST handler for form submission
$group->post('/church-info', function (Request $request, Response $response): Response {
    $body = $request->getParsedBody();
    
    // Validate required field
    if (empty($body['sChurchName'])) {
        // Return to form with error
        // TODO: Implement with session flash or re-render
    }
    
    // Save all fields
    SystemConfig::setValue('sChurchName', $body['sChurchName']);
    SystemConfig::setValue('sChurchAddress', $body['sChurchAddress'] ?? '');
    // ...etc for all fields
    
    // Redirect with success message
    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/admin/system/church-info')
        ->withStatus(303);
});
```

### Controller (Future Enhancement)

While the above inline handlers work, a dedicated controller at `src/ChurchCRM/http/Controllers/Admin/ChurchInfoController.php` would be cleaner for a larger feature.

### View Template

**File to Create**: `src/admin/views/church-info.php`

Structure:
```php
<!-- Bootstrap tabs (vertical layout like AdminLTE) -->
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <!-- Tab navigation sidebar -->
            <div class="nav flex-column nav-tabs" role="tablist">
                <a class="nav-link active" href="#basic-info">Basic Information</a>
                <a class="nav-link" href="#location">Location</a>
                <a class="nav-link" href="#contact">Contact Information</a>
                <a class="nav-link" href="#map">Map & Coordinates</a>
                <a class="nav-link" href="#display">Display Preferences</a>
            </div>
        </div>
        <div class="col-md-9">
            <!-- Form with tabs -->
            <form method="POST" class="tab-content">
                <!-- CSRF token -->
                <input type="hidden" name="csrf_token" value="...">
                
                <!-- Tab 1: Basic Information -->
                <div id="basic-info" class="tab-pane active">
                    <label>Church Name *</label>
                    <input type="text" name="sChurchName" value="<?= e($churchInfo['sChurchName']) ?>" required>
                    
                    <label>Website</label>
                    <input type="url" name="sChurchWebSite" value="<?= e($churchInfo['sChurchWebSite']) ?>">
                </div>
                
                <!-- Tab 2: Location -->
                <div id="location" class="tab-pane">
                    <label>Address</label>
                    <input type="text" name="sChurchAddress" value="<?= e($churchInfo['sChurchAddress']) ?>">
                    
                    <label>City</label>
                    <input type="text" name="sChurchCity" value="<?= e($churchInfo['sChurchCity']) ?>">
                    
                    <!-- etc. -->
                </div>
                
                <!-- More tabs... -->
                
                <button type="submit" class="btn btn-primary">Save Church Information</button>
            </form>
        </div>
    </div>
</div>
```

### Middleware: First-Run Enforcement

**File to Create**: `src/ChurchCRM/Slim/Middleware/ChurchInfoRequiredMiddleware.php`

```php
<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ChurchInfoRequiredMiddleware implements MiddlewareInterface
{
    // Routes that should NOT trigger the redirect
    private const EXEMPT_ROUTES = [
        '/admin/church-info',           // The form itself
        '/admin/dashboard',              // Show warning instead
        '/logout',                       // Auth routes
        '/api/auth',                     // API auth
        '/admin/api/auth',              // Admin API auth
    ];

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $path = $request->getUri()->getPath();
        
        // Check if church name is configured
        if (empty(SystemConfig::getValue('sChurchName'))) {
            // Check if this is an exempt route
            $isExempt = false;
            foreach (self::EXEMPT_ROUTES as $exemptPath) {
                if (strpos($path, $exemptPath) === 0) {
                    $isExempt = true;
                    break;
                }
            }
            
            // Redirect to church-info if not exempt
            if (!$isExempt) {
                $response = $handler->handle($request);
                return $response
                    ->withHeader('Location', SystemURLs::getRootPath() . '/admin/system/church-info')
                    ->withStatus(303);
            }
        }
        
        return $handler->handle($request);
    }
}
```

**Register in** `src/admin/index.php`:
```php
// After auth middleware, before routing
$app->add(ChurchInfoRequiredMiddleware::class);
```

---

## Implementation Phases

### Phase 1: Setup (Day 1-2)

- [ ] Create route handlers in `/admin/routes/system.php`
- [ ] Create view template at `/admin/views/church-info.php`
- [ ] Add basic form with all tabs
- [ ] Test form displays correctly
- [ ] Test form saves via POST

### Phase 2: Middleware & Enforcement (Day 2-3)

- [ ] Create `ChurchInfoRequiredMiddleware.php`
- [ ] Register middleware in `/admin/index.php`
- [ ] Test redirect on empty church name
- [ ] Test exempt routes don't redirect
- [ ] Test dashboard shows warning

### Phase 3: Testing (Day 3-4)

- [ ] Update Finance Dashboard link to point to new page
- [ ] Write Cypress tests for:
  - Auth check (admin only)
  - Form submission (POST + AJAX)
  - Field validation (church name required)
  - First-run redirect behavior
  - Middleware exemptions
  - Dashboard badge updates
- [ ] Manual QA on fresh install

### Phase 4: Documentation (Day 4-5)

- [ ] Update `/docs/getting-started/first-run.md`
  - Add screenshot of new page
  - Explain each tab
  - Show new workflow
- [ ] Update wiki [Documentation-Links.md](wiki/Documentation-Links.md)
- [ ] Update CHANGELOG.md

### Phase 5: Polish & Review (Day 5-7)

- [ ] Code review feedback
- [ ] PR tests pass
- [ ] Final UX audit
- [ ] Merge to main

---

## File Changes Matrix

| File | Action | Impact | Priority |
|------|--------|--------|----------|
| `src/admin/routes/system.php` | **Add** GET/POST routes | Core functionality | P0 |
| `src/admin/views/church-info.php` | **Create** | UI/form | P0 |
| `src/ChurchCRM/Slim/Middleware/ChurchInfoRequiredMiddleware.php` | **Create** | First-run enforcement | P0 |
| `src/admin/index.php` | **Modify** | Register middleware | P0 |
| `src/finance/views/dashboard.php` | **Modify** | Update link to new page | P1 |
| `src/SystemSettings.php` | **No change** | Backward compat | - |
| `src/ChurchCRM/dto/SystemConfig.php` | **No change** | Uses existing fields | - |
| `docs.churchcrm.io/docs/getting-started/first-run.md` | **Update** | Document new page | P1 |
| `wiki/Documentation-Links.md` | **Update** | Link mapping | P2 |
| `CHANGELOG.md` | **Update** | Release notes | P1 |

---

## Testing Strategy

### Unit Tests (Cypress)

**Location**: `cypress/e2e/admin/church-info.cy.js` (create new)

```javascript
describe('Church Information Configuration', () => {
  
  beforeEach(() => {
    cy.visit('/admin/system/church-info');
  });
  
  it('requires admin authentication', () => {
    cy.clearCookies();
    cy.visit('/admin/system/church-info');
    cy.url().should('include', '/index.php'); // Login page
  });
  
  it('displays current church info', () => {
    cy.get('input[name="sChurchName"]').should('have.value', 'Test Church');
    cy.get('input[name="sChurchCity"]').should('have.value', 'Springfield');
  });
  
  it('prevents submit without church name', () => {
    cy.get('input[name="sChurchName"]').clear();
    cy.get('form').submit();
    cy.get('.invalid-feedback').should('be.visible');
  });
  
  it('saves form via POST', () => {
    cy.get('input[name="sChurchName"]').clear().type('New Church Name');
    cy.get('form').submit();
    cy.url().should('include', '/admin/system/church-info'); // Stays on page
    cy.get('input[name="sChurchName"]').should('have.value', 'New Church Name');
  });
  
  it('displays tabs correctly', () => {
    cy.get('[role="tab"]').should('have.length', 5);
    cy.get('[role="tab"]').eq(0).should('contain', 'Basic');
    cy.get('[role="tab"]').eq(1).should('contain', 'Location');
  });
});

describe('First-Run Enforcement', () => {
  
  beforeEach(() => {
    // Setup: clear church name
    cy.exec('php bin/cli.php --clear-church-name');
  });
  
  it('redirects to church-info if name not set', () => {
    cy.visit('/admin/dashboard');
    cy.url().should('include', '/admin/system/church-info');
  });
  
  it('does not redirect from church-info page', () => {
    cy.visit('/admin/system/church-info');
    cy.url().should('include', '/admin/system/church-info');
  });
  
  it('allows logout without church info', () => {
    cy.visit('/logout');
    cy.url().should('include', '/index.php'); // Login page OK
  });
});

describe('Dashboard Integration', () => {
  
  it('shows green badge when church info complete', () => {
    // Setup with full info
    cy.visit('/finance/dashboard.php');
    cy.get('.badge-success').should('be.visible');
    cy.get('.badge-success').should('contain', '✓');
  });
  
  it('shows red badge when church info incomplete', () => {
    // Setup: clear address
    cy.exec('php bin/cli.php --clear-church-address');
    cy.visit('/finance/dashboard.php');
    cy.get('.badge-danger').should('be.visible');
    cy.get('.badge-danger').should('contain', '✗');
  });
});
```

### Manual Testing Checklist

- [ ] Fresh install: Church name prompt appears
- [ ] Can't navigate elsewhere until church name set
- [ ] After setting church name, normal navigation works
- [ ] Existing installs: Church info already populated
- [ ] All tabs display current values
- [ ] Each field saves correctly
- [ ] Validation error messages are clear
- [ ] Mobile/responsive layout works
- [ ] Admin dashboard link points to new page
- [ ] Old SystemSettings page still works (backward compat)

---

## Documentation Updates

### Docs Site Updates

**File**: `docs.churchcrm.io/docs/getting-started/first-run.md`

Current structure (redirect to docs site):
- ✅ General Settings
- ✅ Email Settings
- ✅ Report Settings (mentions church info)
- ✅ Security Considerations
- ✅ System Locale

**Changes**:
1. Add new section **"Church Information Setup"** after General Settings
2. Add screenshot: `img/Setup/admin-church-info-page.png`
3. Update intro text to mention new dedicated page
4. Update Report Settings section to reference new page

**New Content**:
```markdown
### Church Information Setup

During first-run configuration, you'll see a prompt to configure your church's basic information. This ensures all reports and communications reflect your church's details.

![Church Information Configuration Page](/img/Setup/admin-church-info-1.png)

**To configure church information:**

1. In the admin menu, select **Church Information**
2. Complete the **Basic Information** tab:
   - **Church Name*** (required) — Your congregation's official name
   - **Website** — Your church's website URL
3. Complete other tabs as needed:
   - **Location** — Address details used on reports
   - **Contact** — Phone and email
   - **Map** — Geographic coordinates and timezone
   - **Display** — Custom headers and letterhead
4. Click **Save** when finished

*Note: The Church Name is required to proceed with other tasks in ChurchCRM.*
```

### Wiki Updates

**File**: `wiki/Documentation-Links.md`

Add mapping if applicable:
```
| First-Run Configuration | → | docs.churchcrm.io: [First Run Setup](https://docs.churchcrm.io/getting-started/first-run) |
```

---

## Launch Checklist

### Before Merge
- [ ] All code reviewed by maintainers
- [ ] All tests passing (Cypress + unit)
- [ ] No breaking changes to existing APIs
- [ ] Backward compatible with existing SystemSettings
- [ ] Code follows ChurchCRM standards (see CLAUDE.md)
- [ ] All strings use `gettext()` for localization

### Documentation
- [ ] Docs site updated with screenshots
- [ ] Wiki links updated
- [ ] CHANGELOG.md entry added
- [ ] Release notes drafted

### After Merge
- [ ] Deploy to demo site
- [ ] Test on staging with fresh install
- [ ] Get feedback from community
- [ ] Monitor for issues

---

## Future Enhancements

While not in scope for this issue, consider for future versions:

1. **Logo Upload**: Allow direct image upload (store in dedicated directory)
2. **Auto Geo-Lookup**: Click button to auto-populate lat/lon from address
3. **Form Sections**: Break into mobile-friendly wizard-style flow
4. **Validation**: Add more granular validation (phone format, etc.)
5. **Audit Log**: Track who changed church info and when
6. **Multi-Church**: Support for multi-church instances (future roadmap)

---

## References

### Related Code
- [src/ChurchCRM/dto/SystemConfig.php](src/ChurchCRM/dto/SystemConfig.php) — Configuration system
- [src/admin/routes/system.php](src/admin/routes/system.php) — Route patterns
- [src/admin/views/](src/admin/views/) — View examples (backup.php, users.php)
- [src/finance/views/dashboard.php](src/finance/views/dashboard.php) — Dashboard integration
- [src/ChurchCRM/Slim/](src/ChurchCRM/Slim/) — Middleware patterns

### Documentation
- [docs.churchcrm.io/getting-started/first-run.md](docs.churchcrm.io/docs/getting-started/first-run.md)
- [CRM/CLAUDE.md](CRM/CLAUDE.md) — Development standards
- [CRM/CONTRIBUTING.md](CRM/CONTRIBUTING.md) — PR guidelines

### Skills
- **admin-mvc-migration.md** — MVC pattern for admin pages
- **slim-4-best-practices.md** — Slim framework patterns
- **frontend-development.md** — UI/UX standards

---

**Status**: ✅ Planning Complete  
**Issue**: [#8190 on GitHub](https://github.com/ChurchCRM/CRM/issues/8190)  
**Created**: March 5, 2026
