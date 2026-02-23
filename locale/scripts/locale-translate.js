#!/usr/bin/env node

/**
 * ChurchCRM Locale Translation Helper
 *
 * File-operations utility used by the /locale-translate Claude Code skill.
 * This script handles reading and writing batch files; the actual translations
 * are produced by Claude Code (invoke with /locale-translate in the Claude CLI).
 *
 * Standalone usage:
 *   node locale/scripts/locale-translate.js --list
 *     → List all locales that have missing terms and exit
 *
 *   node locale/scripts/locale-translate.js --info --locale <code>
 *     → Print locale metadata (language name, country, batch files) for Claude Code
 *
 *   node locale/scripts/locale-translate.js --apply --locale <code> --file <batchFile> --translations <jsonString>
 *     → Merge a JSON translations string into the specified batch file
 *
 * The /locale-translate Claude Code skill drives this helper automatically.
 * See .claude/commands/locale-translate.md for the full workflow.
 */

const fs = require('fs');
const path = require('path');
const config = require('./locale-config');

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
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

function buildLocaleMap() {
    const localesConfig = loadJSON(config.localesJson);
    if (!localesConfig) throw new Error('Cannot load locales.json');
    const map = {};
    for (const [name, entry] of Object.entries(localesConfig)) {
        if (entry.skip_audit) continue;
        map[entry.poEditor] = { name, locale: entry.locale, countryCode: entry.countryCode };
    }
    return map;
}

function hasUntranslatedValues(terms) {
    return Object.values(terms).some(v =>
        v === '' ||
        (v && typeof v === 'object' && Object.values(v).some(s => s === ''))
    );
}

function getBatchFiles(poEditorCode) {
    const localeDir = path.join(config.terms.missing, poEditorCode);
    if (!fs.existsSync(localeDir)) return [];
    return fs.readdirSync(localeDir)
        .filter(f => f.endsWith('.json'))
        .sort()
        .map(f => path.join(localeDir, f))
        .filter(fp => {
            const data = loadJSON(fp);
            return data && hasUntranslatedValues(data);
        });
}

function countUntranslated(terms) {
    return Object.values(terms).filter(v =>
        v === '' ||
        (v && typeof v === 'object' && Object.values(v).some(s => s === ''))
    ).length;
}

// ---------------------------------------------------------------------------
// Commands
// ---------------------------------------------------------------------------
function cmdList(localeMap) {
    const missingDir = config.terms.missing;
    if (!fs.existsSync(missingDir)) {
        console.log('No missing terms directory found. Run: npm run locale:missing');
        return;
    }

    const results = [];
    for (const [code, entry] of Object.entries(localeMap)) {
        const files = getBatchFiles(code);
        if (files.length === 0) continue;
        const total = files.reduce((n, fp) => {
            const data = loadJSON(fp);
            return n + (data ? countUntranslated(data) : 0);
        }, 0);
        if (total > 0) results.push({ code, name: entry.name, total, files: files.length });
    }

    if (results.length === 0) {
        console.log('✅ All locales are fully translated.');
        return;
    }

    console.log('\n📋 Locales with untranslated terms:\n');
    console.log('  Code     Language                         Terms   Files');
    console.log('  -------- -------------------------------- ------- -----');
    for (const r of results) {
        console.log(`  ${r.code.padEnd(8)} ${r.name.padEnd(32)} ${String(r.total).padEnd(7)} ${r.files}`);
    }
    console.log(`\n  Total: ${results.length} locales, ${results.reduce((n, r) => n + r.total, 0)} terms\n`);
    console.log('  To translate, run in Claude Code: /locale-translate --locale <code>');
    console.log('  Or translate all at once:         /locale-translate --all\n');
}

