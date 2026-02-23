#!/usr/bin/env node

/**
 * Generate missing translation terms for all locales
 * 
 * This script:
 * 1. Reads messages.json (English master term list from POEditor)
 * 2. For each locale in locales.json, checks the corresponding i18n JSON file
 * 3. Compares to find untranslated terms
 * 4. Creates JSON files with missing terms for POEditor import
 * 
 * Output: /locale/terms/missing/{locale}/
 * 
 * Usage: node locale-build-missing.js
 */

const fs = require('fs');
const path = require('path');
const config = require('./locale-config');

const LOCALES_JSON = config.localesJson;
const MESSAGES_JSON = config.messagesJson;
const I18N_DIR = config.i18nDir;
const OUTPUT_DIR = config.terms.missingNew;

function loadJSON(filePath) {
    if (!fs.existsSync(filePath)) {
        return null;
    }
    
    try {
        return JSON.parse(fs.readFileSync(filePath, 'utf8'));
    } catch (err) {
        console.error(`  ✗ Error reading ${path.basename(filePath)}: ${err.message}`);
        return null;
    }
}

function loadLocalesConfig() {
    try {
        return JSON.parse(fs.readFileSync(LOCALES_JSON, 'utf8'));
    } catch (err) {
        console.error(`❌ Error reading locales.json: ${err.message}`);
        process.exit(1);
    }
}

function saveMissingTermsFileBatched(poEditorCode, missingTerms) {
    // Ensure output directory for this status exists
    if (!fs.existsSync(OUTPUT_DIR)) {
        fs.mkdirSync(OUTPUT_DIR, { recursive: true });
    }

    // Create a subdirectory for this locale code to group batches
    const localeOutDir = path.join(OUTPUT_DIR, poEditorCode);
    if (!fs.existsSync(localeOutDir)) {
        fs.mkdirSync(localeOutDir, { recursive: true });
    }

    // Remove any existing batch files for this locale-status to ensure a clean rebuild
    const existingBatches = fs.readdirSync(localeOutDir).filter(f => f.endsWith('.json'));
    existingBatches.forEach(f => {
        try { fs.unlinkSync(path.join(localeOutDir, f)); } catch (e) { /* ignore */ }
    });

    const TERMS_PER_FILE = config.settings.missingTermsBatchSize;
    const entries = Object.entries(missingTerms);
    const files = [];
    let batchNumber = 1;

    for (let i = 0; i < entries.length; i += TERMS_PER_FILE) {
        const batch = Object.fromEntries(entries.slice(i, i + TERMS_PER_FILE));
        const filename = path.join(localeOutDir, `${poEditorCode}-${batchNumber}.json`);
        fs.writeFileSync(filename, JSON.stringify(batch, null, 2));
        files.push({ number: batchNumber, filename, count: Object.keys(batch).length });
        batchNumber++;
    }

    return files;
}

function cleanupMissingTermsDir() {
    // Clean the missing terms folder (remove per-locale subfolders and files)
    if (fs.existsSync(OUTPUT_DIR)) {
        const entries = fs.readdirSync(OUTPUT_DIR);
        entries.forEach(entry => {
            const entryPath = path.join(OUTPUT_DIR, entry);
            try {
                // remove files or directories recursively
                const stat = fs.statSync(entryPath);
                if (stat.isDirectory()) {
                    const { execSync } = require('child_process');
                    execSync(`rm -rf "${entryPath}"`);
                } else {
                    fs.unlinkSync(entryPath);
                }
            } catch (e) {
                // ignore errors
            }
        });
        console.log(`🧹 Cleaned up existing missing-terms files in ${OUTPUT_DIR}\n`);
    }
}

