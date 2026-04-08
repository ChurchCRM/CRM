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
 *   node locale/scripts/poeditor-upload-missing.js te        # positional locale (like downloader)
 *   node locale/scripts/poeditor-upload-missing.js --locale es-co,uk,hi
 *   node locale/scripts/poeditor-upload-missing.js --dry-run
 *   node locale/scripts/poeditor-upload-missing.js --yes     # skip confirmation prompts
 *   node locale/scripts/poeditor-upload-missing.js --no-download  # skip post-upload refresh
 *   npm run locale:upload:missing
 *   npm run locale:upload:missing -- --locale uk
 *   npm run locale:upload:missing -- te
 *
 * After each upload, the script re-fetches missing (untranslated) terms from
 * POEditor and updates the local batch files so you can see what's left.
 * Use --no-download to skip this step.
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

const localeConfig = require('./locale-config');

// ── Configuration ────────────────────────────────────────────────────────────

const POEDITOR_API_BASE = 'https://api.poeditor.com/v2';
const PROJECT_ID = '77079';
const MISSING_DIR = localeConfig.terms.missing;
const LOCALES_FILE = localeConfig.localesJson;

// File containing terms that are intentionally English for a locale
// (e.g. brand names, country names). This allowlist lets the uploader
// send translations that are identical to the source key.
const ENGLISH_OK_FILE = localeConfig.terms.englishOk;
const SAMPLE_SIZE = 5;

// POEditor rate limits (free tier):
//   - Uploads: 1 request per 20 seconds
//   - General: 100 requests/min, 2000 requests/hour
//   - Paid tier: uploads 1 per 10s, 200 req/min, 6000 req/hour
// Per-locale cycle: upload (1 req) + 3s pause + refresh export (1 req) = 2 API calls
// 22s gap between locales keeps uploads ≥20s apart on free tier.
const BETWEEN_LOCALES_DELAY_MS = 22_000;

// Sanitize untrusted strings before logging to prevent log injection
// eslint-disable-next-line no-control-regex
const sanitize = (str) => String(str).replace(/[\x00-\x1f\x7f]/g, ' ').slice(0, 200);

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

// ── English-OK allowlist ─────────────────────────────────────────────────────

/**
 * Loads locale/terms/english-ok.json and returns a Map from locale code
 * (lowercase) to a Set of term keys that are intentionally English (e.g.
 * country names, brand names, universal tech terms).  Terms in this set are
 * safe to upload to POEditor even though their translation is identical to the
 * source key.
 *
 * Returns an empty Map if the file does not exist.
 */
