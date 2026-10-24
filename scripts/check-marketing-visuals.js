#!/usr/bin/env node
'use strict';

/**
 * Verifies every marketing capture that playwright/support/capture.ts said
 * it wrote actually landed on disk and isn't a truncated/empty file. Reads
 * the metadata sidecars under playwright/artifacts/metadata/ instead of a
 * hardcoded list, so it stays correct as workflows/videos are added or
 * renamed — it checks "did the pipeline deliver what it itself claimed to
 * produce," not a separately-maintained expected-file list.
 *
 * Screenshot devices (desktop/tablet/mobile) are checked against
 * artifacts/screenshots/<device>/<name>.png. Video-only projects
 * (recordings/setup) are checked against artifacts/videos/<device>/<name>.webm.
 * capture.ts's metadata always includes a `video` filename even for
 * screenshot-only devices (no video is actually recorded there — see
 * playwright.config.ts's global `video: 'off'`), so video devices are
 * checked, not screenshot devices' video field.
 *
 * Exits non-zero (and lists every problem) if anything is missing or
 * suspiciously small, has the wrong viewport dimensions, or lacks contrast
 * across the image tiles, so this can gate CI like a failed Playwright assertion.
 */

const fs = require('node:fs');
const path = require('node:path');
const { PNG } = require('pngjs');

const ARTIFACTS_ROOT = path.resolve(process.argv[2] || path.join(__dirname, '..', 'playwright', 'artifacts'));
const METADATA_ROOT = path.join(ARTIFACTS_ROOT, 'metadata');
const VIDEO_DEVICES = new Set(['recordings', 'setup']);

// Real captures at these viewports run several KB/hundred KB; a
// zero-byte or few-hundred-byte file means Playwright wrote a blank or
// truncated screenshot, not a rendered page.
const MIN_SCREENSHOT_BYTES = 5 * 1024;
const MIN_VIDEO_BYTES = 20 * 1024;

// See the matching comment in generate-marketing-manifest.js — same
// CRM #10048 two-level nesting (metadata/<locale>/<device>/*.json for
// screenshots), same flat exception for video-only project dirs.
function listMetadataFiles(dir) {
  if (!fs.existsSync(dir)) {
    return [];
  }
  const out = [];
  for (const entry of fs.readdirSync(dir)) {
    const entryPath = path.join(dir, entry);
    if (!fs.statSync(entryPath).isDirectory()) {
      continue;
    }

    if (VIDEO_DEVICES.has(entry)) {
      for (const file of fs.readdirSync(entryPath)) {
        if (file.endsWith('.json')) {
          out.push({ locale: 'en', device: entry, file: path.join(entryPath, file) });
        }
      }
      continue;
    }

    const locale = entry;
    for (const device of fs.readdirSync(entryPath)) {
      const deviceDir = path.join(entryPath, device);
      if (!fs.statSync(deviceDir).isDirectory()) {
        continue;
      }
      for (const file of fs.readdirSync(deviceDir)) {
        if (file.endsWith('.json')) {
          out.push({ locale, device, file: path.join(deviceDir, file) });
        }
      }
    }
  }
  return out;
}

function checkFile(problems, label, filePath, minBytes) {
  if (!fs.existsSync(filePath)) {
    problems.push(`MISSING ${label}: ${filePath}`);
    return;
  }
  const { size } = fs.statSync(filePath);
  if (size < minBytes) {
    problems.push(`TOO SMALL ${label} (${size} bytes, expected >= ${minBytes}): ${filePath}`);
  }
}

function checkScreenshot(problems, label, filePath, viewport) {
  if (!fs.existsSync(filePath)) {
    return; // checkFile reports the missing file.
  }
  try {
    const { width, height, data } = PNG.sync.read(fs.readFileSync(filePath));
    // capture.ts writes viewport frames at deviceScaleFactor: 2, not full-page images.
    if (!viewport || !Number.isInteger(viewport.width) || !Number.isInteger(viewport.height) ||
        viewport.width <= 0 || viewport.height <= 0) {
      problems.push(`NO VIEWPORT ${label}: cannot validate image dimensions`);
    } else if (width !== viewport.width * 2 || height !== viewport.height * 2) {
      problems.push(`WRONG DIMENSIONS ${label} (${width}x${height}, expected ${viewport.width * 2}x${viewport.height * 2}): ${filePath}`);
    }

    // Measure local contrast so a flat background cannot pass merely by occupying
    // many bytes. Sparse real UI still spans several tiles.
    let contentTiles = 0;
    for (let row = 0; row < 4; row++) {
      for (let column = 0; column < 4; column++) {
        const min = [255, 255, 255];
        const max = [0, 0, 0];
        for (let y = Math.floor(row * height / 4); y < Math.floor((row + 1) * height / 4); y += 4) {
          for (let x = Math.floor(column * width / 4); x < Math.floor((column + 1) * width / 4); x += 4) {
            const offset = (y * width + x) * 4;
            for (let channel = 0; channel < 3; channel++) {
              const value = Math.round(data[offset + channel] * data[offset + 3] / 255 + 255 - data[offset + 3]);
              min[channel] = Math.min(min[channel], value);
              max[channel] = Math.max(max[channel], value);
            }
          }
        }
        if (max.some((value, channel) => value - min[channel] >= 16)) {
          contentTiles++;
        }
      }
    }
    if (contentTiles < 2) {
      problems.push(`BLANK ${label} (${contentTiles}/16 tiles contain contrast, expected >= 2): ${filePath}`);
    }
  } catch (error) {
    problems.push(`INVALID PNG ${label}: ${filePath} (${error.message})`);
  }
}

function main() {
  const metadataFiles = listMetadataFiles(METADATA_ROOT);
  if (metadataFiles.length === 0) {
    console.error(`No metadata found under ${METADATA_ROOT} — did the marketing pipeline actually run?`);
    process.exit(1);
  }

  const problems = [];
  let checked = 0;

  for (const { locale, device, file } of metadataFiles) {
    const meta = JSON.parse(fs.readFileSync(file, 'utf8'));

    for (const field of ['title', 'category']) {
      if (!meta[field]) {
        problems.push(`${meta.workflow} (${locale}/${device}): sidecar has no ${field} — add it to the captureScreen() options`);
      }
    }

    if (VIDEO_DEVICES.has(device)) {
      if (meta.video) {
        checkFile(problems, `video (${meta.workflow})`, path.join(ARTIFACTS_ROOT, 'videos', device, meta.video), MIN_VIDEO_BYTES);
        checked++;
      }
    } else if (meta.artifact) {
      checkFile(
        problems,
        `screenshot (${meta.workflow}, ${locale})`,
        path.join(ARTIFACTS_ROOT, 'screenshots', locale, device, meta.artifact),
        MIN_SCREENSHOT_BYTES
      );
      checkScreenshot(
        problems,
        `screenshot (${meta.workflow}, ${locale}/${device})`,
        path.join(ARTIFACTS_ROOT, 'screenshots', locale, device, meta.artifact),
        meta.viewport
      );
      checked++;
    }
  }

  if (problems.length > 0) {
    console.error(`Marketing visuals check failed — ${problems.length} problem(s) across ${checked} artifact(s):\n`);
    for (const problem of problems) {
      console.error(`  - ${problem}`);
    }
    process.exit(1);
  }

  console.log(`Marketing visuals check passed — verified ${checked} artifact(s) across ${metadataFiles.length} capture(s).`);
}

main();
