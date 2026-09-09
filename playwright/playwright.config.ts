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
    video: 'on',
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
  projects: [
    {
      // Setup wizard, church info, and demo data import — real recorded
      // tests (not Playwright's globalSetup, which is never video-recorded),
      // run once, before every other project. See setup/bootstrap.setup.ts.
      name: 'setup',
      testMatch: /setup\/.*\.setup\.ts/,
      use: { ...devices['Desktop Chrome'], channel: browserChannel, viewport: { width: 1440, height: 900 } },
    },
    {
      name: 'desktop',
      testMatch: /workflows\/.*\.spec\.ts/,
      dependencies: ['setup'],
      use: {
        ...devices['Desktop Chrome'],
        channel: browserChannel,
        viewport: { width: 1440, height: 900 },
        // Retina (shot list prep: "1440×900 browser at 2×").
        deviceScaleFactor: 2,
        storageState: STORAGE_STATE_PATH,
      },
    },
    {
      name: 'tablet',
      testMatch: /workflows\/.*\.spec\.ts/,
      dependencies: ['setup'],
      use: {
        ...devices['Desktop Chrome'],
        channel: browserChannel,
        viewport: { width: 834, height: 1194 },
        deviceScaleFactor: 2,
        storageState: STORAGE_STATE_PATH,
      },
    },
    {
      name: 'mobile',
      testMatch: /workflows\/.*\.spec\.ts/,
      dependencies: ['setup'],
      use: {
        ...devices['Desktop Chrome'],
        channel: browserChannel,
        // 390×844 matches the shot list's "Mobile — one panel cropped" spec
        // (still comfortably inside the <768px mobile breakpoint).
        viewport: { width: 390, height: 844 },
        deviceScaleFactor: 2,
        storageState: STORAGE_STATE_PATH,
      },
    },
  ],
});
