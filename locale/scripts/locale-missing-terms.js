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
 * Output: /locale/missing-terms/poeditor-{locale}.json
 * 
 * Usage: node locale-missing-terms.js
 */

const fs = require('fs');
const path = require('path');

const LOCALES_JSON = path.join(__dirname, '../../src/locale/locales.json');
const MESSAGES_JSON = path.join(__dirname, '../messages.json');
const I18N_DIR = path.join(__dirname, '../../src/locale/i18n');
const OUTPUT_DIR = path.join(__dirname, '../missing-terms');

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

function saveMissingTermsFile(poEditorCode, missingTerms) {
    if (!fs.existsSync(OUTPUT_DIR)) {
        fs.mkdirSync(OUTPUT_DIR, { recursive: true });
    }
    
    const filename = path.join(OUTPUT_DIR, `poeditor-${poEditorCode}.json`);
    fs.writeFileSync(filename, JSON.stringify(missingTerms, null, 2));
    return filename;
}

function main() {
    console.log(`📋 Analyzing missing translations for all locales...\n`);
    
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
    
    let totalMissing = 0;
    let filesGenerated = 0;
    const results = [];
    
    // Process each locale
    Object.entries(localesConfig).forEach(([localeName, config]) => {
        const poEditorCode = config.poEditor;
        const locale = config.locale;
        
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
        
        // Generate missing terms file
        const missingFile = saveMissingTermsFile(poEditorCode, missingTerms);
        filesGenerated++;
        totalMissing += missingKeys.length;
        
        const missingPercent = Math.round((missingKeys.length / masterTermCount) * 100);
        console.log(`📄 ${localeName} (${poEditorCode})`);
        console.log(`   → Missing: ${missingKeys.length}/${masterTermCount} terms (${missingPercent}%)`);
        console.log(`   → File: ${path.relative(process.cwd(), missingFile)}\n`);
        
        results.push({
            locale: localeName,
            code: poEditorCode,
            missing: missingKeys.length,
            total: localeTermCount
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
