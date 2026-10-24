#!/usr/bin/env node

/**
 * cypress/data/seed.sql holds test data. The test database gets its schema from
 * src/mysql/install/Install.sql, which loads first, so a table defined in both files drifts
 * (#9754: a charset change reached Install.sql but not the seed, and CI tested a schema no
 * install has). This check fails when the seed defines a table other than the per-group
 * groupprop_N tables the app creates itself, or inserts into a table without naming its
 * columns (a positional insert breaks when a column is added anywhere but the end).
 */

const fs = require('fs');
const path = require('path');

const SEED_SQL = path.join(__dirname, '..', 'cypress', 'data', 'seed.sql');
const APP_CREATED = /^groupprop_\d+$/;

function problems(sql) {
    const found = [];
    for (const match of sql.matchAll(/^\s*(?:DROP\s+TABLE(?:\s+IF\s+EXISTS)?|CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?)\s+(?:`(\w+)`|(\w+))/gim)) {
        const table = match[1] || match[2];
        if (!APP_CREATED.test(table)) {
            found.push(`table \`${table}\` is defined in the seed; define it in Install.sql only`);
        }
    }
    for (const match of sql.matchAll(/^\s*INSERT(?:\s+IGNORE)?\s+INTO\s+(?:`(\w+)`|(\w+))\s+VALUES/gim)) {
        const table = match[1] || match[2];
        found.push(`insert into \`${table}\` names no columns; use INSERT INTO \`${table}\` (\`col\`, ...) VALUES`);
    }
    return found;
}

function main() {
    const found = problems(fs.readFileSync(SEED_SQL, 'utf8'));
    if (found.length > 0) {
        console.error(`cypress/data/seed.sql:\n  ${found.join('\n  ')}`);
        process.exit(1);
    }
    console.log('cypress/data/seed.sql is data only');
}

if (require.main === module) {
    main();
}

module.exports = { problems };
