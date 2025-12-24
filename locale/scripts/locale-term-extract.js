#!/usr/bin/env node

/**
 * ChurchCRM Term Extraction Script
 * 
 * Extracts all translatable terms from ChurchCRM for POEditor upload:
 * 1. Database terms extraction
 * 2. Static data (countries/locales) extraction  
 * 3. PHP source code extraction
 * 4. JavaScript/React terms extraction
 * 5. Merging all translation files
 * 6. Cleanup of temporary files
 * 
 * Output: locale/messages.po file ready for POEditor upload
 */

const fs = require('fs');
const path = require('path');
const { execSync, spawn } = require('child_process');

class TermExtractor {
    constructor() {
        this.projectRoot = path.resolve(__dirname, '../..');
        this.localeDir = path.join(this.projectRoot, 'locale');
        this.messagesFile = path.join(this.localeDir, 'messages.po');
    }

    /**
     * Log with emoji prefix
     */
    log(emoji, message) {
        console.log(`${emoji} ${message}`);
    }

    /**
     * Execute a command and return the output
     */
    exec(command, options = {}) {
        const defaultOptions = {
            cwd: this.projectRoot,
            encoding: 'utf8',
            stdio: 'inherit'
        };
        return execSync(command, { ...defaultOptions, ...options });
    }

    /**
     * Execute a Node.js script
     */
    async execNode(scriptPath) {
        return new Promise((resolve, reject) => {
            const child = spawn('node', [scriptPath], {
                cwd: this.projectRoot,
                stdio: 'inherit'
            });

            child.on('close', (code) => {
                if (code === 0) {
                    resolve();
                } else {
                    reject(new Error(`Script ${scriptPath} exited with code ${code}`));
                }
            });

            child.on('error', reject);
        });
    }

    /**
     * Get temporary directory path from a script
     */
    getTempDir(scriptPath) {
        try {
            const result = execSync(`node ${scriptPath} --temp-dir`, {
                cwd: this.projectRoot,
                encoding: 'utf8'
            });
            return result.trim();
        } catch (error) {
            console.error(`Failed to get temp dir from ${scriptPath}:`, error.message);
            return null;
        }
    }

    /**
     * Check if a file exists
     */
    fileExists(filePath) {
        return fs.existsSync(filePath);
    }

    /**
     * Copy file
     */
    copyFile(src, dest) {
        fs.copyFileSync(src, dest);
    }

    /**
     * Create empty file with content
     */
    createFile(filePath, content) {
        fs.writeFileSync(filePath, content, 'utf8');
    }

    /**
     * Merge PO files using msgcat
     */
    mergePOFiles(file1, file2, output) {
        try {
            this.exec(`msgcat --use-first --no-wrap --sort-output "${file1}" "${file2}" -o "${output}.tmp"`);
            this.exec(`mv "${output}.tmp" "${output}"`);
            return true;
        } catch (error) {
            console.error('Failed to merge PO files:', error.message);
            // Don't continue with a failed merge - the static data won't be included
            return false;
        }
    }

    /**
     * Clean up temporary directory
     */
    cleanup(tempDir) {
        if (tempDir && fs.existsSync(tempDir)) {
            this.log('🧹', `Cleaning up temporary directory: ${tempDir}`);
            this.exec(`rm -rf "${tempDir}"`);
        }
    }

    /**
     * Remove PO entries whose msgid is only numeric characters
     */
    filterOutNumericOnlyTerms(filePath) {
        try {
            if (!this.fileExists(filePath)) {
                return;
            }

            let content = fs.readFileSync(filePath, 'utf8');

            // Split into blocks separated by one or more blank lines
            const blocks = content.split(/\n{2,}/);
            const kept = [];

            for (const block of blocks) {
                // Find the first msgid and collect any continued string lines
                const lines = block.split(/\n/);
                let msgid = null;

                for (let i = 0; i < lines.length; i++) {
                    const m = lines[i].match(/^msgid\s*"([^"]*)"/);
                    if (m) {
                        let s = m[1];
                        let j = i + 1;
                        while (j < lines.length && lines[j].match(/^"([^"]*)"/)) {
                            const m2 = lines[j].match(/^"([^"]*)"/);
                            s += m2 ? m2[1] : '';
                            j++;
                        }
                        msgid = s;
                        break;
                    }
                }

                // If no msgid found, keep the block (e.g., comments)
                if (msgid === null) {
                    kept.push(block);
                    continue;
                }

                // If msgid is purely numeric or numeric ranges like 2012/2013 or 2020-2021, skip it
                // Matches digits optionally separated by '/' or '-' (e.g., 2012, 2012/2013, 2020-2021)
                if (/^\s*\d+(?:[\/\-]\d+)*\s*$/.test(msgid)) {
                    this.log('🧾', `Removing numeric-only term msgid="${msgid}"`);
                    continue;
                }

