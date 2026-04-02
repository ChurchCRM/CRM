#!/usr/bin/env node

/**
 * Locale Term Exclusion Filter
 *
 * Removes terms from batch files that should NOT be localized:
 * - Brand/product names (ChurchCRM, Vonage, etc.)
 * - Universal abbreviations (N/A, API, JSON, etc.)
 * - Email examples (name@example.com)
 * - Technical terms (SMS, SMTP, etc.)
 *
 * Usage:
 *   node locale/scripts/locale-exclude-terms.js --locale af
 *     Remove excluded terms from af locale
 *
 *   node locale/scripts/locale-exclude-terms.js --all
 *     Remove excluded terms from all locales
 */

const fs = require('fs');
const path = require('path');
const config = require('./locale-config');

// Terms that should NEVER be localized
const EXCLUDED_TERMS = [
  'N/A',
  'name@example.com',
  'ChurchCRM',
  'Vonage',
  'SMS',
  'SMTP',
  'API',
  'JSON',
  'CSV',
  'URL',
  'OAuth',
  'HTTP',
  'HTTPS',
  'UUID',
  'RFC',
  'ISO',
  'UTC',
  'HTML',
  'XML',
  'CSS',
  'JavaScript',
  'PHP',
  'SQL',
  'GitHub',
  'Mailchimp',
  'Google Meet',
  'Slack',
  'POEditor',
];

function sanitize(str) {
  return String(str).replace(/[\r\n]/g, ' ');
}

function loadJSON(filePath) {
  if (!fs.existsSync(filePath)) return null;
  try {
    return JSON.parse(fs.readFileSync(filePath, 'utf8'));
  } catch (err) {
    console.error(`Error reading ${filePath}: ${err.message}`);
    return null;
  }
}

function saveJSON(filePath, data) {
  fs.writeFileSync(filePath, JSON.stringify(data, null, 2), 'utf8');
}

/**
 * Remove excluded terms from a batch file
 * Returns { removed: count, filePath, termsRemoved: [...] }
 */
function excludeTermsFromFile(filePath) {
  const data = loadJSON(filePath);
  if (!data) return null;

  const removed = [];
  const original = { ...data };

  // Remove each excluded term
  for (const term of EXCLUDED_TERMS) {
    if (term in original) {
      delete data[term];
      removed.push(term);
    }
  }

  if (removed.length === 0) {
    return { removed: 0, filePath, termsRemoved: [] };
  }

  // Only save if changes were made
  saveJSON(filePath, data);
  return {
    removed: removed.length,
    filePath: path.relative(config.projectRoot, filePath),
    termsRemoved: removed,
  };
}

/**
 * Build locale map for filtering
 */
function buildLocaleMap() {
  const localesConfig = loadJSON(config.localesJson);
  if (!localesConfig) throw new Error('Cannot load locales.json');
  const map = {};
  for (const [name, entry] of Object.entries(localesConfig)) {
    if (entry.skip_audit) continue;
    map[entry.poEditor] = name;
  }
  return map;
}

/**
 * Get batch files for a locale
 */
function getBatchFiles(poEditorCode) {
  const localeDir = path.join(config.terms.missing, poEditorCode);
  if (!fs.existsSync(localeDir)) return [];
  return fs.readdirSync(localeDir)
    .filter(f => f.endsWith('.json'))
    .sort()
    .map(f => path.join(localeDir, f));
}

// ── CLI ──────────────────────────────────────────────────────────────────────

function parseArgs() {
  const args = process.argv.slice(2);
  const opts = { command: null, locale: null };

  for (let i = 0; i < args.length; i++) {
    switch (args[i]) {
      case '--locale':
        opts.locale = args[++i];
        break;
      case '--all':
        opts.command = 'all';
        break;
      case '--help':
      case '-h':
        console.log(`
ChurchCRM Locale Term Exclusion Filter

Usage:
  node locale/scripts/locale-exclude-terms.js --locale <code>
    Remove excluded terms from one locale
    Example: --locale af

  node locale/scripts/locale-exclude-terms.js --all
    Remove excluded terms from all locales

Excluded terms (never localized):
  ${EXCLUDED_TERMS.join(', ')}
`);
        process.exit(0);
    }
  }
  return opts;
}

function main() {
  const opts = parseArgs();
  const localeMap = buildLocaleMap();

  console.log('🔍 Locale Term Exclusion Filter\n');
  console.log(`Excluded terms: ${EXCLUDED_TERMS.join(', ')}\n`);

  if (opts.command === 'all') {
    // Process all locales
    let totalRemoved = 0;
    for (const code of Object.keys(localeMap)) {
      const files = getBatchFiles(code);
      for (const file of files) {
        const result = excludeTermsFromFile(file);
        if (result && result.removed > 0) {
          totalRemoved += result.removed;
          console.log(
            `  ${result.filePath}: removed ${result.removed} term(s)`
          );
        }
      }
    }
    console.log(`\n✅ Done — ${totalRemoved} term(s) excluded from all locales\n`);
  } else if (opts.locale) {
    // Process one locale
    if (!localeMap[opts.locale]) {
      console.error(`❌ Unknown locale: ${opts.locale}`);
      process.exit(1);
    }
    const files = getBatchFiles(opts.locale);
    if (files.length === 0) {
      console.log(`  No batch files found for ${opts.locale}\n`);
      return;
    }
    let totalRemoved = 0;
    for (const file of files) {
      const result = excludeTermsFromFile(file);
      if (result && result.removed > 0) {
        totalRemoved += result.removed;
        console.log(
          `  ${result.filePath}: removed ${result.removed} term(s)`
        );
      }
    }
    console.log(`\n✅ Done — ${totalRemoved} term(s) excluded from ${opts.locale}\n`);
  } else {
    console.error('❌ Specify --locale <code> or --all');
    process.exit(1);
  }
}

main();
