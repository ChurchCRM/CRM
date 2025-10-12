#!/usr/bin/env node

/**
 * ChurchCRM Database Term Extraction Tool
 * 
 * Extracts translatable terms from the database and generates PHP files
 * with gettext() calls for inclusion in the locale generation process.
 * 
 * This replaces the PHP-based extract-db-locale-terms.php with a modern
 * Node.js implementation that:
 * - Connects to MySQL using the mysql2 package
 * - Extracts terms from database tables
 * - Generates PHP files with gettext() calls
 * - Handles countries and locales data
 * 
 * Output: Creates PHP files in db-strings/ directory for xgettext processing
 */

const fs = require('fs');
const mysql = require('mysql2/promise');
const os = require('os');
const path = require('path');

// Handle command line options
if (process.argv.includes('--temp-dir')) {
    const projectRoot = path.resolve(__dirname, '../..');
    console.log(path.join(projectRoot, 'temp', 'churchcrm-locale-db-strings'));
    process.exit(0);
}

class DatabaseTermExtractor {
    constructor() {
        this.configPath = path.resolve(__dirname, '../../BuildConfig.json');
        // Use project temp directory instead of OS temp
        const projectRoot = path.resolve(__dirname, '../..');
        this.stringsDir = path.join(projectRoot, 'temp', 'churchcrm-locale-db-strings');
        this.stringFiles = [];
        this.connection = null;
    }

    /**
     * Load database configuration from BuildConfig.json
     */
    loadConfig() {
        console.log('=====================================================');
        console.log('========== Building locale from DB started ==========');
        console.log('=====================================================\n');

        if (!fs.existsSync(this.configPath)) {
            throw new Error(`ERROR: The file ${this.configPath} does not exist`);
        }

        const buildConfig = fs.readFileSync(this.configPath, 'utf8');
        const config = JSON.parse(buildConfig);

        if (!config.Env?.local?.database) {
            throw new Error(`ERROR: The file ${this.configPath} does not have local db env, check ${this.configPath}.example for schema`);
        }

        return config.Env.local.database;
    }

    /**
     * Connect to MySQL database
     */
    async connectDatabase(dbConfig) {
        this.connection = await mysql.createConnection({
            host: dbConfig.server,
            port: dbConfig.port,
            database: dbConfig.database,
            user: dbConfig.user,
            password: dbConfig.password,
            charset: 'utf8mb4'
        });

        console.log('Database connection established');
    }

    /**
     * Create db-strings directory if it doesn't exist
     */
    ensureStringsDirectory() {
        if (!fs.existsSync(this.stringsDir)) {
            fs.mkdirSync(this.stringsDir, { recursive: true });
        }
    }

    /**
     * Main execution method - generates PO file directly
     */
    async run() {
        try {
            const dbConfig = this.loadConfig();
            await this.connectDatabase(dbConfig);
            
            // Generate PO file directly instead of creating PHP files
            const poFile = await this.generateDatabasePoFile();
            console.log(`\n${poFile} created with database terms`);
            
            await this.connection.end();
            
            console.log('\n=====================================================');
            console.log('==========   Building locale from DB end   ==========');
            console.log('=====================================================\n');
            
        } catch (error) {
            console.error('Error:', error.message);
            if (this.connection) {
                await this.connection.end();
            }
            process.exit(1);
        }
    }
    
