---
title: "Admin MVC Module Migration"
intent: "Target Admin/Finance MVC structure and the steps to move a pre-MVC page onto it"
tags: ["admin","mvc","migration","slim"]
prereqs: ["[[service-layer]]","[[php-best-practices]]"]
complexity: "intermediate"
---

# Admin MVC Module Migration

Move a page onto MVC in the PR that touches it. Group pages follow the same steps (`groups-mvc-guidelines.md`).

## Structure

- Routes: `src/admin/routes/[feature].php`
- Admin APIs: `src/admin/routes/api/[feature-api].php`, served at `/admin/api/...`, kebab-case endpoint names, `AdminRoleAuthMiddleware` at router level
- Views: `src/admin/views/`
- Services: `src/ChurchCRM/Service/[Feature]Service.php` (shared by routes and APIs)
- Admin system pages: `/admin/system/...` in `src/admin/routes/system.php`
- Finance: Slim 4 app `src/finance/index.php`, routes in `src/finance/routes/`, views in `src/finance/views/`, `FinanceRoleAuthMiddleware` (admin or finance permission)
- Menu entries: `src/ChurchCRM/Config/Menu/Menu.php`

## Migration steps

1. Extract business logic from the pre-MVC PHP file into a Service class.
2. Add the route in `src/admin/routes/` that calls the Service and passes data to a view.
3. Add the view in `src/admin/views/`; render initial state server-side.
4. Add an API in `src/admin/routes/api/` when the UI needs dynamic updates.
5. Point `Menu.php` and every caller at the new URL: `grep -rn "OldPage.php" src/`.
6. `git rm` the old file in the same PR. Callers move to the new URL; no redirect stub stays behind.
7. Rename URL, form and field parameters to camelCase (below).

### Example split

```
src/UserList.php  →
  src/ChurchCRM/Service/UserService.php   getUserStats(), getUserSettingsConfig()
  src/admin/views/users.php               stats cards, user table, settings panels
  src/admin/routes/api/user-admin.php     reset password, delete, 2FA (via UserService)
```

### Route

```php
// Path is relative to the /admin base path set by MvcAppFactory::create()
$app->get('/system/users', function (Request $request, Response $response): Response {
    $userService = new UserService();

    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    return $renderer->render($response, 'users.php', [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => gettext('System Users'),
        'sPageSubtitle' => gettext('Manage system users, permissions, and 2FA'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs([
            [gettext('Admin'), '/admin/'],
            [gettext('System Users')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Add User'), 'url' => '/admin/system/users/new', 'icon' => 'fa-user-plus'],
        ]),
        'stats'    => $userService->getUserStats(),
        'settings' => $userService->getUserSettingsConfig(),
    ]);
});
```

### View

`Header.php` renders breadcrumbs, title and subtitle. `PhpRenderer` extracts each `$pageArgs` key into a variable.

```php
<?php
use ChurchCRM\dto\SystemURLs;
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<h3><?= (int)$stats['total'] ?></h3>
<p><?= gettext('Total Users') ?></p>
<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
```

### API

```php
// Path is relative to the /admin base path
$app->group('/api/users', function (RouteCollectorProxy $group): void {
    $group->post('/{userId}/reset-password', function (Request $request, Response $response, array $args): Response {
        try {
            $result = (new UserService())->resetPassword((int)$args['userId']);
            return SlimUtils::renderJSON($response, ['data' => $result]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to reset password'), [], 500, $e, $request);
        }
    });
});
```

### Settings panels

The Service returns settings through `SystemConfig::getSettingsConfig([...keys])`; the view loops over the result.

```php
public function getUserSettingsConfig(): array
{
    return SystemConfig::getSettingsConfig(['iSessionTimeout', 'iMaxFailedLogins', 'bEnableLostPassword']);
}
```

## Multi-form pages

A page with several `<form>` tags posting to one URL moves over in one of two shapes:

