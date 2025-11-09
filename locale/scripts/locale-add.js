#!/usr/bin/env node

/**
 * ChurchCRM Language Setup Tool
 * 
 * Automates the process of adding a new language to ChurchCRM by:
 * - Adding locale configuration to locales.json
 * - Creating necessary directory structure
 * - Generating empty translation files
 * - Creating locale JavaScript files
 * - Updating system files
 * 
 * Usage: node locale/locale-add.js [options]
 * 
 * Options:
 *   --name <name>          Language name (e.g., "Korean", "French")
 *   --code <code>          POEditor language code (e.g., "ko", "fr")
 *   --locale <locale>      Full locale code (e.g., "ko_KR", "fr_FR")
 *   --country <country>    Country code (e.g., "KR", "FR")
 *   --datatables <name>    DataTables locale name (e.g., "Korean", "French")
 *   --interactive          Interactive mode (prompts for all values)
 *   --dry-run              Show what would be created without making changes
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

class LanguageSetup {
    constructor() {
        this.localesPath = path.resolve(__dirname, '../../src/locale/locales.json');
        this.dryRun = false;
        this.interactive = false;
    }

    /**
     * Parse command line arguments
     */
    parseArguments() {
        const args = process.argv.slice(2);
        const config = {};
        
        for (let i = 0; i < args.length; i++) {
            const arg = args[i];
            
            switch (arg) {
                case '--name':
                    config.name = args[++i];
                    break;
                case '--code':
                    config.code = args[++i];
                    break;
                case '--locale':
                    config.locale = args[++i];
                    break;
                case '--country':
                    config.country = args[++i];
                    break;
                case '--datatables':
                    config.datatables = args[++i];
                    break;
                case '--interactive':
                    this.interactive = true;
                    break;
                case '--dry-run':
                    this.dryRun = true;
                    break;
                case '--help':
                case '-h':
                    this.showHelp();
                    process.exit(0);
                default:
                    if (arg.startsWith('--')) {
                        console.error(`❌ Unknown option: ${arg}`);
                        this.showHelp();
                        process.exit(1);
                    }
            }
        }
        
        return config;
    }

    /**
     * Show help information
     */
    showHelp() {
        console.log(`
🌍 ChurchCRM Language Setup Tool

Usage: node locale/locale-add.js [options]

Options:
  --name <name>          Language name (e.g., "Korean", "French")
  --code <code>          POEditor language code (e.g., "ko", "fr")
  --locale <locale>      Full locale code (e.g., "ko_KR", "fr_FR")
  --country <country>    Country code (e.g., "KR", "FR")
  --datatables <name>    DataTables locale name (e.g., "Korean", "French")
  --interactive          Interactive mode (prompts for all values)
  --dry-run              Show what would be created without making changes
  --help, -h             Show this help message

Examples:
  # Interactive mode
  node locale/locale-add.js --interactive

  # Direct mode
  node locale/locale-add.js --name "Korean" --code "ko" --locale "ko_KR" --country "KR" --datatables "Korean"

  # Dry run to see what would be created
    node locale/locale-add.js --name "Korean" --code "ko" --locale "ko_KR" --country "KR" --datatables "Korean"


  node locale/locale-add.js --name "Korean" --code "ko" --locale "ko_KR" --country "KR" --datatables "Korean" --dry-run
        `);
    }

    /**
     * Prompt for interactive input
     */
    async promptForInput() {
        const readline = require('readline').createInterface({
            input: process.stdin,
            output: process.stdout
        });

        const question = (prompt) => {
            return new Promise((resolve) => {
                readline.question(prompt, resolve);
            });
        };

        console.log('🌍 Interactive Language Setup\n');

        const config = {};
        
        config.name = await question('Language name (e.g., Korean, French): ');
        config.code = await question('POEditor language code (e.g., ko, fr): ');
        
        // Auto-generate suggestions based on code
        const suggestedLocale = `${config.code}_${config.code.toUpperCase()}`;
        const suggestedCountry = config.code.toUpperCase();
        
        config.locale = await question(`Full locale code (default: ${suggestedLocale}): `) || suggestedLocale;
        config.country = await question(`Country code (default: ${suggestedCountry}): `) || suggestedCountry;
        config.datatables = await question(`DataTables locale name (default: ${config.name}): `) || config.name;

        readline.close();
        return config;
    }

    /**
     * Validate configuration
     */
    validateConfig(config) {
        const required = ['name', 'code', 'locale', 'country', 'datatables'];
        const missing = required.filter(field => !config[field]);
        
        if (missing.length > 0) {
            console.error(`❌ Missing required fields: ${missing.join(', ')}`);
            console.error('Use --interactive mode or provide all required options.');
            console.error('Run with --help for usage information.');
            return false;
        }

        // Validate format
        if (!/^[a-z]{2,3}$/.test(config.code)) {
            console.error(`❌ Invalid language code: ${config.code}. Should be 2-3 lowercase letters (e.g., ko, fr, zh)`);
            return false;
        }

        if (!/^[a-z]{2,3}_[A-Z]{2,3}$/.test(config.locale)) {
            console.error(`❌ Invalid locale format: ${config.locale}. Should be language_COUNTRY (e.g., ko_KR, fr_FR)`);
            return false;
        }

        if (!/^[A-Z]{2,3}$/.test(config.country)) {
            console.error(`❌ Invalid country code: ${config.country}. Should be 2-3 uppercase letters (e.g., KR, FR)`);
            return false;
        }

        return true;
    }

    /**
     * Check if language already exists
     */
    checkLanguageExists(config) {
        try {
            const locales = JSON.parse(fs.readFileSync(this.localesPath, 'utf8'));
            
            // Check by name
            if (locales[config.name]) {
                console.error(`❌ Language "${config.name}" already exists in locales.json`);
                return true;
            }

            // Check by POEditor code
            for (const [name, locale] of Object.entries(locales)) {
                if (locale.poEditor === config.code) {
                    console.error(`❌ POEditor code "${config.code}" already used by "${name}"`);
                    return true;
                }
                if (locale.locale === config.locale) {
                    console.error(`❌ Locale code "${config.locale}" already used by "${name}"`);
                    return true;
                }
            }

            return false;
        } catch (error) {
            console.error(`❌ Failed to read locales.json: ${error.message}`);
            return true;
        }
    }

    /**
     * Create language configuration object
     */
    createLanguageConfig(config) {
        return {
            poEditor: config.code,
            locale: config.locale,
            languageCode: config.code,
            countryCode: config.country,
            dataTables: config.datatables,
            fullCalendar: true,
            fullCalendarLocale: config.code,
            datePicker: true,
            select2: true
        };
    }

    /**
     * Add language to locales.json
     */
    addToLocalesFile(config) {
        try {
            const locales = JSON.parse(fs.readFileSync(this.localesPath, 'utf8'));
            const languageConfig = this.createLanguageConfig(config);

            // Add the new language
            locales[config.name] = languageConfig;

            // Sort alphabetically by key
            const sortedLocales = {};
            Object.keys(locales)
                .sort()
                .forEach(key => {
                    sortedLocales[key] = locales[key];
                });

            const content = JSON.stringify(sortedLocales, null, 4);

            if (this.dryRun) {
                console.log(`📄 Would update ${this.localesPath}:`);
                console.log(`   Added: "${config.name}" with code "${config.code}"`);
            } else {
                fs.writeFileSync(this.localesPath, content, 'utf8');
                console.log(`✅ Added "${config.name}" to ${this.localesPath}`);
            }

            return true;
        } catch (error) {
            console.error(`❌ Failed to update locales.json: ${error.message}`);
            return false;
        }
    }

    /**
     * Create directory structure
     */
    createDirectories(config) {
        const directories = [
            path.resolve(__dirname, `../../src/locale/textdomain/${config.locale}`),
            path.resolve(__dirname, `../../src/locale/textdomain/${config.locale}/LC_MESSAGES`),
            path.resolve(__dirname, '../../src/locale/i18n')
        ];

        directories.forEach(dir => {
            if (this.dryRun) {
                console.log(`📁 Would create directory: ${dir}`);
            } else {
                try {
                    fs.mkdirSync(dir, { recursive: true });
                    console.log(`✅ Created directory: ${dir}`);
                } catch (error) {
                    console.warn(`⚠️  Directory already exists or failed to create: ${dir}`);
                }
            }
        });
    }

    /**
     * Create empty translation files
     */
    createTranslationFiles(config) {
        const files = [
            {
                path: path.resolve(__dirname, `../../src/locale/textdomain/${config.locale}/LC_MESSAGES/messages.po`),
                content: `# ChurchCRM ${config.name} Translation
# Language: ${config.name} (${config.code})
# Locale: ${config.locale}
# Generated: ${new Date().toISOString()}

msgid ""
msgstr ""
"Language: ${config.code}\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: nplurals=1; plural=0;\\n"

# Add translations below
`
            },
            {
                path: `src/locale/i18n/${config.locale}.json`,
                content: '{\n    "": "Empty translation file - translations will be populated from POEditor"\n}'
            }
        ];

        files.forEach(file => {
            if (this.dryRun) {
                console.log(`📄 Would create file: ${file.path}`);
            } else {
                try {
                    fs.writeFileSync(file.path, file.content, 'utf8');
                    console.log(`✅ Created file: ${file.path}`);
                } catch (error) {
                    console.error(`❌ Failed to create ${file.path}: ${error.message}`);
                }
            }
        });
    }

    /**
     * Show next steps
     */
    showNextSteps(config) {
        console.log(`\n🎉 Language setup ${this.dryRun ? 'planned' : 'completed'} for ${config.name}!\n`);
        
        console.log('📋 **Next Steps:**\n');
        console.log('1. **Download translations from POEditor:**');
        console.log('   npm run locale-download');
        console.log('   (This will populate the actual translations)\n');
        
        console.log('2. **Verify the setup:**');
        console.log('   npm run locale-audit');
        console.log('   (Should show the new language in supported locales)\n');
        
        console.log('3. **Test the new locale:**');
        console.log('   - Check that translation files were created');
        console.log('   - Verify the language appears in ChurchCRM language selector\n');
        
        console.log('📄 **Files created:**');
        console.log(`   - ${this.localesPath} (updated)`);
        console.log(`   - ../../src/locale/textdomain/${config.locale}/LC_MESSAGES/messages.po`);
        console.log(`   - ../../src/locale/i18n/${config.locale}.json\n`);
        
        if (this.dryRun) {
            console.log('🔄 **Run without --dry-run to actually create the files**\n');
        }
    }

    /**
     * Run the language setup process
     */
    async run() {
        try {
            console.log('🚀 ChurchCRM Language Setup Tool\n');

            // Parse arguments or get interactive input
            let config;
            if (this.interactive) {
                config = await this.promptForInput();
            } else {
                config = this.parseArguments();
            }

            // Validate configuration
            if (!this.validateConfig(config)) {
                process.exit(1);
            }

            // Show configuration
            console.log('\n📋 Language Configuration:');
            console.log(`   Name: ${config.name}`);
            console.log(`   POEditor Code: ${config.code}`);
            console.log(`   Locale: ${config.locale}`);
            console.log(`   Country: ${config.country}`);
            console.log(`   DataTables: ${config.datatables}`);
            console.log(`   Dry Run: ${this.dryRun}\n`);

            // Check if language already exists
            if (this.checkLanguageExists(config)) {
                process.exit(1);
            }

            // Perform setup steps
            console.log('🔧 Setting up language files...\n');

            // Add to locales.json
            if (!this.addToLocalesFile(config)) {
                process.exit(1);
            }

            // Create directories
            this.createDirectories(config);

            // Create translation files
            this.createTranslationFiles(config);

            // Show next steps
            this.showNextSteps(config);

            console.log('✨ Language setup completed successfully!');
            process.exit(0);

        } catch (error) {
            console.error('💥 Language setup failed:', error.message);
            process.exit(1);
        }
    }
}

// Run the tool if this script is executed directly
if (require.main === module) {
    const setupTool = new LanguageSetup();
    
    // Check for dry-run flag before async operations
    if (process.argv.includes('--dry-run')) {
        setupTool.dryRun = true;
    }
    if (process.argv.includes('--interactive')) {
        setupTool.interactive = true;
    }
    
    setupTool.run();
}

module.exports = LanguageSetup;