#!/usr/bin/env node

/**
 * ChurchCRM Locale Audit Tool
 * 
 * Replaces the Grunt-based locale audit system with a modern Node.js solution.
 * Downloads language statistics from POEditor API and generates a completeness report.
 * 
 * Features:
 * - Downloads POEditor language statistics via native fetch API
 * - Generates detailed locale completeness report  
 * - Identifies missing locales with translations (excludes English base locales)
 * - Outputs markdown-formatted table for easy reading
 * - Saves comprehensive audit report to locale/poeditor-audit.md
 * - No external dependencies (uses Node.js built-ins)
 * 
 * Output Files:
 * - Console: Formatted table output for immediate review
 * - src/locale/poeditor.json: Raw API response (for compatibility)
 * - locale/poeditor-audit.md: Comprehensive markdown report
 * 
 * Note: English locales (en, en-us, en-au, etc.) are excluded from missing locale 
 * reports as they represent base locales rather than translation targets.
 */

const fs = require('fs');
const path = require('path');

class LocaleAuditor {
    constructor() {
        this.configPath = path.resolve(__dirname, '../../BuildConfig.json');
        this.configExamplePath = path.resolve(__dirname, '../../BuildConfig.json.example');
        this.localesPath = path.resolve(__dirname, '../../src/locale/locales.json');
        this.outputPath = path.resolve(__dirname, '../../src/locale/poeditor.json');
        this.reportPath = path.resolve(__dirname, '../poeditor-audit.md');
        this.config = null;
        this.locales = null;
    }

    /**
     * Load configuration from BuildConfig.json or BuildConfig.json.example
     */
    loadConfig() {
        try {
            let configFile = this.configPath;
            if (!fs.existsSync(this.configPath)) {
                if (fs.existsSync(this.configExamplePath)) {
                    console.log('⚠️  BuildConfig.json not found, using BuildConfig.json.example');
                    configFile = this.configExamplePath;
                } else {
                    throw new Error(`Configuration file not found: neither ${this.configPath} nor ${this.configExamplePath} exist`);
                }
            }
            
            this.config = JSON.parse(fs.readFileSync(configFile, 'utf8'));
            
            if (!this.config.POEditor || !this.config.POEditor.token || !this.config.POEditor.id) {
                throw new Error('POEditor configuration missing in BuildConfig.json');
            }
            
            console.log('✅ Configuration loaded successfully');
            return true;
        } catch (error) {
            console.error('❌ Failed to load configuration:', error.message);
            return false;
        }
    }

    /**
     * Load supported locales from locales.json
     */
    loadLocales() {
        try {
            if (!fs.existsSync(this.localesPath)) {
                throw new Error(`Locales file not found: ${this.localesPath}`);
            }
            
            this.locales = JSON.parse(fs.readFileSync(this.localesPath, 'utf8'));
            console.log(`✅ Loaded ${Object.keys(this.locales).length} supported locales`);
            return true;
        } catch (error) {
            console.error('❌ Failed to load locales:', error.message);
            return false;
        }
    }

