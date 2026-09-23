# What maintainers look for before a ChurchCRM PR is merged

Use this **before opening a PR**. Matching it does not guarantee merge. Final approve and merge are always a human maintainer decision.

Thank you for donating time (and often tokens). We try not to nitpick. We will say so when something is a hard block versus a follow-up.

## Before you open the PR

1. Link an open issue.
2. Run lint and build.
3. Add or update tests.
4. Wrap new user-visible strings in `gettext()` / `i18next.t()`. `locale:build` runs on merge to `master`.
5. Dates, times, numbers, and currency: API/storage is ISO `Y-m-d` or naive church wall-clock. Display uses `sDateFormat*`, `sTimeZone`, and `sLanguage`. Do not parse JSON dates with the display format or the browser timezone.
6. If you change the UI, keep Tabler / Bootstrap 5 and **check tablet and mobile** as well as desktop. Attach screenshots if you have them — they help, they are not the whole gate.
7. Existing installs must keep working. New config defaults safely.
8. Do not slow login / people list / Sunday paths without measuring.
9. No security holes.
10. Keep the PR title and description true to the current diff.
11. Do **not** add features to Query View (`QueryView.php`, `QueryList.php`, or new/rewritten predefined `query_qry` rows). New reports use Slim/Tabler MVC + Propel, or a reports plugin.

## Hard blocks (expect Request changes)

- Security problem
- Unmeasured performance risk on a common path
- Unsafe upgrade or surprise behavior
- UI that does not look like ChurchCRM, or a UI change with no tablet/mobile check
- New strings not wrapped
- Dates/times/numbers/currency that ignore the storage vs display rules above
- No tests for a feature or bug fix
- Title or body that describes different work than the files
- New Query View / predefined-query work (see above). Security-only patches on that page need an explicit maintainer exception.

Missing screenshots alone is not a hard block. If you already captured tablet or mobile, add them.

## Not merge blockers (Comment + follow-up)

- Demo-import data in `src/admin/demo/config.json` (not Cypress seed if tests would break)
- User manual issue on ChurchCRM/CRM. Docs PRs merge after that release ships.
- Blog or marketing (maintainer decides; skip for bug/security-only)
- A later member-facing consumer for storage-only admin work

Reviews will list what is still open as checkboxes on the PR.