function cmdInfo(localeMap, poEditorCode) {
    const entry = localeMap[poEditorCode];
    if (!entry) {
        console.error(`Unknown locale code: ${poEditorCode}`);
        process.exit(1);
    }
    const files = getBatchFiles(poEditorCode);
    // Returns metadata + file paths only — NO term content.
    // Use --read-file to load one batch file at a time during translation.
    const info = {
        code: poEditorCode,
        name: entry.name,
        locale: entry.locale,
        countryCode: entry.countryCode,
        batchFiles: files.map(fp => ({
            path: path.relative(config.projectRoot, fp),
            termCount: countUntranslated(loadJSON(fp) || {}),
        })),
    };
    console.log(JSON.stringify(info, null, 2));
}

function cmdReadFile(filePath) {
    const absPath = path.isAbsolute(filePath)
        ? filePath
        : path.join(config.projectRoot, filePath);

    if (!fs.existsSync(absPath)) {
        console.error(`Batch file not found: ${absPath}`);
        process.exit(1);
    }

    const terms = loadJSON(absPath);
    if (!terms) { console.error('Failed to read file'); process.exit(1); }

    // Return only untranslated entries to minimise token usage
    const untranslated = {};
    for (const [key, value] of Object.entries(terms)) {
        if (value === '' || value === null) {
            untranslated[key] = value;
        } else if (value && typeof value === 'object' && Object.values(value).some(s => s === '')) {
            untranslated[key] = value;
        }
    }
    console.log(JSON.stringify(untranslated, null, 2));
}

function cmdApply(batchFilePath, translationsJson) {
    const absPath = path.isAbsolute(batchFilePath)
        ? batchFilePath
        : path.join(config.projectRoot, batchFilePath);

    if (!fs.existsSync(absPath)) {
        console.error(`Batch file not found: ${absPath}`);
        process.exit(1);
    }

    let incoming;
    try {
        incoming = JSON.parse(translationsJson);
    } catch (err) {
        console.error(`Invalid translations JSON: ${err.message}`);
        process.exit(1);
    }

    const existing = loadJSON(absPath) || {};
    const updated = { ...existing, ...incoming };
    saveJSON(absPath, updated);

    const count = Object.keys(incoming).length;
    console.log(`✅ Applied ${count} translations to ${path.relative(config.projectRoot, absPath)}`);
}

// ---------------------------------------------------------------------------
// CLI
// ---------------------------------------------------------------------------
function parseArgs() {
    const args = process.argv.slice(2);
    const opts = { command: null, locale: null, file: null, translations: null };

    for (let i = 0; i < args.length; i++) {
        switch (args[i]) {
            case '--list':         opts.command = 'list';      break;
            case '--info':         opts.command = 'info';      break;
            case '--read-file':    opts.command = 'read-file'; break;
            case '--apply':        opts.command = 'apply';     break;
            case '--locale':       opts.locale       = args[++i]; break;
            case '--file':         opts.file         = args[++i]; break;
            case '--translations': opts.translations = args[++i]; break;
            case '--help': case '-h':
                console.log(`
ChurchCRM Locale Translation Helper

Usage:
  node locale/scripts/locale-translate.js --list
  node locale/scripts/locale-translate.js --info --locale <code>
  node locale/scripts/locale-translate.js --read-file --file <path>
  node locale/scripts/locale-translate.js --apply --file <path> --translations '<json>'

This script is driven by the /locale-translate Claude Code skill.
Run /locale-translate in the Claude Code CLI to translate missing terms.
`);
                process.exit(0);
        }
    }
    return opts;
}

function main() {
    const opts = parseArgs();
    const localeMap = buildLocaleMap();

    switch (opts.command) {
        case 'list':
            cmdList(localeMap);
            break;
        case 'info':
            if (!opts.locale) { console.error('--locale required'); process.exit(1); }
            cmdInfo(localeMap, opts.locale);
            break;
        case 'apply':
            if (!opts.file || !opts.translations) {
                console.error('--file and --translations are required');
                process.exit(1);
            }
            cmdApply(opts.file, opts.translations);
            break;
        case 'read-file':
            if (!opts.file) { console.error('--file required'); process.exit(1); }
            cmdReadFile(opts.file);
            break;
        default:
            console.error('Specify --list, --info, --read-file, or --apply. Run --help for usage.');
            process.exit(1);
    }
}

main();
