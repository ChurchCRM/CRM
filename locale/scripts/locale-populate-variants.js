#!/usr/bin/env node

/**
 * Generate missing translation terms in locale variants for POEditor upload
 * 
 * This script:
 * 1. Reads locales.json and identifies base locales (marked with "base": true)
 * 2. For each base locale, finds all its variants
 * 3. Compares base locale i18n JSON with variant i18n JSON (READ-ONLY)
 * 4. Creates JSON files with missing terms from base (for POEditor upload)
 * 
 * NOTE: src/locale/i18n/ files are auto-generated - we only read them
 * The generated missing-terms files should be imported into POEditor manually
 * 
 * Usage: node locale-populate-variants.js
 */

const fs = require('fs');
const path = require('path');

const LOCALES_JSON = path.join(__dirname, '../../src/locale/locales.json');
const I18N_DIR = path.join(__dirname, '../../src/locale/i18n');
const OUTPUT_DIR = path.join(__dirname, '../base-term-updates');

function getBaseLocales() {
    const localesJson = JSON.parse(fs.readFileSync(LOCALES_JSON, 'utf8'));
    const baseLocales = {};

    // Identify base locales
    Object.entries(localesJson).forEach(([name, config]) => {
        if (config.base === true) {
            const poEditor = config.poEditor.split('-')[0];
            const locale = config.locale; // e.g., "es_ES", "pt_PT", "en_GB"
            
            if (!baseLocales[poEditor]) {
                baseLocales[poEditor] = {
                    name: name,
                    config: config,
                    locale: locale,
                    variants: []
                };
            }
        }
    });

    // Find variants for each base locale
    Object.entries(localesJson).forEach(([name, config]) => {
        Object.values(baseLocales).forEach(base => {
            const variantLangCode = config.languageCode.split('-')[0];
            const baseLangCode = base.config.languageCode.split('-')[0];
            
            if (variantLangCode === baseLangCode && config.poEditor !== base.config.poEditor && !config.base) {
                base.variants.push({
                    name: name,
                    poEditor: config.poEditor,
                    locale: config.locale, // e.g., "es_MX", "es_AR"
                    config: config
                });
            }
        });
    });

    return baseLocales;
}

function loadI18nFile(locale) {
    const i18nFile = path.join(I18N_DIR, `${locale}.json`);
    
    if (!fs.existsSync(i18nFile)) {
        return null;
    }
    
    try {
        return JSON.parse(fs.readFileSync(i18nFile, 'utf8'));
    } catch (err) {
        console.error(`  ✗ Error reading ${locale}.json: ${err.message}`);
        return null;
    }
}

function saveMissingTermsFile(variantCode, baseCode, missingTerms) {
    if (!fs.existsSync(OUTPUT_DIR)) {
        fs.mkdirSync(OUTPUT_DIR, { recursive: true });
    }
    
    const filename = path.join(OUTPUT_DIR, `${variantCode}-missing-from-${baseCode}.json`);
    fs.writeFileSync(filename, JSON.stringify(missingTerms, null, 2));
    return filename;
}

function main() {
    console.log(`🌍 Scanning for missing translation terms in variants...\n`);
    console.log('📝 Note: src/locale/i18n/ files are read-only (auto-generated)\n');
    console.log('📤 Generated files are for POEditor import\n');
    
    const baseLocales = getBaseLocales();
    let totalMissing = 0;
    let filesGenerated = 0;
    
    Object.entries(baseLocales).forEach(([baseLangCode, baseInfo]) => {
        console.log(`\n📌 Base: ${baseInfo.name} (${baseInfo.config.poEditor} → ${baseInfo.locale})`);
        
        const baseTranslation = loadI18nFile(baseInfo.locale);
        if (!baseTranslation) {
            console.log(`   ✗ No i18n file found: ${baseInfo.locale}.json`);
            return;
        }
        
        const baseTermCount = Object.keys(baseTranslation).length;
        console.log(`   ✓ Base has ${baseTermCount} terms`);
        
        if (baseInfo.variants.length === 0) {
            console.log('   No variants found');
            return;
        }
        
        baseInfo.variants.forEach(variant => {
            const variantCode = variant.poEditor;
            const variantLocale = variant.locale;
            const variantTranslation = loadI18nFile(variantLocale);
            
            if (!variantTranslation) {
                console.log(`   ℹ️  ${variant.name} (${variantCode} → ${variantLocale}) - no i18n file yet`);
                return;
            }
            
            const variantTermCount = Object.keys(variantTranslation).length;
            
            // Find missing terms
            const missingTerms = {};
            const missingKeys = [];
            
            Object.entries(baseTranslation).forEach(([key, value]) => {
                if (!(key in variantTranslation)) {
                    missingTerms[key] = value;
                    missingKeys.push(key);
                }
            });
            
            if (missingKeys.length === 0) {
                console.log(`   ✓ ${variant.name} (${variantCode}) - complete (${variantTermCount}/${baseTermCount} terms)`);
                return;
            }
            
            totalMissing += missingKeys.length;
            
            // Generate missing terms file for POEditor upload
            const missingFile = saveMissingTermsFile(variantCode, baseLangCode, missingTerms);
            filesGenerated++;
            
            console.log(`   📄 ${variant.name} (${variantCode}) - ${missingKeys.length} missing terms (${variantTermCount}/${baseTermCount})`);
            console.log(`      → File: ${path.relative(process.cwd(), missingFile)}`);
        });
    });
    
    console.log(`\n📊 Summary: ${totalMissing} missing terms across ${filesGenerated} variant files`);
    console.log(`📤 Import the generated JSON files into POEditor to add the missing terms\n`);
}

main();



