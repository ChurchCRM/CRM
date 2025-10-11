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
const path = require('path');
const mysql = require('mysql2/promise');

class DatabaseTermExtractor {
    constructor() {
        this.configPath = path.resolve(__dirname, '../../BuildConfig.json');
        this.stringsDir = path.resolve(__dirname, '../db-strings');
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
     * Extract terms from database tables
     */
    async extractDatabaseTerms() {
        const query = `
            SELECT DISTINCT ucfg_tooltip AS term, "" AS translation, "userconfig_ucfg" AS cntx FROM userconfig_ucfg
            UNION ALL
            SELECT DISTINCT qry_Name AS term, "" AS translation, "query_qry" AS cntx FROM query_qry
            UNION ALL
            SELECT DISTINCT qry_Description AS term, "" AS translation, "query_qry" AS cntx FROM query_qry
            UNION ALL
            SELECT DISTINCT qpo_Display AS term, "" AS translation, "queryparameteroptions_qpo" AS cntx FROM queryparameteroptions_qpo
            UNION ALL
            SELECT DISTINCT qrp_Name AS term, "" AS translation, "queryparameters_qrp" AS cntx FROM queryparameters_qrp
            UNION ALL
            SELECT DISTINCT qrp_Description AS term, "" AS translation, "queryparameters_qrp" AS cntx FROM queryparameters_qrp
        `;

        const [rows] = await this.connection.execute(query);
        console.log('DB read complete');

        // Process each term
        for (const row of rows) {
            const stringFile = path.join(this.stringsDir, `${row.cntx}.php`);
            
            // Create file with PHP opening tag if it doesn't exist
            if (!fs.existsSync(stringFile)) {
                fs.writeFileSync(stringFile, "<?php\r\n");
                this.stringFiles.push(stringFile);
            }

            // Escape the term for PHP and add gettext call
            const dbTerm = this.addslashes(row.term);
            fs.appendFileSync(stringFile, `gettext('${dbTerm}');\r\n`);
        }

        // Add PHP closing tags to all created files
        for (const stringFile of this.stringFiles) {
            fs.appendFileSync(stringFile, "\r\n?>\r\n");
        }
    }

    /**
     * Generate countries translation file
     */
    async generateCountriesFile() {
        // Load countries data from the JSON equivalent
        // Since we can't require PHP classes, we'll need to get this data differently
        const countriesFile = path.join(this.stringsDir, 'settings-countries.php');
        fs.writeFileSync(countriesFile, "<?php\r\n");

        // We'll need to get countries data from a different source
        // For now, let's load it from a potential JSON file or hardcode common countries
        const countries = await this.getCountriesData();
        
        for (const country of countries) {
            const countryTerm = this.addslashes(country);
            fs.appendFileSync(countriesFile, `gettext('${countryTerm}');\r\n`);
        }

        fs.appendFileSync(countriesFile, "\r\n?>\r\n");
        console.log(`${countriesFile} updated`);
    }

    /**
     * Generate locales translation file
     */
    async generateLocalesFile() {
        const stringFile = path.join(this.stringsDir, 'settings-locales.php');
        fs.writeFileSync(stringFile, "<?php\r\n");

        const localesPath = path.resolve(__dirname, '../../src/locale/locales.json');
        const localesData = fs.readFileSync(localesPath, 'utf8');
        const locales = JSON.parse(localesData);

        for (const key in locales) {
            fs.appendFileSync(stringFile, `gettext('${key}');\r\n`);
        }

        fs.appendFileSync(stringFile, "\r\n?>\r\n");
        console.log(`${stringFile} updated`);
    }

    /**
     * Get countries data (replacement for PHP Countries class)
     */
    async getCountriesData() {
        // For now, return a basic set of countries
        // This could be enhanced to read from a JSON file or API
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

    /**
     * Main extraction process
     */
    async run() {
        try {
            // Load configuration
            const dbConfig = this.loadConfig();

            // Setup
            this.ensureStringsDirectory();
            await this.connectDatabase(dbConfig);

            // Extract terms
            await this.extractDatabaseTerms();
            await this.generateCountriesFile();
            await this.generateLocalesFile();

            // Cleanup
            if (this.connection) {
                await this.connection.end();
            }

            console.log('\n=====================================================');
            console.log('==========   Building locale from DB end   ==========');
            console.log('=====================================================\n');

        } catch (error) {
            console.error('Error:', error.message);
            process.exit(1);
        }
    }
}

// Run the extractor if this file is executed directly
if (require.main === module) {
    const extractor = new DatabaseTermExtractor();
    extractor.run();
}

module.exports = DatabaseTermExtractor;