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
    for (const match of sql.matchAll(/^(?:DROP TABLE(?: IF EXISTS)?|CREATE TABLE(?: IF NOT EXISTS)?)\s+`(\w+)`/gim)) {
        if (!APP_CREATED.test(match[1])) {
            found.push(`table \`${match[1]}\` is defined in the seed; define it in Install.sql only`);
        }
    }
    for (const match of sql.matchAll(/^INSERT(?: IGNORE)? INTO\s+`(\w+)`\s+VALUES/gim)) {
        found.push(`insert into \`${match[1]}\` names no columns; use INSERT INTO \`${match[1]}\` (\`col\`, ...) VALUES`);
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
