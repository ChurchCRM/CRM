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
 * suspiciously small, so this can gate CI the same way a failed Playwright
 * assertion would.
 */

const fs = require('node:fs');
const path = require('node:path');

const ARTIFACTS_ROOT = path.join(__dirname, '..', 'playwright', 'artifacts');
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
      checked++;
    }
  }

  if (problems.length > 0) {
    console.error(`Marketing visuals check failed — ${problems.length}/${checked} artifact(s) missing or too small:\n`);
    for (const problem of problems) {
      console.error(`  - ${problem}`);
    }
    process.exit(1);
  }

  console.log(`Marketing visuals check passed — verified ${checked} artifact(s) across ${metadataFiles.length} capture(s).`);
}

main();
