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
        this.configExamplePath = path.resolve(__dirname, '../../BuildConfig.json.example');
        // Use project temp directory instead of OS temp
        const projectRoot = path.resolve(__dirname, '../..');
        this.stringsDir = path.join(projectRoot, 'temp', 'churchcrm-locale-db-strings');
        this.stringFiles = [];
        this.connection = null;
    }

    /**
     * Load database configuration from BuildConfig.json or BuildConfig.json.example
     */
    loadConfig() {
        console.log('=====================================================');
        console.log('========== Building locale from DB started ==========');
        console.log('=====================================================\n');

        let configFile = this.configPath;
        if (!fs.existsSync(this.configPath)) {
            if (fs.existsSync(this.configExamplePath)) {
                console.log(`⚠️  BuildConfig.json not found, using BuildConfig.json.example\n`);
                configFile = this.configExamplePath;
            } else {
                throw new Error(`ERROR: Neither ${this.configPath} nor ${this.configExamplePath} exist`);
            }
        }

        const buildConfig = fs.readFileSync(configFile, 'utf8');
        const config = JSON.parse(buildConfig);

        if (!config.Env?.local?.database) {
            throw new Error(`ERROR: The configuration file does not have local db env, check BuildConfig.json.example for schema`);
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
     * Generate a .po file directly with database terms only
     */
    async generateDatabasePoFile() {
        this.ensureStringsDirectory();
        
        const poFile = path.join(this.stringsDir, 'database-terms.po');
        const entries = [];
        const seenTerms = new Map(); // Track terms to avoid duplicates
        
        // Extract database terms only
        console.log('Extracting database terms...');
        const [rows] = await this.connection.execute(this.getDatabaseQuery());
        console.log('DB read complete');
        
        for (const row of rows) {
            if (row.term && row.term.trim() !== '') {
                const term = row.term.trim();
                const context = row.cntx || 'database';
                
                // Use the first occurrence of each term (like msgcat --use-first)
                if (!seenTerms.has(term)) {
                    seenTerms.set(term, context);
                    entries.push({
                        msgid: this.escapePo(term),
                        context: context
                    });
                }
            }
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
}

// Run the extractor if this file is executed directly
if (require.main === module) {
    const extractor = new DatabaseTermExtractor();
    extractor.run();
}

module.exports = DatabaseTermExtractor;