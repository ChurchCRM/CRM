#!/usr/bin/env node

/**
 * ChurchCRM Term Extraction Script
 * 
 * Extracts all translatable terms from ChurchCRM for POEditor upload:
 * 1. Database terms extraction
 * 2. Static data (countries/locales) extraction
 * 3. Plugin help (help.json) extraction
 * 4. PHP source code extraction
 * 5. JavaScript/React terms extraction
 * 6. Merging all translation files
 * 7. Cleanup of temporary files (locale/.work/)
 *
 * Output: locale/messages.po file ready for POEditor upload
 */

const fs = require('fs');
const path = require('path');
const { execSync, spawn, spawnSync } = require('child_process');
const config = require('./locale-config');

class TermExtractor {
    constructor() {
        this.projectRoot = config.projectRoot;
        this.localeDir = config.localeRoot;
        this.messagesFile = config.messagesPo;
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
     * (filters out dotenv warnings that may be mixed with stdout)
     */
    getTempDir(scriptPath) {
        try {
            const result = execSync(`node ${scriptPath} --temp-dir`, {
                cwd: this.projectRoot,
                encoding: 'utf8',
                stdio: ['pipe', 'pipe', 'pipe']
            });
            // Extract the last non-empty line (the actual path)
            // dotenv warnings may be printed to stdout along with the path
            const lines = result.trim().split('\n').filter(line => line.trim());
            return lines.length > 0 ? lines[lines.length - 1] : null;
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
     * Merge PO files using msgcat — throws on failure so callers always exit cleanly.
     */
    mergePOFiles(file1, file2, output) {
        this.exec(`msgcat --use-first --no-wrap --sort-output "${file1}" "${file2}" -o "${output}.tmp"`);
        this.exec(`mv "${output}.tmp" "${output}"`);
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
        await this.execNode('locale/scripts/locale-build-db.js');
        return this.getTempDir('locale/scripts/locale-build-db.js');
    }

    /**
     * Extract static data (countries and locales)
     */
    async extractStaticData() {
        this.log('🌍', 'Extracting static data (countries and locales)...');
        await this.execNode('locale/scripts/locale-build-static.js');
        return this.getTempDir('locale/scripts/locale-build-static.js');
    }

    /**
     * Extract plugin help terms from help.json files
     */
    async extractPluginHelpTerms() {
        this.log('🔌', 'Extracting plugin help terms...');
        await this.execNode('locale/scripts/locale-build-plugin-help.js');
        return this.getTempDir('locale/scripts/locale-build-plugin-help.js');
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
                // Use absolute path to ensure PHP terms merge into the correct file
                const outputPath = this.messagesFile;
                // Capture stderr to filter known cosmetic xgettext best-practice warnings
                // about embedded URLs/emails in translatable strings (non-errors).
                const result = spawnSync(
                    'xgettext',
                    ['--no-location', '--no-wrap', '--join-existing', '--from-code=UTF-8',
                     '-o', outputPath, '-L', 'PHP', ...phpFiles],
                    { cwd: srcDir, encoding: 'utf8', stdio: ['inherit', 'inherit', 'pipe'] }
                );
                if (result.stderr) {
                    const filtered = result.stderr.split('\n')
                        .filter(l => !l.includes('warning: Message contains an embedded'))
                        .join('\n').trimEnd();
                    if (filtered) process.stderr.write(filtered + '\n');
                }
                if (result.status !== 0) {
                    throw new Error(`xgettext failed with exit code ${result.status}`);
                }
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
            // Check if npx is available
            execSync('which npx', { stdio: 'pipe' });

            // Verify i18next-cli is installed
            try {
                execSync('npx i18next-cli --version', { stdio: 'pipe' });
            } catch (e) {
                this.log('⚠️', 'i18next-cli not found - skipping JavaScript term extraction');
                return;
            }

            // Run i18next-cli extract (successor to i18next-parser)
            try {
                this.exec('npx i18next-cli extract --quiet --config locale/scripts/i18next.config.ts');
            } catch (error) {
                console.error('i18next-cli extraction failed:', error.message);
                throw error;
            }

            // Paths derived from central config (output dir: locale/.work/locales/)
            const translationJson = path.join(config.localesDir, 'en/translation.json');
            const translationPo = path.join(config.localesDir, 'en/translation.po');
            const translationJsonRel = path.relative(this.projectRoot, translationJson);
            const translationPoRel = path.relative(this.projectRoot, translationPo);

            if (this.fileExists(translationJson)) {
                try {
                    this.exec(`npx i18next-conv --quiet -l en -s "${translationJsonRel}" -t "${translationPoRel}"`);
                } catch (error) {
                    console.error('i18next-conv conversion failed:', error.message);
                    throw error;
                }
            }

            // Merge with main messages.po if PO file was created
            if (this.fileExists(translationPo)) {
                this.log('🔗', 'Merging JavaScript terms...');
                this.mergePOFiles(this.messagesFile, translationPo, this.messagesFile);
            }

            // Cleanup temporary files
            this.log('🧹', 'Cleaning up temporary files...');
            this.exec(`rm -f "${translationJsonRel}" "${translationPoRel}"`);

        } catch (error) {
            console.error('❌ JavaScript term extraction failed:', error.message);
            throw new Error('Locale build failed: JavaScript term extraction encountered an error. See above for details.');
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

            // 3. Extract plugin help terms
            const pluginHelpTempDir = await this.extractPluginHelpTerms();
            const pluginHelpTermsFile = pluginHelpTempDir ? path.join(pluginHelpTempDir, 'plugin-help-terms.po') : null;

            this.log('📁', `Using database temp directory: ${dbTempDir}`);
            this.log('📁', `Using static temp directory: ${staticTempDir}`);
            this.log('📁', `Using plugin help temp directory: ${pluginHelpTempDir}`);

            // 4. Start with database terms as the base
            if (dbTermsFile && this.fileExists(dbTermsFile)) {
                this.log('📄', 'Starting with database terms...');
                this.copyFile(dbTermsFile, this.messagesFile);
            } else {
                this.log('⚠️', 'No database terms file found, creating empty messages.po');
                this.createFile(this.messagesFile, '# ChurchCRM locale file\n');
            }

            // 5. Merge static data
            if (staticTermsFile && this.fileExists(staticTermsFile)) {
                this.log('🔗', 'Merging static data (countries and locales)...');
                this.mergePOFiles(this.messagesFile, staticTermsFile, this.messagesFile);
            } else {
                this.log('⚠️', 'No static terms file found');
            }

            // 6. Merge plugin help terms
            if (pluginHelpTermsFile && this.fileExists(pluginHelpTermsFile)) {
                this.log('🔗', 'Merging plugin help terms...');
                this.mergePOFiles(this.messagesFile, pluginHelpTermsFile, this.messagesFile);
            } else {
                this.log('⚠️', 'No plugin help terms file found');
            }

            // 7. Extract and merge PHP terms
            this.extractPHPTerms();

            // 8. Extract and merge JavaScript terms
            this.extractJavaScriptTerms();

            // 9. Final sort to ensure consistent ordering
            this.log('📋', 'Sorting messages.po for consistent ordering...');
            // Remove numeric-only msgids before final sort
            this.filterOutNumericOnlyTerms(this.messagesFile);

            this.exec(`msgcat --no-wrap --sort-output "${this.messagesFile}" -o "${this.messagesFile}.tmp"`);
            this.exec(`mv "${this.messagesFile}.tmp" "${this.messagesFile}"`);

            // 10. Cleanup — wipe the entire .work/ tree at once
            this.cleanup(config.temp.root);

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