                kept.push(block);
            }

            const out = kept.join('\n\n') + '\n';
            fs.writeFileSync(filePath, out, 'utf8');
        } catch (error) {
            console.error('Failed to filter numeric-only terms:', error.message);
        }
    }

    /**
     * Extract database terms
     */
    async extractDatabaseTerms() {
        this.log('🗄️', 'Extracting database terms...');
        await this.execNode('locale/scripts/locale-extract-db.js');
        return this.getTempDir('locale/scripts/locale-extract-db.js');
    }

    /**
     * Extract static data (countries and locales)
     */
    async extractStaticData() {
        this.log('🌍', 'Extracting static data (countries and locales)...');
        await this.execNode('locale/scripts/locale-extract-static.js');
        return this.getTempDir('locale/scripts/locale-extract-static.js');
    }

    /**
     * Extract PHP terms
     */
    extractPHPTerms() {
        this.log('📄', 'Extracting and merging PHP terms...');
        
        try {
            // Change to src directory and run xgettext
            const srcDir = path.join(this.projectRoot, 'src');
            const phpFiles = execSync(
                'find . -iname "*.php" | sort | grep -v ./vendor',
                { cwd: srcDir, encoding: 'utf8' }
            ).trim().split('\n').filter(f => f);

            if (phpFiles.length > 0) {
                const filesArg = phpFiles.join(' ');
                execSync(
                    `xgettext --no-location --no-wrap --join-existing --from-code=UTF-8 -o ../locale/messages.po -L PHP ${filesArg}`,
                    { cwd: srcDir, stdio: 'inherit' }
                );
            }
        } catch (error) {
            console.error('PHP extraction failed:', error.message);
        }
    }

    /**
     * Extract JavaScript/React terms
     */
    extractJavaScriptTerms() {
        this.log('⚛️', 'Extracting JavaScript/React terms...');
        
        try {
            // Check if i18next-parser is available
            execSync('which npx', { stdio: 'pipe' });
            
            // Run i18next parser
            this.exec('npx i18next-parser --config locale/i18next-parser.config.js');
            
            // Convert JSON to PO if translation files were created
            const translationJson = path.join(this.localeDir, 'locales/en/translation.json');
            const translationPo = path.join(this.localeDir, 'locales/en/translation.po');
            
            if (this.fileExists(translationJson)) {
                this.exec('npx i18next-conv -l en -s locale/locales/en/translation.json -t locale/locales/en/translation.po');
            }
            
            // Merge with main messages.po if PO file was created
            if (this.fileExists(translationPo)) {
                this.log('🔗', 'Merging JavaScript terms...');
                this.mergePOFiles(this.messagesFile, translationPo, this.messagesFile);
            }
            
            // Cleanup temporary files
            this.log('🧹', 'Cleaning up temporary files...');
            this.exec('rm -f locale/locales/en/translation.*');
            
        } catch (error) {
            this.log('⚠️', 'i18next not found - skipping JavaScript term extraction');
        }
    }

    /**
     * Main execution pipeline
     */
    async run() {
        try {
            this.log('🚀', `Starting term extraction from ${this.projectRoot}`);

            // 1. Extract database terms
            const dbTempDir = await this.extractDatabaseTerms();
            const dbTermsFile = dbTempDir ? path.join(dbTempDir, 'database-terms.po') : null;

            // 2. Extract static data
            const staticTempDir = await this.extractStaticData();
            const staticTermsFile = staticTempDir ? path.join(staticTempDir, 'static-terms.po') : null;

            this.log('📁', `Using database temp directory: ${dbTempDir}`);
            this.log('📁', `Using static temp directory: ${staticTempDir}`);

            // 3. Start with database terms as the base
            if (dbTermsFile && this.fileExists(dbTermsFile)) {
                this.log('📄', 'Starting with database terms...');
                this.copyFile(dbTermsFile, this.messagesFile);
            } else {
                this.log('⚠️', 'No database terms file found, creating empty messages.po');
                this.createFile(this.messagesFile, '# ChurchCRM locale file\n');
            }

            // 4. Merge static data
            if (staticTermsFile && this.fileExists(staticTermsFile)) {
                this.log('🔗', 'Merging static data (countries and locales)...');
                const mergeSuccess = this.mergePOFiles(this.messagesFile, staticTermsFile, this.messagesFile);
                if (!mergeSuccess) {
                    throw new Error('Failed to merge static data - countries and locales will be missing');
                }
            } else {
                this.log('⚠️', 'No static terms file found');
            }

            // 5. Extract and merge PHP terms
            this.extractPHPTerms();

            // 6. Extract and merge JavaScript terms
            this.extractJavaScriptTerms();

            // 7. Final sort to ensure consistent ordering
            this.log('�', 'Sorting messages.po for consistent ordering...');
            // Remove numeric-only msgids before final sort
            this.filterOutNumericOnlyTerms(this.messagesFile);

            this.exec(`msgcat --no-wrap --sort-output "${this.messagesFile}" -o "${this.messagesFile}.tmp"`);
            this.exec(`mv "${this.messagesFile}.tmp" "${this.messagesFile}"`);

            // 8. Cleanup temporary directories
            this.cleanup(dbTempDir);
            this.cleanup(staticTempDir);

            this.log('✅', 'Term extraction completed!');

        } catch (error) {
            console.error('❌ Term extraction failed:', error.message);
            process.exit(1);
        }
    }
}

// Run if called directly
if (require.main === module) {
    const extractor = new TermExtractor();
    extractor.run();
}

module.exports = TermExtractor;