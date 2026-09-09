import type { Page, TestInfo } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

import { writeMetadata } from './metadata';

export const ARTIFACTS_ROOT = path.join(__dirname, '..', 'artifacts');

export interface CaptureOptions {
  /** Must match the test's title — see the note below. */
  name: string;
  purpose: string;
}

/**
 * Captures a viewport-framed screenshot (not a full-page scroll capture) at
 * a deterministic path, and writes its metadata sidecar. The `setup`
 * project is video-only — no screenshot needed alongside the setup-wizard
 * and demo-import recordings.
 *
 * Bounded to the configured viewport rather than `fullPage: true` per
 * ChurchCRM/ChurchCRM.io#100's shot spec: "no browser chrome / device
 * bezels — crop to the app viewport" and "cropped with intent (a wide
 * establishing frame or a tight detail crop, not the whole app shrunk)". A
 * full-page capture is the opposite of that — a long scrolling stitch of
 * the entire page, not a clean single frame. Each workflow's own waits
 * already land the page in a state where the "useful view" is visible
 * without scrolling, so this doesn't lose anything the workflow intended to
 * show. At `deviceScaleFactor: 2` (see playwright.config.ts), the desktop
 * project's 1440x900 viewport already produces a 2880x1800 PNG, clearing
 * #100's 2x/2560px-wide minimum for full-bleed use.
 *
 * The test's title MUST equal `name`. Playwright only finalizes a test's
 * recorded video after the browser context closes, which happens after the
 * test function itself has already returned — so there is no point inside
 * the test where the real video file is available to rename. Instead,
 * scripts/finalize-marketing-videos.js runs once after the whole suite
 * finishes, reads the JSON reporter output, and matches each video
 * attachment back to the deterministic path claimed here by test title +
 * project name.
 */
interface ViewportConfig {
  device: 'desktop' | 'tablet' | 'mobile';
  width: number;
  height: number;
}

const VIEWPORTS: ViewportConfig[] = [
  { device: 'desktop', width: 1440, height: 900 },
  { device: 'tablet', width: 1024, height: 768 },
  { device: 'mobile', width: 430, height: 932 },
];

export async function captureScreen(page: Page, testInfo: TestInfo, opts: CaptureOptions): Promise<void> {
  if (testInfo.title !== opts.name) {
    throw new Error(
      `Test title "${testInfo.title}" must match capture name "${opts.name}" so the post-run video ` +
        'finalize step (scripts/finalize-marketing-videos.js) can match them up.'
    );
  }

  // For setup tests, capture once at the configured viewport
  if (testInfo.project.name === 'setup') {
    await captureAtViewport(page, testInfo, opts, testInfo.project.name);
    return;
  }

  // For screenshot tests, capture all viewports in a single test run (3x faster)
  for (const vp of VIEWPORTS) {
    await page.setViewportSize({ width: vp.width, height: vp.height });
    await captureAtViewport(page, testInfo, opts, vp.device);
  }
}

async function captureAtViewport(
  page: Page,
  testInfo: TestInfo,
  opts: CaptureOptions,
  device: string
): Promise<void> {
  const viewport = page.viewportSize();
  if (!viewport) {
    throw new Error(`No viewport configured for project "${device}"`);
  }

  const metadataDir = path.join(ARTIFACTS_ROOT, 'metadata', device);
  fs.mkdirSync(metadataDir, { recursive: true });

  // Let AJAX-loaded content (DataTables, dashboard widgets, etc.) finish
  // before capturing — a caller's own explicit waits get the page to a
  // "visually ready" state, but in-flight requests can still be filling in
  // detail. Bounded and best-effort: some pages keep a background poll
  // alive indefinitely, which would make a strict wait hang forever.
  await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => undefined);

  let screenshotPath: string | null = null;
  if (device !== 'setup') {
    const screenshotDir = path.join(ARTIFACTS_ROOT, 'screenshots', device);
    fs.mkdirSync(screenshotDir, { recursive: true });
    screenshotPath = path.join(screenshotDir, `${opts.name}.png`);
    await page.screenshot({ path: screenshotPath, fullPage: false });
  }

  const videoPath = path.join(ARTIFACTS_ROOT, 'videos', device, `${opts.name}.webm`);

  writeMetadata(path.join(metadataDir, `${opts.name}.json`), {
    workflow: opts.name,
    purpose: opts.purpose,
    device,
    viewport: { width: viewport.width, height: viewport.height },
    screenshot: screenshotPath,
    video: videoPath,
  });
}
