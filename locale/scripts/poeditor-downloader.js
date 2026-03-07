#!/usr/bin/env node

/**
 * POEditor Translation Downloader
 * Replaces grunt-poeditor-gd with native Node.js HTTPS (zero external dependencies)
 * 
 * Usage:
 *   node locale/scripts/poeditor-downloader.js              # Download all locales
 *   node locale/scripts/poeditor-downloader.js fi           # Download Finnish only
 *   npm run locale:download                                 # Download all locales
 *   npm run locale:download -- --locale fi                  # Download Finnish only
 * 
 * Requires:
 * - POEDITOR_TOKEN environment variable (from .env or POEditor API Access)
 * - No additional npm packages (uses native https module)
 * 
 * Note: POEditor project ID is hardcoded as 77079 (ChurchCRM official project)
 */

require('dotenv').config();

const fs = require('fs');
const path = require('path');
const https = require('https');
const { URLSearchParams } = require('url');

// Configuration
const LOCALES_FILE = path.join(__dirname, '../../src/locale/locales.json');
const JSON_OUTPUT_DIR = path.join(__dirname, '../../src/locale/i18n');
const MASTER_LIST_DIR = path.join(__dirname, '../'); // /locale directory for master English list
// PO/MO files historically live under src/locale/textdomain so other scripts
// (and gettext lookups) find them there. Match the previous layout.
const TEXTDOMAIN_OUTPUT_DIR = path.join(__dirname, '../../src/locale/textdomain');
const POEDITOR_API = 'https://api.poeditor.com/v2/projects/export';

// Use centralized locale config for missing-terms paths and settings
const localeConfig = require('./locale-config');
const MISSING_OUTPUT_DIR = localeConfig.terms.missingNew;
const TERMS_PER_FILE = localeConfig.settings?.missingTermsBatchSize || 150;
const MIN_MISSING_TERMS = 0; // never skip missing terms

// File formats to download
const FILE_FORMATS = [
    { type: 'key_value_json', dir: JSON_OUTPUT_DIR, ext: 'json', filename: (locale) => `${locale}.json` },
    { type: 'po', dir: TEXTDOMAIN_OUTPUT_DIR, ext: 'po', filename: (locale) => `${locale}/LC_MESSAGES/messages.po` },
    { type: 'mo', dir: TEXTDOMAIN_OUTPUT_DIR, ext: 'mo', filename: (locale) => `${locale}/LC_MESSAGES/messages.mo` },
];

// Load configuration from environment
const apiToken = process.env.POEDITOR_TOKEN;
const projectId = '77079'; // ChurchCRM POEditor project ID

if (!apiToken) {
    console.error('❌ POEDITOR_TOKEN environment variable is required');
    console.error('   Get your token from: https://poeditor.com/account/api');
    process.exit(1);
}

// Load locales
let localesConfig;
try {
    localesConfig = JSON.parse(fs.readFileSync(LOCALES_FILE, 'utf8'));
} catch (e) {
    console.error(`❌ Error reading locales.json: ${e.message}`);
    process.exit(1);
}

// Create output directories
const outputDirs = [JSON_OUTPUT_DIR, TEXTDOMAIN_OUTPUT_DIR];
outputDirs.forEach(dir => {
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
});

/**
 * Sleep helper
 */
function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Fetch untranslated (missing) terms for a POEditor locale as a JS object.
 * Returns an object mapping term->empty string (or the untranslated value)
 */
