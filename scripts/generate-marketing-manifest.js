#!/usr/bin/env node
'use strict';

/**
 * Consolidates every per-capture metadata JSON sidecar under
 * playwright/artifacts/metadata/ into one CSV at
 * playwright/artifacts/manifest.csv — one row per device per capture, so
 * reviewing/looking up "what's the file for X on tablet" or scanning the
 * whole marketing set for gaps doesn't mean opening 90+ individual JSON
 * files one at a time.
 *
 * Reads the same metadata sidecars scripts/check-marketing-visuals.js
 * validates (see that file's listMetadataFiles for the shared layout:
 * playwright/artifacts/metadata/<device>/<name>.json), so the manifest
 * always reflects exactly what the pipeline actually produced — not a
 * separately maintained list.
 *
 * Not committed to the repo (see .gitignore's playwright/artifacts/**\/*.json
 * — this is derived, regenerable data, same as the sidecars it summarizes)
 * but included in the full playwright/artifacts/ upload the
 * marketing-visuals-check workflow keeps for 14 days, for anyone doing a
 * marketing/asset-lookup pass on a given run without checking that run's
 * branch out locally.
 */

const fs = require('node:fs');
const path = require('node:path');

const ARTIFACTS_ROOT = path.join(__dirname, '..', 'playwright', 'artifacts');
const METADATA_ROOT = path.join(ARTIFACTS_ROOT, 'metadata');
const MANIFEST_PATH = path.join(ARTIFACTS_ROOT, 'manifest.csv');
const VIDEO_DEVICES = new Set(['recordings', 'setup']);

const COLUMNS = [
  'name',
  'device',
  'type',
  'relative_path',
  'exists',
  'size_bytes',
  'purpose',
  'width',
  'height',
  'product',
  'locale',
  'seed',
  'commit',
  'timestamp',
];

function listMetadataFiles(dir) {
  if (!fs.existsSync(dir)) {
    return [];
  }
  const out = [];
  for (const device of fs.readdirSync(dir)) {
    const deviceDir = path.join(dir, device);
    if (!fs.statSync(deviceDir).isDirectory()) {
      continue;
    }
    for (const file of fs.readdirSync(deviceDir)) {
      if (file.endsWith('.json')) {
        out.push({ device, file: path.join(deviceDir, file) });
      }
    }
  }
  return out;
}

// RFC 4180: wrap in quotes and double up any embedded quotes. Purpose
// strings routinely contain commas ("member photos, geocoded map"), so
// every field is quoted rather than only-when-needed — simpler and no
// less correct.
function csvField(value) {
  const s = value === null || value === undefined ? '' : String(value);
  return `"${s.replace(/"/g, '""')}"`;
}

function csvRow(values) {
  return values.map(csvField).join(',') + '\n';
}

function buildRow(device, meta) {
  const isVideo = VIDEO_DEVICES.has(device);
  const type = isVideo ? 'video' : 'screenshot';
  const filename = isVideo ? meta.video : meta.artifact;
  const subdir = isVideo ? 'videos' : 'screenshots';
  const relativePath = filename ? path.posix.join(subdir, device, filename) : null;
  const absolutePath = relativePath ? path.join(ARTIFACTS_ROOT, relativePath) : null;
  const exists = absolutePath ? fs.existsSync(absolutePath) : false;
  const size = exists ? fs.statSync(absolutePath).size : '';

  return [
    meta.workflow,
    device,
    type,
    relativePath ?? '',
    exists ? 'yes' : 'no',
    size,
    meta.purpose,
    meta.viewport?.width ?? '',
    meta.viewport?.height ?? '',
    meta.product,
    meta.locale,
    meta.seed,
    meta.commit,
    meta.timestamp,
  ];
}

function main() {
  const metadataFiles = listMetadataFiles(METADATA_ROOT);
  if (metadataFiles.length === 0) {
    console.error(`No metadata found under ${METADATA_ROOT} — did the marketing pipeline actually run?`);
    process.exit(1);
  }

  // Sort for a stable, diffable manifest across runs — by capture name
  // first (groups a capture's desktop/tablet/mobile rows together), then
  // device.
  metadataFiles.sort((a, b) => a.file.localeCompare(b.file));

  let csv = csvRow(COLUMNS);
  for (const { device, file } of metadataFiles) {
    const meta = JSON.parse(fs.readFileSync(file, 'utf8'));
    csv += csvRow(buildRow(device, meta));
  }

  fs.writeFileSync(MANIFEST_PATH, csv);
  console.log(`Wrote ${metadataFiles.length} row(s) to ${path.relative(process.cwd(), MANIFEST_PATH)}`);
}

main();
