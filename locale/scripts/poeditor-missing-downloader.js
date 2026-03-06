#!/usr/bin/env node

/**
 * POEditor Missing Terms Downloader
 *
 * Downloads untranslated (missing) terms per locale directly from the POEditor API
 * using the `filters=untranslated` export filter.
 *
 * Output files are written to locale/terms/missing/{poEditorCode}/ as batched JSON
 * files (150 terms per file), ready for the /locale-translate workflow.
 *
 * Usage:
 *   node locale/scripts/poeditor-missing-downloader.js              # All locales
 *   node locale/scripts/poeditor-missing-downloader.js --locale fr  # French only
 *   npm run locale:download:missing                                  # All locales
 *   npm run locale:download:missing -- --locale fr                   # French only
 *
 * Requires:
 *   - POEDITOR_TOKEN environment variable (from .env or POEditor API Access)
 *
 * Note: POEditor project ID is hardcoded as 77079 (ChurchCRM official project)
 */

require('dotenv').config();

const fs = require('fs');
const path = require('path');
const https = require('https');
const { URLSearchParams } = require('url');
const config = require('./locale-config');

// Configuration
const LOCALES_FILE = config.localesJson;
const OUTPUT_DIR = config.terms.missingNew;
const POEDITOR_API = 'https://api.poeditor.com/v2/projects/export';
const PROJECT_ID = '77079'; // ChurchCRM POEditor project ID
const TERMS_PER_FILE = config.settings.missingTermsBatchSize;
const MIN_MISSING_TERMS = 10; // Skip locales with fewer missing terms than this

const apiToken = process.env.POEDITOR_TOKEN;

if (!apiToken) {
    console.error('❌ POEDITOR_TOKEN environment variable is required');
    console.error('   Get your token from: https://poeditor.com/account/api');
    process.exit(1);
}

// Load locales configuration
let localesConfig;
try {
    localesConfig = JSON.parse(fs.readFileSync(LOCALES_FILE, 'utf8'));
} catch (e) {
    console.error(`❌ Error reading locales.json: ${e.message}`);
    process.exit(1);
}

/**
 * Sleep helper for rate-limiting
 */
function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * Make a POST request to the POEditor API
 */
function makeRequest(url, postData) {
    return new Promise((resolve, reject) => {
        const options = {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Content-Length': Buffer.byteLength(postData),
            },
        };

        const req = https.request(url, options, (res) => {
            let data = '';
            res.on('data', (chunk) => {
                data += chunk;
            });
            res.on('end', () => {
                if (res.statusCode >= 200 && res.statusCode < 300) {
                    resolve({ status: res.statusCode, data });
                } else {
                    reject(new Error(`HTTP ${res.statusCode}: ${data}`));
                }
            });
        });

        req.on('error', reject);
        req.write(postData);
        req.end();
    });
}

/**
 * Fetch the untranslated terms for a locale from POEditor as a JSON object.
 * Returns null when the locale has no untranslated terms (empty export).
 */
async function fetchUntranslatedTerms(poEditorLocale) {
    const postData = new URLSearchParams({
        api_token: apiToken,
        id: PROJECT_ID,
        language: poEditorLocale,
        type: 'key_value_json',
        filters: 'untranslated', // POEditor filter: only export terms without a translation
    }).toString();

    const response = await makeRequest(POEDITOR_API, postData);
    const result = JSON.parse(response.data);

    if (result.response.status !== 'success') {
        throw new Error(result.response.message || 'Unknown POEditor error');
    }

    const downloadUrl = result.result.url;

    // Download the JSON file from the signed S3 URL
    return new Promise((resolve, reject) => {
        https
            .get(downloadUrl, (res) => {
                if (res.statusCode < 200 || res.statusCode >= 300) {
                    let body = '';
                    res.on('data', (chunk) => {
                        body += chunk;
                    });
                    res.on('end', () =>
                        reject(
                            new Error(
                                `Download failed HTTP ${res.statusCode}: ${body.substring(0, 200)}`,
                            ),
                        ),
                    );
                    return;
                }

                let raw = '';
                res.on('data', (chunk) => {
                    raw += chunk;
                });
                res.on('end', () => {
                    try {
                        const parsed = JSON.parse(raw);
                        resolve(parsed);
                    } catch (e) {
                        reject(new Error(`Failed to parse POEditor response: ${e.message}`));
                    }
                });
            })
            .on('error', reject);
    });
}

