---
title: "Error Reporting & Issue Filing"
intent: "Shared Tabler-styled error pages wired to the Issue Reporter workflow for consistent UX and GitHub issue pre-fill"
tags: ["frontend", "tabler", "error-handling", "testing"]
prereqs: ["[[tabler-components]]"]
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