function loadEnglishOkAllowlist() {
    if (!fs.existsSync(ENGLISH_OK_FILE)) return new Map();
    try {
        const raw = JSON.parse(fs.readFileSync(ENGLISH_OK_FILE, 'utf8'));
        const result = new Map();
        for (const [locale, terms] of Object.entries(raw)) {
            if (Array.isArray(terms)) {
                result.set(locale.toLowerCase(), new Set(terms));
            }
        }
        return result;
    } catch (err) {
        console.warn(`  ⚠️  Could not load english-ok allowlist: ${sanitize(err.message)}`);
        return new Map();
    }
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
 *   localizedTerms – have translations that differ from the source key,
 *                    OR are in the english-ok allowlist (intentionally English)
 *   suspectTerms   – translation is identical to the source key and NOT in allowlist
 *   emptyTerms     – no translation found at all
 *
 * @param {string} filePath - path to the batch JSON file
 * @param {Set<string>} [englishOkSet] - optional set of terms safe to upload as-is
 */
function analyzeFile(filePath, englishOkSet = new Set()) {
    const raw = JSON.parse(fs.readFileSync(filePath, 'utf8'));
    const localizedTerms = {};
    const suspectTerms = {};
    const emptyTerms = {};

    for (const [term, value] of Object.entries(raw)) {
        if (!hasAnyTranslation(value)) {
            emptyTerms[term] = value;
        } else if (!isNotIdenticalToKey(term, value)) {
            // Value is identical to the source key — check the allowlist
            if (englishOkSet.has(term)) {
                localizedTerms[term] = value;
            } else {
                suspectTerms[term] = value;
            }
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
        type: 'key_value_json',
    };

    const MAX_UPLOAD_RETRIES = 5;
    const UPLOAD_BASE_DELAY_MS = 20_000; // 20s base (matches free-tier upload limit), doubles: 20, 40, 80, 120, 120s

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

// ── Post-upload missing-terms refresh ───────────────────────────────────────

const POEDITOR_EXPORT_API = `${POEDITOR_API_BASE}/projects/export`;
const TERMS_PER_FILE = localeConfig.settings?.missingTermsBatchSize || 150;

/**
 * Fetch untranslated (missing) terms for a POEditor locale.
 * Returns an object mapping term → empty string.
 */
async function fetchUntranslatedTerms(poEditorCode) {
    const postData = new URLSearchParams({
        api_token: apiToken,
        id: PROJECT_ID,
        language: poEditorCode,
        type: 'key_value_json',
        filters: 'untranslated',
    }).toString();

    const response = await makeRequest(POEDITOR_EXPORT_API, postData);
    const result = JSON.parse(response.data);

    if (result.response.status !== 'success') {
        throw new Error(result.response.message || 'Unknown POEditor error');
    }

    const downloadUrl = result.result.url;

    return new Promise((resolve, reject) => {
        https.get(downloadUrl, (res) => {
            let data = '';
            res.on('data', (d) => { data += d; });
            res.on('end', () => {
                if (res.statusCode < 200 || res.statusCode >= 300) {
                    reject(new Error(`Download failed: HTTP ${res.statusCode}`));
                    return;
                }
                const trimmed = (data || '').trim();
                if (trimmed.length === 0) { resolve({}); return; }
                try { resolve(JSON.parse(data)); }
                catch (err) { reject(new Error(`JSON parse error: ${err.message}`)); }
            });
        }).on('error', reject);
    });
}

/**
 * Save missing terms in batched JSON files under MISSING_DIR/{poEditorCode}/
 * Clears existing batch files first for a clean rebuild.
 */
function saveBatchedMissingTerms(poEditorCode, missingTerms) {
    const localeOutDir = path.join(MISSING_DIR, poEditorCode);
    if (!fs.existsSync(localeOutDir)) fs.mkdirSync(localeOutDir, { recursive: true });

    // Remove existing batch files
    try {
        fs.readdirSync(localeOutDir).filter(f => f.endsWith('.json'))
            .forEach(f => { try { fs.unlinkSync(path.join(localeOutDir, f)); } catch (_) {} });
    } catch (_) {}

    const entries = Object.entries(missingTerms);
    const files = [];
    let batchNumber = 1;

    for (let i = 0; i < entries.length; i += TERMS_PER_FILE) {
        const batch = Object.fromEntries(entries.slice(i, i + TERMS_PER_FILE));
        const filename = path.join(localeOutDir, `${poEditorCode}-${batchNumber}.json`);
        fs.writeFileSync(filename, JSON.stringify(batch, null, 2) + '\n');
        files.push(filename);
        batchNumber++;
    }

    return files;
}

/**
 * Remove stale missing-term batch files when a locale has 0 remaining.
 */
function removeBatchedMissingTerms(poEditorCode) {
    const localeOutDir = path.join(MISSING_DIR, poEditorCode);
    if (!fs.existsSync(localeOutDir)) return 0;

    const existing = fs.readdirSync(localeOutDir).filter(f => f.endsWith('.json'));
    let removed = 0;
    for (const f of existing) {
        try { fs.unlinkSync(path.join(localeOutDir, f)); removed++; } catch (_) {}
    }
    try {
        if (fs.readdirSync(localeOutDir).length === 0) fs.rmdirSync(localeOutDir);
    } catch (_) {}
    return removed;
}

/**
 * After upload, re-fetch missing terms from POEditor and update local batch files.
 */
async function refreshMissingTerms(poEditorCode) {
    const missing = await fetchUntranslatedTerms(poEditorCode);
    const termCount = Object.keys(missing).length;

    if (termCount === 0) {
        const removed = removeBatchedMissingTerms(poEditorCode);
        if (removed > 0) {
            console.log(`  🧹 0 missing terms remaining — removed ${removed} stale batch file(s)`);
        } else {
            console.log(`  ✅ 0 missing terms remaining — locale fully translated!`);
        }
    } else {
        const written = saveBatchedMissingTerms(poEditorCode, missing);
        console.log(`  📄 ${termCount} missing term(s) remaining → ${written.length} file(s) updated`);
    }

    return termCount;
}

// ── Main ─────────────────────────────────────────────────────────────────────

async function main() {
    const args = process.argv.slice(2);
    const dryRun = args.includes('--dry-run');
    const autoYes = args.includes('--yes') || args.includes('-y');
    const skipDownload = args.includes('--no-download');
    const localeFilter = (() => {
        // Support: --locale hi,ko,uk  OR positional: node script.js te
        for (let i = 0; i < args.length; i++) {
            if (args[i] === '--locale' && args[i + 1]) {
                return new Set(args[i + 1].toLowerCase().split(',').map(s => s.trim()).filter(Boolean));
            } else if (!args[i].startsWith('-')) {
                return new Set(args[i].toLowerCase().split(',').map(s => s.trim()).filter(Boolean));
            }
        }
        return null;
    })();

    const rl = readline.createInterface({ input: process.stdin, output: process.stdout });

    console.log('🔍 POEditor Missing Terms Uploader\n');
    if (dryRun) console.log('  🔸 DRY RUN — no changes will be sent to POEditor\n');
    if (autoYes) console.log('  🔸 AUTO-CONFIRM — skipping confirmation prompts\n');

    const localeMap = buildLocaleMap();
    const englishOkAllowlist = loadEnglishOkAllowlist();

    if (fs.existsSync(ENGLISH_OK_FILE)) {
        const totalAllowlisted = [...englishOkAllowlist.values()].reduce((n, s) => n + s.size, 0);
        console.log(`  📋 English-OK allowlist loaded: ${totalAllowlisted} term(s) across ${englishOkAllowlist.size} locale(s)\n`);
    }

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

    // Per-locale results for final report
    const results = [];

    const totalLocales = localeFolders.length;

    for (let localeIdx = 0; localeIdx < totalLocales; localeIdx++) {
        const folder = localeFolders[localeIdx];
        if (abortAll) break;

        const localeNum = localeIdx + 1;
        const folderKey = folder.toLowerCase();
        const localeEntry = localeMap[folderKey];

        if (!localeEntry) {
            console.warn(`\n  ⚠️  [${localeNum}/${totalLocales}] Folder "${folder}" does not match any locale in locales.json — skipping.`);
            results.push({ locale: folder, name: folder, status: 'no-match', uploaded: 0, empty: 0, remaining: '?' });
            totalSkipped++;
            continue;
        }

        const { poEditorCode, locale: localeCode, name: localeName } = localeEntry;

        console.log(`\n${'─'.repeat(62)}`);
        console.log(`📂  [${localeNum}/${totalLocales}]  ${localeName}  (${poEditorCode})`);
        console.log(`${'─'.repeat(62)}`);

        const folderPath = path.join(MISSING_DIR, folder);
        const jsonFiles = fs.readdirSync(folderPath)
            .filter(f => f.endsWith('.json'))
            .sort();

        if (jsonFiles.length === 0) {
            console.log('  No JSON batch files found, skipping.\n');
            results.push({ locale: poEditorCode, name: localeName, status: 'no-files', uploaded: 0, empty: 0, remaining: '?' });
            continue;
        }

        // Aggregate across all batch files for this locale
        const allLocalized = {};
        const allSuspect = {};
        let totalEmpty = 0;

        const englishOkSet = englishOkAllowlist.get(folderKey) ?? new Set();
        const englishOkCount = englishOkSet.size;

        for (const file of jsonFiles) {
            const filePath = path.join(folderPath, file);
            const { localizedTerms, suspectTerms, emptyTerms } = analyzeFile(filePath, englishOkSet);
            Object.assign(allLocalized, localizedTerms);
            Object.assign(allSuspect, suspectTerms);
            totalEmpty += Object.keys(emptyTerms).length;
        }

        const localizedCount = Object.keys(allLocalized).length;
        const suspectCount = Object.keys(allSuspect).length;

        console.log(
            `  Files: ${jsonFiles.length}` +
            `  |  Ready to upload: ${localizedCount}` +
            (englishOkCount > 0 ? `  |  English-OK (allowlisted): ${englishOkCount}` : '') +
            `  |  Suspect (skipped): ${suspectCount}` +
            `  |  Empty: ${totalEmpty}`
        );

        if (localizedCount === 0) {
            console.log('  No valid translated terms found — nothing to upload.\n');
            results.push({ locale: poEditorCode, name: localeName, status: 'nothing-to-upload', uploaded: 0, empty: totalEmpty, remaining: '?' });
            totalSkipped++;
            continue;
        }

        // Prompt the user — Enter alone defaults to yes; --yes skips the prompt
        if (!autoYes) {
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
                results.push({ locale: poEditorCode, name: localeName, status: 'user-skipped', uploaded: 0, empty: totalEmpty, remaining: '?' });
                totalSkipped++;
                continue;
            }
        }

        if (dryRun) {
            console.log(`  🔸 DRY RUN: would upload ${localizedCount} term(s) to "${poEditorCode}"`);
            results.push({ locale: poEditorCode, name: localeName, status: 'uploaded', uploaded: localizedCount, empty: totalEmpty, remaining: '(dry run)' });
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
            results.push({ locale: poEditorCode, name: localeName, status: 'upload-failed', uploaded: 0, empty: totalEmpty, remaining: '?' });
            totalSkipped++;
            continue;
        }

        // ── Refresh missing terms ────────────────────────────────────────────
        let remainingCount = '?';
        if (!skipDownload) {
            console.log(`\n  ⬇️  Refreshing missing terms from POEditor...`);
            try {
                await sleep(3000); // brief pause before download to let POEditor process
                remainingCount = await refreshMissingTerms(poEditorCode);
            } catch (err) {
                console.warn(`  ⚠️  Failed to refresh missing terms: ${sanitize(err.message)}`);
            }
        } else {
            console.log(`\n  ⏭️  Skipping download (--no-download)`);
        }

        results.push({ locale: poEditorCode, name: localeName, status: 'uploaded', uploaded: localizedCount, empty: totalEmpty, remaining: remainingCount });

        // ── Rate-limit guard ─────────────────────────────────────────────────
        console.log(`  ⏱️  Waiting ${BETWEEN_LOCALES_DELAY_MS / 1000}s before next locale...`);
        await sleep(BETWEEN_LOCALES_DELAY_MS);
    }

    rl.close();

    // ── Final report ─────────────────────────────────────────────────────────
    console.log(`\n${'═'.repeat(72)}`);
    console.log(`📊  Final Report — ${results.length} locale(s) processed`);
    console.log(`${'═'.repeat(72)}`);

    if (results.length > 0) {
        // Table header
        const colLocale = 18;
        const colStatus = 18;
        const colUp = 10;
        const colEmpty = 8;
        const colLeft = 10;
        const header =
            'Locale'.padEnd(colLocale) +
            'Status'.padEnd(colStatus) +
            'Uploaded'.padStart(colUp) +
            'Empty'.padStart(colEmpty) +
            'Remaining'.padStart(colLeft);
        console.log(`\n  ${header}`);
        console.log(`  ${'─'.repeat(header.length)}`);

        const statusIcon = {
            'uploaded':           '✅ uploaded',
            'upload-failed':     '❌ failed',
            'nothing-to-upload': '⚪ no terms',
            'user-skipped':      '⏭️  skipped',
            'no-match':          '⚠️  no match',
            'no-files':          '⚪ no files',
        };

        for (const r of results) {
            const label = `${r.name} (${r.locale})`.slice(0, colLocale - 1);
            const status = (statusIcon[r.status] || r.status).slice(0, colStatus - 1);
            const up = String(r.uploaded).padStart(colUp);
            const empty = String(r.empty).padStart(colEmpty);
            const left = String(r.remaining).padStart(colLeft);
            console.log(`  ${label.padEnd(colLocale)}${status.padEnd(colStatus)}${up}${empty}${left}`);
        }

        console.log(`  ${'─'.repeat(header.length)}`);

        // Totals
        const totalUp = results.reduce((s, r) => s + r.uploaded, 0);
        const successCount = results.filter(r => r.status === 'uploaded').length;
        const failCount = results.filter(r => r.status === 'upload-failed').length;
        const skipCount = results.filter(r => r.status !== 'uploaded' && r.status !== 'upload-failed').length;

        console.log(`\n  Total uploaded: ${totalUp} term(s) across ${successCount} locale(s)`);
        if (failCount > 0) console.log(`  Failed: ${failCount} locale(s)`);
        if (skipCount > 0) console.log(`  Skipped: ${skipCount} locale(s)`);
    }

    if (!skipDownload) {
        console.log(`\n📝  Missing-terms files have been refreshed locally.`);
        console.log(`    Run "node locale/scripts/locale-translate.js --list" to see what's left.`);
    }
    console.log(`\n📝  Next steps:`);
    console.log(`  1. Share translations with POEditor reviewers`);
    console.log(`  2. Run locale-release workflow to download full translations (JSON + PO/MO)`);
    console.log(`${'═'.repeat(72)}\n`);
}

main().catch((err) => {
    console.error(`\n❌ Fatal error: ${err.message}`);
    process.exit(1);
});
