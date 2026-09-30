import type { Page, TestInfo } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

import { LOCALE } from './env';
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

// Playwright project names (see playwright.config.ts) that record video at
// a single fixed viewport instead of the desktop/tablet/mobile screenshot
// sweep — used as both the capture-mode switch and the artifact device dir.
const VIDEO_ONLY_PROJECTS = new Set(['setup', 'recordings']);

export async function captureScreen(page: Page, testInfo: TestInfo, opts: CaptureOptions): Promise<void> {
  if (testInfo.title !== opts.name) {
    throw new Error(
      `Test title "${testInfo.title}" must match capture name "${opts.name}" so the post-run video ` +
        'finalize step (scripts/finalize-marketing-videos.js) can match them up.'
    );
  }

  // Video-only projects (the bootstrap 'setup' recordings, plus any other
  // standalone recorded workflow like the self-registration video) capture
  // once at their configured viewport — no viewport-resize loop, no PNG.
  if (VIDEO_ONLY_PROJECTS.has(testInfo.project.name)) {
    await captureAtViewport(page, testInfo, opts, testInfo.project.name);
    return;
  }

  // For screenshot tests, capture all viewports in a single test run (3x faster)
  for (const vp of VIEWPORTS) {
    await page.setViewportSize({ width: vp.width, height: vp.height });
    await captureAtViewport(page, testInfo, opts, vp.device);
  }
}

// Leaflet only adds .leaflet-tile-loaded to tiles that loaded successfully,
// so a blocked or failing tile server times out here instead of shipping a
// gray map.
async function waitForMapTiles(page: Page, name: string): Promise<void> {
  const hasVisibleMap = await page
    .locator('.leaflet-container')
    .evaluateAll((maps) => maps.some((m) => (m as HTMLElement).offsetWidth > 0 && (m as HTMLElement).offsetHeight > 0));
  if (!hasVisibleMap) {
    return;
  }

  await page
    .waitForFunction(
      () => {
        const tiles = Array.from(document.querySelectorAll<HTMLImageElement>('.leaflet-container img.leaflet-tile')).filter(
          (t) => t.offsetWidth > 0
        );
        return tiles.length > 0 && tiles.every((t) => t.classList.contains('leaflet-tile-loaded'));
      },
      undefined,
      { timeout: 20000 }
    )
    .catch(() => {
      throw new Error(`Map tiles did not finish loading for "${name}" — check access to tile.openstreetmap.org.`);
    });
  // Leaflet fades tiles in over 200ms.
  await page.waitForTimeout(300);
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

  // CRM #10048 — nest screenshot artifacts under their locale so an
  // 8-locale run doesn't overwrite the same {device}/{name} path 8 times.
  // Applies to a plain English run too (screenshots/en/desktop/...), not
  // just the multi-locale codes, so the layout is uniform and Phase 2's
  // website integration (ChurchCRM/ChurchCRM.io#142) only has to handle
  // one shape.
  //
  // Video-only projects ('setup'/'recordings') are deliberately excluded:
  // they're the bootstrap/setup-wizard videos, not part of #10048's 7
  // screenshots, they never run multi-locale, and
  // scripts/finalize-marketing-videos.js independently hardcodes the flat
  // videos/<project>/<title>.webm path when it renames Playwright's own
  // recordings — inserting LOCALE here would desync capture.ts's metadata
  // from where that script actually puts the file.
  const localeSegment = VIDEO_ONLY_PROJECTS.has(device) ? [] : [LOCALE];
  const metadataDir = path.join(ARTIFACTS_ROOT, 'metadata', ...localeSegment, device);
  fs.mkdirSync(metadataDir, { recursive: true });

  // Let AJAX-loaded content (DataTables, dashboard widgets, etc.) finish
  // before capturing — a caller's own explicit waits get the page to a
  // "visually ready" state, but in-flight requests can still be filling in
  // detail. Bounded and best-effort: some pages keep a background poll
  // alive indefinitely, which would make a strict wait hang forever.
  await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => undefined);
  await waitForMapTiles(page, opts.name);

  let screenshotPath: string | null = null;
  if (!VIDEO_ONLY_PROJECTS.has(device)) {
    const screenshotDir = path.join(ARTIFACTS_ROOT, 'screenshots', LOCALE, device);
    fs.mkdirSync(screenshotDir, { recursive: true });
    screenshotPath = path.join(screenshotDir, `${opts.name}.png`);
    await page.screenshot({ path: screenshotPath, fullPage: false });
  }

  // Left flat (no locale segment) to match finalize-marketing-videos.js —
  // see the comment on metadataDir above.
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
