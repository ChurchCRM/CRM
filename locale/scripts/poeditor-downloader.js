#!/usr/bin/env node

/**
 * POEditor Translation Downloader
 * Replaces grunt-poeditor-gd with native Node.js HTTPS (zero external dependencies)
 * 
 * Usage: node locale/scripts/poeditor-downloader.js
 * 
 * Requires:
 * - BuildConfig.json (or BuildConfig.json.example) with POEditor.id and POEditor.token
 * - No additional npm packages (uses native https module)
 */

const fs = require('fs');
const path = require('path');
const https = require('https');
const { URLSearchParams } = require('url');

// Configuration
const CONFIG_FILE = path.join(__dirname, '../../BuildConfig.json');
const CONFIG_EXAMPLE_FILE = path.join(__dirname, '../../BuildConfig.json.example');
const LOCALES_FILE = path.join(__dirname, '../../src/locale/locales.json');
const JSON_OUTPUT_DIR = path.join(__dirname, '../../src/locale/i18n');
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

// Load configuration
let buildConfig;
let configFile = CONFIG_FILE;
try {
    if (!fs.existsSync(CONFIG_FILE)) {
        if (fs.existsSync(CONFIG_EXAMPLE_FILE)) {
            console.log('⚠️  BuildConfig.json not found, using BuildConfig.json.example');
            configFile = CONFIG_EXAMPLE_FILE;
        } else {
            throw new Error('Neither BuildConfig.json nor BuildConfig.json.example exist');
        }
    }
    buildConfig = JSON.parse(fs.readFileSync(configFile, 'utf8'));
} catch (e) {
    console.error(`❌ Error reading configuration: ${e.message}`);
    process.exit(1);
}

const projectId = buildConfig?.POEditor?.id;
const apiToken = buildConfig?.POEditor?.token;

if (!projectId || !apiToken) {
    console.error('❌ Missing POEditor configuration in BuildConfig.json');
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
 * Main execution
 */
async function main() {
    console.log('🌐 POEditor Translation Downloader\n');
    console.log(`Project ID: ${projectId}`);
    console.log(`Formats: ${FILE_FORMATS.map(f => f.ext.toUpperCase()).join(', ')}`);
    console.log(`Output: ${JSON_OUTPUT_DIR}\n`);

    const failedLanguages = [];
    let successCount = 0;
    let totalAttempts = 0;
    let skippedCount = 0;
    
    // Base English languages that have no translations (POEditor doesn't translate to English)
    const baseEnglishLocales = ['en_US'];
    
    // Calculate total locales to download (excluding base English)
    const totalLocales = Object.keys(localesConfig).length;
    const downloadableLocales = Object.values(localesConfig)
        .filter(config => !baseEnglishLocales.includes(config.locale));
    const totalToDownload = downloadableLocales.length;
    
    console.log(`📋 Total locales: ${totalLocales} (${totalToDownload} to download, ${totalLocales - totalToDownload} base English)\n`);

    for (const [key, localeConfig] of Object.entries(localesConfig)) {
        const locale = localeConfig.locale;
        const poEditorLocale = localeConfig.poEditor;

        // Skip base English languages - they have no translations and will download empty
        if (baseEnglishLocales.includes(locale)) {
            console.log(`  ⏭️  Skipping ${locale} (base English language - no translations)`);
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

    console.log(`\n📊 Summary: ${successCount}/${totalToDownload} languages downloaded (${skippedCount} English variants skipped)`);

    if (failedLanguages.length > 0 && failedLanguages.length < totalToDownload) {
        console.warn(`\n⚠️  Partial failures: ${failedLanguages.slice(0, 5).join(', ')}${failedLanguages.length > 5 ? '...' : ''}`);
    }

    console.log('✨ Download complete!');
}

main().catch((error) => {
    console.error(`\n❌ Fatal error: ${error.message}`);
    process.exit(1);
});