async function fetchUntranslatedTerms(poEditorLocale) {
    const postData = new URLSearchParams({
        api_token: apiToken,
        id: projectId,
        language: poEditorLocale,
        type: 'key_value_json',
        filters: 'untranslated',
    }).toString();

    const response = await makeRequest(POEDITOR_API, postData);
    const result = JSON.parse(response.data);

    if (result.response.status !== 'success') {
        throw new Error(result.response.message || 'Unknown POEditor error');
    }

    const downloadUrl = result.result.url;

    // fetch JSON from signed URL
    return new Promise((resolve, reject) => {
        https.get(downloadUrl, (res) => {
            const { statusCode } = res;
            let data = '';

            res.on('data', (d) => { data += d; });
            res.on('end', () => {
                // Handle non-2xx responses with helpful error
                if (statusCode < 200 || statusCode >= 300) {
                    const snippet = (data || '').toString().slice(0, 200).replace(/\s+/g, ' ');
                    reject(new Error(`Download failed: HTTP ${statusCode} from ${downloadUrl} — response: ${snippet}`));
                    return;
                }

                const trimmed = (data || '').trim();
                if (trimmed.length === 0) {
                    // Empty export -> no missing terms
                    resolve({});
                    return;
                }

                try {
                    const parsed = JSON.parse(data);
                    resolve(parsed);
                } catch (err) {
                    const snippet = data.slice(0, 200);
                    reject(new Error(`Failed to parse JSON from ${downloadUrl}: ${err.message} — snippet: ${snippet}`));
                }
            });
        }).on('error', (err) => reject(err));
    });
}

/**
 * Save missing terms in batched JSON files under MISSING_OUTPUT_DIR/{poEditorCode}/
 * Returns array of written file paths.
 */
function saveBatchedMissingTerms(poEditorCode, missingTerms) {
    const localeOutDir = path.join(MISSING_OUTPUT_DIR, poEditorCode);

    if (!fs.existsSync(localeOutDir)) {
        fs.mkdirSync(localeOutDir, { recursive: true });
    }

    // Remove pre-existing batch files for a clean rebuild
    try {
        const existing = fs.readdirSync(localeOutDir).filter((f) => f.endsWith('.json'));
        existing.forEach((f) => {
            try { fs.unlinkSync(path.join(localeOutDir, f)); } catch (_) {}
        });
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
 * Remove any existing batched missing-term files for a given poEditorCode.
 * Returns number of files removed.
 */
function removeBatchedMissingTerms(poEditorCode) {
    const localeOutDir = path.join(MISSING_OUTPUT_DIR, poEditorCode);
    if (!fs.existsSync(localeOutDir)) return 0;

    const existing = fs.readdirSync(localeOutDir).filter((f) => f.endsWith('.json'));
    let removed = 0;
    for (const f of existing) {
        try {
            fs.unlinkSync(path.join(localeOutDir, f));
            removed++;
        } catch (err) {
            // ignore individual unlink errors
        }
    }

    // If directory is now empty, remove it
    try {
        const remain = fs.readdirSync(localeOutDir);
        if (remain.length === 0) fs.rmdirSync(localeOutDir);
    } catch (_) {}

    return removed;
}


/**
 * Make HTTPS request to POEditor API
 */
function makeRequest(url, postData) {
    // Robust request with retry/backoff for rate limiting (HTTP 429) and 5xx
    const MAX_RETRIES = 5;
    const BASE_DELAY_MS = 1000; // 1s
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
                res.on('end', () => {
                    resolve({ statusCode: res.statusCode, data });
                });
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
                if (statusCode >= 200 && statusCode < 300) {
                    return { status: statusCode, data };
                }

                // Retry on rate limit or server errors
                if (statusCode === 429 || (statusCode >= 500 && statusCode < 600)) {
                    if (attempt === MAX_RETRIES) {
                        throw new Error(`HTTP ${statusCode}: ${data}`);
                    }
                    const backoff = Math.min(BASE_DELAY_MS * (BACKOFF_FACTOR ** (attempt - 1)), 30000);
                    const jitter = Math.floor(Math.random() * Math.floor(backoff / 2));
                    const wait = backoff + jitter;
                    console.warn(`  ⚠️  Request rate-limited (HTTP ${statusCode}), retrying in ${wait}ms (attempt ${attempt}/${MAX_RETRIES})`);
                    await sleep(wait);
                    continue;
                }

                // Other client errors — do not retry
                throw new Error(`HTTP ${statusCode}: ${data}`);
            } catch (err) {
                // Network errors — retry
                if (attempt === MAX_RETRIES) throw err;
                const backoff = Math.min(BASE_DELAY_MS * (BACKOFF_FACTOR ** (attempt - 1)), 30000);
                const jitter = Math.floor(Math.random() * Math.floor(backoff / 2));
                const wait = backoff + jitter;
                console.warn(`  ⚠️  Network error, retrying in ${wait}ms (attempt ${attempt}/${MAX_RETRIES}): ${err.message}`);
                await sleep(wait);
            }
        }
        throw new Error('Exceeded retry attempts');
    })();
}