/**
 * Write missing terms to batched JSON files under OUTPUT_DIR/{poEditorCode}/.
 * Existing batch files for the locale are removed first (clean rebuild).
 * Returns an array of { number, filename, count } objects.
 */
function saveBatchedMissingTerms(poEditorCode, missingTerms) {
    const localeOutDir = path.join(OUTPUT_DIR, poEditorCode);

    // Ensure the output directory exists
    if (!fs.existsSync(localeOutDir)) {
        fs.mkdirSync(localeOutDir, { recursive: true });
    }

    // Remove any pre-existing batch files so we always have a clean rebuild
    const existingBatches = fs.readdirSync(localeOutDir).filter((f) => f.endsWith('.json'));
    existingBatches.forEach((f) => {
        try {
            fs.unlinkSync(path.join(localeOutDir, f));
        } catch (_) {
            /* ignore */
        }
    });

    const entries = Object.entries(missingTerms);
    const files = [];
    let batchNumber = 1;

    for (let i = 0; i < entries.length; i += TERMS_PER_FILE) {
        const batch = Object.fromEntries(entries.slice(i, i + TERMS_PER_FILE));
        const filename = path.join(localeOutDir, `${poEditorCode}-${batchNumber}.json`);
        fs.writeFileSync(filename, JSON.stringify(batch, null, 2) + '\n');
        files.push({ number: batchNumber, filename, count: Object.keys(batch).length });
        batchNumber++;
    }

    return files;
}

/**
 * Parse command-line arguments.
 * Supports: --locale <code>   (or positional: node script.js fr)
 */
function parseArguments() {
    const args = process.argv.slice(2);
    let targetLocale = null;

    for (let i = 0; i < args.length; i++) {
        if (args[i] === '--locale' && args[i + 1]) {
            targetLocale = args[i + 1].toLowerCase();
            break;
        } else if (!args[i].startsWith('-') && !targetLocale) {
            targetLocale = args[i].toLowerCase();
            break;
        }
    }

    return targetLocale;
}

/**
 * Resolve a locale key from the argument provided on the command line.
 * Supports full locale codes (e.g. "fi_FI"), language-only codes (e.g. "fi"),
 * and POEditor codes (e.g. "fi").
 */
function resolveLocale(localeArg) {
    const lower = localeArg.toLowerCase();

    let match = Object.entries(localesConfig).find(
        ([, cfg]) => cfg.locale.toLowerCase() === lower || cfg.poEditor.toLowerCase() === lower,
    );

    if (!match) {
        match = Object.entries(localesConfig).find(
            ([, cfg]) =>
                cfg.locale.toLowerCase().startsWith(lower + '_') ||
                cfg.poEditor.toLowerCase().startsWith(lower + '_'),
        );
    }

    if (!match) {
        const available = Object.values(localesConfig)
            .filter((cfg) => !cfg.super_base)
            .map((cfg) => cfg.locale)
            .sort();

        console.error(`\n❌ Locale "${localeArg}" not found in configuration`);
        console.error(`\nAvailable locales:\n  ${available.join('\n  ')}`);
        console.error(`\nTip: Use a language code (e.g. "fi") or full locale (e.g. "fi_FI")`);
        process.exit(1);
    }

    return match; // [key, config]
}

/**
 * Download and save missing terms for a single locale entry.
 * Returns { poEditorCode, termCount, batchFiles } or null when nothing is missing.
 */
