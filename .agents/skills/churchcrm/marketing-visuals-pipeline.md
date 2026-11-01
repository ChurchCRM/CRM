---
title: "Marketing Visual-Media Pipeline"
intent: "Capture trustworthy screenshots and videos of real ChurchCRM workflows with Playwright for marketing and docs"
---

# Skill: Marketing Visual-Media Pipeline (Playwright)

## Context

A Playwright pipeline in `playwright/` captures screenshots and videos of real ChurchCRM workflows for marketing use. It is the repo's one Playwright suite (multi-viewport projects and built-in video recording); Cypress in `cypress/` covers E2E regression. Issues and PRs for the Playwright pipeline carry the `Marketing` label; `Integration Testing` is Cypress only. Details, directory layout and troubleshooting: `playwright/README.md`.

This is a media-generation pipeline. A passing run is necessary, and the captured screenshots and videos must also look like something shown to a church deciding whether to adopt ChurchCRM: no error banners, no half-loaded AJAX content, no unintended "Inactive" or placeholder state. Some captures show inactive or deceased status on purpose (`person-inactive-profile`, `person-deceased-profile`, `family-inactive-profile`); see "Demo subjects, status and photos". Open the generated PNGs under `playwright/artifacts/screenshots/` before calling a workflow done.

## Running it

```bash
npm run marketing                   # scripts/capture-all-locales.sh: fresh instance, all 8 locales, check, manifest
npm run marketing:screenshots -- --grep "person-.*-profile"   # re-run a subset (setup runs first)
npm run docker:ci:new-system:down   # tear down when done
```

`npm run marketing` installs a fresh instance once, captures the first locale (every screenshot for English, tagged captures for other locales), then applies each remaining locale (`en es pt zh fr ru de ar` by default) through the `locale-set` project and re-runs `screenshots --no-deps --grep @all-locales`. ChurchCRM resolves locale per request from a DB-stored user preference, so no reinstall or re-seed is needed. `marketing:check` and `marketing:manifest` run once at the end across all locales. Pass locales as arguments to capture a subset.

The pipeline uses Playwright's bundled Chromium (`npm run marketing:install`). Set `BROWSER_CHANNEL=chrome` (or `npm run marketing:chrome`) to drive system Chrome.

`marketing:manifest` (`scripts/generate-marketing-manifest.js`) merges the per-capture JSON sidecars under `playwright/artifacts/metadata/` into `playwright/artifacts/manifest.json`: one object per capture, grouped by workflow with devices together, carrying name, device, kind, relative path, file existence and size, purpose text, viewport and commit. The website screenshot gallery reads it. `marketing:check` is the correctness gate. `playwright/artifacts/` is gitignored: manifest, screenshots and videos are generated, never committed.

### How visuals reach the website

`Marketing: capture screenshots & videos` (`marketing-capture-assets.yml`) is one run with back-to-back jobs: English first (it also records the videos), the other seven locales after it, a merge and check, and on `master` a last job that starts `Publish visuals` in `ChurchCRM/visuals`. The artifact is `marketing-visuals-<run id>` (30 day retention). `Publish visuals` downloads it and replaces the repo with that set (one release, one orphan commit), served at https://cdn.churchcrm.io/. Images live at `https://cdn.churchcrm.io/<relativePath from manifest.json>` next to `manifest.json` (with a `release` field) and `release.json`. churchcrm.io and docs.churchcrm.io read from there. It opens no PR.

Run the workflow on `master` only. A run on another branch captures and checks but does not publish. `Release & Start Next` starts it on `master` after the draft exists and never waits for it. The publish job needs `DOCS_RELEASE_TOKEN` (Actions: write on `ChurchCRM/visuals`) and fails without it.

### Adding a capture in a feature PR

The capture spec ships in the same PR as the UI change — but only when the change tells or extends a user workflow story. A new screen, settings page, or CRUD operation on its own earns no capture: if there is no church task behind it (something a staff member or volunteer sets out to do, with a reason a church would care — it saves time, protects people or data, or removes a worry), ship the PR with no capture. The spec's purpose text must state the workflow story; a capture whose purpose is only a new screen or a new CRUD operation is rejected in review. Images serve your own check and stay uncommitted.