    /**
     * Generate a .po file directly with all database terms
     */
    async generateDatabasePoFile() {
        this.ensureStringsDirectory();
        
        const poFile = path.join(this.stringsDir, 'database-terms.po');
        const entries = [];
        
        // Extract database terms
        console.log('Extracting database terms...');
        const [rows] = await this.connection.execute(this.getDatabaseQuery());
        console.log('DB read complete');
        
        for (const row of rows) {
            if (row.term && row.term.trim() !== '') {
                entries.push({
                    msgid: this.escapePo(row.term.trim()),
                    context: row.cntx || 'database'
                });
            }
        }
        
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
        const header = `# Database terms extracted from ChurchCRM
# Generated automatically - do not edit
#
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\\n"
"Language: \\n"
"Generated-By: ChurchCRM locale-extract-db.js\\n"

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
     * Get locales data from locales.json
     */
    async getLocalesData() {
        try {
            const localesPath = path.resolve(__dirname, '../../src/locale/locales.json');
            const localesData = fs.readFileSync(localesPath, 'utf8');
            const locales = JSON.parse(localesData);
            return Object.keys(locales);
        } catch (error) {
            console.error('Failed to load locales:', error.message);
            return [];
        }
    }
    
    /**
     * Escape strings for PO file format
     */
    escapePo(str) {
        if (!str) return '';
        return str.replace(/\\/g, '\\\\')
                  .replace(/"/g, '\\"')
                  .replace(/\n/g, '\\n')
                  .replace(/\r/g, '\\r')
                  .replace(/\t/g, '\\t');
    }
    
    /**
     * Get the database query for extracting terms
     */
    getDatabaseQuery() {
        return `
            SELECT DISTINCT ucfg_tooltip AS term, "userconfig_ucfg" AS cntx FROM userconfig_ucfg
            WHERE ucfg_tooltip IS NOT NULL AND ucfg_tooltip != ""
            UNION ALL
            SELECT DISTINCT qry_Name AS term, "query_qry" AS cntx FROM query_qry
            WHERE qry_Name IS NOT NULL AND qry_Name != ""
            UNION ALL
            SELECT DISTINCT qry_Description AS term, "query_qry" AS cntx FROM query_qry
            WHERE qry_Description IS NOT NULL AND qry_Description != ""
            UNION ALL
            SELECT DISTINCT qpo_Display AS term, "queryparameteroptions_qpo" AS cntx FROM queryparameteroptions_qpo
            WHERE qpo_Display IS NOT NULL AND qpo_Display != ""
            UNION ALL
            SELECT DISTINCT qrp_Name AS term, "queryparameters_qrp" AS cntx FROM queryparameters_qrp
            WHERE qrp_Name IS NOT NULL AND qrp_Name != ""
            UNION ALL
            SELECT DISTINCT qrp_Description AS term, "queryparameters_qrp" AS cntx FROM queryparameters_qrp
            WHERE qrp_Description IS NOT NULL AND qrp_Description != ""
        `;
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
                console.log('Falling back to static list...');
                return this.getFallbackCountries();
            }
        }
        
        try {
            const countriesData = fs.readFileSync(countriesFile, 'utf8');
            return JSON.parse(countriesData);
        } catch (error) {
            console.error('Failed to read countries.json:', error.message);
            return this.getFallbackCountries();
        }
    }
    
    /**
     * Fallback country list in case PHP extraction fails
     */
    getFallbackCountries() {
        return [
            'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Argentina', 'Armenia', 'Australia',
            'Austria', 'Azerbaijan', 'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados', 'Belarus', 'Belgium',
            'Belize', 'Benin', 'Bhutan', 'Bolivia', 'Bosnia and Herzegovina', 'Botswana', 'Brazil', 'Brunei',
            'Bulgaria', 'Burkina Faso', 'Burundi', 'Cambodia', 'Cameroon', 'Canada', 'Cape Verde', 'Chad',
            'Chile', 'China', 'Colombia', 'Comoros', 'Congo', 'Costa Rica', 'Croatia', 'Cuba', 'Cyprus',
            'Czech Republic', 'Denmark', 'Djibouti', 'Dominica', 'Dominican Republic', 'Ecuador', 'Egypt',
            'El Salvador', 'Estonia', 'Ethiopia', 'Fiji', 'Finland', 'France', 'Gabon', 'Gambia', 'Georgia',
            'Germany', 'Ghana', 'Greece', 'Grenada', 'Guatemala', 'Guinea', 'Guyana', 'Haiti', 'Honduras',
            'Hungary', 'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Israel', 'Italy',
            'Jamaica', 'Japan', 'Jordan', 'Kazakhstan', 'Kenya', 'Kuwait', 'Latvia', 'Lebanon', 'Libya',
            'Lithuania', 'Luxembourg', 'Madagascar', 'Malaysia', 'Maldives', 'Mali', 'Malta', 'Mexico',
            'Moldova', 'Monaco', 'Mongolia', 'Montenegro', 'Morocco', 'Myanmar', 'Nepal', 'Netherlands',
            'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'Norway', 'Oman', 'Pakistan', 'Panama',
            'Paraguay', 'Peru', 'Philippines', 'Poland', 'Portugal', 'Qatar', 'Romania', 'Russia',
            'Saudi Arabia', 'Senegal', 'Serbia', 'Singapore', 'Slovakia', 'Slovenia', 'South Africa',
            'South Korea', 'Spain', 'Sri Lanka', 'Sudan', 'Sweden', 'Switzerland', 'Syria', 'Taiwan',
            'Tanzania', 'Thailand', 'Tunisia', 'Turkey', 'Uganda', 'Ukraine', 'United Arab Emirates',
            'United Kingdom', 'United States', 'Uruguay', 'Venezuela', 'Vietnam', 'Yemen', 'Zambia', 'Zimbabwe'
        ];
    }

    /**
     * PHP addslashes equivalent
     */
    addslashes(str) {
        if (!str) return '';
        return str.replace(/\\/g, '\\\\')
                  .replace(/'/g, "\\'")
                  .replace(/"/g, '\\"')
                  .replace(/\0/g, '\\0');
    }
}

// Run the extractor if this file is executed directly
if (require.main === module) {
    // Check for command line arguments
    if (process.argv.includes('--temp-dir')) {
        const extractor = new DatabaseTermExtractor();
        console.log(extractor.stringsDir);
        process.exit(0);
    }
    
    const extractor = new DatabaseTermExtractor();
    extractor.run();
}

module.exports = DatabaseTermExtractor;