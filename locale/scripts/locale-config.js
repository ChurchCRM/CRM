/**
 * ChurchCRM Locale Build System Configuration
 * 
 * Centralized configuration for all locale build scripts to ensure
 * consistent paths and settings across the build system.
 * 
 * Configuration is now loaded via environment variables (.env file).
 * See .env.example for required variables.
 */

const path = require('path');

const PROJECT_ROOT = path.resolve(__dirname, '../..');

const LocaleConfig = {
    projectRoot: PROJECT_ROOT,
    localeRoot: path.join(PROJECT_ROOT, 'locale'),
    srcRoot: path.join(PROJECT_ROOT, 'src'),
    tempRoot: path.join(PROJECT_ROOT, 'temp'),
    
    localesJson: path.join(PROJECT_ROOT, 'src/locale/locales.json'),
    messagesJson: path.join(PROJECT_ROOT, 'locale/messages.json'),
    messagesPo: path.join(PROJECT_ROOT, 'locale/terms/messages.po'),
    
    i18nDir: path.join(PROJECT_ROOT, 'src/locale/i18n'),
    localesDir: path.join(PROJECT_ROOT, 'locale/locales'),
    
    i18nextParserConfig: path.join(__dirname, 'i18next-parser.config.js'),
    
    temp: {
        root: path.join(PROJECT_ROOT, 'temp'),
        dbStrings: path.join(PROJECT_ROOT, 'temp/churchcrm-locale-db-strings'),
        staticStrings: path.join(PROJECT_ROOT, 'temp/churchcrm-locale-static'),
        phpStrings: path.join(PROJECT_ROOT, 'temp/churchcrm-locale-php'),
        jsStrings: path.join(PROJECT_ROOT, 'temp/churchcrm-locale-js'),
        pluginHelp: path.join(PROJECT_ROOT, 'temp/churchcrm-locale-plugin-help'),
    },
    
    terms: {
        root: path.join(PROJECT_ROOT, 'locale/terms'),
        base: path.join(PROJECT_ROOT, 'locale/terms/base'),
        missing: path.join(PROJECT_ROOT, 'locale/terms/missing'),
        missingNew: path.join(PROJECT_ROOT, 'locale/terms/missing'),
    },
    
    termsOutput: {
        databasePo: path.join(PROJECT_ROOT, 'locale/terms/base/database-terms.po'),
        staticPo: path.join(PROJECT_ROOT, 'locale/terms/base/static-terms.po'),
        messagesPo: path.join(PROJECT_ROOT, 'locale/terms/base/messages.po'),
        translationJson: path.join(PROJECT_ROOT, 'locale/terms/base/translation.json'),
    },
    
    poeditor: {
        outputJson: path.join(PROJECT_ROOT, 'src/locale/poeditor.json'),
        auditReport: path.join(PROJECT_ROOT, 'locale/poeditor-audit.md'),
    },
    
    settings: {
        missingTermsBatchSize: 150,
        wipThreshold: 5,
        completeThreshold: 90,
        goodThreshold: 75,
    },
};

module.exports = LocaleConfig;
