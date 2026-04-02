#!/usr/bin/env node

/**
 * Locale Excluded Terms Revert
 *
 * Reverts incorrectly localized terms back to their English values.
 * Used when excluded terms were accidentally translated.
 *
 * Usage:
 *   node locale/scripts/locale-revert-excluded-terms.js --locale af
 *     Revert excluded terms in af locale
 *
 *   node locale/scripts/locale-revert-excluded-terms.js --all
 *     Revert excluded terms in all locales
 */

const fs = require('fs');
const path = require('path');
const config = require('./locale-config');

// Terms that should be reverted to English
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
 * Revert excluded terms in a batch file to their English values
 */
function revertTermsInFile(filePath) {
  const data = loadJSON(filePath);
  if (!data) return null;

  const reverted = [];
  let changed = false;

  // Revert each excluded term
  for (const term of EXCLUDED_TERMS) {
    if (term in data && data[term] !== '' && data[term] !== term) {
      // Term exists and is localized (not empty, not matching original)
      reverted.push({ term, was: data[term], now: term });
      data[term] = term;
      changed = true;
    }
  }

  if (!changed) {
    return { reverted: 0, filePath, termsReverted: [] };
  }

  saveJSON(filePath, data);
  return {
    reverted: reverted.length,
    filePath: path.relative(config.projectRoot, filePath),
    termsReverted: reverted,
  };
}

/**
 * Build locale map
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
ChurchCRM Locale Excluded Terms Revert

Reverts incorrectly localized terms back to their English values.

Usage:
  node locale/scripts/locale-revert-excluded-terms.js --locale <code>
    Revert excluded terms in one locale
    Example: --locale af

  node locale/scripts/locale-revert-excluded-terms.js --all
    Revert excluded terms in all locales

Excluded terms (reverted to English):
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

  console.log('⏮️  Locale Excluded Terms Revert\n');
  console.log(`Terms to revert: ${EXCLUDED_TERMS.join(', ')}\n`);

  if (opts.command === 'all') {
    // Process all locales
    let totalReverted = 0;
    for (const code of Object.keys(localeMap)) {
      const files = getBatchFiles(code);
      for (const file of files) {
        const result = revertTermsInFile(file);
        if (result && result.reverted > 0) {
          totalReverted += result.reverted;
          console.log(`  ${result.filePath}: reverted ${result.reverted} term(s)`);
          for (const item of result.termsReverted) {
            console.log(`    - "${item.term}" (was: "${item.was}")`);
          }
        }
      }
    }
    console.log(`\n✅ Done — ${totalReverted} term(s) reverted to English\n`);
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
    let totalReverted = 0;
    for (const file of files) {
      const result = revertTermsInFile(file);
      if (result && result.reverted > 0) {
        totalReverted += result.reverted;
        console.log(`  ${result.filePath}: reverted ${result.reverted} term(s)`);
        for (const item of result.termsReverted) {
          console.log(`    - "${item.term}" (was: "${item.was}")`);
        }
      }
    }
    console.log(`\n✅ Done — ${totalReverted} term(s) reverted to English\n`);
  } else {
    console.error('❌ Specify --locale <code> or --all');
    process.exit(1);
  }
}

main();
