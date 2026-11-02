# mPDF release readiness

The shared implementation and review rules live in the [Reports card](../../.agents/skills/churchcrm/reports.md). Use this page for rollout evidence and dependency-upgrade verification; it does not approve a release.

## Baseline checked 2026-11-02

Reviewed master `868efee700b40bb7d03b123aea4c2dc9483402a1` and cleanup PR [#11079](https://github.com/ChurchCRM/CRM/pull/11079), part of [#8136](https://github.com/ChurchCRM/CRM/issues/8136).

- The lock contains mPDF **8.3.1**, matching the latest stable version published on [its official Composer package page](https://packagist.org/packages/mpdf/mpdf). The package supports PHP 8.4 and 8.5. `src/composer.json` and the lock remain the version sources of truth.
- GitHub's Releases API currently calls 8.1.0 “latest”; use Composer package metadata and tagged source rather than that field to determine the current stable version.
- `composer audit --locked` reports no security advisories for the locked dependencies. It separately reports abandoned `doctrine/annotations`; that is not an mPDF advisory.
- Cleanup removes FPDF while retaining FPDI as an explicit dependency. Composer resolves, validates and installs the resulting lock. An actual PHP 8.5 booklet smoke verifies the parser without FPDF for Letter and A4.
- The renderer enforces Twig HTML escaping, UTF-8, locale/script fonts and RTL. `PdfResponse` supplies private/no-store, nosniff, output preference and safe filenames. These are implementation findings, not proof that every report's access/data filtering is correct.

## Open before rollout sign-off

| Check | Current evidence / remaining work |
| --- | --- |
| Private writable cache | **Implementation needed: [#11080](https://github.com/ChurchCRM/CRM/issues/11080).** Both construction sites omit `tempDir`, inheriting a writable cache inside vendor. Configure one private cache policy outside vendor/web root and verify fresh-cache and read-only deployment. |
| Clean build and integration | Local Composer validation/install and booklet smoke pass. The standard PHP CLI here is 8.3, so the full local build/lint entry points cannot pass the PHP 8.4 floor. Direct PHP 8.5 ORM generation passes; 11 unit-test files pass and one locale test explicitly skips. Require green build/lint and focused Cypress results on the candidate SHA. |
| Actual PDF inspection | Still needed: ordinary and RTL/CJK documents, photos/no photos, multipage repeated headers, long names/addresses, empty results, financial totals and Letter/A4 booklets. Record the exact SHA and files inspected. Browser HTML and a `%PDF` header are insufficient. |
| Deployment artifact | Inspect the built ZIP after cache isolation: mPDF/FPDI code, font files and upstream license notices retained; runtime font/image caches and private PDFs excluded. Render from the extracted artifact under the web account. |
| Resource use | Measure a representative large directory and giving-statement batch under supported hosting memory/time limits. Record records/pages, runtime, peak memory and cold/warm cache. A small smoke does not establish capacity. |
| Allowed/denied workflows | Exercise staff/finance/portal permissions, restricted/custom fields and member-owned records through the relevant routes. Check downloadable and emailed statements and HTML escaping of markup-like names/memos. |

Preserve existing actual-size print-stock checks in [#11015](https://github.com/ChurchCRM/CRM/issues/11015); a visual screen inspection cannot establish bank-slip/label alignment.

## Repeatable checks

Use the [shared environment setup](../../DEVELOPING.md#shared-environment-setup), with PHP 8.4+ and a disposable seeded integration stack:

```sh
npm run env:check -- php
composer --working-dir=src validate --strict --no-check-version --no-check-publish
composer --working-dir=src audit --locked
npm run build
npm run lint
npm run env:check -- integration --services
npx cypress run --config-file cypress/configs/docker.config.ts --spec 'cypress/e2e/ui/reports/directory-booklet-layout.spec.js,cypress/e2e/ui/reports/pdf-response.spec.js,cypress/e2e/ui/reports/confirm-report*.spec.js,cypress/e2e/ui/reports/pdf-report-iconv-issue.spec.js,cypress/e2e/ui/reports/mpdf-report-locales.spec.js,cypress/e2e/ui/finance/report-locale-settings.spec.js'
```

Run a clean Composer install from the committed lock in a disposable checkout as well. Include the affected module's report specs when changing its renderer, templates or data. Record pass counts and visual results with the exact candidate SHA; rerun affected checks when it changes.

## Upstream guidance applied in reviews

- [Installation](https://mpdf.github.io/installation-setup/installation-v7-x.html): install via Composer; verify required extensions and PHP compatibility against the selected stable package.
- [Temporary files](https://mpdf.github.io/installation-setup/folders-for-temporary-files.html): choose dedicated writable storage, restrict permissions and account for automatic stale-file cleanup.
- [HTML input](https://mpdf.github.io/getting-started/html-or-php.html): accept reviewed templates and escaped text. Validate resource locations independently; escaping alone does not make arbitrary HTML/CSS, file paths or image URLs safe. Current graphic letterhead accepts an administrator-configured readable path or HTTPS URL; that is not an outbound-host/IP restriction. Preserve this trust boundary and review any widening.
- [Tagged README](https://github.com/mpdf/mpdf/blob/v8.3.1/README.md): tailor layout to mPDF's supported HTML/CSS, retain required fonts, and use a real web stack for resource-fetch tests rather than a single-threaded `php -S` server.

For upgrades, compare tagged changes and security advisories, resolve the lock deliberately, repeat this evidence on the new candidate, and update existing cards rather than creating competing report instructions.
