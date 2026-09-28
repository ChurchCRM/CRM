#!/usr/bin/env node

/**
 * Verifies that the Cypress seed schema (cypress/data/seed.sql) declares the
 * same table character set as the canonical install schema
 * (src/mysql/install/Install.sql).
 *
 * Why: seed.sql is a hand-refreshed mysqldump of a development database, so it
 * drifts away from Install.sql whenever a charset migration lands only in the
 * install/upgrade SQL. That drift is invisible — every Cypress job loads
 * seed.sql, so the tests exercise a schema no real installation has. Issue
 * #9754 is exactly that: the #8856 utf8mb3 -> utf8mb4 conversion of `note_nte`
 * was applied to Install.sql and src/mysql/upgrade/7.3.1-cleanup.sql but never
 * to seed.sql, so a note containing an emoji 500'd in CI while working fine on
 * a fresh install.
 *
 * Scope, deliberately narrow:
 *   - `utf8` and `utf8mb3` are treated as the same charset. `utf8` is MySQL's
 *     historical alias for the 3-byte charset; the two files simply spell it
 *     differently (Install.sql is hand-written, seed.sql is dumper output).
 *   - Only the table charset is compared, not the collation. seed.sql spells
 *     out a collation on every table while Install.sql leaves it implicit in
 *     places (e.g. `user_settings`), and an absent collation means "the
 *     server default for this charset" — which is not something this script
 *     can resolve without a live server.
 *   - Tables that exist in only one of the two files are skipped: seed.sql
 *     carries runtime-created tables (`groupprop_<id>`) that Install.sql
 *     rightly never declares.
 */

const fs = require('node:fs');
const path = require('node:path');

const REPO_ROOT = path.join(__dirname, '..');
const INSTALL_SQL = path.join(REPO_ROOT, 'src', 'mysql', 'install', 'Install.sql');
const SEED_SQL = path.join(REPO_ROOT, 'cypress', 'data', 'seed.sql');

/**
 * Extract `table name -> declared charset` from a .sql file.
 *
 * Matches each `CREATE TABLE \`name\` ( ... ) <options>;` statement and reads
 * the charset out of the options tail, which is written either as
 * `DEFAULT CHARSET=utf8mb4` (mysqldump) or `CHARACTER SET utf8mb4`
 * (hand-written Install.sql).
 */
function parseTableCharsets(filePath) {
    const sql = fs.readFileSync(filePath, 'utf8');
    const charsets = new Map();
    const createTable = /CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`([^`]+)`([\s\S]*?);\s*$/gm;

    let match;
    while ((match = createTable.exec(sql)) !== null) {
        const [, tableName, statementBody] = match;
        // Everything after the final ")" is the table options tail.
        const optionsTail = statementBody.slice(statementBody.lastIndexOf(')'));
        const charset = optionsTail.match(/(?:DEFAULT\s+CHARSET=|CHARACTER\s+SET\s+)([A-Za-z0-9_]+)/i);
        charsets.set(tableName, charset ? charset[1].toLowerCase() : null);
    }

    return charsets;
}

/** `utf8` is MySQL's legacy alias for the 3-byte `utf8mb3`. */
function normalizeCharset(charset) {
    return charset === 'utf8' ? 'utf8mb3' : charset;
}

console.log('🔍 Seed / Install Charset Validation');
console.log('====================================\n');

for (const filePath of [INSTALL_SQL, SEED_SQL]) {
    if (!fs.existsSync(filePath)) {
        console.error(`❌ Missing ${path.relative(REPO_ROOT, filePath)}`);
        process.exit(1);
    }
}

const installCharsets = parseTableCharsets(INSTALL_SQL);
const seedCharsets = parseTableCharsets(SEED_SQL);

const mismatches = [];
let compared = 0;

for (const [tableName, installCharset] of installCharsets) {
    if (!seedCharsets.has(tableName)) {
        continue; // not seeded — nothing to compare
    }
    compared++;

    const seedCharset = seedCharsets.get(tableName);
    if (normalizeCharset(installCharset) !== normalizeCharset(seedCharset)) {
        mismatches.push({ tableName, installCharset, seedCharset });
    }
}

console.log(
    `📋 Compared ${compared} table(s) present in both Install.sql (${installCharsets.size}) and seed.sql (${seedCharsets.size})\n`
);

if (mismatches.length > 0) {
    console.error('❌ Charset drift between Install.sql and cypress/data/seed.sql:\n');
    for (const { tableName, installCharset, seedCharset } of mismatches) {
        console.error(`   ${tableName}`);
        console.error(`     Install.sql: ${installCharset ?? '(none declared)'}`);
        console.error(`     seed.sql:    ${seedCharset ?? '(none declared)'}`);
    }
    console.error('\nUpdate the CREATE TABLE block in cypress/data/seed.sql to match Install.sql,');
    console.error('so Cypress exercises the same schema a real installation has.');
    process.exit(1);
}

console.log('✅ Seed schema charsets match the install schema.');
