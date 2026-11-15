#!/usr/bin/env node

/**
 * ChurchCRM Locale Audit Tool
 *
 * Counts how complete every locale is, straight from the repo: a source term (an entry of
 * locale/messages.po) counts as translated when the locale JSON has a complete translation for it.
 *
 * Output Files:
 * - Console: Formatted table output for immediate review
 * - src/locale/poeditor.json: per-locale counts the app reads for the translation % it shows (the
 *   file keeps its old name and layout until the app code moves off it)
 * - locale/poeditor-audit.md: Comprehensive markdown report
 *
 * Note: English locales (en, en-us, en-au, etc.) are excluded from missing locale
 * reports as they represent base locales rather than translation targets.
 */

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const config = require('./locale-config');
const repo = require('./lib/locale-repo');
const { entryValue } = require('./lib/locale-files');

class LocaleAuditor {
    constructor() {
        this.localesPath = config.localesJson;
        this.outputPath = config.poeditor.outputJson;
        this.reportPath = config.poeditor.auditReport;
        this.locales = null;
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
     * When the locale JSON last changed, from git (falls back to the file time outside a checkout).
     */
    lastUpdated(file) {
        try {
            const iso = execFileSync('git', ['log', '-1', '--format=%cI', '--', file], { cwd: config.projectRoot, encoding: 'utf8' }).trim();
            if (iso) return new Date(iso).toISOString().replace(/\.\d{3}Z$/, '+0000');
        } catch {
            // not a git checkout
        }
        return fs.existsSync(file) ? fs.statSync(file).mtime.toISOString().replace(/\.\d{3}Z$/, '+0000') : null;
    }

    /**
     * Translation counts for every locale, from the repo. Same layout as the old POEditor
     * languages/list response, which the app reads from src/locale/poeditor.json.
     */
    computeRepoStats() {
        console.log('🌍 Counting translations in the repo...');
        const entries = repo.loadSource();
        if (entries.length === 0) throw new Error('locale/messages.po has no terms');
        const languages = [];
        for (const target of repo.targetLocales()) {
            const ctx = repo.loadLocale(target, entries);
            const translations = entries.filter((e) => entryValue(e, ctx.shapes, ctx.translations) !== null).length;
            languages.push({
                name: target.name,
                code: target.code,
                translations,
                percentage: Math.round((translations / entries.length) * 10000) / 100,
                updated: this.lastUpdated(path.relative(config.projectRoot, ctx.paths.json)),
            });
        }
        const data = { response: { status: 'success', code: '200', message: 'OK' }, result: { terms: entries.length, languages } };
        fs.writeFileSync(this.outputPath, JSON.stringify(data, null, 0));
        console.log(`✅ Counted ${languages.length} locales against ${entries.length} source terms`);
        return data;
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
                    complete: localeData.filter(l => l.percentage >= 90).length,
                    good: localeData.filter(l => l.percentage >= 75 && l.percentage < 90).length,
                    needsWork: localeData.filter(l => l.percentage < 75).length,
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
        markdown += `**Complete Locales (≥90%):** ${localeData.filter(l => l.percentage >= 90).length}\n`;
        markdown += `**Good Locales (75-89%):** ${localeData.filter(l => l.percentage >= 75 && l.percentage < 90).length}\n`;
        markdown += `**Needs Work (<75%):** ${localeData.filter(l => l.percentage < 75).length}\n`;
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
            } else if (locale.percentage >= 90) {
                status = '🟢 Complete';
            } else if (locale.percentage >= 75) {
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
        const complete = localeData.filter(l => l.percentage >= 90);
        const good = localeData.filter(l => l.percentage >= 75 && l.percentage < 90);
        const needsWork = localeData.filter(l => l.percentage < 75 && !l.code.startsWith('en'));
        const incomplete = localeData.filter(l => l.percentage < 75 && l.code.startsWith('en'));

        markdown += `\n## Status Summary\n\n`;
        markdown += `- **🟢 Complete (≥90%):** ${complete.length} locales ready for production\n`;
        markdown += `- **🟡 Good (75-89%):** ${good.length} locales with solid translation coverage\n`;
        markdown += `- **🟠 Needs Work (<75%):** ${needsWork.length} locales requiring translator attention\n`;
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
        markdown += `- **Data Source:** the locale files in the repo (\`src/locale/i18n/*.json\` against \`locale/messages.po\`)\n`;
        markdown += `- **Audit Script:** \`locale/scripts/locale-audit.js\`\n`;
        markdown += `- **Configuration:** \`src/locale/locales.json\`\n`;
        markdown += `- **Command:** \`npm run locale:audit\`\n\n`;
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
            const status = locale.percentage >= 90 ? '🟢' : 
                          locale.percentage >= 75 ? '🟡' : '🟠';
            console.log(`| ${locale.code} | ${locale.name} ${status} | ${locale.translations} | ${locale.percentage}% |`);
        });

        // Summary statistics
        const totalLocales = localeData.length;
        const completeLocales = localeData.filter(l => l.percentage >= 90).length;
        const goodLocales = localeData.filter(l => l.percentage >= 75 && l.percentage < 90).length;
        const needsWorkLocales = localeData.filter(l => l.percentage < 75).length;
        
        console.log('\n📈 **Summary:**');
        console.log(`- **Total supported locales:** ${totalLocales}`);
        console.log(`- **Complete locales (≥90%):** ${completeLocales}`);
        console.log(`- **Good locales (75-89%):** ${goodLocales}`);
        console.log(`- **Needs work locales (<75%):** ${needsWorkLocales}`);
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

            if (!this.loadLocales()) {
                process.exit(1);
            }

            const stats = this.computeRepoStats();
            const report = this.generateAuditReport(stats);

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