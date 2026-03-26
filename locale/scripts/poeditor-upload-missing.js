#!/usr/bin/env node

/**
 * POEditor Missing Terms Uploader
 *
 * Reviews JSON batch files under locale/terms/missing/, validates that terms
 * have real translations (not empty, not identical to the source key), shows
 * the user a sample, prompts for confirmation, uploads approved terms to
 * POEditor, then downloads the locale to verify the result.
 *
 * Usage:
 *   node locale/scripts/poeditor-upload-missing.js           # all locales
 *   node locale/scripts/poeditor-upload-missing.js --locale es-co,uk,hi
 *   node locale/scripts/poeditor-upload-missing.js --dry-run
 *   npm run locale:upload:missing
 *   npm run locale:upload:missing -- --locale uk
 *
 * Requires:
 *   POEDITOR_TOKEN environment variable (from .env or shell)
 */

require('dotenv').config();

const fs = require('fs');
const path = require('path');
const https = require('https');
const readline = require('readline');
const { URLSearchParams } = require('url');
const { spawnSync } = require('child_process');

const localeConfig = require('./locale-config');

// ── Configuration ────────────────────────────────────────────────────────────

const POEDITOR_API_BASE = 'https://api.poeditor.com/v2';
const PROJECT_ID = '77079';
const MISSING_DIR = localeConfig.terms.missing;
const LOCALES_FILE = localeConfig.localesJson;
const SAMPLE_SIZE = 5;
// Pause between processing locales to stay well under POEditor rate limits.
// The downloader itself already adds ~1.5 s of inter-format delay, so this
// extra gap gives the API time to breathe before the next upload.
const BETWEEN_LOCALES_DELAY_MS = 30_000;

// Sanitize untrusted strings before logging to prevent log injection
const sanitize = (str) => String(str).replace(/[\r\n]/g, ' ');

const apiToken = process.env.POEDITOR_TOKEN;
if (!apiToken) {
    console.error('❌ POEDITOR_TOKEN environment variable is required');
    console.error('   Get your token from: https://poeditor.com/account/api');
    process.exit(1);
}

// ── Utilities ────────────────────────────────────────────────────────────────

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * HTTPS POST with exponential-backoff retry (mirrors poeditor-downloader.js).
 */
function makeRequest(url, postData) {
    const MAX_RETRIES = 5;
    const BASE_DELAY_MS = 1000;
    const BACKOFF_FACTOR = 2;

    function doRequest() {
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
                res.on('data', (chunk) => { data += chunk; });
                res.on('end', () => resolve({ statusCode: res.statusCode, data }));
            });
            req.on('error', (err) => reject(err));
            req.write(postData);
            req.end();
        });
    }

    return (async () => {
        for (let attempt = 1; attempt <= MAX_RETRIES; attempt++) {
            try {
                const res = await doRequest();
                const { statusCode, data } = res;

                if (statusCode >= 200 && statusCode < 300) return { status: statusCode, data };

                if (statusCode === 429 || (statusCode >= 500 && statusCode < 600)) {
                    if (attempt === MAX_RETRIES) throw new Error(`HTTP ${statusCode}: ${data}`);
                    const backoff = Math.min(BASE_DELAY_MS * (BACKOFF_FACTOR ** (attempt - 1)), 30_000);
                    const wait = backoff + Math.floor(Math.random() * Math.floor(backoff / 2));
                    console.warn(`  ⚠️  Rate-limited (HTTP ${statusCode}), retrying in ${wait}ms (attempt ${attempt}/${MAX_RETRIES})`);
                    await sleep(wait);
                    continue;
                }

                throw new Error(`HTTP ${statusCode}: ${data}`);
            } catch (err) {
                if (attempt === MAX_RETRIES) throw err;
                const backoff = Math.min(BASE_DELAY_MS * (BACKOFF_FACTOR ** (attempt - 1)), 30_000);
                const wait = backoff + Math.floor(Math.random() * Math.floor(backoff / 2));
                console.warn(`  ⚠️  Network error, retrying in ${wait}ms (attempt ${attempt}/${MAX_RETRIES}): ${sanitize(err.message)}`);
                await sleep(wait);
            }
        }
        throw new Error('Exceeded retry attempts');
    })();
}

function prompt(rl, question) {
    return new Promise((resolve) => rl.question(question, resolve));
}

