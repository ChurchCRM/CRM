#!/usr/bin/env node

/**
 * ChurchCRM Locale Translation Helper
 *
 * Deterministic file operations for the /locale-translate Claude Code skill.
 * Only the translations themselves come from a model; everything else here
 * costs no tokens.
 *
 *   --list                                  locales that still have untranslated terms
 *   --prefill [--locale a,b] [--dry-run]    fill terms from the english-ok allowlist and from
 *                                           existing translations that differ only in case or
 *                                           trailing punctuation
 *   --export [--locale a,b]                 one deduplicated payload of what is still missing
 *   --read-file --file <batch>              untranslated entries of one batch file
 *   --apply --file <batch> --translations '<json>'
 *   --apply-bulk --translations-file <path> {"fr": {"term": "..."}, "de": {...}}
 *
 * Apply validates every entry (placeholders, script, plural shape, known key), writes the
 * valid ones, lists the rejected ones with the reason, and records identical-to-English
 * values in locale/terms/english-ok.json. An empty value leaves the term untranslated.
 *
 * See .claude/commands/locale-translate.md for the workflow.
 */

const fs = require('fs');
const path = require('path');
const config = require('./locale-config');
const { validateTranslation, prefill } = require('./lib/translation-checks');

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
        console.log('No missing terms directory found. Run: npm run locale:download (the downloader now generates missing-term batches)');
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

function englishOkFor(code) {
    const ok = loadJSON(config.terms.englishOk) || {};
    const terms = new Set();
    for (const [locale, list] of Object.entries(ok)) {
        if (locale.toLowerCase() === code.toLowerCase() && Array.isArray(list)) list.forEach(t => terms.add(t));
    }
    return terms;
}

function recordEnglishOk(code, terms) {
    if (terms.length === 0) return;
    const ok = loadJSON(config.terms.englishOk) || {};
    const existing = Object.keys(ok).find(k => k.toLowerCase() === code.toLowerCase()) ?? code.toLowerCase();
    const list = ok[existing] ?? [];
    const added = terms.filter(t => !list.includes(t));
    if (added.length === 0) return;
    ok[existing] = [...list, ...added];
    fs.writeFileSync(config.terms.englishOk, `${JSON.stringify(ok, null, 2)}\n`, 'utf8');
}

function pluralShapeProblems(existing, value) {
    if (existing && typeof existing === 'object') {
        if (!value || typeof value !== 'object') return ['this term has plural forms; send an object with every form'];
        const want = Object.keys(existing).sort().join();
        return Object.keys(value).sort().join() === want ? [] : [`plural forms must be exactly: ${want}`];
    }
    return value && typeof value === 'object' ? ['this term has no plural forms; send a string'] : [];
}

function scriptsFor(code) {
    const locales = loadJSON(config.localesJson) || {};
    return Object.values(locales).find(entry => String(entry.poEditor).toLowerCase() === code.toLowerCase())?.scripts;
}

function applyToBatch(code, absPath, incoming, { write = true } = {}) {
    const batch = loadJSON(absPath) || {};
    const scripts = scriptsFor(code);
    const result = { applied: [], blank: [], rejected: [], identical: [] };
    for (const [key, value] of Object.entries(incoming)) {
        if (!(key in batch)) {
            result.rejected.push({ key, problems: ['not in this batch file'] });
        } else if (value === '') {
            result.blank.push(key);
        } else {
            const problems = [...pluralShapeProblems(batch[key], value), ...validateTranslation(scripts, key, value)];
            if (problems.length > 0) {
                result.rejected.push({ key, problems });
            } else {
                batch[key] = value;
                result.applied.push(key);
                if (value === key) result.identical.push(key);
            }
        }
    }
    if (write && result.applied.length > 0) {
        saveJSON(absPath, batch);
        recordEnglishOk(code, result.identical);
    }
    return result;
}

function report(label, result) {
    const tail = [];
    if (result.identical.length) tail.push(`${result.identical.length} identical to English, allowlisted`);
    if (result.blank.length) tail.push(`${result.blank.length} left blank`);
    console.log(`✅ Applied ${result.applied.length} translations to ${label}${tail.length ? ` (${tail.join(', ')})` : ''}`);
    for (const { key, problems } of result.rejected) {
        console.error(`❌ rejected "${key.slice(0, 70)}": ${problems.join('; ')}`);
        process.exitCode = 1;
    }
}

function exitWith(message) {
    console.error(`❌ ${message}`);
    process.exit(1);
}

function parseTranslations(json) {
    let parsed;
    try {
        parsed = JSON.parse(json);
    } catch (err) {
        exitWith(`Invalid translations JSON: ${err.message}`);
    }
    if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
        exitWith('Translations must be a JSON object of term: translation pairs');
    }
    return parsed;
}

function readTranslationsFile(filePath) {
    if (!filePath || filePath.startsWith('--')) exitWith('--translations-file needs a path');
    try {
        return fs.readFileSync(filePath, 'utf8');
    } catch (err) {
        exitWith(`Cannot read translations file: ${err.message}`);
    }
}

function resolveBatchPath(filePath) {
    const absPath = path.isAbsolute(filePath) ? filePath : path.join(config.projectRoot, filePath);
    if (!fs.existsSync(absPath)) {
        console.error(`Batch file not found: ${absPath}`);
        process.exit(1);
    }
    return absPath;
}

function cmdApply(batchFilePath, translationsJson) {
    const absPath = resolveBatchPath(batchFilePath);
    const code = path.basename(path.dirname(absPath));
    report(path.relative(config.projectRoot, absPath), applyToBatch(code, absPath, parseTranslations(translationsJson)));
}