/**
 * Download translation file from POEditor for a specific format
 */
async function downloadLanguageFormat(locale, poEditorLocale, format) {
    try {
        const postData = new URLSearchParams({
            api_token: apiToken,
            id: projectId,
            language: poEditorLocale,
            type: format.type,
            filters: 'translated', // POEditor filter: only exported translated strings (no untranslated)
        }).toString();

        const response = await makeRequest(POEDITOR_API, postData);
        const result = JSON.parse(response.data);

        if (result.response.status === 'success') {
            const downloadUrl = result.result.url;
            const outputFilename = format.filename(locale);
            const outputPath = path.join(format.dir, outputFilename);
            
            // Ensure directory exists
            const outputFileDir = path.dirname(outputPath);
            if (!fs.existsSync(outputFileDir)) {
                fs.mkdirSync(outputFileDir, { recursive: true });
            }
            
            // Delete old file if it exists (just before download to minimize downtime)
            if (fs.existsSync(outputPath)) {
                fs.unlinkSync(outputPath);
            }
            
            return new Promise((resolve, reject) => {
                https.get(downloadUrl, (res) => {
                    // Check for non-2xx HTTP status
                    if (res.statusCode < 200 || res.statusCode >= 300) {
                        let errorBody = '';
                        res.on('data', (chunk) => { errorBody += chunk; });
                        res.on('end', () => {
                            reject(new Error(`Download failed: HTTP ${res.statusCode} from ${downloadUrl.substring(0, 50)}...`));
                        });
                        return;
                    }

                    let fileData = Buffer.alloc(0);
                    res.on('data', (chunk) => { fileData = Buffer.concat([fileData, chunk]); });
                    res.on('end', () => {
                        if (fileData.length === 0) {
                            reject(new Error(`Downloaded file is empty (0 bytes) for ${format.type}`));
                            return;
                        }
                        // Add trailing newline for JSON files (POSIX standard)
                        if (format.ext === 'json' && fileData[fileData.length - 1] !== 0x0A) {
                            fileData = Buffer.concat([fileData, Buffer.from('\n')]);
                        }                        
                        fs.writeFileSync(outputPath, fileData);
                        const fileSize = fileData.length;
                        console.log(`    ✅ ${format.ext.toUpperCase()}: ${fileSize} bytes`);
                        resolve();
                    });
                }).on('error', reject);
            });
        } else {
            throw new Error(result.response.message || 'Unknown POEditor error');
        }
    } catch (error) {
        throw error;
    }
}

/**
 * Download super base language master list JSON file to /locale directory
 * This serves as a simplified master term list for locale variant syncing
 */
