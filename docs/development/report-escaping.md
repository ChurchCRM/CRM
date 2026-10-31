# Report HTML escaping audit

Tracks #11014. MpdfRenderer creates an HTML-escaping environment for every report, including injected loaders. EscapedReportLoader separates compiled template classes from those created by an unsafe injected environment. Injected application extensions, functions, filters, tests and globals are preserved; escaping filters keep Twig's defaults.

Current callers: Finance giving statements and envelopes, Member Portal giving statements, Sunday School class lists/attendance/photo books, label sheets, and deposit reports through DepositReportRenderer. Open report migration PRs inherit this default when rebased; their compatibility still needs review.

All 14 templates under src/templates/reports were inspected: shared base/letter/letterhead, tax statement, envelope numbers, class list, class attendance, photo book, label sheet, deposit and advanced deposit, and both deposit partials. No raw filter or autoescape-false block exists. Giving addresses use nl2br; Twig escapes their text before adding line breaks. Layout markup remains in the templates, not database strings.

Validation:
- Run npm run env:check -- php.
- Run npm run test:php (includes injected/precompiled environment, Unicode/RTL, repeated giving letterhead, remittance and line-break regression).
- Run giving statement, envelope, class/attendance/photo-book, mailing-label and deposit Cypress report specs on seeded data.
- Inspect representative PDFs, including Arabic/RTL and non-Latin names, before merge. No PDF visual pass is claimed by the static audit.
