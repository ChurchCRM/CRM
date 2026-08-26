/**
 * scenes/pledge_tracking.js — visual capture: Pledge Tracking
 *
 * CLI contract (required by external orchestrators):
 *   node capture/scenes/pledge_tracking.js --out-dir <dir> --scene-id <id>
 *
 * Outputs:
 *   <out-dir>/<scene-id>_raw.webm           – full scene recording (mandatory)
 *   <out-dir>/<scene-id>_pledge_summary.png – named still at summary totals (optional)
 *
 * Exit behaviour:
 *   - Non-zero + clear stderr message on any failure.
 *   - Exit 0 only after verifying the .webm exists and has non-zero size.
 *
 * Requires: ChurchCRM running at BASE_URL (see capture.config.js).
 *           npm install inside capture/ to install Playwright.
 *           Playwright Chromium browser: npm run install:browsers
 */

'use strict';

const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');
const { chromium } = require('@playwright/test');
const minimist = require('minimist');

const { BASE_URL, DEFAULT_VIEWPORT } = require('../capture.config');
const { login } = require('../helpers/auth');
const { installDarkThemeInitScript, forceDarkTheme } = require('../helpers/theme');
const { smoothMouseMove, pause } = require('../helpers/interaction');

// ── CLI argument parsing ───────────────────────────────────────────────────────
const argv = minimist(process.argv.slice(2), { string: ['out-dir', 'scene-id'] });
const outDir = argv['out-dir'];
const sceneId = argv['scene-id'];

if (!outDir || !sceneId) {
  process.stderr.write(
    'Error: --out-dir and --scene-id are required.\n' +
    'Usage: node capture/scenes/pledge_tracking.js --out-dir <dir> --scene-id <id>\n'
  );
  process.exit(1);
}

// Ensure output directory exists.
fs.mkdirSync(outDir, { recursive: true });

// ── Viewport for this scene (mobile/vertical) ─────────────────────────────────
const VIEWPORT = DEFAULT_VIEWPORT; // 390×844

