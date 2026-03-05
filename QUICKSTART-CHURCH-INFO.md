# Church Information Configuration - Developer Quick Start

**GitHub Issue**: [#8190](https://github.com/ChurchCRM/CRM/issues/8190)  
**Implementation Guide**: [IMPLEMENTATION-CHURCH-INFO.md](IMPLEMENTATION-CHURCH-INFO.md)  
**Wireframes**: [CHURCH-INFO-WIREFRAMES.md](CHURCH-INFO-WIREFRAMES.md)

---

## TL;DR - What to Build

Create a new admin page at `/admin/church-info` that:
1. Shows a **tabbed form** with church information
2. **Enforces** church name on first run (redirects if empty)
3. Saves data to existing `system_config` table
4. Updates docs with screenshots

---

## Files to Create/Modify

### Create

| File | Purpose | Lines |
|------|---------|-------|
| `src/admin/views/church-info.php` | Form template with 5 tabs | ~200 |
| `src/ChurchCRM/Slim/Middleware/ChurchInfoRequiredMiddleware.php` | First-run enforcement | ~50 |
| `cypress/e2e/admin/church-info.cy.js` | Cypress tests | ~150 |

### Modify

| File | Change | Impact |
|------|--------|--------|
| `src/admin/routes/system.php` | Add GET/POST routes (30 lines) | Core |
| `src/admin/index.php` | Register middleware (1 line) | Enforcement |
| `src/finance/views/dashboard.php` | Update link (1 line) | UX |

### Update (Docs)

| File | Update |
|------|--------|
| `docs.churchcrm.io/docs/getting-started/first-run.md` | Add Church Info section + screenshot |
| `CHANGELOG.md` | Add feature entry |

---

## Quick Implementation Checklist

### Step 1: Create the Route Handlers (30 min)

**File**: `src/admin/routes/system.php`

Add to the `/system` route group:
```php
// GET - Display form
$group->get('/church-info', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    
    // Collect all church info from SystemConfig
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
    
    return $renderer->render($response, 'church-info.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Church Information'),
        'churchInfo' => $churchInfo,
        'countries' => Countries::getNames(),
        'timezones' => timezone_identifiers_list(),
    ]);
});

// POST - Save form
$group->post('/church-info', function (Request $request, Response $response): Response {
    $body = $request->getParsedBody();
    
    // Validate required field
    if (empty($body['sChurchName'])) {
        // TODO: Implement error handling (flash message or re-render with error)
    }
    
    // Save to system_config via SystemConfig API
    SystemConfig::setValue('sChurchName', $body['sChurchName']);
    SystemConfig::setValue('sChurchAddress', $body['sChurchAddress'] ?? '');
    SystemConfig::setValue('sChurchCity', $body['sChurchCity'] ?? '');
    SystemConfig::setValue('sChurchState', $body['sChurchState'] ?? '');
    SystemConfig::setValue('sChurchZip', $body['sChurchZip'] ?? '');
    SystemConfig::setValue('sChurchCountry', $body['sChurchCountry'] ?? '');
    SystemConfig::setValue('sChurchPhone', $body['sChurchPhone'] ?? '');
    SystemConfig::setValue('sChurchEmail', $body['sChurchEmail'] ?? '');
    SystemConfig::setValue('iChurchLatitude', $body['iChurchLatitude'] ?? '');
    SystemConfig::setValue('iChurchLongitude', $body['iChurchLongitude'] ?? '');
    SystemConfig::setValue('sTimeZone', $body['sTimeZone'] ?? '');
    SystemConfig::setValue('sChurchWebSite', $body['sChurchWebSite'] ?? '');
    
    // Redirect back with success message
    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/admin/system/church-info')
        ->withStatus(303);
});
```

**Reference**: See existing route patterns in `src/admin/routes/system.php` (backup, users, restore routes)

---

### Step 2: Create the View Template (60 min)

**File**: `src/admin/views/church-info.php`

Use Bootstrap tabs layout. Reference: `src/SystemSettings.php` for the original tabbed UI pattern.

```php
<?php
// Top of file - page header
require_once __DIR__ . '/../../Include/HeaderNotifies.php';
require_once __DIR__ . '/../../Include/Header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Navigation (Vertical Tabs) -->
        <div class="col-md-3">
            <div class="nav flex-column nav-tabs h-100" role="tablist">
                <a class="nav-link active" id="basic-tab" data-toggle="pill" href="#basic" role="tab">
                    <?= gettext('Basic Information') ?>
                </a>
                <a class="nav-link" id="location-tab" data-toggle="pill" href="#location" role="tab">
                    <?= gettext('Location') ?>
                </a>
                <!-- ... more tabs ... -->
            </div>
        </div>

        <!-- Main Form Area -->
        <div class="col-md-9">
            <form method="POST" action="<?= SystemURLs::getRootPath() ?>/admin/system/church-info" class="needs-validation">
                
                <!-- Tab 1: Basic Information -->
                <div class="tab-pane fade show active" id="basic" role="tabpanel">
                    <div class="form-group">
                        <label for="sChurchName">
                            <?= gettext('Church Name') ?>
                            <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="sChurchName" name="sChurchName" 
                               value="<?= e($churchInfo['sChurchName']) ?>" required
                               aria-describedby="churchNameHelp">
                        <small id="churchNameHelp" class="form-text text-muted">
                            <?= gettext('Required. Used on all reports and communications.') ?>
                        </small>
                        <div class="invalid-feedback">
                            <?= gettext('Church name is required.') ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="sChurchWebSite"><?= gettext('Website') ?></label>
                        <input type="url" class="form-control" id="sChurchWebSite" name="sChurchWebSite" 
                               value="<?= e($churchInfo['sChurchWebSite']) ?>">
                    </div>
                </div>

                <!-- Tab 2: Location -->
                <div class="tab-pane fade" id="location" role="tabpanel">
                    <!-- ... location fields ... -->
                </div>

                <!-- Tab 3: Contact Information -->
                <div class="tab-pane fade" id="contact" role="tabpanel">
                    <!-- ... contact fields ... -->
                </div>

                <!-- Tab 4: Map & Coordinates -->
                <div class="tab-pane fade" id="map" role="tabpanel">
                    <!-- ... map fields ... -->
                </div>

                <!-- Tab 5: Display Preferences -->
                <div class="tab-pane fade" id="display" role="tabpanel">
                    <!-- ... display fields ... -->
                </div>

                <!-- Form Actions -->
                <div class="form-actions mt-4">
                    <button type="submit" class="btn btn-primary">
                        <?= gettext('Save Church Information') ?>
                    </button>
                    <a href="<?= SystemURLs::getRootPath() ?>/admin/dashboard" class="btn btn-secondary">
                        <?= gettext('Cancel') ?>
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../Include/Footer.php';
?>
```

See wireframes document for all tab layouts: [CHURCH-INFO-WIREFRAMES.md](CHURCH-INFO-WIREFRAMES.md)

---

### Step 3: Create Middleware for First-Run Enforcement (20 min)

**File**: `src/ChurchCRM/Slim/Middleware/ChurchInfoRequiredMiddleware.php`

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
    private const EXEMPT_ROUTES = [
        '/admin/church-info',
        '/admin/dashboard',
        '/logout',
        '/api/auth',
    ];

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $path = $request->getUri()->getPath();
        
        // If church name is not set, redirect (unless exempt)
        if (empty(SystemConfig::getValue('sChurchName'))) {
            foreach (self::EXEMPT_ROUTES as $exemptPath) {
                if (strpos($path, $exemptPath) === 0) {
                    return $handler->handle($request);
                }
            }
            
            // Redirect to church-info
            $response = new \Slim\Psr7\Response();
            return $response
                ->withHeader('Location', SystemURLs::getRootPath() . '/admin/system/church-info')
                ->withStatus(303);
        }
        
        return $handler->handle($request);
    }
}
```

**Register** in `src/admin/index.php` (after auth, before routing):
```php
// Add near line 38, after middleware setup
$app->add(ChurchInfoRequiredMiddleware::class);
```

---

### Step 4: Create Cypress Tests (60 min)

**File**: `cypress/e2e/admin/church-info.cy.js`

```javascript
describe('Church Information Configuration', () => {
  
  beforeEach(() => {
    cy.login('admin@example.com', 'admin'); // Use your test admin
  });

  describe('Page Access', () => {
    it('loads church info page for admins', () => {
      cy.visit('/admin/system/church-info');
      cy.contains('Church Information').should('be.visible');
    });

    it('requires admin authentication', () => {
      cy.logout();
      cy.visit('/admin/system/church-info');
      cy.url().should('include', 'index.php'); // Redirected to login
    });
  });

  describe('Form Display', () => {
    beforeEach(() => {
      cy.visit('/admin/system/church-info');
    });

    it('displays all 5 tabs', () => {
      cy.get('[role="tab"]').should('have.length', 5);
    });

    it('loads current values', () => {
      cy.get('input[name="sChurchName"]').should('have.value', 'Test Church');
      cy.get('input[name="sChurchCity"]').should('have.value', 'Springfield');
    });

    it('shows church name as required', () => {
      cy.get('label:contains("Church Name")').within(() => {
        cy.get('.text-danger').should('exist'); // Red asterisk
      });
    });
  });

  describe('Form Validation', () => {
    beforeEach(() => {
      cy.visit('/admin/system/church-info');
    });

    it('prevents submit without church name', () => {
      cy.get('input[name="sChurchName"]').clear();
      cy.get('form').submit();
      cy.get('.invalid-feedback').should('be.visible');
    });

    it('allows submit with church name', () => {
      cy.get('input[name="sChurchName"]').clear().type('New Church');
      cy.get('form').submit();
      cy.url().should('include', '/admin/system/church-info');
    });
  });

  describe('Form Save', () => {
    beforeEach(() => {
      cy.visit('/admin/system/church-info');
    });

    it('saves via POST form submission', () => {
      cy.get('input[name="sChurchName"]').clear().type('Updated Church Name');
      cy.get('input[name="sChurchCity"]').clear().type('New City');
      cy.get('form').submit();
      
      // Verify saved
      cy.get('input[name="sChurchName"]').should('have.value', 'Updated Church Name');
      cy.get('input[name="sChurchCity"]').should('have.value', 'New City');
    });
  });

  describe('Tab Navigation', () => {
    beforeEach(() => {
      cy.visit('/admin/system/church-info');
    });

    it('switches tabs on click', () => {
      cy.get('[id="location-tab"]').click();
      cy.get('[id="location"]').should('have.class', 'show active');
    });

    it('preserves form state when switching tabs', () => {
      cy.get('input[name="sChurchName"]').type(' - Updated');
      cy.get('[id="location-tab"]').click();
      cy.get('[id="basic-tab"]').click();
      cy.get('input[name="sChurchName"]').should('contain.value', 'Updated');
    });
  });
});

