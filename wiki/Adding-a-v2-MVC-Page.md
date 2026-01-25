# Adding a New Page with v2 MVC Style and Propel ORM

This guide explains how to add a new page to ChurchCRM using the modern `/v2` MVC architecture with Propel ORM for database access. We'll use the **Photo Directory** feature (Issue #7899) as a working example.

## Overview

The v2 MVC architecture separates concerns into:
- **Routes** (`src/v2/routes/`) - Handle HTTP requests and prepare data
- **Templates** (`src/v2/templates/`) - Render HTML views
- **ORM Models** (`src/ChurchCRM/model/`) - Database access via Propel

## Architecture Diagram

```
HTTP Request → index.php → Routes → Template → HTML Response
                              ↓
                         Propel ORM → Database
```

## Step-by-Step Guide

### Step 1: Plan Your Feature

Before coding, identify:
- **URL path**: Where will the page live? (e.g., `/v2/people/photos`)
- **Data needed**: What database queries are required?
- **User interactions**: Filters, pagination, actions?

**Example (Photo Directory):**
- URL: `/v2/people/photos`
- Data: All people with photo status
- Interactions: Filter by classification, toggle "photos only"

### Step 2: Add the Route

Routes are defined in `src/v2/routes/`. Choose an existing file or create a new one based on the feature area.

#### 2.1 Register the Route

In `src/v2/routes/people.php`, add your route to the group:

```php
$app->group('/people', function (RouteCollectorProxy $group): void {
    $group->get('/verify', 'viewPeopleVerify');
    $group->get('/photos', 'viewPeoplePhotoGallery');  // ← New route
    $group->get('/', 'listPeople');
    $group->get('', 'listPeople');
});
```

#### 2.2 Create the Route Handler Function

Add the handler function that:
1. Queries the database using Propel ORM
2. Prepares data for the template
3. Renders the template

```php
<?php
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\InputUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Photo Gallery view - displays medium-sized photos of all people with names.
 */
function viewPeoplePhotoGallery(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/people/');

    // Get query parameters for filtering
    $queryParams = $request->getQueryParams();
    $showOnlyWithPhotos = isset($queryParams['photosOnly']) && $queryParams['photosOnly'] === '1';
    $classificationFilter = isset($queryParams['classification']) 
        ? InputUtils::filterInt($queryParams['classification']) 
        : null;

    // Get classification list for filter dropdown (using Propel ORM)
    $classifications = ListOptionQuery::create()
        ->filterById(1)
        ->orderByOptionSequence()
        ->find();

    // Build query for people - using Propel ORM
    $peopleQuery = PersonQuery::create()
        ->joinWithFamily()
        ->orderByLastName()
        ->orderByFirstName();

    // Apply classification filter if specified
    if ($classificationFilter !== null) {
        $peopleQuery->filterByClsId($classificationFilter);
    }

    $people = $peopleQuery->find();

    // Build array of people with photo info
    $peopleData = [];
    foreach ($people as $person) {
        $photo = new Photo('Person', $person->getId());
        $hasPhoto = $photo->hasUploadedPhoto();

        // Skip people without photos if filter is enabled
        if ($showOnlyWithPhotos && !$hasPhoto) {
            continue;
        }

        $peopleData[] = [
            'person'   => $person,
            'hasPhoto' => $hasPhoto,
        ];
    }

    // Prepare template arguments
    $pageArgs = [
        'sRootPath'            => SystemURLs::getRootPath(),
        'sPageTitle'           => gettext('Photo Directory'),
        'peopleData'           => $peopleData,
        'classifications'      => $classifications,
        'showOnlyWithPhotos'   => $showOnlyWithPhotos,
        'classificationFilter' => $classificationFilter,
        'totalPeople'          => count($peopleData),
    ];

    return $renderer->render($response, 'photo-gallery.php', $pageArgs);
}
```

### Step 3: Create the Template

Templates are PHP files in `src/v2/templates/` organized by feature area.

Create `src/v2/templates/people/photo-gallery.php`:

```php
<?php
/**
 * Photo Gallery Template
 * 
 * Variables passed from route:
 * @var string $sRootPath
 * @var string $sPageTitle
 * @var array $peopleData
 * @var \Propel\Runtime\Collection\ObjectCollection $classifications
 * @var bool $showOnlyWithPhotos
 * @var int|null $classificationFilter
 * @var int $totalPeople
 */

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

// Include the standard header
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="card">
    <div class="card-header bg-primary">
        <h3 class="card-title">
            <i class="fa-solid fa-images mr-2"></i>
            <?= gettext('Photo Directory') ?>
        </h3>
    </div>
    
    <div class="card-body">
        <!-- Filter Form -->
        <form method="GET" action="<?= $sRootPath ?>/v2/people/photos" class="mb-4">
            <div class="row">
                <div class="col-md-4">
                    <select name="classification" class="form-control" onchange="this.form.submit()">
                        <option value=""><?= gettext('All Classifications') ?></option>
                        <?php foreach ($classifications as $cls): ?>
                            <option value="<?= $cls->getOptionId() ?>" 
                                <?= ($classificationFilter === $cls->getOptionId()) ? 'selected' : '' ?>>
                                <?= InputUtils::escapeHTML($cls->getOptionName()) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>

        <!-- Photo Grid -->
        <div class="row">
            <?php foreach ($peopleData as $data): 
                $person = $data['person'];
            ?>
                <div class="col-lg-2 col-md-3 col-sm-4 col-6 mb-4">
                    <div class="card h-100">
                        <a href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= $person->getId() ?>">
                            <?php if ($data['hasPhoto']): ?>
                                <img src="<?= $sRootPath ?>/api/person/<?= $person->getId() ?>/photo" 
                                     alt="<?= InputUtils::escapeAttribute($person->getFullName()) ?>"
                                     class="img-fluid"
                                     loading="lazy">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <?= substr($person->getFirstName(), 0, 1) . substr($person->getLastName(), 0, 1) ?>
                                </div>
                            <?php endif; ?>
                            <div class="card-body text-center">
                                <h6><?= InputUtils::escapeHTML($person->getFullName()) ?></h6>
                            </div>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
```

