#!/usr/bin/env node

/**
 * ChurchCRM Plugin Help Terms Extraction Script
 * 
 * Extracts translatable strings from plugin help.json files for localization.
 * 
 * Scans:
 *   src/plugins/core/{plugin}/help.json
 *   src/plugins/community/{plugin}/help.json
 * 
 * Extracts:
 *   summary text
 *   section titles and content
 *   link labels
 * 
 * Output: PO format file for merging with main messages.po
 */

const fs = require('fs');
const path = require('path');
const config = require('./locale-config');

class PluginHelpExtractor {
    constructor() {
        this.projectRoot = config.projectRoot;
        this.pluginsDir = path.join(this.projectRoot, 'src/plugins');
        this.tempDir = path.join(config.temp.root, 'churchcrm-locale-plugin-help');
        this.outputFile = path.join(this.tempDir, 'plugin-help-terms.po');
        // Map<text, context> — keyed by text so the same string from multiple
        // plugins is deduplicated (duplicate msgids cause msgcat fatal errors).
        this.terms = new Map();
    }

    /**
     * Log with emoji prefix
     */
    log(emoji, message) {
        console.log(`${emoji} ${message}`);
    }

    /**
     * Create temp directory if it doesn't exist
     */
    ensureTempDir() {
        if (!fs.existsSync(this.tempDir)) {
            fs.mkdirSync(this.tempDir, { recursive: true });
        }
    }

    /**
     * Find all help.json files in plugins directories
     */
    findHelpFiles() {
        const helpFiles = [];
        const pluginTypes = ['core', 'community'];

        for (const type of pluginTypes) {
            const typeDir = path.join(this.pluginsDir, type);
            
            if (!fs.existsSync(typeDir)) {
                continue;
            }

            const plugins = fs.readdirSync(typeDir, { withFileTypes: true })
                .filter(dirent => dirent.isDirectory())
                .map(dirent => dirent.name);

            for (const plugin of plugins) {
                const helpFile = path.join(typeDir, plugin, 'help.json');
                if (fs.existsSync(helpFile)) {
                    helpFiles.push({
                        path: helpFile,
                        plugin: plugin,
                        type: type
                    });
                }
            }
        }

        return helpFiles;
    }

    /**
     * Add a term to the set (with deduplication)
     */
    addTerm(text, context = '') {
        if (!text || typeof text !== 'string') {
            return;
        }

        // Normalize whitespace but preserve newlines for multiline content
        const normalized = text.trim();

        // Deduplicate by text — the same string appearing across multiple plugins
        // must only produce one msgid entry, or msgcat will report fatal errors.
        if (normalized.length > 0 && !this.terms.has(normalized)) {
            this.terms.set(normalized, context);
        }
    }

    /**
     * Extract terms from a single help.json file
     */
    extractFromHelpFile(fileInfo) {
        try {
            const content = fs.readFileSync(fileInfo.path, 'utf8');
            const help = JSON.parse(content);
            const contextPrefix = `plugin:${fileInfo.plugin}`;

            // Extract summary
            if (help.summary) {
                this.addTerm(help.summary, `${contextPrefix}:summary`);
            }

            // Extract sections
            if (Array.isArray(help.sections)) {
                help.sections.forEach((section, index) => {
                    if (section.title) {
                        this.addTerm(section.title, `${contextPrefix}:section:${index}:title`);
                    }
                    if (section.content) {
                        this.addTerm(section.content, `${contextPrefix}:section:${index}:content`);
                    }
                });
            }

            // Extract link labels
            if (Array.isArray(help.links)) {
                help.links.forEach((link, index) => {
                    if (link.label) {
                        this.addTerm(link.label, `${contextPrefix}:link:${index}:label`);
                    }
                });
            }

            this.log('📖', `Extracted terms from ${fileInfo.plugin}/help.json`);

        } catch (error) {
            this.log('⚠️', `Failed to parse ${fileInfo.path}: ${error.message}`);
        }
    }

    /**
     * Escape a string for PO file format
     */
    escapePOString(str) {
        return str
            .replace(/\\/g, '\\\\')
            .replace(/"/g, '\\"')
            .replace(/\n/g, '\\n')
            .replace(/\t/g, '\\t');
    }

    /**
     * Generate PO file content
     */
    generatePOContent() {
        const header = `# ChurchCRM Plugin Help Terms
# Extracted from help.json files in src/plugins/
# 
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"

`;

        const entries = [];

        for (const [text, context] of this.terms) {
            let entry = '';

            // Add context as a comment
            if (context) {
                entry += `#. ${context}\n`;
            }

            // Handle multiline strings
            const escaped = this.escapePOString(text);

            entry += `msgid "${escaped}"\n`;
            entry += `msgstr ""\n`;

            entries.push(entry);
        }

        return header + entries.join('\n');
    }

    /**
     * Write PO file
     */
    writePOFile() {
        const content = this.generatePOContent();
        fs.writeFileSync(this.outputFile, content, 'utf8');
        this.log('💾', `Wrote ${this.terms.size} terms to ${this.outputFile}`);
    }

    /**
     * Main execution
     */
    run() {
        // Handle --temp-dir flag for integration with main build
        if (process.argv.includes('--temp-dir')) {
            console.log(this.tempDir);
            return;
        }

        this.log('🔌', 'Extracting plugin help terms...');

        this.ensureTempDir();

        const helpFiles = this.findHelpFiles();
        
        if (helpFiles.length === 0) {
            this.log('⚠️', 'No plugin help.json files found');
            // Create empty PO file anyway
            this.writePOFile();
            return;
        }

        this.log('📂', `Found ${helpFiles.length} help.json file(s)`);

        for (const fileInfo of helpFiles) {
            this.extractFromHelpFile(fileInfo);
        }

        this.writePOFile();

        this.log('✅', `Plugin help extraction complete: ${this.terms.size} terms`);
    }
}

// Run if called directly
if (require.main === module) {
    const extractor = new PluginHelpExtractor();
    extractor.run();
}

module.exports = PluginHelpExtractor;
