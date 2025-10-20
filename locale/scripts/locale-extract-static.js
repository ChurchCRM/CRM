#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

// Handle command line options
if (process.argv.includes('--temp-dir')) {
    const projectRoot = path.resolve(__dirname, '../..');
    console.log(path.join(projectRoot, 'temp', 'churchcrm-locale-static'));
    process.exit(0);
}

/**
 * Extract static data (countries and locales) for translation
 */
class StaticDataExtractor {
    constructor() {
        // Use project temp directory
        const projectRoot = path.resolve(__dirname, '../..');
        this.stringsDir = path.join(projectRoot, 'temp', 'churchcrm-locale-static');
    }

    /**
     * Ensure the temp strings directory exists
     */
    ensureStringsDirectory() {
        if (!fs.existsSync(this.stringsDir)) {
            fs.mkdirSync(this.stringsDir, { recursive: true });
        }
    }

    /**
     * Escape PO file strings
     */
    escapePo(str) {
        return str.replace(/\\/g, '\\\\')
                  .replace(/"/g, '\\"')
                  .replace(/\n/g, '\\n')
                  .replace(/\t/g, '\\t');
    }

    /**
     * Generate static data PO file
     */
    async generateStaticPoFile() {
        this.ensureStringsDirectory();
        
        const poFile = path.join(this.stringsDir, 'static-terms.po');
        const entries = [];
        
        // Add countries
        console.log('Adding countries...');
        const countries = await this.getCountriesData();
        for (const country of countries) {
            entries.push({
                msgid: this.escapePo(country),
                context: 'countries'
            });
        }
        
        // Add locales
        console.log('Adding locales...');
        const locales = await this.getLocalesData();
        for (const locale of locales) {
            entries.push({
                msgid: this.escapePo(locale),
                context: 'locales'
            });
        }
        
        // Write PO file
        this.writePoFile(poFile, entries);
        
        return poFile;
    }

    /**
     * Write entries to a PO file
     */
    writePoFile(filePath, entries) {
        // Write PO file header
        const header = `# Static data (countries, locales) extracted from ChurchCRM
# Generated automatically - do not edit
#
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\\n"
"Language: \\n"
"Generated-By: ChurchCRM locale-extract-static.js\\n"

`;
        
        fs.writeFileSync(filePath, header);
        
        // Write entries
        for (const entry of entries) {
            const poEntry = `#. Context: ${entry.context}\nmsgid "${entry.msgid}"\nmsgstr ""\n\n`;
            fs.appendFileSync(filePath, poEntry);
        }
        
        console.log(`${filePath} updated with ${entries.length} terms`);
    }

    /**
     * Get countries data from the authoritative Countries.php source
     * This ensures we always use the same data as the main application
     */
    async getCountriesData() {
        const countriesFile = path.join(this.stringsDir, 'countries.json');
        
        // If countries.json doesn't exist, generate it from the PHP source
        if (!fs.existsSync(countriesFile)) {
            console.log('Generating countries.json from PHP Countries class...');
            try {
                const { execSync } = require('child_process');
                const phpCommand = `php -r "
                    require 'src/ChurchCRM/data/Countries.php';
                    require 'src/ChurchCRM/data/Country.php';
                    \\$countries = ChurchCRM\\data\\Countries::getNames();
                    echo json_encode(array_values(\\$countries), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                "`;
                
                const output = execSync(phpCommand, { 
                    cwd: path.resolve(__dirname, '../..'),
                    encoding: 'utf8' 
                });
                
                fs.writeFileSync(countriesFile, output);
                console.log('Generated countries.json from authoritative PHP source');
            } catch (error) {
                console.error('Failed to generate countries.json from PHP:', error.message);
                return [];
            }
        }

        try {
            const countriesData = fs.readFileSync(countriesFile, 'utf8');
            return JSON.parse(countriesData);
        } catch (error) {
            console.error('Failed to read countries.json:', error.message);
            return [];
        }
    }

    /**
     * Get locales data from locales.json
     */
    async getLocalesData() {
        try {
            const localesPath = path.resolve(__dirname, '../../src/locale/locales.json');
            const localesData = fs.readFileSync(localesPath, 'utf8');
            const locales = JSON.parse(localesData);
            
            // Extract locale names for translation
            return Object.keys(locales);
        } catch (error) {
            console.error('Failed to read locales.json:', error.message);
            return [];
        }
    }

    /**
     * Cleanup temporary files
     */
    cleanup() {
        if (fs.existsSync(this.stringsDir)) {
            const { execSync } = require('child_process');
            execSync(`rm -rf "${this.stringsDir}"`, { stdio: 'inherit' });
        }
    }
}

// Main execution
async function main() {
    const extractor = new StaticDataExtractor();
    
    try {
        console.log('=====================================================');
        console.log('======== Building static locale data started =======');
        console.log('=====================================================\n');

        const poFile = await extractor.generateStaticPoFile();
        console.log(`\n${poFile} created with static data terms`);

        console.log('\n=====================================================');
        console.log('========   Building static locale data end   =======');
        console.log('=====================================================\n');
        
    } catch (error) {
        console.error('Error:', error.message);
        process.exit(1);
    }
}

// Run if called directly
if (require.main === module) {
    main();
}

module.exports = StaticDataExtractor;