### Step 4: Add Menu Entry

Add your page to the navigation menu in `src/ChurchCRM/Config/Menu/Menu.php`:

```php
private static function getPeopleMenu(bool $isAdmin, bool $isMenuOptions, bool $isAddRecordsEnabled): MenuItem
{
    $peopleMenu = new MenuItem(gettext('People'), '', true, 'fa-user');
    $peopleMenu->addSubMenu(new MenuItem(gettext('Dashboard'), 'PeopleDashboard.php', true, 'fa-tachometer-alt'));
    $peopleMenu->addSubMenu(new MenuItem(gettext('Person Listing'), 'v2/people', true, 'fa-list'));
    $peopleMenu->addSubMenu(new MenuItem(gettext('Photo Directory'), 'v2/people/photos', true, 'fa-images')); // ← New
    // ... other menu items
}
```

### Step 5: Register Route File (if new)

If you created a new route file, register it in `src/v2/index.php`:

```php
require __DIR__ . '/routes/common/mvc-helper.php';
require __DIR__ . '/routes/user.php';
require __DIR__ . '/routes/people.php';  // ← Ensure this exists
require __DIR__ . '/routes/your-new-feature.php';  // ← Add new files here
// ...
```

## Key Patterns & Best Practices

### Propel ORM Usage

**Always use Propel Query classes** - never raw SQL:

```php
// ✅ CORRECT - Propel ORM
$people = PersonQuery::create()
    ->filterByClsId($classificationId)
    ->orderByLastName()
    ->find();

// ❌ WRONG - Raw SQL
$sSQL = "SELECT * FROM person_per WHERE per_cls_ID = " . $classificationId;
```

### Input Sanitization

Always sanitize user input:

```php
// Filter integers
$id = InputUtils::filterInt($_GET['id']);

// Escape HTML output
<?= InputUtils::escapeHTML($person->getName()) ?>

// Escape HTML attributes
<input value="<?= InputUtils::escapeAttribute($value) ?>">
```

### Asset Paths

Always use `SystemURLs::getRootPath()` for URLs:

```php
// ✅ CORRECT
<img src="<?= SystemURLs::getRootPath() ?>/api/person/<?= $id ?>/photo">

// ❌ WRONG - Breaks in subdirectory installs
<img src="/api/person/<?= $id ?>/photo">
```

### Internationalization

Wrap all user-facing text with `gettext()`:

```php
<?= gettext('Photo Directory') ?>
<?= sprintf(ngettext('%d person', '%d people', $count), $count) ?>
```

### Header/Footer Inclusion

Use `require` (not `include`) for critical files:

```php
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
// ... page content ...
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
```

## File Structure Summary

```
src/
├── v2/
│   ├── index.php                    # Slim app entry point
│   ├── routes/
│   │   ├── people.php               # Route handlers
│   │   └── common/
│   │       └── mvc-helper.php       # Shared helper functions
│   └── templates/
│       └── people/
│           └── photo-gallery.php    # View template
└── ChurchCRM/
    ├── Config/
    │   └── Menu/
    │       └── Menu.php             # Navigation menu
    └── model/
        └── ChurchCRM/
            └── PersonQuery.php      # Propel ORM (auto-generated)
```

## Testing Your New Page

1. **Start the development server:**
   ```bash
   npm run docker:dev:start
   ```

2. **Visit your new page:**
   ```
   http://localhost:8080/v2/people/photos
   ```

3. **Check the menu** - Your new item should appear under People

4. **Test filters** - Verify query parameters work correctly

5. **Check mobile** - Ensure responsive design works

## Troubleshooting

| Issue | Solution |
|-------|----------|
| 404 Not Found | Check route registration in index.php |
| 500 Error | Check PHP logs: `cat src/logs/$(date +%Y-%m-%d)-php.log` |
| Missing menu item | Verify Menu.php changes and clear browser cache |
| ORM errors | Check Propel Base Query class for correct method names |

## References

- [Propel ORM Documentation](http://propelorm.org/documentation/)
- [Slim 4 Framework](https://www.slimframework.com/docs/v4/)
- [Bootstrap 4.6.2](https://getbootstrap.com/docs/4.6/)
- [ChurchCRM Copilot Instructions](.github/copilot-instructions.md)