// ── Locale mapping ───────────────────────────────────────────────────────────

/**
 * Builds a map from lowercase POEditor code → { poEditorCode, locale, name }
 * so folder names like "es-CO" resolve to code "es-CO", locale "es_CO", and a
 * display name.  `locale` (underscore format, e.g. "es_AR") is used when
 * invoking the downloader; `poEditorCode` (dash format) is used for uploads.
 */
function buildLocaleMap() {
    const locales = JSON.parse(fs.readFileSync(LOCALES_FILE, 'utf8'));
    const map = {};
    for (const [name, entry] of Object.entries(locales)) {
        if (entry.poEditor) {
            map[entry.poEditor.toLowerCase()] = {
                poEditorCode: entry.poEditor,
                locale: entry.locale || entry.poEditor,
                name,
            };
        }
    }
    return map;
}

// ── Term analysis ────────────────────────────────────────────────────────────

/**
 * Returns true if the term value has at least one non-empty translation string.
 */
function hasAnyTranslation(value) {
    if (typeof value === 'string') return value.trim() !== '';
    if (typeof value === 'object' && value !== null) {
        return Object.values(value).some(v => typeof v === 'string' && v.trim() !== '');
    }
    return false;
}

/**
 * Returns true when the translated text differs from the source term key —
 * catches trivial copy-paste where someone just repeated the English term.
 * For plural objects, at least one non-empty form must differ from the key.
 */
function isNotIdenticalToKey(termKey, value) {
    if (typeof value === 'string') {
        return value.trim() !== termKey.trim();
    }
    if (typeof value === 'object' && value !== null) {
        return Object.values(value)
            .filter(v => typeof v === 'string' && v.trim() !== '')
            .some(v => v.trim() !== termKey.trim());
    }
    return false;
}

/**
 * Analyzes a single batch file and splits its terms into three buckets:
 *   localizedTerms – have translations that differ from the source key
 *   suspectTerms   – translation is identical to the source key
 *   emptyTerms     – no translation found at all
 */
function analyzeFile(filePath) {
    const raw = JSON.parse(fs.readFileSync(filePath, 'utf8'));
    const localizedTerms = {};
    const suspectTerms = {};
    const emptyTerms = {};

    for (const [term, value] of Object.entries(raw)) {
        if (!hasAnyTranslation(value)) {
            emptyTerms[term] = value;
        } else if (!isNotIdenticalToKey(term, value)) {
            suspectTerms[term] = value;
        } else {
            localizedTerms[term] = value;
        }
    }

    return { localizedTerms, suspectTerms, emptyTerms };
}

// ── POEditor API helpers ─────────────────────────────────────────────────────

/**
 * Builds a key_value_json object ready for file import.
 * Only includes terms that have at least one non-empty translated form.
 */
function buildKeyValueJson(terms) {
    const out = {};
    for (const [term, value] of Object.entries(terms)) {
        if (typeof value === 'string' && value.trim()) {
            out[term] = value;
        } else if (typeof value === 'object' && value !== null) {
            const filled = {};
            for (const [form, text] of Object.entries(value)) {
                if (typeof text === 'string' && text.trim()) {
                    filled[form] = text;
                }
            }
            if (Object.keys(filled).length > 0) out[term] = filled;
        }
    }
    return out;
}

/**
 * Sends a multipart/form-data POST (used for projects/upload file import).
 * Shares the same exponential-backoff retry logic as makeRequest.
 */