- One `POST /resource/{id}` route that dispatches on a hidden `Action` field. Use it when the forms share layout and validation.
- One POST route per action (`/resource/{id}/name`, `/resource/{id}/time`). Use it when forms are independent, need different auth, or new actions are added.

```php
$app->post('/event/types/{id}', function (Request $req, Response $res, array $args): Response {
    $body = (array) $req->getParsedBody();
    $action = $body['Action'] ?? '';
    return match (true) {
        $action === 'ADD'  => handleAdd($args['id'], $body),
        $action === 'NAME' => handleName($args['id'], $body),
        str_starts_with($action, 'DELETE_') => handleDelete($args['id'], $action),
        default => RedirectUtils::redirect('/event/types/' . (int) $args['id']),
    };
});
```

## camelCase parameters

Read one name per parameter, in camelCase: `typeId`, `eventId`, `year`, `addedCount`, `typeFilter`, `personId`, `familyId`.

```php
$eventId = (int) ($args['id'] ?? 0);
$typeId  = (int) ($params['typeId'] ?? 0);
```

Change every place in one commit:
1. PHP route `$args[...]`, `$params[...]` and `$body[...]` reads
2. View `name`, `id`, `<label for>` and redirect URLs
3. Webpack JS selectors and URL templates
4. Cypress selectors and URL assertions

## Middleware and error handling

Create MVC apps with `MvcAppFactory::create()`; it sets middleware order and error handling.

```php
$app = MvcAppFactory::create('/admin', [
    'dashboardUrl'   => '/admin/',
    'dashboardText'  => gettext('Return to Admin Dashboard'),
    'roleMiddleware' => AdminRoleAuthMiddleware::class,
]);
```

Non-MVC entry points (API, external, session) add error middleware after `addRoutingMiddleware()`. Reference: [`slim-4-best-practices.md`](./slim-4-best-practices.md) (Middleware Order, Error Handler Architecture).

## Admin dashboard setup checklist

`src/admin/routes/dashboard.php` computes the Setup Progress checklist. To add a step, compute a `$hasX` boolean, add it to `$completedSteps`, and append to `$setupChecklist`:

```php
$setupChecklist[] = [
    'done'  => $hasPlugins,
    'label' => gettext('Enable Plugins'),
    'desc'  => gettext('Extend ChurchCRM with MailChimp, backups, and more'),
    'link'  => SystemURLs::getRootPath() . '/plugins/management',
    'icon'  => 'fa-plug',
];
```

Call `PluginManager::init()` before plugin state checks (`plugin-system.md`). The card hides when every step is done; keep the Quick Start shortcuts in the same order.

## Get Started pages

- `GET /admin/get-started` (landing) is registered in `src/admin/routes/dashboard.php`; view `src/admin/views/get-started.php`; assets `webpack/get-started.js` and `webpack/get-started.css`. Cards use `.gs-card` with a colour modifier inside `.gs-wrap`, and are `<a role="button">` elements so card padding holds.
- `GET /admin/get-started/manual` is the only route in `src/admin/routes/get-started.php`; view `src/admin/views/get-started-manual.php`. It explains Family first, then People; giving is recorded at family level.

## Styling

Use native Tabler classes in the HTML. When a class is missing, put CSS inline in that template. `src/skin/scss/_tabler-bridge.scss` is a temporary bridge for systemic fixes only.

Authentication pages (`src/session/templates/*.php`) are self-contained: a `<style>` block in the template, Tabler variables and colours, a `@media (max-width: 768px)` stack for phones, and visible focus states. Check at 480px and 768px, then run `npm run build && npm run lint`.

## No Redirect Shims

A migrated page leaves no stub at the old path. Stubs bypass the new module's middleware and need a cleanup PR later. Find callers with `grep -rn "OldPage.php" src/`, update each (menu, template links, JS redirects, PHP), `git rm` the file, all in one PR. A PR that keeps a redirect stub is blocked until it does this.
