# Report HTML escaping audit

Tracks #11014. MpdfRenderer creates an HTML-escaping environment for every report, including injected loaders. EscapedReportLoader separates compiled template classes from those created by an unsafe injected environment. Injected application extensions, functions, filters, tests and globals are preserved; escaping filters keep Twig's defaults.

Report construction and review rules live in [reports.md](../../.agents/skills/churchcrm/reports.md). Trace current `MpdfRenderer` consumers and shared templates when auditing a change.

Templates live under `src/templates/reports/`. Giving addresses use `nl2br`; Twig escapes their text before adding line breaks. Layout markup belongs in templates.

Validation:
- Run npm run env:check -- php.
- Run npm run test:php (includes injected/precompiled environment, Unicode/RTL, repeated giving letterhead, remittance and line-break regression).
- Run giving statement, envelope, class/attendance/photo-book, mailing-label and deposit Cypress report specs on seeded data.
- Inspect representative PDFs, including Arabic/RTL and non-Latin names, before merge. No PDF visual pass is claimed by the static audit.
