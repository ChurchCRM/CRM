import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

import { BASE_URL } from './support/env';

const STORAGE_STATE_PATH = path.join(__dirname, '.auth', 'admin.json');

// Determine browser channel: prefer local Chrome if BROWSER_CHANNEL is set,
// otherwise use bundled Chromium. Set BROWSER_CHANNEL=chrome to use system Chrome.
const browserChannel = process.env.BROWSER_CHANNEL || undefined;

// Form factors mirror .agents/skills/churchcrm/responsive-design-guidelines.md
// (Mobile < 768px, Tablet 768-1199.98px, Laptop/Desktop >= 1200px), using
// one representative viewport per factor rather than full device emulation
// (no touch/UA overrides) to keep automation simple and robust for a first
// milestone. By default, all projects use Playwright's bundled Chromium
// (installed via `npm run marketing:visuals:install`). To use system Chrome,
// set BROWSER_CHANNEL=chrome. If the bundled-Chromium download hangs, allow
// `cdn.playwright.dev` in your network policy or set BROWSER_CHANNEL=chrome
// to use the system installation instead.
export default defineConfig({
  testDir: '.',
  // Playwright's default (30s) is far shorter than setup-church-info's own
  // sequential waits can add up to: up to 120s for the prerequisites check,
  // up to 120s for the #setup-success DB migration wait, plus several
  // shorter waitForURL calls after — worst case sum comfortably exceeds
  // 150s, so this is sized with real headroom above that worst case rather
  // than just the single longest step.
  timeout: 300000,
  globalSetup: require.resolve('./global-setup'),
  // Deliberately outside artifacts/ — Playwright wipes and recreates this
  // directory at the start of every run, which raced with our own
  // concurrent directory creation under artifacts/ when nested inside it.
  outputDir: path.join(__dirname, '.playwright-output'),
  fullyParallel: false,
  // Sequential on purpose: this hit a filesystem race on outputDir creation
  // under concurrent workers in testing (ENOTDIR from parallel mkdir/rm on
  // the same path). A handful of screenshot workflows don't need the speed,
  // and running them one at a time against a single Docker instance is also
  // just simpler to debug.
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: 0,
  reporter: [['list'], ['json', { outputFile: path.join(__dirname, 'artifacts', 'report.json') }]],
  use: {
    baseURL: BASE_URL,
    video: 'off',
    trace: 'retain-on-failure',
    actionTimeout: 15000,
    navigationTimeout: 30000,
    // Matches the demo data's sTimeZone (src/admin/demo/config.json) so the
    // app's "Browser time zone differs" warning never fires — these
    // screenshots are marketing material, not test evidence, and that
    // banner has no business being in either.
    timezoneId: 'America/Chicago',
    // This only controls Playwright's own automatic on-failure screenshot
    // attachment (for debugging a failed run), not the marketing
    // screenshots themselves — those are explicit page.screenshot() calls
    // in support/capture.ts, which is where the actual
    // ChurchCRM/ChurchCRM.io#100 viewport-framing fix lives.
    screenshot: { mode: 'only-on-failure', fullPage: false },
  },
  // Add visual cursor indicator for videos (injected on every page load)
  async addInitScript() {
    // Create a visual cursor indicator circle
    const cursor = document.createElement('div');
    cursor.id = '__playwright_cursor__';
    cursor.style.cssText = `
      position: fixed;
      width: 20px;
      height: 20px;
      border: 2px solid #ff0000;
      border-radius: 50%;
      pointer-events: none;
      z-index: 999999;
      display: none;
      box-shadow: 0 0 10px rgba(255, 0, 0, 0.8);
    `;
    document.documentElement.appendChild(cursor);

    // Track mouse position and update cursor indicator
    document.addEventListener('mousemove', (e) => {
      cursor.style.display = 'block';
      cursor.style.left = (e.clientX - 10) + 'px';
      cursor.style.top = (e.clientY - 10) + 'px';
    });

    // Hide cursor indicator when mouse leaves the window
    document.addEventListener('mouseleave', () => {
      cursor.style.display = 'none';
    });
  },
  projects: [
    {
      // Setup wizard, church info, and demo data import — real recorded
      // tests (not Playwright's globalSetup, which is never video-recorded),
      // run once, before every other project. See setup/bootstrap.setup.ts.
      // Video cursor is enhanced via high-quality recording.
      name: 'setup',
      testMatch: /setup\/.*\.setup\.ts/,
      use: {
        ...devices['Desktop Chrome'],
        channel: browserChannel,
        viewport: { width: 1440, height: 900 },
        video: { mode: 'on', size: { width: 1440, height: 900 } },
      },
    },
    {
      // Additional recorded workflows beyond the core bootstrap videos
      // (setup wizard, demo import) — e.g. the public self-registration
      // flow. Same high-quality video treatment as 'setup', kept as its
      // own project instead of appended to bootstrap.setup.ts so that
      // file stays scoped to system bootstrap only.
      // Named 'recordings', not 'videos' — capture.ts writes each
      // video-only project's output to artifacts/videos/<project-name>/,
      // and a project literally named 'videos' collided with that parent
      // folder (artifacts/videos/videos/...).
      name: 'recordings',
      testMatch: /videos\/.*\.video\.ts/,
      dependencies: ['setup'],
      use: {
        ...devices['Desktop Chrome'],
        channel: browserChannel,
        viewport: { width: 1440, height: 900 },
        video: { mode: 'on', size: { width: 1440, height: 900 } },
      },
    },
    {
      // Screenshot tests run once per test, capturing all viewports (desktop/tablet/mobile)
      // in a single page load. Tests manually resize viewport between captures.
      // This is 3x faster than running separate desktop/tablet/mobile projects.
      // Viewport sizes: Desktop 1440×900, iPad 1024×768, iPhone Pro Max 430×932
      name: 'screenshots',
      testMatch: /workflows\/.*\.spec\.ts/,
      dependencies: ['setup'],
      use: {
        ...devices['Desktop Chrome'],
        channel: browserChannel,
        viewport: { width: 1440, height: 900 },
        deviceScaleFactor: 2,
        storageState: STORAGE_STATE_PATH,
      },
    },
  ],
});