    /**
     * Download language statistics from POEditor API
     */
    async downloadPOEditorStats() {
        try {
            console.log('🌍 Downloading language statistics from POEditor...');
            
            const formData = new URLSearchParams();
            formData.append('api_token', this.config.POEditor.token);
            formData.append('id', this.config.POEditor.id);

            const response = await fetch('https://api.poeditor.com/v2/languages/list', {
                method: 'POST',
                body: formData,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                }
            });

            if (!response.ok) {
                throw new Error(`POEditor API request failed: ${response.status} ${response.statusText}`);
            }

            const data = await response.json();
            
            if (data.response.status !== 'success') {
                throw new Error(`POEditor API error: ${data.response.message}`);
            }

            // Save the response to file (maintains compatibility)
            fs.writeFileSync(this.outputPath, JSON.stringify(data, null, 0));
            
            console.log(`✅ Downloaded statistics for ${data.result.languages.length} languages`);
            return data;
        } catch (error) {
            console.error('❌ Failed to download POEditor statistics:', error.message);
            throw error;
        }
    }

    /**
     * Generate locale audit report
     */
    generateAuditReport(poEditorData) {
        try {
            console.log('🔍 Generating locale audit report...\n');

            // Get supported POEditor codes from locales.json
            const supportedPOEditorCodes = [];
            for (const key in this.locales) {
                supportedPOEditorCodes.push(this.locales[key].poEditor.toLowerCase());
            }

            const localeData = [];
            const wipCandidates = [];

            // Process POEditor languages
            for (const language of poEditorData.result.languages) {
                const name = language.name;
                const code = language.code.toLowerCase();
                const percentage = language.percentage;
                const translations = language.translations;

                if (supportedPOEditorCodes.indexOf(code) !== -1) {
                    // Supported locale
                    localeData.push({
                        code: code,
                        name: name,
                        percentage: percentage,
                        translations: translations
                    });
                } else if (!code.startsWith('en') && percentage > 0) {
                    // WIP candidate: has translations, not English variant, not in system
                    wipCandidates.push({
                        name: name,
                        code: code,
                        percentage: percentage,
                        translations: translations
                    });
                    console.log(`⚠️  WIP Candidate ${name} (${code}): ${percentage}% completion (${translations} translations)`);
                }
            }

            // Sort by percentage (descending)
            localeData.sort((a, b) => b.percentage - a.percentage);
            wipCandidates.sort((a, b) => b.percentage - a.percentage);

            // Generate markdown report content
            const reportContent = this.generateMarkdownReport(localeData, wipCandidates);

            // Save to markdown file
            this.saveReportToFile(reportContent);

            // Display report to console
            this.displayConsoleReport(localeData, wipCandidates);

            console.log('\n✅ Locale audit completed');
            return {
                localeData,
                wipCandidates,
                summary: {
                    total: localeData.length,
                    complete: localeData.filter(l => l.percentage >= 75).length,
                    good: localeData.filter(l => l.percentage >= 51 && l.percentage < 75).length,
                    needsWork: localeData.filter(l => l.percentage < 51).length,
                    wipCandidates: wipCandidates.length
                }
            };
        } catch (error) {
            console.error('❌ Failed to generate audit report:', error.message);
            throw error;
        }
    }

    /**
     * Generate markdown report content
     */
    generateMarkdownReport(localeData, wipCandidates) {
        let markdown = `# ChurchCRM Locale Audit Report\n\n`;
        markdown += `**Total Supported Locales:** ${localeData.length}\n`;
        markdown += `**Complete Locales (≥75%):** ${localeData.filter(l => l.percentage >= 75).length}\n`;
        markdown += `**Good Locales (51-74%):** ${localeData.filter(l => l.percentage >= 51 && l.percentage < 75).length}\n`;
        markdown += `**Needs Work (<51%):** ${localeData.filter(l => l.percentage < 51).length}\n`;
        markdown += `**WIP Candidates (>5%, not yet added):** ${wipCandidates.length}\n\n`;

        // Single unified locale completeness table
        markdown += `## Locale Completeness Overview\n\n`;
        markdown += `| Locale | Language | Translations | Percentage | Status | Supported |\n`;
        markdown += `|--------|----------|--------------|------------|--------|----------|\n`;
        
        // Get all POEditor languages
        const allLanguages = [];
        for (const language of wipCandidates) {
            allLanguages.push(language);
        }
        for (const locale of localeData) {
            allLanguages.push(locale);
        }
        
        // Sort by percentage descending
        allLanguages.sort((a, b) => b.percentage - a.percentage);
        
        allLanguages.forEach(locale => {
            let status;
            if (locale.code.startsWith('en')) {
                status = 'N/A';
            } else if (locale.percentage >= 75) {
                status = '🟢 Complete';
            } else if (locale.percentage >= 51) {
                status = '🟡 Good';
            } else if (locale.percentage > 0) {
                status = '🟠 Needs Work';
            } else {
                status = '⚪ No translations';
            }
            
            const isSupported = localeData.some(l => l.code === locale.code) ? '✅ Yes' : '❌ No';
            markdown += `| \`${locale.code}\` | ${locale.name} | ${locale.translations} | ${locale.percentage}% | ${status} | ${isSupported} |\n`;
        });

        // Status summary
        const complete = localeData.filter(l => l.percentage >= 75);
        const good = localeData.filter(l => l.percentage >= 51 && l.percentage < 75);
        const needsWork = localeData.filter(l => l.percentage < 51 && !l.code.startsWith('en'));
        const incomplete = localeData.filter(l => l.percentage < 51 && l.code.startsWith('en'));

        markdown += `\n## Status Summary\n\n`;
        markdown += `- **🟢 Complete (≥75%):** ${complete.length} locales ready for production\n`;
        markdown += `- **🟡 Good (51-74%):** ${good.length} locales with solid translation coverage\n`;
        markdown += `- **🟠 Needs Work (<51%):** ${needsWork.length} locales requiring translator attention\n`;
        markdown += `- **🔴 Incomplete:** ${needsWork.filter(l => l.percentage < 5).length} locales (requiring translator attention)\n`;
        markdown += `- **N/A:** ${incomplete.length} locales (English variants - English is the default language)\n`;
        
        // WIP candidates section
        if (wipCandidates.length > 0) {
            markdown += `\n## WIP Candidates (not yet in system)\n\n`;
            markdown += `These locales have translations but are not yet in locales.json:\n\n`;
            markdown += `| Language | Code | Translations | Percentage | Status |\n`;
            markdown += `|----------|------|--------------|------------|--------|\n`;
            wipCandidates.forEach(locale => {
                const readiness = locale.percentage >= 5 ? '⭐ Ready to add' : '📝 Monitor';
                markdown += `| ${locale.name} | \`${locale.code}\` | ${locale.translations} | ${locale.percentage}% | ${readiness} |\n`;
            });
        } else {
            markdown += `\n## WIP Candidates (not yet in system)\n\n`;
            markdown += `No locale candidates available.\n`;
        }

        markdown += `\n**Note:** \n`;
        markdown += `- English variants (en-*) are marked as N/A since English is the default language\n`;
        markdown += `- Locales with ⭐ **Ready to add** (≥5% completion) should be prioritized for addition to \`src/locale/locales.json\`\n`;
        markdown += `- Locales with 📝 **Monitor** (<5% completion) are tracked for future addition when they reach 5%\n`;

        markdown += `\n## Technical Notes\n\n`;
        markdown += `- **Data Source:** POEditor API (Project ID: ${this.config.POEditor.id})\n`;
        markdown += `- **Audit Script:** \`locale/locale-audit.js\`\n`;
        markdown += `- **Configuration:** \`src/locale/locales.json\`\n`;
        markdown += `- **Command:** \`npm run locale-audit\`\n\n`;
        markdown += `---\n`;
        markdown += `*This report was automatically generated by the ChurchCRM locale audit system.*\n`;

        return markdown;
    }

    /**
     * Save report to markdown file
     */
    saveReportToFile(content) {
        try {
            // Ensure locale directory exists
            const localeDir = path.dirname(this.reportPath);
            if (!fs.existsSync(localeDir)) {
                fs.mkdirSync(localeDir, { recursive: true });
            }

            fs.writeFileSync(this.reportPath, content, 'utf8');
            console.log(`📄 Audit report saved to: ${this.reportPath}`);
        } catch (error) {
            console.error(`❌ Failed to save report to file: ${error.message}`);
            // Don't throw - this shouldn't stop the audit
        }
    }

    /**
     * Display console report (simplified version)
     */
    displayConsoleReport(localeData, wipCandidates) {
        // Display report
        console.log('\n📊 **Locale Completeness Report**\n');
        console.log('| Locale | Language | Translations | Percentage |');
        console.log('|--------|----------|--------------|------------|');
        
        localeData.forEach(locale => {
            const status = locale.percentage >= 75 ? '🟢' : 
                          locale.percentage >= 51 ? '🟡' : '🟠';
            console.log(`| ${locale.code} | ${locale.name} ${status} | ${locale.translations} | ${locale.percentage}% |`);
        });

        // Summary statistics
        const totalLocales = localeData.length;
        const completeLocales = localeData.filter(l => l.percentage >= 75).length;
        const goodLocales = localeData.filter(l => l.percentage >= 51 && l.percentage < 75).length;
        const needsWorkLocales = localeData.filter(l => l.percentage < 51).length;
        
        console.log('\n📈 **Summary:**');
        console.log(`- **Total supported locales:** ${totalLocales}`);
        console.log(`- **Complete locales (≥75%):** ${completeLocales}`);
        console.log(`- **Good locales (51-74%):** ${goodLocales}`);
        console.log(`- **Needs work locales (<51%):** ${needsWorkLocales}`);
        console.log(`- **WIP candidates (>5%, not yet added):** ${wipCandidates.length}`);

        if (wipCandidates.length > 0) {
            console.log('\n🔄 **WIP Candidates** (not yet in system):');
            wipCandidates.forEach(locale => {
                const readiness = locale.percentage >= 5 ? '⭐ READY TO ADD' : '📝 MONITOR';
                console.log(`- ${locale.name} (${locale.code}): ${locale.percentage}% complete - ${readiness}`);
            });
        }
    }

    /**
     * Run the complete locale audit process
     */
    async run() {
        try {
            console.log('🚀 Starting ChurchCRM Locale Audit\n');

            // Load configuration and locales
            if (!this.loadConfig() || !this.loadLocales()) {
                process.exit(1);
            }

            // Download POEditor statistics
            const poEditorData = await this.downloadPOEditorStats();

            // Generate audit report
            const report = this.generateAuditReport(poEditorData);

            console.log('\n🎉 Locale audit completed successfully!');
            
            // Exit with non-zero code if there are issues that need attention
            const hasIssues = report.summary.needsWork > 0 || report.summary.wipCandidates > 0;
            if (hasIssues && process.env.LOCALE_AUDIT_STRICT === 'true') {
                console.log('\n⚠️  Exiting with code 1 due to incomplete translations (LOCALE_AUDIT_STRICT=true)');
                process.exit(1);
            }

            process.exit(0);
        } catch (error) {
            console.error('💥 Locale audit failed:', error.message);
            process.exit(1);
        }
    }
}

// Run the auditor if this script is executed directly
if (require.main === module) {
    const auditor = new LocaleAuditor();
    auditor.run();
}

module.exports = LocaleAuditor;