// ── Main capture function ──────────────────────────────────────────────────────
async function run() {
  const browser = await chromium.launch({ headless: true });
  try {
  // Playwright records video per-context.  One context = one video file.
  const context = await browser.newContext({
    viewport: VIEWPORT,
    recordVideo: {
      dir: outDir,
      size: VIEWPORT,
    },
    // Ignore HTTPS errors for local Docker setups.
    ignoreHTTPSErrors: true,
  });

  const page = await context.newPage();

  // Install init script BEFORE the first navigation so the very first paint
  // is already dark (FOWT-safe).
  await installDarkThemeInitScript(page);

  try {
    // ── 1. Log in ──────────────────────────────────────────────────────────
    await login(page);

    // ── 2. Force dark theme using ChurchCRM's real toggle ─────────────────
    // window.CRM.theme.setMode('dark') is defined in src/Include/Header.php
    await forceDarkTheme(page);
    await pause(500);

    // ── 3. Navigate to Pledge Tracking ────────────────────────────────────
    await page.goto(`${BASE_URL}/PledgeDetails.php`, { waitUntil: 'networkidle' });
    await forceDarkTheme(page);   // re-apply after navigation
    await pause(1000);             // let viewer absorb the page

    // ── 4. Slow scroll — reveal pledge summary section ───────────────────
    //  Scroll down gently so the viewer can read the totals.
    const scrollSteps = 20;
    const scrollTarget = VIEWPORT.height * 0.5; // scroll half a viewport
    for (let i = 1; i <= scrollSteps; i++) {
      await page.evaluate((scrollY) => window.scrollTo(0, scrollY), (i / scrollSteps) * scrollTarget);
      await pause(60);
    }
    await pause(800);

    // ── 5. Smooth mouse moves over summary totals ─────────────────────────
    //  Move across the summary area to highlight figures.
    const centerX = Math.round(VIEWPORT.width / 2);
    await smoothMouseMove(
      page,
      { x: 30, y: 300 },
      { x: centerX, y: 350 },
      25,
      700
    );
    await pause(600);

    await smoothMouseMove(
      page,
      { x: centerX, y: 350 },
      { x: VIEWPORT.width - 30, y: 350 },
      25,
      700
    );
    await pause(600);

    // ── 6. Named screenshot at the summary view ───────────────────────────
    const screenshotPath = path.join(outDir, `${sceneId}_pledge_summary.png`);
    await page.screenshot({ path: screenshotPath, fullPage: false });
    console.log(`[capture] Screenshot saved: ${screenshotPath}`);
    await pause(500);

    // ── 7. Click a pledge fund row if one is visible ───────────────────────
    //  Attempt to interact with the first clickable row; skip gracefully if
    //  no data is loaded (captures still work with an empty pledge list).
    try {
      const firstRow = page.locator('table tbody tr').first();
      const rowCount = await firstRow.count();
      if (rowCount > 0) {
        const rowBox = await firstRow.boundingBox();
        if (rowBox) {
          await smoothMouseMove(
            page,
            { x: centerX, y: rowBox.y + rowBox.height / 2 - 60 },
            { x: centerX, y: rowBox.y + rowBox.height / 2 },
            20,
            500
          );
          await pause(400);
          await firstRow.click();
          await page.waitForLoadState('networkidle');
          await forceDarkTheme(page);
          await pause(1200);
        }
      }
    } catch (_e) {
      // No table rows or click failed — not fatal, continue.
    }

    // ── 8. Final scroll back to top ────────────────────────────────────────
    for (let i = scrollSteps; i >= 0; i--) {
      await page.evaluate((scrollY) => window.scrollTo(0, scrollY), (i / scrollSteps) * scrollTarget);
      await pause(40);
    }
    await pause(800);

  } finally {
    // Closing the context flushes the video to disk.
    await context.close();
  }
  } finally {
    await browser.close();
  }

  // ── 9. Move the recorded video to the required output path ────────────────
  //  Playwright saves the video with an auto-generated UUID filename inside
  //  outDir.  We locate it (newest .webm in outDir) and rename to the
  //  canonical <scene-id>_raw.webm name.
  const rawWebmPath = path.join(outDir, `${sceneId}_raw.webm`);

  const webmFiles = fs
    .readdirSync(outDir)
    .filter((f) => f.endsWith('.webm') && !f.startsWith(sceneId))
    .map((f) => ({ f, mtime: fs.statSync(path.join(outDir, f)).mtimeMs }))
    .sort((a, b) => b.mtime - a.mtime);

  if (webmFiles.length === 0) {
    // Playwright may already have named it after page.video().path() resolution.
    // Check if rawWebmPath itself already exists (shouldn't, but guard anyway).
    if (!fs.existsSync(rawWebmPath)) {
      throw new Error('No .webm file was produced in the output directory.');
    }
  } else {
    const srcWebm = path.join(outDir, webmFiles[0].f);
    fs.renameSync(srcWebm, rawWebmPath);
    console.log(`[capture] Video saved: ${rawWebmPath}`);
  }

  // ── 10. Verify output ──────────────────────────────────────────────────────
  verifyOutput(rawWebmPath);
}

/**
 * Verify the output .webm file is non-empty and, if ffprobe is available,
 * has a non-zero duration.
 *
 * @param {string} webmPath
 */
function verifyOutput(webmPath) {
  if (!fs.existsSync(webmPath)) {
    throw new Error(`Expected output file not found: ${webmPath}`);
  }

  const { size } = fs.statSync(webmPath);
  if (size === 0) {
    throw new Error(`Output file is empty (0 bytes): ${webmPath}`);
  }
  console.log(`[capture] Verified: ${webmPath} (${size} bytes)`);

  // Attempt duration check via ffprobe; skip gracefully if not installed.
  try {
    const result = execFileSync(
      'ffprobe',
      ['-v', 'error', '-show_entries', 'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1', webmPath],
      { encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] }
    ).trim();

    const duration = parseFloat(result);
    if (isNaN(duration) || duration <= 0) {
      throw new Error(`Video has zero or invalid duration (${result}): ${webmPath}`);
    }
    console.log(`[capture] Duration: ${duration.toFixed(2)}s ✓`);
  } catch (e) {
    if (e.code === 'ENOENT') {
      // ffprobe not installed — rely on size check above.
      console.log('[capture] ffprobe not available; duration check skipped (size check passed).');
      return;
    }
    // ffprobe ran but failed (corrupt video, non-zero exit, bad duration…)
    throw e;
  }
}

// ── Entry point ────────────────────────────────────────────────────────────────
run().then(() => {
  console.log('[capture] Scene complete. ✓');
  process.exit(0);
}).catch((err) => {
  process.stderr.write(`[capture] FAILED: ${err.message}\n${err.stack || ''}\n`);
  process.exit(1);
});
