---
title: "Error Reporting & Issue Filing"
intent: "Shared Tabler-styled error pages wired to the Issue Reporter workflow for consistent UX and GitHub issue pre-fill"
tags: ["frontend", "tabler", "error-handling", "testing"]
prereqs: ["[[frontend-development]]"]
complexity: "intermediate"
---
# Error Reporting & Issue Filing

Every user-facing 4xx/5xx page renders the shared partial `src/v2/templates/common/error-page.php`. It shows the error code, title, message, a primary return button, and a `Report an issue` button that opens the Issue Reporter with the current page URL.

## Template usage

The partial reads `$code`, `$title`, `$message`, `$returnUrl`, `$returnText` and optional `$extraHtml`.

```php
use ChurchCRM\dto\SystemURLs;

$code = 404;
$title = gettext('Person not found');
$message = gettext('We could not find the person you were looking for.');
$returnUrl = SystemURLs::getRootPath() . '/v2/people';
$returnText = gettext('Return to People');
$extraHtml = '<div class="callout callout-warning text-start">...role info...</div>';

require __DIR__ . '/common/error-page.php';
```

Add ARIA roles to any callout passed as `$extraHtml`.

## Pages outside the app

Pages that must render without the app booting (`src/errors/`, the PHP requirements check) and the in-app version-mismatch page (`src/external/templates/system-db-update.php`) use the standalone Tabler layout in `src/errors/template.php`. It needs no autoloader, `Config.php` or gettext, and parses on old PHP. Pass the install root path: `renderErrorPage($rootPath, $title, $icon, $variant, $contentHtml, $issueBody)`, or `renderStandaloneHeader()` / `renderStandaloneFooter()` for a custom body. Error pages default to `standaloneRootPath()`; an includer sets `$errorRootPath` first. Styles come from `webpack/error.js` (Tabler, light and dark through `theme.min.js`); no CDN.

The unknown-page fallback in `src/index.php` shows the in-app 404 (application header, menu and footer) to a signed-in user. A signed-out visitor is redirected to `errors/not-found.php`, which renders through the standalone layout, so root-level legacy URLs such as `/old-page.php` and subdirectory installs get a 404 page either way.

## Report button

The button writes `window.location.pathname + window.location.search` into `input[name="pageName"]`, so the server-side IssueReporter builds the GitHub issue body from the path and query. It clicks the header `#reportIssue` link (which carries `data-bs-toggle` / `data-bs-target`) so the IssueReporter payload gathering runs. If that link is missing it opens `#IssueReportModal` through `window.bootstrap.Modal`, then falls back to the support URL. `window.bootstrap` is set by `webpack/setup.js`; the partial checks it before use.

## Testing

Cypress specs for not-found and access-denied pages assert:

- the error code and title are visible
- the `Return` button links to the correct listing
- `Report an issue` opens `#IssueReportModal` and `input[name="pageName"]` holds the current path and query

```js
cy.visit('/v2/person/not-found?id=9999');
cy.contains('Person not found');
cy.get('a.btn').contains('Return to People').should('have.attr', 'href').and('include', '/v2/people');
cy.get('#errorReportBtn').click();
cy.get('#IssueReportModal').should('be.visible');
cy.get('input[name="pageName"]').should('have.value').and('include', '/v2/person/not-found?id=9999');
```

`php-error.php`, `config-error.php` and `Bootstrapper::systemFailure()` (database connection and access failures) report an `install_failed` event through `standaloneReportInstallFailure()`: fixed reason codes, once per UTC day, no autoloader, never blocking the page. The requirements check sends `requirements_failed`. The failed-upgrade page sends nothing itself; `UpgradeService` already reports `upgrade_failed`. All senders share the PostHog project in `src/Include/PostHogProject.php`.