1. Make a `git worktree` at the PR commit, symlink `node_modules`, then run `composer:install`, `build:webpack`, `build:signatures`
2. Start the pipeline's own stack: `export COMPOSE_PROJECT_NAME=crm-pw-capture MAILSERVER_PORT=1026 MAILSERVER_GUI_PORT=8026`, then `npm run docker:ci:new-system:start`. The web server and database use ports 8081 and 3308; Mailpit binds `MAILSERVER_PORT` / `MAILSERVER_GUI_PORT`, which default to the dev stack's 1025 / 8025, so set them. The captures read Mailpit at `MAILSERVER_GUI_PORT` (or `CHURCHCRM_MAILPIT_URL`), so run them in the same shell. The start removes `src/Include/Config.php`, so it runs in the worktree and never in the main checkout
3. First run: `BROWSER_CHANNEL=chrome CHURCHCRM_LOCALE=en npm run marketing:screenshots -- --grep "<capture-name>"`. After a UI tweak, re-run only the capture on the seeded instance: `-- --project=screenshots --no-deps --grep "<capture-name>"`
4. Open the desktop, tablet and mobile PNGs, fix anything cramped, re-run
5. Commit the spec only
6. Tear down: `COMPOSE_PROJECT_NAME=crm-pw-capture npm run docker:ci:new-system:down`

### Choosing capture locales

Screenshots default to English only. Key self-service and staff workflows, plus
page shots used by the website in the visitor's language, set `locales: 'all'`.
Declare their options with `defineCapture()`, pass that object as the test's
second argument and to `captureScreen()`. It supplies `@all-locales` at discovery
time, before Playwright filters the tests. The screenshots project also filters
non-English runs, including standalone CI locale jobs. Videos run once in English.

Sidecars and the generated manifest carry `locales: 'all' | 'en'` alongside
`usedIn`. The gallery filters actual entries by `locale`; English-only cards are
absent in other languages. Dark-twin links are emitted only when a matching
locale/device capture exists. Completeness guards must use this locale policy
rather than expect every capture in every locale.

### Marking used captures

