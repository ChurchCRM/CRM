#!/usr/bin/env node
'use strict';

/**
 * Consolidates every per-capture metadata JSON sidecar under
 * playwright/artifacts/metadata/ into one JSON manifest at
 * playwright/artifacts/manifest.json — one object per capture, organized
 * by workflow name, with all devices grouped together.
 *
 * Reads the same metadata sidecars scripts/check-marketing-visuals.js
 * validates (see that file's listMetadataFiles for the shared layout:
 * playwright/artifacts/metadata/<device>/<name>.json), so the manifest
 * always reflects exactly what the pipeline actually produced — not a
 * separately maintained list.
 *
 * Titles, categories and dark-mode links come from each capture's sidecar,
 * which captureScreen() writes from the options in the spec, so the spec is the
 * single source of truth for display data.
 *
 * Committed to the repo and published to the website for dynamic screenshot
 * gallery loading.
 */

const fs = require('node:fs');
const path = require('node:path');

const ARTIFACTS_ROOT = path.join(__dirname, '..', 'playwright', 'artifacts');
const METADATA_ROOT = path.join(ARTIFACTS_ROOT, 'metadata');
const MANIFEST_PATH = path.join(ARTIFACTS_ROOT, 'manifest.json');
const MANIFEST_CSV_PATH = path.join(ARTIFACTS_ROOT, 'manifest.csv');
const VIDEO_DEVICES = new Set(['recordings', 'setup']);

/**
 * CRM #10048 — metadata sidecars now live two directory levels deep for
 * screenshot captures (metadata/<locale>/<device>/<name>.json) instead of
 * one (metadata/<device>/<name>.json), because capture.ts nests artifacts
 * under locale to avoid every locale overwriting the same {device}/{name}
 * path. Video-only project dirs (setup/recordings) are unaffected — they
 * never got a locale segment (see capture.ts) — so they're still read the
 * old, flat way here.
 */
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
      // entry is a video-only device dir (setup/recordings) — flat, as before.
      for (const file of fs.readdirSync(entryPath)) {
        if (file.endsWith('.json')) {
          out.push({ locale: 'en', device: entry, file: path.join(entryPath, file) });
        }
      }
      continue;
    }

    // entry is a locale dir — descend one more level into each device.
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

function buildArtifactEntry(locale, device, meta) {
  const isVideo = VIDEO_DEVICES.has(device);
  const type = isVideo ? 'video' : 'screenshot';
  const filename = isVideo ? meta.video : meta.artifact;
  const subdir = isVideo ? 'videos' : 'screenshots';
  // Video paths stay flat (videos/<device>/<name>.webm) to match
  // finalize-marketing-videos.js; screenshot paths gain the locale
  // segment capture.ts now writes to (screenshots/<locale>/<device>/<name>.png).
  const relativePath = filename
    ? isVideo
      ? path.posix.join(subdir, device, filename)
      : path.posix.join(subdir, locale, device, filename)
    : null;
  const absolutePath = relativePath ? path.join(ARTIFACTS_ROOT, relativePath) : null;
  const exists = absolutePath ? fs.existsSync(absolutePath) : false;
  const size = exists ? fs.statSync(absolutePath).size : 0;

  return {
    name: meta.workflow,
    device,
    type,
    relativePath: relativePath ?? '',
    exists,
    sizeBytes: size,
    purpose: meta.purpose,
    width: meta.viewport?.width ?? null,
    height: meta.viewport?.height ?? null,
    product: meta.product ?? 'ChurchCRM',
    locale: meta.locale ?? locale,
    seed: meta.seed ?? null,
    commit: meta.commit ?? null,
    timestamp: meta.timestamp ?? null,
  };
}

function main() {
  const metadataFiles = listMetadataFiles(METADATA_ROOT);
  if (metadataFiles.length === 0) {
    console.error(`No metadata found under ${METADATA_ROOT} — did the marketing pipeline actually run?`);
    process.exit(1);
  }

  // Sort for stable, diffable manifest across runs — by capture name first
  // (groups a capture's desktop/tablet/mobile together), then device.
  metadataFiles.sort((a, b) => a.file.localeCompare(b.file));

  // Group artifacts by workflow name
  const workflowMap = new Map();
  for (const { locale, device, file } of metadataFiles) {
    const meta = JSON.parse(fs.readFileSync(file, 'utf8'));
    const workflow = meta.workflow;
    if (!workflowMap.has(workflow)) {
      workflowMap.set(workflow, []);
    }
    workflowMap.get(workflow).push({ locale, device, meta });
  }

  // Build manifest array with merged data
  const artifacts = [];
  for (const [workflow, entries] of workflowMap) {
    for (const { locale, device, meta } of entries) {
      artifacts.push({
        ...buildArtifactEntry(locale, device, meta),
        title: meta.title || workflow,
        category: meta.category || null,
        dark: meta.dark || null,
      });
    }
  }

  // Write JSON manifest
  fs.writeFileSync(MANIFEST_PATH, JSON.stringify(artifacts, null, 2));
  console.log(`Wrote ${artifacts.length} artifact(s) to ${path.relative(process.cwd(), MANIFEST_PATH)}`);

  // Clean up old CSV file if it exists
  if (fs.existsSync(MANIFEST_CSV_PATH)) {
    fs.unlinkSync(MANIFEST_CSV_PATH);
    console.log(`Deleted old CSV manifest at ${path.relative(process.cwd(), MANIFEST_CSV_PATH)}`);
  }
}

main();
