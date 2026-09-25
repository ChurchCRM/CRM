#!/usr/bin/env node
'use strict';

/**
 * One-time conversion script: reads manifest.csv and converts it to
 * manifest.json format with screenshot metadata (titles, categories).
 * Merges CSV data with screenshot-metadata.json for display information.
 */

const fs = require('node:fs');
const path = require('node:path');

const ARTIFACTS_ROOT = path.join(__dirname, '..', 'playwright', 'artifacts');
const CSV_PATH = path.join(ARTIFACTS_ROOT, 'manifest.csv');
const JSON_PATH = path.join(ARTIFACTS_ROOT, 'manifest.json');
const SCREENSHOT_METADATA_PATH = path.join(__dirname, '..', 'playwright', 'screenshot-metadata.json');

// Simple CSV parser for RFC 4180 format
function parseCSV(csvString) {
  const lines = csvString.split('\n');
  const headers = [];
  const rows = [];

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i].trim();
    if (!line) continue;

    const row = [];
    let current = '';
    let inQuotes = false;

    for (let j = 0; j < line.length; j++) {
      const char = line[j];
      if (char === '"') {
        if (inQuotes && line[j + 1] === '"') {
          current += '"';
          j++;
        } else {
          inQuotes = !inQuotes;
        }
      } else if (char === ',' && !inQuotes) {
        row.push(current);
        current = '';
      } else {
        current += char;
      }
    }
    row.push(current);

    if (i === 0) {
      headers.push(...row);
    } else {
      const obj = {};
      headers.forEach((h, idx) => {
        obj[h] = row[idx] !== undefined ? row[idx] : '';
      });
      rows.push(obj);
    }
  }

  return rows;
}

function main() {
  if (!fs.existsSync(CSV_PATH)) {
    console.error(`CSV not found at ${CSV_PATH}`);
    process.exit(1);
  }

  const csvContent = fs.readFileSync(CSV_PATH, 'utf8');
  const rows = parseCSV(csvContent);

  if (rows.length === 0) {
    console.error('No data rows in CSV');
    process.exit(1);
  }

  // Load screenshot metadata
  let screenshotMetadata = {};
  if (fs.existsSync(SCREENSHOT_METADATA_PATH)) {
    screenshotMetadata = JSON.parse(fs.readFileSync(SCREENSHOT_METADATA_PATH, 'utf8'));
  }

  // Convert CSV rows to JSON format
  const artifacts = rows.map((row) => {
    const name = row.name;
    const metadata = screenshotMetadata[name] || {};

    return {
      name,
      device: row.device,
      type: row.type,
      relativePath: row.relative_path,
      exists: row.exists === 'yes',
      sizeBytes: parseInt(row.size_bytes, 10) || 0,
      purpose: row.purpose,
      width: row.width ? parseInt(row.width, 10) : null,
      height: row.height ? parseInt(row.height, 10) : null,
      product: row.product || 'ChurchCRM',
      locale: row.locale || 'en',
      seed: row.seed || null,
      commit: row.commit || null,
      timestamp: row.timestamp || null,
      title: metadata.title || name,
      category: metadata.category || null,
      dark: metadata.dark || null,
    };
  });

  // Write JSON manifest
  fs.writeFileSync(JSON_PATH, JSON.stringify(artifacts, null, 2));
  console.log(`Converted ${artifacts.length} artifact(s) to ${path.relative(process.cwd(), JSON_PATH)}`);

  // Remove CSV
  fs.unlinkSync(CSV_PATH);
  console.log(`Deleted ${path.relative(process.cwd(), CSV_PATH)}`);
}

main();
