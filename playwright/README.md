# Marketing Visual-Media Pipeline

Captures trustworthy screenshots and videos of real ChurchCRM workflows for
marketing use, with metadata identifying exactly what generated each
artifact. Bootstrap milestone for issue [#9644](https://github.com/ChurchCRM/CRM/issues/9644).

This drives the actual application through Playwright — no mocked UI, no
fabricated screenshots. If a workflow fails, the run fails; nothing falls
back to a stale or placeholder artifact.

## How to run

Uses Playwright's bundled Chromium (`npm run marketing:install` once). Set
`BROWSER_CHANNEL=chrome` or run `npm run marketing:chrome` to use installed
Google Chrome instead.

```bash
npm run marketing                   # fresh instance, seed it, capture, finalize videos, check, manifest
npm run marketing:screenshots -- --grep "person-.*-profile"   # re-run a subset against a fresh DB
npm run docker:ci:new-system:down   # tear down the instance when you're done
```

`marketing` chains these steps (see `package.json`):

1. `docker:ci:new-system:start` — brings up a fresh ChurchCRM instance with an
   empty database (the same Docker Compose profile the `test-new-system` CI
   job uses), reachable at `http://127.0.0.1:8081/`.
2. `marketing:screenshots` — runs `playwright test`. Its `setup` project
   (`playwright/setup/bootstrap.setup.ts`) drives the setup wizard, sets a
   working admin password, saves church info, and imports demo data — the
   same UI flow already proven by
   `cypress/e2e/new-system/01-setup-wizard.spec.js` and
   `02-demo-import.spec.js` — as two real, recorded tests (this is the
   "setup and church info" video and the "how to use the sample data" video),
   then saves an authenticated session so the `recordings` and `screenshots`
   projects don't have to log in again. Every test then screenshots its
   "useful view" via `playwright/support/capture.ts`, using human-paced
   interactions (`playwright/support/human.ts`) so the videos show someone
   actually working through the app rather than an instant scripted bot.
3. `marketing:videos` — `scripts/finalize-marketing-videos.js` copies
   each workflow's recorded video from Playwright's internal report to its
   deterministic artifact path (see below). This runs as a separate step
   because Playwright only finalizes a video after its browser context
   closes, which happens after the test itself has already returned.
4. `marketing:check` — `scripts/check-marketing-visuals.js` verifies every
   capture produced its artifacts.
5. `marketing:manifest` — `scripts/generate-marketing-manifest.js` rolls
   every metadata sidecar (see below) into one `playwright/artifacts/manifest.csv`.

The instance is left running after a successful (or failed) run so you can
inspect it — `docker:ci:new-system:down` tears it down explicitly.

## What it does

- **Environment**: `docker:ci:new-system` Compose profile — an empty MariaDB
  database and a webserver with no `Config.php`, so the app boots into its
  setup wizard. Same infrastructure the "New System Setup" CI job already
  uses; nothing new was built here.
- **Seed data**: the existing demo-data importer
  (`POST /admin/api/demo/load`, driven via the `/admin/get-started` UI —
  see `ChurchCRM\Service\DemoDataService`), which reads deterministic
  fixtures from `src/admin/demo/`. It is not re-seeded per screenshot; it
  runs once, in the `setup` project, for the whole pipeline.
- **Workflows**: `playwright/setup/bootstrap.setup.ts` (setup/church-info,
  demo data import) and `playwright/workflows/*.spec.ts` — Dashboard (hero),
  People & Families (incl. the optional person directory list), Groups &
  Ministry, Events & Attendance, Communication, Giving (deposit entry + pledge
  report), and Settings (user permissions, nice-to-have). Each test describes
  what a church user is doing (e.g. "locate a family, open its profile"), not
  just a sequence of clicks — and skips records that would look bad in
  marketing material (an inactive group/family from the demo data, say)
  rather than blindly taking whatever's first in a list. This set maps
  directly to the marketing screenshot shot list — see the "Shot list
  coverage" section below.
- **Marketing-clean, not just functional**: `playwright/support/marketing-clean.ts`
  dismisses the "System Update Available" banner once (persisted per-user),
  and `playwright.config.ts` sets a matching `timezoneId` so the "Browser
  time zone differs" warning never appears — neither belongs in a marketing
  screenshot. See the "Marketing-clean" section in
  `.agents/skills/churchcrm/marketing-visuals-pipeline.md` for the full list
  of what's been handled and what to watch for in a new workflow.
- **Screenshots**: viewport-cropped (not full-page — see `captureScreen()`'s
  doc comment in `playwright/support/capture.ts` for why), one per test per
  form factor, at `playwright/artifacts/screenshots/<device>/<name>.png`,
  where `<device>` is `desktop` (1440×900), `tablet` (1024×768), or
  `mobile` (430×932) — see
  `.agents/skills/churchcrm/responsive-design-guidelines.md`.
- **Videos**: one per test, at
  `playwright/artifacts/videos/<device>/<name>.webm`, where `<device>` is
  `setup` (the two bootstrap recordings) or `recordings` (other click-through
  demos).
- **Metadata**: one JSON file per screenshot/video, at
  `playwright/artifacts/metadata/<device>/<name>.json`, containing the
  workflow name, purpose, product, git commit SHA, locale, device, viewport,
  timestamp, seed version, and artifact filenames. `npm run marketing`
  rolls every sidecar into one `playwright/artifacts/manifest.csv` for a
  quick, spreadsheet-friendly look at a whole run (see "How to run" above).
- **Artifacts**: screenshots, videos, and `manifest.csv` are committed;
  everything else (the metadata JSON sidecars, `report.json`) is
  gitignored — see `.gitignore`.

## How to add a workflow

1. Add a spec file under `playwright/workflows/`, e.g. `finance.spec.ts`.
2. Give each `test()` a title that is the exact name you want for its
   artifacts (no spaces — this doubles as the screenshot/video/metadata
   filename), e.g. `test('finance-deposits-overview', async ({ page }, testInfo) => { ... })`.
3. Drive the real UI to the "useful view," then call:

   ```ts
   import { captureScreen } from '../support/capture';

   await captureScreen(page, testInfo, {
     name: 'finance-deposits-overview',
     purpose: 'Show the finance dashboard with seeded deposits',
   });
   ```

4. That's it — the three device projects in `playwright/playwright.config.ts`
   run every spec automatically, so one new test yields three screenshots,
   three videos, and three metadata files (one per device).

## How to update seed data

Seed data lives entirely in `src/admin/demo/` (`people.json`, `groups.json`,
`finance.json`, `events.csv`, etc.) and is owned by
`ChurchCRM\Service\DemoDataService` — this pipeline does not maintain its own
copy. Edit those fixtures directly; the next pipeline run picks them up
automatically via the existing `/admin/api/demo/load` import.

`playwright/workflows/people-family.spec.ts`'s `people-family-new-family`
test additionally creates one family of its own ("Whitfield") as part of
the People & Families workflow, to demonstrate the *create a new family*
flow — that is workflow behavior, not seed data. Pick a family/person name
here (and anywhere else a spec creates or edits a demo record) that's
confirmed absent from `src/admin/demo/people.json` — grep `playwright/`
first regardless, since a name already claimed by a *different* spec is
just as real a collision as one already in the seed data.

## How to troubleshoot

- **`ChurchCRM did not become ready at http://127.0.0.1:8081/`** — the
  `webserver-new-system` container failed to start or is still building.
  Check `docker compose -f docker/docker-compose.yaml -f docker/docker-compose.parallel.yaml --profile ci-new-system logs`.
- **A selector timeout in the `setup` project** — the instance may not
  actually be on a fresh install (e.g. you're re-running against a database
  that already has `Config.php`/data from a previous run). Run
  `npm run docker:ci:new-system:down` first to reset it.
- **A workflow spec fails on a selector** — the app's UI changed since this
  was written; update the spec's selector to match. Cross-check against the
  equivalent Cypress spec in `cypress/e2e/ui*/` if one exists for the same
  page.
- **A workflow lands on a record that looks bad in the screenshot**
  (inactive, an error state, etc.) — filter it out in the spec rather than
  assuming "first row" is presentable; see the "Marketing-clean" section of
  `.agents/skills/churchcrm/marketing-visuals-pipeline.md` for the pattern
  already used for inactive groups/families.
- **Port 8081 already in use** — another `ci-new-system` instance (or a
  previous run's containers) is still up; `npm run docker:ci:new-system:down`
  before retrying.
- **Network policy blocks in a sandbox** — allow `deb.debian.org` (Docker
  image `apt-get`) and `tile.openstreetmap.org` (map tiles). Without tiles,
  every capture with a map fails with "Map tiles did not finish loading".
- **Browser missing** — `npm run marketing:install`, or use
  `BROWSER_CHANNEL=chrome` with Chrome installed.

## Shot list coverage

Each screenshot test captures desktop 1440×900, tablet 1024×768 and mobile
430×932 (all @2×) in one page load.

| Shot | Test |
| --- | --- |
| Hero — dashboard | `dashboard-hero` |
| People — family record | `people-family-overview` |
| People — person list/search (optional) | `people-directory-list` |
| Groups — group manager | `groups-ministry-overview` |
| Events — calendar month view | `events-calendar-overview` |
| Attendance — check-in/attendance grid | `events-attendance-overview` |
| Communication — email/mailing list | `communication-mailing-list` |
| Giving — deposit entry | `finance-deposit-entry` |
| Giving — fund/pledge report | `finance-pledge-report` |
| Settings — user permissions (nice-to-have) | `settings-user-permissions` |
| Mobile — one panel cropped | any of the above from the `mobile` project |

**Not automated** — pick these from the generated artifacts by hand:
- **UI detail texture crop** (a single card/table-header/form-field group) —
  a post-production crop of an existing screenshot, not a distinct page.
- **No modals/toasts in frame** — `captureScreen()` waits for network-idle
  plus a human pause before shooting, which is normally enough for a
  `window.CRM.notify()` toast to have already faded; spot-check the actual
  PNG for any still-visible toast before using it.

## Known limitations (first milestone)

- One fixed viewport per form factor (no touch/UA emulation).
- English locale only.
- Root-path install only (no subdirectory variant).
- No content-hash/change-detection field in the metadata sidecars — see
  #9663 for what was deliberately descoped there (closed not-planned).
  `manifest.csv` (above) is a rollup of the existing sidecars, not that.
- CI wiring (`.github/workflows/marketing-visuals-check.yml`,
  `workflow_dispatch`) and its automated update-PR on `master` both exist
  now — this is no longer a gap.
- Media-quality passes so far only cover what was actually visually
  inspected in this milestone (the system-update banner, timezone warning,
  inactive-record filtering). A new workflow should get the same visual
  once-over before being considered done — see the note at the top of
  `.agents/skills/churchcrm/marketing-visuals-pipeline.md`.