describe('First-Run Enforcement', () => {
  
  beforeEach(() => {
    cy.task('db:clearChurchName');
    cy.login('admin@example.com', 'admin');
  });

  it('redirects to church-info when name is empty', () => {
    cy.visit('/admin/dashboard');
    cy.url().should('include', '/admin/system/church-info');
  });

  it('does not redirect from church-info page', () => {
    cy.visit('/admin/system/church-info');
    cy.url().should('include', '/admin/system/church-info');
    cy.url().should('not.include', 'redirect');
  });

  it('allows logout without church info', () => {
    cy.visit('/logout');
    cy.url().should('include', 'index.php');
  });

  it('allows access after setting church name', () => {
    cy.visit('/admin/system/church-info');
    cy.get('input[name="sChurchName"]').type('Test Church');
    cy.get('form').submit();
    cy.wait(500);
    
    cy.visit('/admin/dashboard');
    cy.url().should('include', '/admin/dashboard');
    cy.url().should('not.include', 'church-info');
  });
});

describe('Dashboard Integration', () => {
  
  it('shows green badge when church info is complete', () => {
    cy.task('db:setChurchInfo', {
      name: 'Test Church',
      address: '123 Main St'
    });
    cy.login('admin@example.com', 'admin');
    cy.visit('/finance/dashboard.php');
    
    cy.get('[data-church-info-status]').should('have.class', 'badge-success');
    cy.get('[data-church-info-status]').should('contain', '✔');
  });

  it('shows red badge when info is incomplete', () => {
    cy.task('db:clearChurchAddress');
    cy.login('admin@example.com', 'admin');
    cy.visit('/finance/dashboard.php');
    
    cy.get('[data-church-info-status]').should('have.class', 'badge-danger');
    cy.get('[data-church-info-status]').should('contain', '✗');
  });

  it('links to church-info page from dashboard', () => {
    cy.login('admin@example.com', 'admin');
    cy.visit('/finance/dashboard.php');
    
    cy.get('[data-church-info-status]').parent().find('a').click();
    cy.url().should('include', '/admin/system/church-info');
  });
});
```

---

### Step 5: Update Existing Files (10 min)

**File**: `src/finance/views/dashboard.php` (~line 241)

Change:
```php
<a href="<?= SystemURLs::getRootPath() ?>/SystemSettings.php" class="btn btn-sm btn-outline-secondary">
```

To:
```php
<a href="<?= SystemURLs::getRootPath() ?>/admin/system/church-info" class="btn btn-sm btn-outline-secondary">
```

---

### Step 6: Update Documentation (30 min)

**File**: `docs.churchcrm.io/docs/getting-started/first-run.md`

Add new section after "General Settings":

```markdown
### Church Information Setup