async function processLocale(localeName, localeConfig, current, total) {
    const poEditorCode = localeConfig.poEditor;
    const locale = localeConfig.locale;

    console.log(`  ⏳ [${current}/${total}] ${localeName} (${poEditorCode})…`);

    let terms;
    try {
        terms = await fetchUntranslatedTerms(poEditorCode);
    } catch (err) {
        throw new Error(`Failed to fetch untranslated terms for ${locale}: ${err.message}`);
    }

    const termCount = Object.keys(terms).length;

    if (termCount === 0) {
        console.log(`  ✅ ${localeName} (${poEditorCode}) — fully translated`);
        return { poEditorCode, locale: localeName, termCount: 0, batchFiles: [] };
    }

    if (termCount < MIN_MISSING_TERMS) {
        console.log(
            `  ⏭️  ${localeName} (${poEditorCode}) — only ${termCount} missing term(s), skipping (minimum ${MIN_MISSING_TERMS})`,
        );
        return { poEditorCode, locale: localeName, termCount, batchFiles: [], skipped: true };
    }

    const batchFiles = saveBatchedMissingTerms(poEditorCode, terms);
    console.log(
        `  📄 ${localeName} (${poEditorCode}) — ${termCount} missing term(s) → ${batchFiles.length} file(s)`,
    );
    batchFiles.forEach((f) => {
        console.log(
            `      ${path.relative(process.cwd(), f.filename)} (${f.count} terms)`,
        );
    });

    return { poEditorCode, locale: localeName, termCount, batchFiles };
}

/**
 * Main entry point
 */
async function main() {
    const targetLocaleArg = parseArguments();

    console.log('🌐 POEditor Missing Terms Downloader\n');
    console.log(`Project ID : ${PROJECT_ID}`);
    console.log(`Output dir : ${OUTPUT_DIR}\n`);

    // Ensure root output directory exists
    if (!fs.existsSync(OUTPUT_DIR)) {
        fs.mkdirSync(OUTPUT_DIR, { recursive: true });
    }

    // Build the list of locales to process
    let localesToProcess;
    if (targetLocaleArg) {
        const [key, cfg] = resolveLocale(targetLocaleArg);
        console.log(`🎯 Single-locale mode: ${cfg.locale} (${cfg.poEditor})\n`);
        localesToProcess = { [key]: cfg };
    } else {
        localesToProcess = localesConfig;
    }

    // Filter out the super-base (English source) locale — it is never a translation target
    const processableEntries = Object.entries(localesToProcess).filter(
        ([, cfg]) => cfg.super_base !== true && cfg.skip_audit !== true,
    );
    const total = processableEntries.length;

    console.log(`📋 Locales to check: ${total}\n`);

    const results = [];
    let current = 0;
    let failCount = 0;

    for (const [localeName, localeConfig] of processableEntries) {
        current++;
        try {
            const result = await processLocale(localeName, localeConfig, current, total);
            results.push(result);
        } catch (err) {
            console.error(`  ❌ ${localeName}: ${err.message}`);
            failCount++;
        }

        // Rate-limit: 1 second between locales (we make one API call + one download per locale)
        if (current < total) {
            await sleep(1000);
        }
    }

    // Summary
    const complete = results.filter((r) => r.termCount === 0).length;
    const withMissing = results.filter((r) => r.termCount >= MIN_MISSING_TERMS && !r.skipped).length;
    const skipped = results.filter((r) => r.skipped).length;
    const totalBatchFiles = results.reduce((acc, r) => acc + (r.batchFiles ? r.batchFiles.length : 0), 0);
    const totalMissingTerms = results
        .filter((r) => !r.skipped)
        .reduce((acc, r) => acc + r.termCount, 0);

    console.log(`\n📊 Summary`);
    console.log(`   ✅ Fully translated : ${complete} locale(s)`);
    console.log(`   📄 With missing     : ${withMissing} locale(s)`);
    console.log(`   ⏭️  Skipped (< ${MIN_MISSING_TERMS})  : ${skipped} locale(s)`);
    if (failCount > 0) {
        console.log(`   ❌ Errors          : ${failCount} locale(s)`);
    }
    console.log(`   📝 Batch files      : ${totalBatchFiles}`);
    console.log(`   🔤 Total missing    : ${totalMissingTerms} term(s)\n`);

    if (totalBatchFiles > 0) {
        console.log(`📤 Next steps:`);
        console.log(`   1. Review / translate the batch files in ${OUTPUT_DIR}`);
        console.log(`      Run: /locale-translate --all   (inside Claude Code)`);
        console.log(`   2. Upload the translated files to POEditor`);
        console.log(`   3. Pull approved translations: npm run locale:download`);
    }

    if (failCount > 0) {
        process.exit(1);
    }
}

main().catch((err) => {
    console.error(`\n❌ Fatal error: ${err.message}`);
    process.exit(1);
});