`docs.churchcrm.io` and `ChurchCRM.io` link captures by name (`cdn.churchcrm.io/screenshots/<locale>/<device>/<name>.png`, the website's `cdn-shot` partial, and its setup videos). A capture a page links sets `usedIn: ['docs']`, `['website']` or both in its `captureScreen()` call, in the same PR as the link. `usedIn` reaches the generated manifest. `npm run lint` fails when a capture that is marked on `origin/master` is removed or renamed. To retire one, change the links, clear its `usedIn` in a merged PR, then delete it. The website gallery lists every manifest entry, so unmarked captures can still appear there; `usedIn` records the links a page hard-codes.

### Fixing a failed capture

A broken locator fails the same test in every locale, and serial specs skip the tests behind it, so one failure can hide several more.

- Run the capture workflow on `master` only. Do not dispatch it on a branch, and do not start a second run while one is queued.
- A run's log downloads only after the run finishes. While it runs, read the `marketing-failure-<locale>-<run id>` artifact (kept 14 days): each failed test has `error-context.md` and `test-failed-*.png`. Open the screenshot first; a page that renders correctly means the locator is stale.
- Locators must not use English text. Captures run in 8 locales and FullCalendar translates its aria-labels. Videos run in `en` only.
- Verify locally before another CI run, in a worktree on its own compose project (see "Adding a capture in a feature PR"). Start the stack once, then run only the failed tests with `npx playwright test --config=playwright/playwright.config.ts --project=screenshots --no-deps --grep "<name>|<name>"`, and run a second locale through the `locale-set` project. Repeat until they pass.
- Reset the database between runs. The volunteer fixture leaves users behind, so a locale set in one run shows up in the next (an English-only video failed on Portuguese text). `scripts/capture-all-locales.sh <locales>` tears the stack down and reinstalls, so use it for the final check, English first. macOS has no `timeout`.
- The full run (`capture-all-locales.sh en pt`, about 15 minutes) must pass `marketing:check` before the fix goes up.

## Architecture

Three Playwright projects run in this order (`playwright/playwright.config.ts`):

1. `setup` (`playwright/setup/bootstrap.setup.ts`) drives the setup wizard, forced admin password change, church info and demo data import as recorded tests (`setup-church-info`, `demo-data-import`), then saves a Playwright `storageState`
2. `recordings` (`playwright/videos/*.video.ts`) holds click-through videos
3. `screenshots` (`playwright/workflows/*.spec.ts`): each test captures desktop 1440x900, tablet 1024x768 and mobile 430x932 in one page load (`VIEWPORTS` in `playwright/support/capture.ts`)

`workers: 1`, so projects share one database in order: a video that edits a person changes what later screenshots see. Concurrent workers race creating `outputDir` (`ENOTDIR`).

The screenshot shot list maps to `playwright/workflows/*.spec.ts` (`playwright/README.md` → "Shot list coverage"). Retina comes from `deviceScaleFactor: 2` on every device project, and the mobile viewport is `VIEWPORTS` in `capture.ts`. The "UI detail texture crop" is a manual post-production crop of an existing screenshot.

## Patterns and gotchas

- **UI-driving logic lives in the `setup` project.** `use.video: 'on'` applies to a real test's `page` fixture, so setup-wizard and demo-import steps live in tests; `playwright/global-setup.ts` only waits for the server to accept connections
- **Video finalization is a post-run step.** Playwright finalizes a video after the context closes, after the test returns. `scripts/finalize-marketing-videos.js` reads the JSON reporter output and copies each video to `artifacts/videos/<device>/<test-title>.webm`. Each test title equals the artifact name passed to `captureScreen()`; `capture.ts` throws on a mismatch
- **Wait for AJAX before each capture, bounded.** `captureScreen()` calls `page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => undefined)` before screenshotting; the bound keeps pages with a permanent background poll from hanging
- **Human-paced interaction.** `playwright/support/human.ts` (`humanClick`, `humanType`, `humanSelect`, `humanPause`) replaces raw `.click()` / `.fill()` so videos show someone working through the app
  - `humanType` calls `.clear()` before `.pressSequentially()` because some fields arrive pre-filled (setup wizard DB Server/Port)
  - `humanSelect` skips `.hover()`: TomSelect-enhanced `<select>` fields (e.g. `#sChurchState`) hide the native element, which `.selectOption()` still handles
- **Marketing-clean pages** (`playwright/support/marketing-clean.ts`, `playwright.config.ts`)
  - The "System Update Available" banner is dismissed once after first login through `POST /api/user/{id}/setting/notification.dismissed.system-update-available` in `setup/bootstrap.setup.ts`; the dismissal persists per user for the run
  - The calendar's "Browser time zone differs" warning never appears because `use.timezoneId: 'America/Chicago'` matches the demo `sTimeZone` (`src/admin/demo/config.json`)
  - Demo data includes inactive groups and families, so a first row may be unpresentable; `groups-ministry.spec.ts` walks rows until `window.CRM.groupIsActive === true`
  - Church info typed in `setup-church-info` is replaced by the demo import's `sChurchName` (`src/admin/demo/config.json`); that step exists to satisfy `ChurchInfoRequiredMiddleware`
- **Sandboxed runs** need these hosts allowed: `deb.debian.org` (webserver image `apt-get`), `tile.openstreetmap.org` (map tiles), `download.cypress.io` (else `CYPRESS_INSTALL_BINARY=0 npm ci`). `composer:install` needs PHP `ext-bcmath`. If `playwright install` stalls, set `BROWSER_CHANNEL=chrome` or point `PLAYWRIGHT_BROWSERS_PATH` at a preinstalled Chromium

## Recorded-workflow tests

- **Set `storageState` on every project that needs a login.** `dependencies: ['setup']` orders the run and shares no session, so a new project in `playwright.config.ts` sets `storageState: STORAGE_STATE_PATH` in its `use` block, as `recordings` and `screenshots` do
- **Scope locators past hidden duplicates.** Several pages render the same href or text twice: the visible element plus a hidden decoy in a closed dropdown menu (`family-view.php` "Find Neighbors", `person-view.php` per-member "Edit"), a hidden global widget (`cartview.php` "Map cart items", `/people/map?groupId=0`) or a hidden sidebar link (`nav-link-title` matched by `getByText(groupName)`). A bare `.first()` can land on the hidden one, and `scrollIntoViewIfNeeded` then times out with "element is not visible". Scope to what makes the real element unique: a `.btn` class (`a.btn[href*="PersonEditor.php"]`), a `:not([href*=...])` exclusion, or a containing element (`.text-body-secondary` for a page subtitle)
- **Diagnose with a throwaway script** run from the repo root (so `@playwright/test` resolves), using the saved state `playwright/.auth/admin.json`:
  ```js
  import { chromium } from '@playwright/test';
  const browser = await chromium.launch();
  const context = await browser.newContext({ storageState: 'playwright/.auth/admin.json' });
  const page = await context.newPage();
  await page.goto('http://127.0.0.1:8081/people/view/139');
  for (const l of await page.locator('a[href*="PersonEditor.php"]').all()) {
    console.log({ href: await l.getAttribute('href'), visible: await l.isVisible() });
  }
  await browser.close();
  ```
- **Search DataTables before locating a row.** DataTables (`#families`, `#members`, `#groupsTable`) page at 10 rows, and `rows.filter({ hasText })` sees only rendered rows. Type into `.dt-search input` first, as `people-family.spec.ts` does
- **Navigate by URL in `workflows/*.spec.ts`.** Once an id is known (extract it from `page.url()`), `page.goto('/people/map/neighbors?familyId=' + id)` replaces a click-through, as the Cypress specs do (`cypress/e2e/ui/people/standard.map.spec.js`). `*.video.ts` usability demos keep the real click path, which is the point of a video

## Demo subjects, status and photos

- **Status screenshots use imported data.** `DemoDataService::importCongregation` honors family `"active": false`, member `"active": false` and `"dateDeceased"` in `src/admin/demo/people.json`. Pick subjects from that file
- **Videos own their subjects** (`mark-member-inactive.video.ts`, `mark-member-deceased.video.ts`). Screenshots pick other people; grep `playwright/` before choosing a name
- **Default lists hide status.** `/people/list` shows active people in active families and forces "Living". Use `?personActiveStatus=inactive`, `/people/family?familyActiveStatus=inactive`, or read the profile link from the server-rendered rows for a deceased person
- **Pick subjects whose whole family has photos**, with a unique full name. Person profiles show the family table; family profiles show the family photo (`images/families/`)
- **Photos match name, gender and rough age and suit a church.** Check by rendering a labelled contact sheet of `images/people/*` with Playwright
- **Family portrait photos are scarce.** Only 2 of 62 demo families have an uploaded group photo (`family.hernandez60.jpg`, `family.campbell.jpg`); other families show the app's initials avatar. Hernandez members lack individual photos and Campbell is the `family-inactive-profile` subject, so `people-family-overview` and its dark variant keep Scott (real member photos, no portrait) with accurate purpose text
- **Date pickers use `sDatePickerFormat` (`Y-m-d`).** Type `YYYY-MM-DD` and assert `toHaveValue` before submit
- **Maps.** `captureScreen()` waits for every visible Leaflet tile to get `.leaflet-tile-loaded` and throws when tiles fail
- **Map position depends on layout.** At 1440x900, `person-view.php` puts the photo in a narrow left column and the map in the wide right column, within the fold. `family-view.php` stacks photo above the Address/map card in one narrow right column, below the fold. `people-family-overview` and its dark variant therefore show the "Geocoded" badge and say "geocoded address" in the purpose text. A shot that needs the family map in frame is a UI/copy decision (taller capture or scroll-and-crop); ask first