Your church's basic information is required before proceeding with other configuration. This information appears on reports, communications, and throughout the system.

![Church Information Configuration](/img/Setup/admin-church-info-1.png)

**To configure church information:**

1. During first-run setup, you'll be prompted to enter church information
2. If not prompted, open **Admin** → **Church Information** from the sidebar
3. Complete at least the **Basic Information** tab:
   - **Church Name*** — Your congregation's legal name (required)
   - **Website** — Your church's website URL (optional)
4. Other tabs are optional:
   - **Location** — Used on printed reports and directories
   - **Contact** — Phone and email for administrative purposes
   - **Map** — Geographic coordinates and timezone
   - **Display** — Custom headers and letterhead for reports

Click **Save Church Information** when finished.
```

**Update** section headers if moving content from Report Settings section.

---

## Testing Checklist

- [ ] Form displays all tabs correctly
- [ ] Form preserves field values when switching tabs
- [ ] Form saves without errors via POST
- [ ] Church name validation works (required field)
- [ ] Redirect happens when church name is empty
- [ ] Redirect doesn't happen on exempt routes
- [ ] Dashboard link points to new page
- [ ] Dashboard badge updates after save
- [ ] Mobile responsive layout works
- [ ] All strings are translatable (gettext)
- [ ] Existing SystemSettings page still works

---

## Key Code Patterns Used

### SystemConfig Access
```php
SystemConfig::getValue('sChurchName');      // Get
SystemConfig::setValue('sChurchName', $val); // Set
```

### Routing in Slim 4
```php
$group->get('/path', function (Request $request, Response $response): Response {
    // Handler
});
```

### View Rendering
```php
$renderer = new PhpRenderer(__DIR__ . '/../views/');
return $renderer->render($response, 'template.php', ['data' => $data]);
```

---

## Common Pitfalls

❌ **Don't**:
- Modify SystemConfig directly via SQL — use the API
- Forget to use `gettext()` for user-visible strings
- Skip validation on form submission
- Modify fields in SystemSettings.php (keep backward compat)
- Redirect inside view templates (use middleware instead)

✅ **Do**:
- Use existing `SystemConfig::getValue/setValue` methods
- Wrap all strings in `gettext()` for i18n
- Validate on both client (HTML5) and server (PHP)
- Reference existing route/view patterns
- Test on fresh install for first-run flow

---

## Resources

### Related Code Files
- Routing patterns: `src/admin/routes/system.php`
- View examples: `src/admin/views/backup.php`, `users.php`
- Middleware patterns: `src/ChurchCRM/Slim/Middleware/AuthMiddleware.php`
- Config system: `src/ChurchCRM/dto/SystemConfig.php`
- Original form: `src/SystemSettings.php`

### Documentation
- Slim 4: https://www.slimframework.com/docs/v4/
- Bootstrap 4: https://getbootstrap.com/docs/4.6/
- Cypress: https://docs.cypress.io/

### ChurchCRM Developer Guides
- **CLAUDE.md** (project standards)
- **CONTRIBUTING.md** (PR guidelines)
- `.agents/skills/churchcrm/SKILL.md` (architecture overview)

---

**Status**: ✅ Ready for Development  
**Estimated Time**: 5-7 days  
**Issue**: [#8190](https://github.com/ChurchCRM/CRM/issues/8190)