async function downloadEnglishMasterList() {
    console.log('\n📝 Downloading master term list...');
    
    // Find the super_base locale configuration
    const superBaseLocale = Object.entries(localesConfig).find(
        ([name, config]) => config.super_base === true
    );
    
    if (!superBaseLocale) {
        console.error('  ❌ No super_base locale found in locales.json');
        throw new Error('super_base locale not configured');
    }
    
    const [superBaseName, superBaseConfig] = superBaseLocale;
    const poEditorCode = superBaseConfig.poEditor;
    
    try {
        const postData = new URLSearchParams({
            api_token: apiToken,
            id: projectId,
            language: poEditorCode,
            type: 'key_value_json',
            filters: 'all', // Download all terms, including untranslated (for master list)
        }).toString();

        const response = await makeRequest(POEDITOR_API, postData);
        const result = JSON.parse(response.data);

        if (result.response.status === 'success') {
            const downloadUrl = result.result.url;
            const outputPath = path.join(MASTER_LIST_DIR, 'messages.json');
            
            return new Promise((resolve, reject) => {
                https.get(downloadUrl, (res) => {
                    let fileData = Buffer.alloc(0);
                    res.on('data', (chunk) => {
                        fileData = Buffer.concat([fileData, chunk]);
                    });
                    res.on('end', () => {
                        // Ensure JSON ends with newline
                        if (fileData[fileData.length - 1] !== 0x0A) {
                            fileData = Buffer.concat([fileData, Buffer.from('\n')]);
                        }
                        fs.writeFileSync(outputPath, fileData);
                        const fileSize = fileData.length;
                        console.log(`  ✅ Master term list (${superBaseName}): ${fileSize} bytes → ${outputPath}`);
                        resolve();
                    });
                }).on('error', reject);
            });
        } else {
            throw new Error(result.response.message || 'Unknown POEditor error');
        }
    } catch (error) {
        console.error(`  ❌ Failed to download English master list: ${error.message}`);
        throw error;
    }
}

/**
 * Download translation file from POEditor for all formats
 */
async function downloadLanguage(locale, poEditorLocale, current, total, localeCfg) {
    console.log(`  ⏳ Downloading ${locale} (${current} of ${total})...`);
    
    for (const format of FILE_FORMATS) {
        try {
            await downloadLanguageFormat(locale, poEditorLocale, format);
            // Delay between format downloads (700ms to reduce rate pressure)
            await sleep(700);
        } catch (error) {
            console.error(`    ❌ ${format.ext.toUpperCase()}: ${error.message}`);
            throw error;
        }
    }

    // Also fetch missing (untranslated) terms and write batched files
    try {
        if (localeCfg && localeCfg.skip_audit === true) {
            console.log(`  ⏭️  Skipping missing-terms for ${locale} (skip_audit)`);
        } else {
            const missing = await fetchUntranslatedTerms(poEditorLocale);
            const termCount = Object.keys(missing).length;

            if (termCount === 0) {
                // No missing terms — remove any previously existing local batch files
                const removed = removeBatchedMissingTerms(poEditorLocale);
                if (removed > 0) {
                    console.log(`  🧹 No missing terms for ${locale} (${poEditorLocale}) — removed ${removed} stale batch file(s)`);
                } else {
                    console.log(`  ✅ No missing terms for ${locale} (${poEditorLocale})`);
                }
            } else {
                if (!fs.existsSync(MISSING_OUTPUT_DIR)) fs.mkdirSync(MISSING_OUTPUT_DIR, { recursive: true });
                const written = saveBatchedMissingTerms(poEditorLocale, missing);
                console.log(`  📄 Saved ${termCount} missing term(s) → ${written.length} file(s) for ${poEditorLocale}`);
            }
        }
    } catch (err) {
        console.warn(`  ⚠️  Failed to fetch missing terms for ${locale}: ${err.message}`);
    }
}

/**
 * Parse command-line arguments for locale parameter
 */
function parseArguments() {
    const args = process.argv.slice(2);
    let targetLocale = null;

    // Handle both formats: node script.js fi  and npm script -- --locale fi
    for (let i = 0; i < args.length; i++) {
        if (args[i] === '--locale' && args[i + 1]) {
            targetLocale = args[i + 1].toLowerCase();
            break;
        } else if (!args[i].startsWith('-') && !targetLocale) {
            // First non-flag argument is the locale
            targetLocale = args[i].toLowerCase();
            break;
        }
    }

    return targetLocale;
}

/**
 * Validate locale exists in configuration
 */
