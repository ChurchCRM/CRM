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
whether to adopt ChurchCRM: no error banners, no "Inactive" records, no
half-loaded AJAX content. When adding or changing a workflow, look at the
actual generated screenshot before considering it done (see
`playwright/README.md` for the exact artifact paths), not just the green
checkmark.

## Running it

```bash
npm run marketing:visuals           # fresh instance → seed → run all workflows
npm run docker:ci:new-system:down   # tear down when done
```

Requires Google Chrome installed — the pipeline drives it via
`channel: 'chrome'` in `playwright.config.ts`, not Playwright's bundled
Chromium, so no `playwright install` step is needed (see the "Browser
download hangs" gotcha below).

Full details, directory layout, and troubleshooting: `playwright/README.md`.

## Architecture

Four Playwright projects, run in this order (`playwright/playwright.config.ts`):

1. **`setup`** (`playwright/setup/bootstrap.setup.ts`) — drives the setup
   wizard, forced admin password change, church info, and demo data import
   as two real, recorded tests (`setup-church-info`, `demo-data-import`).
   Saves a Playwright `storageState` at the end.
2. **`desktop` / `tablet` / `mobile`** (`playwright/workflows/*.spec.ts`) —
   the three end-user workflows (People & Families, Groups & Ministry,
   Events & Attendance), each declaring `dependencies: ['setup']` and
   reusing the saved `storageState` so they don't need to log in again.

## Shot list mapping <!-- learned: 2026-09-08 -->

The marketing screenshot shot list (hero dashboard, family record, person
directory, group manager, calendar, attendance, communication, deposit
entry, pledge/fund report, mobile panel, settings/permissions) is fully
covered by `playwright/workflows/*.spec.ts` — see the table in
`playwright/README.md` → "Shot list coverage" for the test-name mapping.
Two shot-list requirements live in `playwright.config.ts` rather than a
spec: **retina** (`deviceScaleFactor: 2` on every device project) and the
**mobile viewport** (390×844, not an arbitrary breakpoint width — matches
the shot list's "narrow viewport (390×844)" line exactly). The shot list's
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
  - **Demo data includes inactive groups and families** — clicking "the
    first row" in a list can land you on a group/family flagged
    "Inactive", which is a bad look for a marketing screenshot.
    `groups-ministry.spec.ts` walks rows until it finds one where
    `window.CRM.groupIsActive === true` (exposed by `group-view.php`
    specifically for this); `people-family.spec.ts` filters out any row
    already showing an "Inactive" badge before clicking. If you add a new
    workflow that lists records, check whether the underlying data can
    include an inactive/disabled/error state and filter it out the same
    way — don't assume "row 1" is presentable.
  - Church info entered during `setup-church-info` (e.g. "Grace Community
    Church") gets **overwritten** moments later by the demo data import's
    own fixture (`sChurchName: "Main St. Cathedral"` in
    `src/admin/demo/config.json`). That's expected, not a bug — the
    church-info save is only there to satisfy
    `ChurchInfoRequiredMiddleware`'s required-fields gate before you can
    reach anything else; the specific values you enter there don't survive.
- **Three form factors, one engine**: `desktop` (1440×900), `tablet`
  (834×1194), `mobile` (375×812) — matching the breakpoints in
  `responsive-design-guidelines.md` — all on Chromium with a fixed viewport
  only (no touch/UA emulation). Full device emulation was deliberately
  skipped for the first milestone to keep automation robust; see
  `playwright/README.md` → Known limitations.
- **Sequential workers only** (`workers: 1` in `playwright.config.ts`).
  Concurrent workers hit a filesystem race creating `outputDir`
  (`ENOTDIR` from parallel mkdir/rm on the same path) in this environment.
  A handful of screenshot workflows don't need the speed either way.
- **This sandbox's network policy blocks `deb.debian.org`** (the
  `docker:ci:new-system` image build's `apt-get update`) by default — needs
  an explicit `sbx policy allow network deb.debian.org` before the Docker
  image will build. If you hit a `403`/`no matching allow rule` error
  building the image in a similar sandboxed environment, that's almost
  certainly it, not a bug in this code.
- **Browser download hangs — use system Chrome instead.**
  `playwright install`'s download of its bundled Chromium from
  `cdn.playwright.dev` (redirects to `storage.googleapis.com`) hung
  indefinitely (30s socket timeout, repeated) in this sandbox even after
  both hosts were added to `sbx policy allow network` — a plain `curl`/
  `node -e "https.get(...)"` to the exact same URLs returned instantly, so
  it's specific to Playwright's downloader (most likely its forced
  `autoSelectFamily`/Happy-Eyeballs socket option, set directly in its
  request code rather than inherited from Node's `--network-family-
  autoselection` flag, so that flag can't override it), not a general
  network block. Fix: `playwright.config.ts`'s top-level `use.channel:
  'chrome'` makes every project launch the machine's already-installed
  Google Chrome instead — no download, no `playwright install` step at
  all. Requires Chrome to actually be installed on the machine running the
  pipeline.
