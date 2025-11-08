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
            const missingLocales = [];

            // Process POEditor languages
            for (const language of poEditorData.result.languages) {
                const name = language.name;
                const code = language.code.toLowerCase();
                const percentage = language.percentage;
                const translations = language.translations;

                if (supportedPOEditorCodes.indexOf(code) === -1 && percentage > 0) {
                    // Skip English locales as they are base locales, not missing translations
                    if (code.startsWith('en')) {
                        continue;
                    }
                    
                    // Missing locale with translations
                    missingLocales.push({
                        name: name,
                        code: code,
                        percentage: percentage,
                        translations: translations
                    });
                    console.log(`⚠️  Missing ${name} (${code}) but has ${percentage}% completion (${translations} translations)`);
                } else if (supportedPOEditorCodes.indexOf(code) !== -1) {
                    // Supported locale
                    localeData.push({
                        code: code,
                        name: name,
                        percentage: percentage,
                        translations: translations
                    });
                }
            }

            // Sort by percentage (descending)
            localeData.sort((a, b) => b.percentage - a.percentage);

            // Generate markdown report content
            const reportContent = this.generateMarkdownReport(localeData, missingLocales);

            // Save to markdown file
            this.saveReportToFile(reportContent);

            // Display report to console
            this.displayConsoleReport(localeData, missingLocales);

            console.log('\n✅ Locale audit completed');
            return {
                localeData,
                missingLocales,
                summary: {
                    total: localeData.length,
                    complete: localeData.filter(l => l.percentage >= 95).length,
                    incomplete: localeData.filter(l => l.percentage < 50).length,
                    missing: missingLocales.length
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
    generateMarkdownReport(localeData, missingLocales) {
        const currentDate = new Date().toISOString().split('T')[0];
        const currentTime = new Date().toLocaleTimeString();
        
        let markdown = `# ChurchCRM Locale Audit Report\n\n`;
        markdown += `**Total Supported Locales:** ${localeData.length}\n`;
        markdown += `**Complete Locales (≥95%):** ${localeData.filter(l => l.percentage >= 95).length}\n`;
        markdown += `**Incomplete Locales (<50%):** ${localeData.filter(l => l.percentage < 50).length}\n`;
        markdown += `**Missing Locales with Translations:** ${missingLocales.length}\n\n`;

        // Locale completeness table
        markdown += `## Supported Locale Completeness\n\n`;
        markdown += `| Locale | Language | Translations | Percentage | Status |\n`;
        markdown += `|--------|----------|--------------|------------|--------|\n`;
        
        localeData.forEach(locale => {
            const status = locale.percentage >= 95 ? '🟢 Complete' : 
                          locale.percentage >= 80 ? '🟡 Good' : 
                          locale.percentage >= 50 ? '🟠 Needs Work' : '🔴 Incomplete';
            markdown += `| \`${locale.code}\` | ${locale.name} | ${locale.translations} | ${locale.percentage}% | ${status} |\n`;
        });

        // Summary by status
        const complete = localeData.filter(l => l.percentage >= 95);
        const good = localeData.filter(l => l.percentage >= 80 && l.percentage < 95);
        const needsWork = localeData.filter(l => l.percentage >= 50 && l.percentage < 80);
        const incomplete = localeData.filter(l => l.percentage < 50);

        markdown += `\n## Summary by Status\n\n`;
        markdown += `### 🟢 Complete Locales (≥95%) - ${complete.length} total\n`;
        if (complete.length > 0) {
            complete.forEach(locale => {
                markdown += `- **${locale.name}** (\`${locale.code}\`): ${locale.percentage}%\n`;
            });
        } else {
            markdown += `*No complete locales*\n`;
        }

        markdown += `\n### 🟡 Good Locales (80-94%) - ${good.length} total\n`;
        if (good.length > 0) {
            good.forEach(locale => {
                markdown += `- **${locale.name}** (\`${locale.code}\`): ${locale.percentage}%\n`;
            });
        } else {
            markdown += `*No locales in this range*\n`;
        }

        markdown += `\n### 🟠 Needs Work (50-79%) - ${needsWork.length} total\n`;
        if (needsWork.length > 0) {
            needsWork.forEach(locale => {
                markdown += `- **${locale.name}** (\`${locale.code}\`): ${locale.percentage}%\n`;
            });
        } else {
            markdown += `*No locales in this range*\n`;
        }

        markdown += `\n### 🔴 Incomplete Locales (<50%) - ${incomplete.length} total\n`;
        if (incomplete.length > 0) {
            incomplete.forEach(locale => {
                markdown += `- **${locale.name}** (\`${locale.code}\`): ${locale.percentage}%\n`;
            });
        } else {
            markdown += `*No incomplete locales*\n`;
        }

        // Missing locales section
        if (missingLocales.length > 0) {
            markdown += `\n## 🚨 Missing Locales with Translations\n\n`;
            markdown += `These locales have translations available in POEditor but are not currently supported in ChurchCRM:\n\n`;
            markdown += `*Note: English locales (en-*) are excluded as they are base locales, not missing translations.*\n\n`;
            markdown += `| Language | Code | Translations | Percentage | Recommendation |\n`;
            markdown += `|----------|------|--------------|------------|----------------|\n`;
            
            missingLocales.forEach(locale => {
                const recommendation = locale.percentage >= 80 ? '⭐ High Priority' :
                                     locale.percentage >= 50 ? '📋 Medium Priority' :
                                     locale.percentage >= 20 ? '📝 Low Priority' : '⏸️ Wait for more progress';
                markdown += `| ${locale.name} | \`${locale.code}\` | ${locale.translations} | ${locale.percentage}% | ${recommendation} |\n`;
            });

            // Highlight high-priority missing locales
            const highPriority = missingLocales.filter(l => l.percentage >= 80);
            if (highPriority.length > 0) {
                markdown += `\n### ⭐ High Priority Missing Locales\n\n`;
                markdown += `Consider adding support for these well-translated locales:\n\n`;
                highPriority.forEach(locale => {
                    markdown += `- **${locale.name}** (\`${locale.code}\`): ${locale.percentage}% complete with ${locale.translations} translations\n`;
                });
            }
        } else {
            markdown += `\n## 🚨 Missing Locales with Translations\n\n`;
            markdown += `No missing locales found with significant translations.\n\n`;
            markdown += `*Note: English locales (en-*) are excluded as they are base locales, not missing translations.*\n`;
        }

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
    displayConsoleReport(localeData, missingLocales) {
        // Display report
        console.log('\n📊 **Locale Completeness Report**\n');
        console.log('| Locale | Language | Translations | Percentage |');
        console.log('|--------|----------|--------------|------------|');
        
        localeData.forEach(locale => {
            const status = locale.percentage >= 95 ? '🟢' : 
                          locale.percentage >= 80 ? '🟡' : 
                          locale.percentage >= 50 ? '🟠' : '🔴';
            console.log(`| ${locale.code} | ${locale.name} ${status} | ${locale.translations} | ${locale.percentage}% |`);
        });

        // Summary statistics
        const totalLocales = localeData.length;
        const completeLocales = localeData.filter(l => l.percentage >= 95).length;
        const incompleteLocales = localeData.filter(l => l.percentage < 50).length;
        
        console.log('\n📈 **Summary:**');
        console.log(`- **Total supported locales:** ${totalLocales}`);
        console.log(`- **Complete locales (≥95%):** ${completeLocales}`);
        console.log(`- **Incomplete locales (<50%):** ${incompleteLocales}`);
        console.log(`- **Missing locales with translations:** ${missingLocales.length}`);

        if (missingLocales.length > 0) {
            console.log('\n🚨 **Missing Locales** (consider adding support):');
            missingLocales.forEach(locale => {
                console.log(`- ${locale.name} (${locale.code}): ${locale.percentage}% complete`);
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
            const hasIssues = report.summary.incomplete > 0 || report.summary.missing > 0;
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