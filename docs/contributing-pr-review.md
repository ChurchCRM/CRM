# What maintainers look for before a ChurchCRM PR is merged

Use this **before opening a PR**. Matching it does not guarantee merge. A human maintainer makes the final approve and merge decision.

Thank you for donating time (and often tokens). Reviews mark each item as a hard block or a follow-up.

## Before you open the PR

1. Link an issue if `CONTRIBUTING.md` requires one.
2. Run lint and build.
3. Add or update tests.
4. Wrap new user-visible strings in `gettext()` / `i18next.t()`. `locale:build` runs on merge to `master`.
5. Dates, times, numbers, and currency: API/storage is ISO `Y-m-d` or naive church wall-clock. Display uses `sDateFormat*`, `sTimeZone`, and `sLanguage`. Parse JSON dates as ISO, independent of the display format and the browser timezone.
6. If you change the UI, keep Tabler / Bootstrap 5 and **check tablet and mobile** as well as desktop. Attach screenshots if you have them — they help, they are not the whole gate.
7. Existing installs must keep working. New config defaults safely.
8. Measure login, people list, and Sunday paths when you change them.
9. Keep the change free of security holes.
10. Keep the PR title and description true to the current diff.
11. Build new reports with Slim/Tabler MVC + Propel, or a reports plugin. Query View (`QueryView.php`, `QueryList.php`, predefined `query_qry` rows) is closed to new features.
12. Let names and tests carry intent; comment only a *why* the code cannot say.

## Hard blocks (expect Request changes)

- Security problem
- Unmeasured performance risk on a common path
- Unsafe upgrade or surprise behavior
- UI that does not look like ChurchCRM, or a UI change without a tablet/mobile check
- New strings that are not wrapped
- Dates/times/numbers/currency that ignore the storage vs display rules above
- A feature or bug fix without tests
- Title or body that describes different work than the files
- New Query View / predefined-query work (see above). Security-only patches on that page need an explicit maintainer exception.

Missing screenshots alone is not a hard block. If you already captured tablet or mobile, add them. Missing comments are not a hard block. Wrong comments can be deleted in the same PR.

Fix failing CI before asking for review; a PR merges only with green CI.

## Not merge blockers (Comment + follow-up)

- Demo-import data in `src/admin/demo/config.json` (not Cypress seed if tests would break)
- User manual issue on ChurchCRM/CRM. Docs PRs merge after that release ships. For a PR that adds or changes user-visible behavior, the review ends with a short note that the docs PR and the screenshots or video for docs, blog and marketing still need a follow-up after merge.
- Blog or marketing (maintainer decides; skip for bug/security-only)
- A later member-facing consumer for storage-only admin work

Reviews will list what is still open as checkboxes on the PR. CI is not one of those boxes.
