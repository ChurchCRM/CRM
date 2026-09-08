import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

import { BASE_URL } from './support/env';

const STORAGE_STATE_PATH = path.join(__dirname, '.auth', 'admin.json');

// Form factors mirror .agents/skills/churchcrm/responsive-design-guidelines.md
// (Mobile < 768px, Tablet 768-1199.98px, Laptop/Desktop >= 1200px), using
// one representative viewport per factor rather than full device emulation
// (no touch/UA overrides) to keep automation simple and robust for a first
// milestone. All four projects use the Chromium engine.
export default defineConfig({
  testDir: '.',
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
  },
  projects: [
    {
      // Setup wizard, church info, and demo data import — real recorded
      // tests (not Playwright's globalSetup, which is never video-recorded),
      // run once, before every other project. See setup/bootstrap.setup.ts.
      name: 'setup',
      testMatch: /setup\/.*\.setup\.ts/,
      use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } },
    },
    {
      name: 'desktop',
      testMatch: /workflows\/.*\.spec\.ts/,
      dependencies: ['setup'],
      use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 }, storageState: STORAGE_STATE_PATH },
    },
    {
      name: 'tablet',
      testMatch: /workflows\/.*\.spec\.ts/,
      dependencies: ['setup'],
      use: { ...devices['Desktop Chrome'], viewport: { width: 834, height: 1194 }, storageState: STORAGE_STATE_PATH },
    },
    {
      name: 'mobile',
      testMatch: /workflows\/.*\.spec\.ts/,
      dependencies: ['setup'],
      use: { ...devices['Desktop Chrome'], viewport: { width: 375, height: 812 }, storageState: STORAGE_STATE_PATH },
    },
  ],
});