function makeMultipartRequest(url, fields, fileContent) {
    const MAX_RETRIES = 5;
    const BASE_DELAY_MS = 1000;
    const BACKOFF_FACTOR = 2;
    const boundary = `----POEditorBoundary${Date.now()}`;

    function buildBody() {
        const parts = [];
        for (const [name, value] of Object.entries(fields)) {
            parts.push(
                `--${boundary}\r\n` +
                `Content-Disposition: form-data; name="${name}"\r\n\r\n` +
                `${value}\r\n`
            );
        }
        // file part
        parts.push(
            `--${boundary}\r\n` +
            `Content-Disposition: form-data; name="file"; filename="translations.json"\r\n` +
            `Content-Type: application/json\r\n\r\n` +
            `${fileContent}\r\n`
        );
        parts.push(`--${boundary}--\r\n`);
        return Buffer.from(parts.join(''), 'utf8');
    }

    function doRequest(body) {
        return new Promise((resolve, reject) => {
            const options = {
                method: 'POST',
                headers: {
                    'Content-Type': `multipart/form-data; boundary=${boundary}`,
                    'Content-Length': body.length,
                },
            };
            const req = https.request(url, options, (res) => {
                let data = '';
                res.on('data', (chunk) => { data += chunk; });
                res.on('end', () => resolve({ statusCode: res.statusCode, data }));
            });
            req.on('error', (err) => reject(err));
            req.write(body);
            req.end();
        });
    }

    return (async () => {
        const body = buildBody();
        for (let attempt = 1; attempt <= MAX_RETRIES; attempt++) {
            try {
                const res = await doRequest(body);
                const { statusCode, data } = res;

                if (statusCode >= 200 && statusCode < 300) return { status: statusCode, data };

                if (statusCode === 429 || (statusCode >= 500 && statusCode < 600)) {
                    if (attempt === MAX_RETRIES) throw new Error(`HTTP ${statusCode}: ${data}`);
                    const backoff = Math.min(BASE_DELAY_MS * (BACKOFF_FACTOR ** (attempt - 1)), 30_000);
                    const wait = backoff + Math.floor(Math.random() * Math.floor(backoff / 2));
                    console.warn(`  ⚠️  Rate-limited (HTTP ${statusCode}), retrying in ${wait}ms (attempt ${attempt}/${MAX_RETRIES})`);
                    await sleep(wait);
                    continue;
                }

                throw new Error(`HTTP ${statusCode}: ${data}`);
            } catch (err) {
                if (attempt === MAX_RETRIES) throw err;
                const backoff = Math.min(BASE_DELAY_MS * (BACKOFF_FACTOR ** (attempt - 1)), 30_000);
                const wait = backoff + Math.floor(Math.random() * Math.floor(backoff / 2));
                console.warn(`  ⚠️  Network error, retrying in ${wait}ms (attempt ${attempt}/${MAX_RETRIES}): ${sanitize(err.message)}`);
                await sleep(wait);
            }
        }
        throw new Error('Exceeded retry attempts');
    })();
}

/**
 * Uploads translations to POEditor via projects/upload (file import).
 * Uses key_value_json format with updating=translations and overwrite=1.
 * Returns { parsed, added, updated } from the API response.
 *
 * POEditor rate-limit errors come back as HTTP 200 with status:"fail" in the
 * JSON body, so we handle application-level retries here independently of the
 * transport-level retries in makeMultipartRequest.
 */
async function uploadTranslations(poEditorCode, terms) {
    const kvJson = buildKeyValueJson(terms);
    if (Object.keys(kvJson).length === 0) return { parsed: 0, added: 0, updated: 0 };

    const fileContent = JSON.stringify(kvJson, null, 2);

    const fields = {
        api_token: apiToken,
        id: PROJECT_ID,
        language: poEditorCode,
        updating: 'translations',
        overwrite: '1',
        sync_terms: '0',
    };

    const MAX_UPLOAD_RETRIES = 5;
    const UPLOAD_BASE_DELAY_MS = 15_000; // 15 s base, doubles each attempt (15, 30, 60, 120, 120 s)

    let result;
    for (let attempt = 1; attempt <= MAX_UPLOAD_RETRIES; attempt++) {
        const response = await makeMultipartRequest(
            `${POEDITOR_API_BASE}/projects/upload`,
            fields,
            fileContent
        );
        result = JSON.parse(response.data);

        if (result.response.status === 'success') break;

        const msg = result.response.message || 'Unknown POEditor error';
        const isRateLimit = /too many|rate.?limit|short period/i.test(msg);

        if (!isRateLimit || attempt === MAX_UPLOAD_RETRIES) {
            throw new Error(msg);
        }

        const wait = Math.min(UPLOAD_BASE_DELAY_MS * (2 ** (attempt - 1)), 120_000);
        console.warn(`  ⚠️  Upload rate-limited: "${msg}" — retrying in ${wait / 1000}s (attempt ${attempt}/${MAX_UPLOAD_RETRIES})`);
        await sleep(wait);
    }

    // projects/upload returns result.result.translations or result.result.terms
    const stats = result.result?.translations ?? result.result?.terms ?? {};
    return {
        parsed:  stats.parsed  ?? Object.keys(kvJson).length,
        added:   stats.added   ?? 'n/a',
        updated: stats.updated ?? 'n/a',
    };
}