function cmdApplyBulk(translationsJson) {
    for (const [code, incoming] of Object.entries(parseTranslations(translationsJson))) {
        if (incoming === null || typeof incoming !== 'object' || Array.isArray(incoming)) {
            exitWith(`${code}: expected an object of term: translation pairs`);
        }
        const files = getBatchFiles(code);
        if (files.length === 0) {
            console.error(`❌ ${code}: no batch files with untranslated terms`);
            process.exitCode = 1;
            continue;
        }
        const unplaced = new Set(Object.keys(incoming));
        for (const file of files) {
            const batch = loadJSON(file) || {};
            const subset = Object.fromEntries(Object.entries(incoming).filter(([key]) => key in batch));
            Object.keys(subset).forEach(key => unplaced.delete(key));
            if (Object.keys(subset).length > 0) report(path.relative(config.projectRoot, file), applyToBatch(code, file, subset));
        }
        for (const key of unplaced) {
            console.error(`❌ ${code}: rejected "${key.slice(0, 70)}": not in any batch file`);
            process.exitCode = 1;
        }
    }
}

function selectedCodes(localeMap, localeArg) {
    if (!localeArg) return Object.keys(localeMap);
    const wanted = localeArg.split(',').map(c => c.trim().toLowerCase());
    return Object.keys(localeMap).filter(code => wanted.includes(code.toLowerCase()));
}

function loadExistingTranslations(localeMap, code) {
    const candidates = [`${localeMap[code].locale}.json`, `${code}.json`];
    for (const name of candidates) {
        const data = loadJSON(path.join(config.i18nDir, name));
        if (data) return data;
    }
    return {};
}

function cmdPrefill(localeMap, localeArg, dryRun) {
    let total = 0;
    for (const code of selectedCodes(localeMap, localeArg)) {
        const translated = loadExistingTranslations(localeMap, code);
        const okSet = englishOkFor(code);
        for (const file of getBatchFiles(code)) {
            const { filled, fromMemory, fromAllowlist } = prefill(loadJSON(file) || {}, translated, okSet);
            const result = applyToBatch(code, file, filled, { write: !dryRun });
            total += result.applied.length;
            for (const { key, problems } of result.rejected) {
                console.error(`❌ ${code}: not filled "${key.slice(0, 60)}": ${problems.join('; ')}`);
                process.exitCode = 1;
            }
            if (result.applied.length === 0) continue;
            const applied = new Set(result.applied);
            const memoryFilled = fromMemory.filter(key => applied.has(key));
            console.log(`${dryRun ? '🔍' : '✅'} ${code}: ${fromAllowlist.filter(key => applied.has(key)).length} from english-ok, ${memoryFilled.length} from existing translations`);
            for (const key of memoryFilled) console.log(`     ${key.slice(0, 60)} → ${String(filled[key]).slice(0, 60)}`);
        }
    }
    console.log(`\n${dryRun ? 'Would fill' : 'Filled'} ${total} terms without a model. Review the existing-translation matches above.`);
}

function cmdExport(localeMap, localeArg) {
    const strings = {};
    const plurals = {};
    for (const code of selectedCodes(localeMap, localeArg)) {
        for (const file of getBatchFiles(code)) {
            for (const [key, value] of Object.entries(loadJSON(file) || {})) {
                if (value === '') {
                    (strings[key] ??= []).push(code);
                } else if (value && typeof value === 'object' && Object.values(value).some(v => v === '')) {
                    (plurals[code] ??= {})[key] = value;
                }
            }
        }
    }
    console.log(JSON.stringify({ strings, plurals }, null, 2));
}

// ---------------------------------------------------------------------------
// CLI
// ---------------------------------------------------------------------------
function parseArgs() {
    const args = process.argv.slice(2);
    const opts = { command: null, locale: null, file: null, translations: null, dryRun: false };

    for (let i = 0; i < args.length; i++) {
        switch (args[i]) {
            case '--list':         opts.command = 'list';      break;
            case '--read-file':    opts.command = 'read-file'; break;
            case '--apply':        opts.command = 'apply';     break;
            case '--apply-bulk':   opts.command = 'apply-bulk'; break;
            case '--prefill':      opts.command = 'prefill';   break;
            case '--export':       opts.command = 'export';    break;
            case '--dry-run':      opts.dryRun = true;         break;
            case '--translations-file':
                opts.translations = readTranslationsFile(args[++i]);
                break;
            case '--locale':       opts.locale       = args[++i]; break;
            case '--file':         opts.file         = args[++i]; break;
            case '--translations': opts.translations = args[++i]; break;
            case '--help': case '-h':
                console.log(`
ChurchCRM Locale Translation Helper

Usage:
  node locale/scripts/locale-translate.js --list
  node locale/scripts/locale-translate.js --prefill [--locale a,b] [--dry-run]
  node locale/scripts/locale-translate.js --export [--locale a,b]
  node locale/scripts/locale-translate.js --read-file --file <path>
  node locale/scripts/locale-translate.js --apply --file <path> --translations '<json>'
  node locale/scripts/locale-translate.js --apply-bulk (--translations '<json>' | --translations-file <path>)

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
        case 'prefill':
            cmdPrefill(localeMap, opts.locale, opts.dryRun);
            break;
        case 'export':
            cmdExport(localeMap, opts.locale);
            break;
        case 'apply-bulk':
            if (!opts.translations) { console.error('--translations or --translations-file required'); process.exit(1); }
            cmdApplyBulk(opts.translations);
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
            console.error('Specify --list, --prefill, --export, --read-file, --apply, or --apply-bulk. Run --help for usage.');
            process.exit(1);
    }
}

main();
