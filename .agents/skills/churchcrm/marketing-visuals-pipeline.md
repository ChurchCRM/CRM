# Skill: Marketing Visual-Media Pipeline (Playwright)

<!-- learned: 2026-09-08 -->

## Context

A Playwright-based pipeline captures trustworthy screenshots/videos of real
ChurchCRM workflows for marketing use. It lives in `playwright/` and is
separate from the Cypress E2E suite in `cypress/` — this is intentionally
the one place in the repo that uses Playwright instead of Cypress, because
Playwright's built-in multi-viewport projects and video recording were a
better fit for that specific job. Bootstrap milestone: issue
[#9644](https://github.com/ChurchCRM/CRM/issues/9644).

Don't extend Cypress to try to replicate this, and don't migrate this to
Cypress — they serve different purposes (E2E regression coverage vs.
marketing artifact generation) and neither needs the other's capabilities.

**This is a media-generation pipeline, not a test suite.** A run "passing"
is necessary but not sufficient — the resulting screenshots/videos must
also look like something you'd actually put in front of a church deciding
whether to adopt ChurchCRM: no error banners, no half-loaded AJAX content,
and no *unintended* "Inactive"/placeholder state. (Some captures show an
inactive/deceased status **on purpose** — `person-inactive-profile`,
`person-deceased-profile`, `family-inactive-profile` — because that status
*is* the feature being shown off; see "Demo subjects, status and photos"
below for how those stay deliberate instead of accidental.) When adding or
changing a workflow, look at the actual generated screenshot before
considering it done (see `playwright/README.md` for the exact artifact
paths), not just the green checkmark.

## Running it

```bash
npm run marketing                   # scripts/capture-all-locales.sh: fresh instance → all 8 locales → check → manifest
npm run marketing:screenshots -- --grep "person-.*-profile"   # re-run a subset (setup runs first)
npm run docker:ci:new-system:down   # tear down when done
```

`npm run marketing` runs `scripts/capture-all-locales.sh` (CRM #10048): it
installs a fresh instance once (the same step chain below, minus the
trailing check/manifest steps), captures the first locale, then re-applies
each remaining locale (`en es pt zh fr ru de ar` by default) via the
`locale-set` project and re-runs `screenshots --no-deps` — no reinstall or
re-seeding needed, since ChurchCRM resolves locale per-request from a
DB-stored user preference. `marketing:check` and `marketing:manifest` run
once at the end, across all captured locales. Pass explicit locales as
arguments to capture a subset instead of all 8.

Uses Playwright's bundled Chromium (`npm run marketing:install`). Set
`BROWSER_CHANNEL=chrome` (or `npm run marketing:chrome`) to drive system
Chrome instead. A green run is not enough: open the PNGs under
`playwright/artifacts/screenshots/` and look at them.

The run ends with `marketing:manifest`
(`scripts/generate-marketing-manifest.js`), which reads every per-capture
JSON sidecar under `playwright/artifacts/metadata/` and consolidates them
into one JSON manifest at `playwright/artifacts/manifest.json` — one
object per capture, organized by workflow name, with all devices grouped
together, plus title/category metadata from `screenshot-metadata.json` —
name, device, screenshot-or-video, relative path, whether that file
actually exists and its size, purpose text, viewport, commit, etc. It's
for scanning/looking up a whole run's output at a glance without opening
90+ individual JSON files, and it's what the website's screenshot gallery
reads; it isn't a correctness gate — `marketing:check` (which runs just
before it) is what fails the build. Unlike the JSON sidecars it
summarizes, it **is** committed — regenerate it with `npm run
marketing:manifest` after any run that changes captures, don't hand-edit
it, and don't be surprised if its `commit`/`timestamp` columns lag the
repo by a commit or two (it reflects whatever run last regenerated it, not
necessarily HEAD). An older `manifest.csv` format is obsolete: the script
deletes any leftover `manifest.csv` on each run, and it's gitignored.

Full details, directory layout, and troubleshooting: `playwright/README.md`.

## Architecture

Three Playwright projects, run in this order (`playwright/playwright.config.ts`):

1. **`setup`** (`playwright/setup/bootstrap.setup.ts`) — drives the setup
   wizard, forced admin password change, church info, and demo data import
   as two real, recorded tests (`setup-church-info`, `demo-data-import`).
   Saves a Playwright `storageState` at the end.
2. **`recordings`** (`playwright/videos/*.video.ts`) — click-through videos.
3. **`screenshots`** (`playwright/workflows/*.spec.ts`) — each test captures
   desktop 1440×900, tablet 1024×768 and mobile 430×932 in one page load
   (`VIEWPORTS` in `support/capture.ts`).

Both depend on `setup` and set `storageState`. `workers: 1`, so they share
one database in order: a video that edits a person changes what later
screenshots see.

## Shot list mapping <!-- learned: 2026-09-08 -->

The marketing screenshot shot list (hero dashboard, family record, person
directory, group manager, calendar, attendance, communication, deposit
entry, pledge/fund report, mobile panel, settings/permissions) is fully
covered by `playwright/workflows/*.spec.ts` — see the table in
`playwright/README.md` → "Shot list coverage" for the test-name mapping.
Two shot-list requirements live outside any one spec: **retina**
(`deviceScaleFactor: 2` on every device project, `playwright.config.ts`)
and the **mobile viewport** (430×932 — `VIEWPORTS` in
`playwright/support/capture.ts`, the actual current value; don't trust an
older shot-list doc's exact px figure over that file). The shot list's
"UI detail texture crop" is a manual post-production crop of an existing
screenshot, not something a new page/test can produce — don't try to
automate it.

## Key patterns and gotchas learned building this

- **`globalSetup` is never video-recorded — don't put UI-driving logic
  there.** `use.video: 'on'` only applies to a real test's `page` fixture.
  The first version of this pipeline drove the setup wizard and demo import
  from `globalSetup`, which meant neither ever produced a video — exactly
  backwards, since those are the two most important recordings. Fixed by
  moving that work into the `setup` project above (Playwright's official
  "auth setup project" pattern — `dependencies` + a shared `storageState`
  file — solves both the "run once, first" requirement and the "actually
  gets recorded" requirement at the same time). `playwright/global-setup.ts`
  now does nothing but wait for the server to accept connections.
- **Video finalization is a separate post-run step, not part of the test**:
  Playwright only finalizes a test's recorded video after its browser
  context closes, which happens after the test function has already
  returned — there's no point *inside* a test where the real video file can
  be renamed to a deterministic path. `scripts/finalize-marketing-videos.js`
  runs once after the whole suite completes, reads the JSON reporter output,
  and copies each video to `artifacts/videos/<device>/<test-title>.webm`.
  This is why every test's title must exactly equal the artifact name
  passed to `captureScreen()` — `playwright/support/capture.ts` throws if
  they don't match, specifically so this coupling can't silently drift.
- **Wait for AJAX before every capture, but bounded.**
  `captureScreen()` calls `page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => undefined)`
  right before screenshotting — DataTables/dashboard widgets load async, and
  a screenshot taken a beat too early looks broken. Bounded + swallowed
  because some pages keep a background poll alive indefinitely, which would
  make a strict wait hang forever.
- **Human-paced interaction, not instant scripted clicks** —
  `playwright/support/human.ts` (`humanClick`, `humanType`, `humanSelect`,
  `humanPause`) is used everywhere instead of raw `.click()`/`.fill()`, so
  the recorded videos show someone working through the app. Two traps hit
  while building it:
  - `humanType` **must** `.clear()` before `.pressSequentially()` — some
    fields come pre-filled with auto-detected defaults (e.g. the setup
    wizard's DB Server/Port), and typing without clearing first appends
    onto the existing value (`localhost` + `database-new-system` →
    `localhostdatabase-new-system`), silently producing an invalid value.
  - `humanSelect` must **not** `.hover()` first, unlike `humanClick`.
    Several `<select>` fields (e.g. `#sChurchState`) are TomSelect-enhanced,
    which hides the native `<select>` behind its own widget — hovering the
    hidden element fails even though `.selectOption()` itself works fine on
    it (matches the TomSelect note in `code-standards.md`).
- **Marketing-clean, not just functionally correct** — see
  `playwright/support/marketing-clean.ts` and `playwright.config.ts`:
  - The "System Update Available" banner (`src/Include/Header.php`, shown
    on every logged-in page) is dismissed once, right after the first
    login, via a direct API call
    (`POST /api/user/{id}/setting/notification.dismissed.system-update-available`
    — see `src/skin/js/Footer.js`'s `.js-dismiss-notification` handler for
    the pattern). The dismissal is persisted per-user, so one call early in
    `setup/bootstrap.setup.ts` keeps it gone for the entire run.
  - The "Browser time zone differs" warning on the calendar page is fixed
    at the source instead of dismissed: `playwright.config.ts` sets
    `use.timezoneId: 'America/Chicago'` to match the demo data's
    `sTimeZone` (`src/admin/demo/config.json`), so the mismatch never
    exists in the first place.
  - **Demo data includes inactive groups and families** — don't assume
    "row 1" is presentable. `groups-ministry.spec.ts` walks rows until
    `window.CRM.groupIsActive === true`.
  - Church info entered during `setup-church-info` (e.g. "Grace Community
    Church") gets **overwritten** moments later by the demo data import's
    own fixture (`sChurchName: "Main St. Cathedral"` in
    `src/admin/demo/config.json`). That's expected, not a bug — the
    church-info save is only there to satisfy
    `ChurchInfoRequiredMiddleware`'s required-fields gate before you can
    reach anything else; the specific values you enter there don't survive.
- **Sequential workers only** (`workers: 1` in `playwright.config.ts`).
  Concurrent workers hit a filesystem race creating `outputDir`
  (`ENOTDIR` from parallel mkdir/rm on the same path) in this environment.
  A handful of screenshot workflows don't need the speed either way.
- **Sandboxed runs need these hosts allowed**: `deb.debian.org` (webserver
  image `apt-get`), `tile.openstreetmap.org` (map tiles — captures fail
  without it), `download.cypress.io` (else `CYPRESS_INSTALL_BINARY=0 npm ci`).
  `composer:install` needs PHP `ext-bcmath` on the host.

## Recorded-workflow tests: storageState, duplicate-link traps, pagination <!-- learned: 2026-09-22 -->

Debugging five failing tests (three `*.video.ts` recordings, two `workflows/*.spec.ts`
screenshots added alongside maps + member-status-video features) surfaced four
reusable lessons:

- **A `dependencies: ['setup']` project does NOT inherit the setup project's
  authenticated session by itself.** The dependency only guarantees ordering
  (setup runs first) — it does not share `storageState`. The `recordings`
  project (`playwright/videos/*.video.ts`) was missing `storageState:
  STORAGE_STATE_PATH` in its `use` block while the `screenshots` project had
  it; every recorded-video test ran unauthenticated, landing on empty tables
  or redirects instead of the seeded demo data. Any new project added to
  `playwright.config.ts` that depends on `setup` and needs to be logged in
  must set `storageState` explicitly too.

- **A bare `.first()` (or unscoped `getByText()`) can silently resolve to a
  hidden duplicate of the element you meant to click/assert on.** Several
  ChurchCRM pages render the *same* href or text twice: once as the visible,
  intended element, and once as a hidden decoy — a closed dropdown-menu item
  (`family-view.php`'s "Find Neighbors", `person-view.php`'s per-family-member
  "Edit"), a hidden global widget (`cartview.php`'s "Map cart items" button,
  `/people/map?groupId=0`), or a hidden sidebar nav link
  (`<span class="nav-link-title">{groupName}</span>`, matched by
  `getByText(groupName)`). The symptom is distinctive: `scrollIntoViewIfNeeded`
  times out with **"element is not visible"** repeated for the full timeout —
  not a "not found" error, because an element genuinely exists, it's just the
  wrong (CSS-hidden) one.
  - **Fix**: scope the locator to what makes the real element unique — a
    `.btn` class the decoy lacks (`a.btn[href*="PersonEditor.php"]`), a
    `:not([href*=...])` exclusion for a known decoy pattern
    (`:not([href*="groupId=0"])`), or a containing element the decoy isn't
    inside (`.text-body-secondary` for a page subtitle vs. a sidebar nav
    link).
  - **Diagnose fast**: don't guess from stack traces alone — write a
    throwaway Playwright script using the saved `storageState` at
    `playwright/.auth/admin.json` to list every match with `.isVisible()`
    and `.boundingBox()`:
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
    Run from the repo root (not `/tmp`) so `@playwright/test` resolves.

- **DataTables-backed tables (`#families`, `#members`, `#groupsTable`, etc.)
  paginate by default — 10 rows/page.** `rows.filter({ hasText: 'X' })`
  only searches whatever rows are *currently rendered*; if the target isn't
  on the default first page (e.g. "Worship Service" is 11th of 12 demo
  groups alphabetically), the filter silently matches nothing and
  `toBeVisible()` times out with "element(s) not found". Always type into
  `.dt-search input` first to filter server/client-side, exactly like the
  existing family/member search pattern in `people-family.spec.ts`, before
  locating a specific row.

- **For `workflows/*.spec.ts` screenshot tests (landing-page views), prefer
  direct URL navigation over a full UI click-through once an id is known
  from an earlier navigation** — e.g. after clicking into a family's profile,
  extract the id from `page.url()` and `page.goto('/people/map/neighbors?familyId=' + id)`
  instead of also clicking the page's "Find Neighbors" link. This matches
  the existing Cypress pattern for the same pages
  (`cypress/e2e/ui/people/standard.map.spec.js`:
  `cy.visit("people/map/neighbors?familyId=1")`;
  `cypress/e2e/ui/people/standard.deceased-person.spec.js`:
  `cy.visit("/PersonEditor.php")`) and sidesteps the duplicate-link and
  pagination pitfalls above entirely, since there's one fewer click to get
  wrong. **Reserve the full click-through for `*.video.ts` usability-demo
  tests**, where showing the real click path *is* the point — don't
  simplify those away.

- **Browser download hangs** — if `playwright install` stalls in a sandbox,
  set `BROWSER_CHANNEL=chrome` to use installed Chrome, or point
  `PLAYWRIGHT_BROWSERS_PATH` at a preinstalled Chromium.

## Demo subjects, status and photos

- **Status screenshots use imported data, never UI edits.** The demo
  importer (`DemoDataService::importCongregation`) honors family
  `"active": false`, member `"active": false` and `"dateDeceased"` in
  `src/admin/demo/people.json`. Current subjects: Mark King (inactive
  person), Daniel Johnson (deceased), Campbell (inactive family).
- **Videos own their subjects.** `mark-member-inactive.video.ts` edits Joseph
  Hall and `mark-member-deceased.video.ts` edits Matthew Davis. Screenshots
  must not search for them. Grep `playwright/` before picking a name.
- **Default lists hide status.** `/people/list` shows only active people in
  active families and forces "Living"; use `?personActiveStatus=inactive`,
  `/people/family?familyActiveStatus=inactive`, or read the profile link from
  the server-rendered rows for a deceased person.
- **Pick subjects whose whole family has photos**, and a unique full name.
  Person profiles show the family table, not the family photo; family
  profiles show the family photo (`images/families/`).
- **Photos must match name, gender and rough age, and suit a church**: no
  shirtless, smoking or glamour shots. Check by rendering a labelled contact
  sheet of `images/people/*` with Playwright and viewing it.
- **Family *portrait* photos are scarce — don't swap a family in just to
  chase one.** Only 2 of 62 demo families have an uploaded group photo
  (`family.hernandez60.jpg`, `family.campbell.jpg`); every other family's
  profile card shows the app's real initials-avatar fallback, which is
  correct/expected behavior, not a bug. Before swapping a screenshot's
  family to "fix" a placeholder, check the trade fully: Hernandez's own
  *members* have no individual photos (worse than the placeholder — see
  the "whole family has photos" rule above), and Campbell is the
  `family-inactive-profile` subject, so using it for a shot meant to show
  a normal active family would (wrongly) show the inactive banner. Given
  that, `people-family-overview`/`-dark` keep Scott (real member photos,
  no family portrait) — accurate purpose text over a forced swap.
- **Date pickers use `sDatePickerFormat` (`Y-m-d`).** Type `YYYY-MM-DD` and
  assert `toHaveValue` before submit; `MM/DD/YYYY` saves a wrong date.
- **Maps**: `captureScreen()` waits for every visible Leaflet tile to get
  `.leaflet-tile-loaded` and throws if tiles fail, so gray maps never ship.
- **Map visibility depends on page layout, not on the wait above.** At the
  1440×900 desktop viewport, `person-view.php` puts the photo in a narrow
  *left* column and the Family Members + Address/map cards in a wide
  *right* column, so the map lands within the fold (confirmed on
  `person-inactive-profile`/`person-deceased-profile` — real street tiles
  render). `family-view.php` instead stacks the photo *above* the
  Address/map card in one narrow right column — regardless of family size,
  since that stack's height doesn't depend on the member count — which
  pushes the map below the fold every time. So `people-family-overview`
  and its dark variant only show the "Geocoded" badge, not the rendered
  map; their purpose text says "geocoded address", not "map", on purpose.
  If a future shot needs the family map actually in frame, that's a real
  UI/copy call (taller capture just for that one shot, or scroll-and-crop),
  not a one-line fix — ask before doing either.
