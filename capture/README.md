# ChurchCRM Visual Capture System

Standalone Playwright-based system for producing video and screenshot assets used in social-media tutorials (YouTube Shorts, Reels, TikTok).

This folder is completely independent of `cypress/` and must never modify or share its `package.json`.

---

## Quick Start

```bash
# 1. Install dependencies (inside this folder only)
cd capture
npm install
npm run install:browsers   # downloads Playwright's Chromium

# 2. Start a ChurchCRM Docker stack with demo data (see Docker section below)
cd ..
npm run docker:dev:start   # or: npm run docker:ci:new-system:start

# 3. Run a scene
node capture/scenes/pledge_tracking.js --out-dir /tmp/capture-test --scene-id demo
```

Output files will be in `/tmp/capture-test/`:

| File | Description |
|------|-------------|
| `demo_raw.webm` | Full scene recording — **always produced** |
| `demo_pledge_summary.png` | Named still captured mid-scene — optional per scene |

---

## CLI Contract

Every scene is a self-contained Node script with the same interface:

```
node capture/scenes/<name>.js --out-dir <dir> --scene-id <id>
```

| Argument | Required | Description |
|----------|----------|-------------|
| `--out-dir` | ✅ | Directory where output files are written (created if absent) |
| `--scene-id` | ✅ | Prefix for all output filenames, e.g. `demo` |

### Output filenames

- **Mandatory**: `<scene-id>_raw.webm` — the full recording
- **Optional**: `<scene-id>_<label>.png` — named still(s) captured during the scene

### Exit codes

- `0` — scene completed successfully; `.webm` verified non-empty
- Non-zero — failure, with a clear message on stderr

---

## Docker Environments

### PATH A — Demo-seeded stack (quick iteration)

```bash
npm run docker:dev:start
```

Starts ChurchCRM and replays `cypress/data/seed.sql`.  Data is ready immediately.
Contains test-run artifacts (test users, generated data) — fine for development but not
ideal for polished tutorial recordings.

Credentials: `admin / changeme`  
Base URL: `http://localhost`

### PATH B — Fresh install + demo import (preferred for capture)

```bash
# 1. Start a fresh-install stack (no Config.php → triggers setup wizard)
npm run docker:ci:new-system:start

# 2. Complete the wizard at http://localhost (set church name, admin password)

# 3. Seed with clean demo data via Node
node -e "
const { chromium } = require('@playwright/test');
const { login } = require('./capture/helpers/auth');
const { importDemoData } = require('./capture/helpers/seed');
(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext()).newPage();
  await login(page);
  await importDemoData(page, { force: true });
  await browser.close();
  console.log('Seeded!');
})();
"
```

This imports from `src/admin/demo/` (people.json, groups.json, events.csv, finance.json)
producing clean, photogenic data with no test artifacts.

**DB reset between captures:**

- PATH A: `npm run docker:test:reset:db` (replays seed.sql)
- PATH B: `npm run docker:ci:new-system:down && npm run docker:ci:new-system:start` (full teardown + restart)

---

## Configuration

| Environment variable | Default | Description |
|----------------------|---------|-------------|
| `CAPTURE_BASE_URL` | `http://localhost` | ChurchCRM base URL |
| `CAPTURE_ADMIN_USERNAME` | `admin` | Admin username (matches Cypress docker.config.ts) |
| `CAPTURE_ADMIN_PASSWORD` | `changeme` | Admin password |
| `CAPTURE_OUTPUT_ROOT` | `./out` | Default output directory for ad-hoc runs |

---

## How Capture Differs from Cypress

| | `capture/` | `cypress/` |
|--|------------|------------|
| **Purpose** | Produce video/screenshot assets for tutorials | Automated regression testing |
| **Output** | `.webm` recordings + named `.png` stills | Pass/fail test results |
| **Run style** | One Node script per scene, no test runner | Cypress runner (mocha-based) |
| **Dark theme** | Forced dark (photogenic) | Default app theme |
| **Viewport** | 390×844 mobile (parameterisable) | 1920×1080 desktop |
| **Shared** | Nothing — completely separate `package.json` | — |

---

## Adding a New Scene

1. Copy `scenes/pledge_tracking.js` to `scenes/<your_scene>.js`.
2. Update the `--out-dir` / `--scene-id` usage comment at the top.
3. Replace the interaction block (steps 3–8) with your scene's navigation and interactions.
4. Use helpers:
   - `login(page)` — authenticate
   - `forceDarkTheme(page)` — re-apply after each navigation
   - `smoothMouseMove(page, from, to, steps, durationMs)` — natural mouse movement
   - `pause(ms)` — readability beat
   - `typeSlowly(page, selector, text, delayMs)` — human-paced typing
   - `page.screenshot({ path })` — named still at any moment

The CLI contract, exit codes, and output naming are handled by the scaffold — you only
write the interaction steps.

---

## Dark Theme

ChurchCRM's dark theme sets `data-bs-theme="dark"` on `<html>`.  The real toggle is
`window.CRM.theme.setMode('dark')` (defined in `src/Include/Header.php`).

`helpers/theme.js` calls this toggle after each navigation and also installs an init
script that stamps the attribute before the first paint (FOWT-safe).  Do not invent
your own theme mechanism — always call `forceDarkTheme(page)` from this helper.

---

## Viewport

Default is **390×844** (mobile portrait — ideal for Shorts/Reels/TikTok).

For desktop-only views, override in your scene:

```js
const context = await browser.newContext({
  viewport: { width: 1280, height: 800 },
  recordVideo: { dir: outDir, size: { width: 1280, height: 800 } },
});
```

---

## File Structure

```
capture/
  package.json             # standalone deps — playwright, minimist
  capture.config.js        # BASE_URL, DEFAULT_VIEWPORT, OUTPUT_ROOT, DEFAULT_CREDENTIALS
  .gitignore               # excludes node_modules and local video output
  helpers/
    auth.js                # login(page, credentials) — ported from Cypress
    theme.js               # installDarkThemeInitScript(), forceDarkTheme()
    interaction.js         # smoothMouseMove(), pause(), typeSlowly()
    seed.js                # importDemoData() + Docker setup documentation
  scenes/
    pledge_tracking.js     # example scene — Pledge Tracking
  README.md                # this file
```
