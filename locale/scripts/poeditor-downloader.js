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
 * Make HTTPS request to POEditor API
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
            res.on('data', (chunk) => { data += chunk; });
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
async function downloadLanguage(locale, poEditorLocale, current, total) {
    console.log(`  ⏳ Downloading ${locale} (${current} of ${total})...`);
    
    for (const format of FILE_FORMATS) {
        try {
            await downloadLanguageFormat(locale, poEditorLocale, format);
            // Delay between format downloads (500ms to handle 3 formats per language)
            await sleep(500);
        } catch (error) {
            console.error(`    ❌ ${format.ext.toUpperCase()}: ${error.message}`);
            throw error;
        }
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
            await downloadLanguage(locale, poEditorLocale, totalAttempts, totalToDownload);
            successCount++;
            
            // Throttle requests to avoid rate limiting (1 second between languages)
            // We make 3 API calls per language (JSON, PO, MO) + download time
            if (totalAttempts < totalToDownload) {
                await sleep(1000); // 1 second between languages
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
