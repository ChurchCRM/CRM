#!/usr/bin/env node

/**
 * The 7.9.0 upgrade copies a self-service account's language to the person
 * only when it is a language ChurchCRM ships. That list is written out in the
 * SQL, so it must equal the `locale` codes in src/locale/locales.json; a locale
 * added or removed there without the SQL would be dropped or kept by mistake.
 *
 *   node scripts/validate-upgrade-locales.js
 */

const fs = require('node:fs');
const path = require('node:path');

const ROOT = path.join(__dirname, '..');
const UPGRADE_SQL = 'src/mysql/upgrade/7.9.0.sql';
const LOCALES_JSON = 'src/locale/locales.json';

/** The quoted codes in the `IN (...)` list that follows the ui.locale join. */
function upgradeLocaleCodes(sql) {
    const block = sql.match(/`setting_name` = 'ui\.locale'[\s\S]*?\bIN \(([^)]*)\)/);
    if (!block) {
        return null;
    }
    return [...block[1].matchAll(/'([^']*)'/g)].map((match) => match[1]);
}

function shippedLocaleCodes(locales) {
    return Object.values(locales).map((entry) => entry.locale);
}

/** @returns {string[]} one line per problem; empty when the lists agree */
function compare(upgradeCodes, shippedCodes) {
    if (upgradeCodes === null) {
        return [`${UPGRADE_SQL}: the ui.locale language list was not found`];
    }
    const problems = [];
    const seen = new Set();
    for (const code of upgradeCodes) {
        if (seen.has(code)) {
            problems.push(`${UPGRADE_SQL}: '${code}' is listed twice`);
        }
        seen.add(code);
    }
    const shipped = new Set(shippedCodes);
    for (const code of shipped) {
        if (!seen.has(code)) {
            problems.push(`${UPGRADE_SQL}: '${code}' is in ${LOCALES_JSON} but missing from the language list`);
        }
    }
    for (const code of seen) {
        if (!shipped.has(code)) {
            problems.push(`${UPGRADE_SQL}: '${code}' is in the language list but not in ${LOCALES_JSON}`);
        }
    }
    return problems;
}

function main() {
    const sql = fs.readFileSync(path.join(ROOT, UPGRADE_SQL), 'utf8');
    const locales = JSON.parse(fs.readFileSync(path.join(ROOT, LOCALES_JSON), 'utf8'));
    const problems = compare(upgradeLocaleCodes(sql), shippedLocaleCodes(locales));
    if (problems.length > 0) {
        console.error(problems.join('\n'));
        process.exit(1);
    }
    console.log(`${UPGRADE_SQL}: the language list matches ${LOCALES_JSON}`);
}

if (require.main === module) {
    main();
}

module.exports = { compare, shippedLocaleCodes, upgradeLocaleCodes };
