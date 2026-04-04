/**
 * ChurchCRM Locale Build System Configuration
 *
 * Defines shared static paths and build settings used by all locale
 * build scripts to ensure consistency across the build system.
 */

const path = require('path');

const PROJECT_ROOT = path.resolve(__dirname, '../..');

const LocaleConfig = {
    projectRoot: PROJECT_ROOT,
    localeRoot: path.join(PROJECT_ROOT, 'locale'),
    srcRoot: path.join(PROJECT_ROOT, 'src'),

    localesJson: path.join(PROJECT_ROOT, 'src/locale/locales.json'),
    messagesPo: path.join(PROJECT_ROOT, 'locale/messages.po'),

    i18nDir: path.join(PROJECT_ROOT, 'src/locale/i18n'),
    // All i18next extraction output goes to .work/ (git-ignored)
    localesDir: path.join(PROJECT_ROOT, 'locale/.work/locales'),

    i18nextConfig: path.join(__dirname, 'i18next.config.ts'),

    // All build-time intermediate files go here (git-ignored via locale/.work/)
    // The main build wipes this entire directory on completion.
    temp: {
        root: path.join(PROJECT_ROOT, 'locale/.work'),
        dbStrings: path.join(PROJECT_ROOT, 'locale/.work/db-strings'),
        staticStrings: path.join(PROJECT_ROOT, 'locale/.work/static-strings'),
        pluginHelp: path.join(PROJECT_ROOT, 'locale/.work/plugin-help'),
    },

    terms: {
        root: path.join(PROJECT_ROOT, 'locale/terms'),
        missing: path.join(PROJECT_ROOT, 'locale/terms/missing'),
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