/**
 * Runs the existing poeditor-downloader.js for one locale so we reuse
 * all its logic (JSON + PO + MO + missing-terms rebuild) without a second
 * independent API call.
 *
 * The downloader accepts "locale" format (e.g. "es_AR") but not POEditor dash
 * format (e.g. "es-AR"), so we pass the underscore locale code here.
 */
function runDownloaderForLocale(localeCode) {
    const downloaderScript = path.join(__dirname, 'poeditor-downloader.js');
    const result = spawnSync(process.execPath, [downloaderScript, '--locale', localeCode], {
        env: process.env,
        stdio: 'inherit',
    });
    if (result.status !== 0) {
        throw new Error(`Downloader exited with code ${result.status}`);
    }
}

// ── Display helpers ──────────────────────────────────────────────────────────

function displaySample(localizedTerms, sampleSize) {
    const entries = Object.entries(localizedTerms);
    const sample = entries.slice(0, sampleSize);

    console.log(`\n  📝 Sample translations (showing ${sample.length} of ${entries.length}):\n`);

    for (const [term, value] of sample) {
        const shortTerm = term.length > 55 ? term.slice(0, 52) + '...' : term;

        if (typeof value === 'string') {
            console.log(`    Key:  "${shortTerm}"`);
            console.log(`    Val:  "${value}"`);
        } else {
            const filledForms = Object.entries(value)
                .filter(([, v]) => v && v.trim())
                .map(([k, v]) => `${k}: "${v}"`)
                .join(', ');
            console.log(`    Key:  "${shortTerm}"`);
            console.log(`    Val:  { ${filledForms} }`);
        }
        console.log();
    }
}

function displaySuspects(suspectTerms) {
    const count = Object.keys(suspectTerms).length;
    if (count === 0) return;

    console.log(`  ⚠️  Skipping ${count} term(s) where translation is identical to the source key:`);
    const preview = Object.keys(suspectTerms).slice(0, 3);
    for (const term of preview) {
        const shortTerm = term.length > 60 ? term.slice(0, 57) + '...' : term;
        console.log(`     - "${shortTerm}"`);
    }
    if (count > 3) console.log(`     ... and ${count - 3} more`);
    console.log();
}

// ── Main ─────────────────────────────────────────────────────────────────────