function main() {
    console.log(`📋 Analyzing missing translations for all locales...\n`);
    
    // Clean up existing missing-terms files
    cleanupMissingTermsDir();
    
    // Load master English terms
    const masterTerms = loadJSON(MESSAGES_JSON);
    if (!masterTerms) {
        console.error(`❌ Could not load messages.json`);
        console.log(`   Make sure to run: npm run locale:download\n`);
        process.exit(1);
    }
    
    const masterTermCount = Object.keys(masterTerms).length;
    console.log(`📝 Master term list: ${masterTermCount} terms\n`);
    
    // Load locales configuration
    const localesConfig = loadLocalesConfig();

    // Determine which locales are actually present in the system (i18n files)
    let installedLocales = [];
    if (fs.existsSync(I18N_DIR)) {
        installedLocales = fs.readdirSync(I18N_DIR)
            .filter(f => f.endsWith('.json'))
            .map(f => path.basename(f, '.json'));
    }
    console.log(`ℹ️ Found ${installedLocales.length} installed i18n locale files in ${I18N_DIR}\n`);
    
    let totalMissing = 0;
    let filesGenerated = 0;
    const results = [];
    
    // Process each locale (only those part of the running system)
    Object.entries(localesConfig).forEach(([localeName, config]) => {
        const poEditorCode = config.poEditor;
        const locale = config.locale;
        // Skip locales not installed on this system (no i18n file)
        if (!installedLocales.includes(locale)) {
            console.log(`⏭️  ${localeName} (${poEditorCode}) - skipped (not installed: ${locale})`);
            results.push({ locale: localeName, code: poEditorCode, missing: 0, total: 0, skipped: true });
            return;
        }
        
        // Skip locales marked for audit skipping (e.g., English variants)
        if (config.skip_audit === true) {
            console.log(`⏭️  ${localeName} (${poEditorCode}) - skipped (audit disabled)`);
            return;
        }
        
        // Load the i18n file for this locale
        const i18nFile = path.join(I18N_DIR, `${locale}.json`);
        const localeTerms = loadJSON(i18nFile);
        
        if (!localeTerms) {
            console.log(`⚠️  ${localeName} (${poEditorCode})`);
            console.log(`   → No i18n file: ${locale}.json`);
            return;
        }
        
        const localeTermCount = Object.keys(localeTerms).length;
        
        // Find missing terms (in master but not in locale, or empty value in locale)
        const missingTerms = {};
        const missingKeys = [];
        
        Object.entries(masterTerms).forEach(([key, value]) => {
            // Count as missing if:
            // 1. Key doesn't exist in locale, OR
            // 2. Key exists but value is empty/whitespace
            const localeValue = localeTerms[key];
            const hasTranslation = localeValue && 
                typeof localeValue === 'string' && 
                localeValue.trim() !== '';
            
            if (!hasTranslation) {
                missingTerms[key] = value;
                missingKeys.push(key);
            }
        });
        
        if (missingKeys.length === 0) {
            console.log(`✅ ${localeName} (${poEditorCode}) - complete (${localeTermCount}/${masterTermCount} terms)`);
            results.push({
                locale: localeName,
                code: poEditorCode,
                missing: 0,
                total: localeTermCount
            });
            return;
        }

        // Skip writing file if fewer than 10 missing terms
        if (missingKeys.length < 10) {
            console.log(`⏭️  ${localeName} (${poEditorCode}) - skipped (only ${missingKeys.length} missing terms, minimum 10 required)`);
            results.push({
                locale: localeName,
                code: poEditorCode,
                missing: missingKeys.length,
                total: localeTermCount,
                skipped: true
            });
            return;
        }

        // Generate missing terms files (batched into 150 terms per file)
        const missingFiles = saveMissingTermsFileBatched(poEditorCode, missingTerms);
        filesGenerated += missingFiles.length;
        totalMissing += missingKeys.length;
        
        const missingPercent = Math.round((missingKeys.length / masterTermCount) * 100);
        console.log(`📄 ${localeName} (${poEditorCode})`);
        console.log(`   → Missing: ${missingKeys.length}/${masterTermCount} terms (${missingPercent}%)`);
        missingFiles.forEach(file => {
            console.log(`   → File: ${path.relative(process.cwd(), file.filename)} (${file.count} terms)`);
        });
        console.log();
        
        results.push({
            locale: localeName,
            code: poEditorCode,
            missing: missingKeys.length,
            total: localeTermCount,
            batches: missingFiles.length
        });
    });
    
    // Print summary
    console.log(`\n📊 Summary:`);
    console.log(`   Generated: ${filesGenerated} missing-terms files`);
    console.log(`   Total missing terms across all locales: ${totalMissing}`);
    console.log(`\n📤 Import the missing-terms files into POEditor to complete translations\n`);
    
    // Print completion status by locale
    const complete = results.filter(r => r.missing === 0).length;
    const incomplete = results.filter(r => r.missing > 0).length;
    console.log(`✅ Complete: ${complete} locales`);
    console.log(`❌ Incomplete: ${incomplete} locales\n`);
}

main();