function validateLocale(localeKey) {
    const lowerKey = localeKey.toLowerCase();
    
    // First try exact match
    let matching = Object.entries(localesConfig).find(
        ([key, config]) => 
            config.locale.toLowerCase() === lowerKey || 
            key.toLowerCase() === lowerKey
    );
    
    // If no exact match, try language code prefix (e.g., "fi" matches "fi_FI")
    if (!matching) {
        matching = Object.entries(localesConfig).find(
            ([key, config]) => 
                config.locale.toLowerCase().startsWith(lowerKey + '_') ||
                key.toLowerCase().startsWith(lowerKey + '_')
        );
    }
    
    if (!matching) {
        const availableLocales = Object.values(localesConfig)
            .filter(config => !config.super_base)
            .map(config => config.locale)
            .sort();
        
        console.error(`\n❌ Locale "${localeKey}" not found in configuration`);
        console.error(`\nAvailable locales:\n  ${availableLocales.join('\n  ')}`);
        console.error(`\nTip: You can use language code (e.g., "fi") or full locale (e.g., "fi_FI")`);
        process.exit(1);
    }
    
    return matching;
}

/**
 * Main execution
 */
async function main() {
    const targetLocale = parseArguments();
    
    console.log('🌐 POEditor Translation Downloader\n');
    console.log(`Project ID: ${projectId}`);
    console.log(`Formats: ${FILE_FORMATS.map(f => f.ext.toUpperCase()).join(', ')}`);
    console.log(`Output: ${JSON_OUTPUT_DIR}\n`);

    const failedLanguages = [];
    let successCount = 0;
    let totalAttempts = 0;
    let skippedCount = 0;
    
    // Calculate total locales to download
    const totalLocales = Object.keys(localesConfig).length;
    const downloadableLocales = Object.values(localesConfig)
        .filter(config => config.super_base !== true);
    let totalToDownload = downloadableLocales.length;
    let localestoProcess = localesConfig;

    // If a specific locale is requested, validate and filter to just that locale
    if (targetLocale) {
        const [matchKey, matchConfig] = validateLocale(targetLocale);
        console.log(`🎯 Single locale mode: ${matchConfig.locale}\n`);
        localestoProcess = { [matchKey]: matchConfig };
        totalToDownload = 1;
    }
    
    console.log(`📋 Total locales: ${totalLocales} (${totalToDownload} to download, ${skippedCount} skipped)\n`);

    for (const [key, localeConfig] of Object.entries(localestoProcess)) {
        const locale = localeConfig.locale;
        const poEditorLocale = localeConfig.poEditor;

        // Skip super_base locales - they are the source language, not translation targets
        if (localeConfig.super_base === true) {
            console.log(`  ⏭️  Skipping ${locale} (super base language - source)`);
            skippedCount++;
            continue;
        }

        totalAttempts++;
        try {
            await downloadLanguage(locale, poEditorLocale, totalAttempts, totalToDownload, localeConfig);
            successCount++;
            
            // Throttle requests to avoid rate limiting (1 second between languages)
            // We make 3 API calls per language (JSON, PO, MO) + download time
            if (totalAttempts < totalToDownload) {
                await sleep(1500); // 1.5 seconds between languages to reduce API pressure
            }
        } catch (error) {
            console.error(`  ❌ ${locale}: ${error.message}`);
            failedLanguages.push(locale);
        }
    }

    // Download English master list as simplified reference for variant syncing
    // (only when downloading all locales, not in single locale mode)
    if (!targetLocale) {
        try {
            await downloadEnglishMasterList();
        } catch (error) {
            console.error('⚠️  English master list download failed (non-critical)');
        }
    }

    console.log(`\n📊 Summary: ${successCount}/${totalToDownload} languages downloaded (${skippedCount} super base skipped)`);

    if (failedLanguages.length > 0 && failedLanguages.length < totalToDownload) {
        console.warn(`\n⚠️  Partial failures: ${failedLanguages.slice(0, 5).join(', ')}${failedLanguages.length > 5 ? '...' : ''}`);
    }

    console.log('✨ Download complete!');
    
    // Only run audit for full downloads (not single locale mode)
    if (!targetLocale) {
        console.log('\n🔍 Running locale audit...');
        const { execSync } = require('child_process');
        try {
            execSync('npm run locale:audit', { stdio: 'inherit' });
        } catch (error) {
            console.error('⚠️  Locale audit failed');
        }
    }
}

main().catch((error) => {
    console.error(`\n❌ Fatal error: ${error.message}`);
    process.exit(1);
});