async function main() {
    const args = process.argv.slice(2);
    const dryRun = args.includes('--dry-run');
    const localeFilter = (() => {
        const idx = args.indexOf('--locale');
        if (idx === -1) return null;
        // Support comma-separated list: --locale hi,ko,uk
        return new Set(args[idx + 1].toLowerCase().split(',').map(s => s.trim()).filter(Boolean));
    })();

    const rl = readline.createInterface({ input: process.stdin, output: process.stdout });

    console.log('🔍 POEditor Missing Terms Uploader\n');
    if (dryRun) console.log('  🔸 DRY RUN — no changes will be sent to POEditor\n');

    const localeMap = buildLocaleMap();

    if (!fs.existsSync(MISSING_DIR)) {
        console.log('  No missing terms directory found. Run: npm run locale:download');
        rl.close();
        return;
    }

    // Discover locale sub-folders
    const localeFolders = fs.readdirSync(MISSING_DIR)
        .filter(f => {
            const fullPath = path.join(MISSING_DIR, f);
            return fs.statSync(fullPath).isDirectory() && !f.startsWith('.');
        })
        .filter(f => !localeFilter || localeFilter.has(f.toLowerCase()))
        .sort();

    if (localeFolders.length === 0) {
        console.log(
            localeFilter
                ? `  No missing-terms folder(s) found for locale(s): ${[...localeFilter].join(', ')}`
                : '  No locale folders found in the missing terms directory.'
        );
        rl.close();
        return;
    }

    let totalUploaded = 0;
    let totalSkipped = 0;
    let abortAll = false;

    for (const folder of localeFolders) {
        if (abortAll) break;

        const folderKey = folder.toLowerCase();
        const localeEntry = localeMap[folderKey];

        if (!localeEntry) {
            console.warn(`\n  ⚠️  Folder "${folder}" does not match any locale in locales.json — skipping.`);
            totalSkipped++;
            continue;
        }

        const { poEditorCode, locale: localeCode, name: localeName } = localeEntry;

        console.log(`\n${'─'.repeat(62)}`);
        console.log(`📂  ${localeName}  (${poEditorCode})`);
        console.log(`${'─'.repeat(62)}`);

        const folderPath = path.join(MISSING_DIR, folder);
        const jsonFiles = fs.readdirSync(folderPath)
            .filter(f => f.endsWith('.json'))
            .sort();

        if (jsonFiles.length === 0) {
            console.log('  No JSON batch files found, skipping.\n');
            continue;
        }

        // Aggregate across all batch files for this locale
        const allLocalized = {};
        const allSuspect = {};
        let totalEmpty = 0;

        for (const file of jsonFiles) {
            const filePath = path.join(folderPath, file);
            const { localizedTerms, suspectTerms, emptyTerms } = analyzeFile(filePath);
            Object.assign(allLocalized, localizedTerms);
            Object.assign(allSuspect, suspectTerms);
            totalEmpty += Object.keys(emptyTerms).length;
        }

        const localizedCount = Object.keys(allLocalized).length;
        const suspectCount = Object.keys(allSuspect).length;

        console.log(
            `  Files: ${jsonFiles.length}` +
            `  |  Ready to upload: ${localizedCount}` +
            `  |  Suspect (skipped): ${suspectCount}` +
            `  |  Empty: ${totalEmpty}`
        );

        if (localizedCount === 0) {
            console.log('  No valid translated terms found — nothing to upload.\n');
            totalSkipped++;
            continue;
        }

        // Show sample and suspect warnings
        displaySample(allLocalized, SAMPLE_SIZE);
        displaySuspects(allSuspect);

        // Prompt the user — Enter alone defaults to yes
        const answer = await prompt(
            rl,
            `  Upload ${localizedCount} term(s) to POEditor for "${poEditorCode}"? [Y/n/skip-all] `
        );
        const choice = answer.trim().toLowerCase();

        if (choice === 'skip-all' || choice === 'q') {
            console.log('\n  ⏭️  Stopping — no further locales will be processed.');
            abortAll = true;
            break;
        }

        if (choice !== '' && choice !== 'y' && choice !== 'yes') {
            console.log('  ⏭️  Skipped.');
            totalSkipped++;
            continue;
        }

        if (dryRun) {
            console.log(`  🔸 DRY RUN: would upload ${localizedCount} term(s) to "${poEditorCode}"`);
            totalUploaded += localizedCount;
            continue;
        }

        // ── Upload ───────────────────────────────────────────────────────────
        console.log(`\n  ⬆️  Uploading ${localizedCount} term(s)...`);
        let uploadStats;
        try {
            uploadStats = await uploadTranslations(poEditorCode, allLocalized);
            const parsed  = uploadStats.parsed  ?? localizedCount;
            const added   = uploadStats.added   ?? 'n/a';
            const updated = uploadStats.updated ?? 'n/a';
            console.log(`  ✅ Upload complete — parsed: ${parsed}, added: ${added}, updated: ${updated}`);
            totalUploaded += localizedCount;
        } catch (err) {
            console.error(`  ❌ Upload failed: ${sanitize(err.message)}`);
            totalSkipped++;
            continue;
        }

        // ── Download (reuse existing downloader — no extra API cost) ─────────
        console.log(`\n  ⬇️  Running locale downloader for "${poEditorCode}"...`);
        try {
            runDownloaderForLocale(localeCode);
        } catch (err) {
            console.warn(`  ⚠️  Download step failed: ${err.message}`);
        }

        // ── Rate-limit guard ─────────────────────────────────────────────────
        console.log(`  ⏱️  Waiting ${BETWEEN_LOCALES_DELAY_MS / 1000}s before next locale...`);
        await sleep(BETWEEN_LOCALES_DELAY_MS);
    }

    rl.close();

    console.log(`\n${'═'.repeat(62)}`);
    console.log(`📊  Done — ${totalUploaded} term(s) uploaded, ${totalSkipped} locale(s) skipped`);
    console.log(`${'═'.repeat(62)}\n`);
}

main().catch((err) => {
    console.error(`\n❌ Fatal error: ${err.message}`);
    process.exit(1);
});
