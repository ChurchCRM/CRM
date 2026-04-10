# Error Reporting & Issue Filing

This skill documents the new shared Tabler-styled error pages and how to wire them to the existing Issue Reporter workflow so error pages across the app are consistent and can open a GitHub issue pre-filled with the current page URL and system info.

## Overview

- Use the shared partial: `src/v2/templates/common/error-page.php` for all user-facing 4xx/5xx pages.
- The partial provides a consistent layout: large error code, title, message, prominent primary action (return) and a secondary `Report an issue` button.
- The `Report an issue` button will populate the hidden `input[name="pageName"]` with `window.location.pathname + window.location.search` and then open the issue modal. If the header `#reportIssue` link exists it will be clicked (preferred), otherwise the bootstrap modal API is used, and finally a support URL fallback.

## Template usage

Example (in a page template):

```php
use ChurchCRM\dto\SystemURLs;

// in your page template
$code = 404;
$title = gettext('Person not found');
$message = gettext('We could not find the person you were looking for.');
$returnUrl = SystemURLs::getRootPath() . '/v2/people';
$returnText = gettext('Return to People');

// optional raw HTML for context (e.g. role callout)
$extraHtml = '<div class="callout callout-warning text-start">...role info...</div>';

require __DIR__ . '/common/error-page.php';
```

## Implementation notes

- The partial expects these variables: `$code`, `$title`, `$message`, `$returnUrl`, `$returnText`, and optional `$extraHtml`.
- It renders a Tabler/Bootstrap 5 friendly card and two large buttons.
- The report button populates `input[name="pageName"]` so the server-side IssueReporter can build a GitHub issue body including the path + query.
- The partial is intentionally JS-light; it prefers to click the existing header `#reportIssue` (which has `data-bs-toggle`/`data-bs-target`) so the established IssueReporter behavior (ajax payload gathering) is preserved.

## JavaScript & Load-order

- `window.bootstrap` is provided by our webpack `setup.js` entry which imports `bootstrap` and sets `window.bootstrap = bootstrap;`.
- Error pages may render before the bootstrap module has initialized in some edge cases; the partial therefore checks `window.bootstrap` before using `bootstrap.Modal` and falls back safely.

## Testing guidance

- Update existing Cypress tests for not-found or access-denied pages to assert:
  - the error `code` and `title` are visible
  - the primary `Return` button exists and links to the correct listing
  - the `Report an issue` button opens `#IssueReportModal` and `input[name="pageName"]` contains the current path (`/v2/...?id=...`)

Example Cypress snippet:

```js
cy.visit('/v2/person/not-found?id=9999');
cy.contains('Person not found');
cy.get('a.btn').contains('Return to People').should('have.attr', 'href').and('include', '/v2/people');
cy.get('#errorReportBtn').click();
cy.get('#IssueReportModal').should('be.visible');
cy.get('input[name="pageName"]').should('have.value').and('include', '/v2/person/not-found?id=9999');
```

## Accessibility

- Buttons use clear labels and `btn-lg` sizing for discoverability.
- The partial uses semantic HTML and visible text for screen-readers.
- If adding `extraHtml` ensure any callouts use ARIA roles where appropriate.

## Migration checklist

- Replace legacy error views (`not-found-view.php`, `access-denied.php`) with the shared partial and pass `extraHtml` for role-specific content.
- Update Cypress tests to assert the new layout and reporting behavior.
- Ensure `webpack` bundles include `bootstrap` and `window.bootstrap` is set (already done in `webpack/setup.js`).

<!-- learned: 2026-03-23 -->
