#!/usr/bin/env node

/**
 * Rejects MariaDB-only column-level `IF EXISTS` / `IF NOT EXISTS` clauses in
 * upgrade scripts under src/mysql/upgrade/.
 *
 * Why: real MySQL does not support `IF [NOT] EXISTS` on `ADD COLUMN`,
 * `DROP COLUMN`, `CHANGE COLUMN`, or `MODIFY COLUMN` — that's a MariaDB
 * extension. Every nightly "Build, Test and Package" run since 2026-09-10
 * failed on the MySQL matrix (churchcrm-mysql / v6-mysql, both PHP 8.4 and
 * 8.5) because src/mysql/upgrade/7.7.0-2fa-grace-period.sql used
 * `ADD COLUMN IF NOT EXISTS`, which MySQL rejects with a hard SQL syntax
 * error (1064) — permanently parking any real MySQL install on the
 * /external/system/db-upgrade page.
 *
 * The project already documents (and works around) this exact gotcha in
 * multiple places — see the "MySQL-compatible conditional column drop"
 * comments in src/mysql/upgrade/6.5.0.sql and pre-6.0.0-consolidated.sql —
 * this script just makes the existing convention machine-enforced instead
 * of relying on every author rediscovering it.
 *
 * No guard is needed in the first place: UpgradeService::upgradeDatabaseVersion()
 * gates each script by the exact starting dbVersion in mysql/upgrade.json, so
 * a script that adds a column can assume that column does not yet exist, and
 * a script that drops a column can assume it does — the version-gated runner
 * guarantees it. If a script genuinely needs to guard column existence (e.g.
 * a schema that can arrive via more than one upgrade path), use the
 * information_schema.columns + PREPARE/EXECUTE dynamic-SQL pattern already
 * used elsewhere (see src/mysql/upgrade/7.7.0-events-utf8mb4.sql for the
 * equivalent pattern applied to an existence guard).
 *
 * Table/index-level `IF [NOT] EXISTS` (e.g. `CREATE TABLE IF NOT EXISTS`,
 * `DROP TABLE IF EXISTS`, `ADD INDEX IF NOT EXISTS`, `DROP INDEX IF EXISTS`)
 * IS supported by both MySQL and MariaDB and is NOT flagged by this script —
 * only the column-level variant is a MariaDB-only extension.
 */

const fs = require('node:fs');
const path = require('node:path');
const { execSync } = require('node:child_process');

const REPO_ROOT = path.join(__dirname, '..');
const UPGRADE_DIR = path.join(REPO_ROOT, 'src', 'mysql', 'upgrade');

// Matches ADD/DROP/CHANGE/MODIFY [COLUMN] IF [NOT] EXISTS — the COLUMN
// keyword is optional in MariaDB's grammar, so it must be optional here too.
const DISALLOWED_PATTERN = /\b(ADD|DROP|CHANGE|MODIFY)\s+(COLUMN\s+)?IF\s+(NOT\s+)?EXISTS\b/gi;

function findViolations(filePath) {
    const sql = fs.readFileSync(filePath, 'utf8');
    const lines = sql.split('\n');
    const violations = [];

    lines.forEach((line, index) => {
        // Strip a trailing `-- comment` so a prose mention of the disallowed
        // syntax (e.g. explaining why a script avoids it) isn't flagged —
        // only an actual SQL clause counts as a violation.
        const codePart = line.split(/--/)[0];

        DISALLOWED_PATTERN.lastIndex = 0;
        const match = DISALLOWED_PATTERN.exec(codePart);
        if (match) {
            violations.push({ line: index + 1, text: line.trim(), matched: match[0] });
        }
    });

    return violations;
}

function getStagedUpgradeSqlFiles() {
    let output;
    try {
        output = execSync('git diff --cached --name-only --diff-filter=ACM', { encoding: 'utf8' });
    } catch {
        return [];
    }

    return output
        .trim()
        .split('\n')
        .filter(Boolean)
        .filter((relPath) => relPath.startsWith('src/mysql/upgrade/') && relPath.endsWith('.sql'))
        .map((relPath) => path.join(REPO_ROOT, relPath))
        .filter((absPath) => fs.existsSync(absPath));
}

function getAllUpgradeSqlFiles() {
    return fs
        .readdirSync(UPGRADE_DIR)
        .filter((name) => name.endsWith('.sql'))
        .map((name) => path.join(UPGRADE_DIR, name));
}

function main() {
    const staged = process.argv.includes('--staged');
    const files = staged ? getStagedUpgradeSqlFiles() : getAllUpgradeSqlFiles();

    console.log('🔍 MySQL Upgrade Script Syntax Validation');
    console.log('==========================================');
    console.log('');
    console.log(`📋 Checking ${files.length} upgrade SQL file(s)${staged ? ' (staged)' : ''}`);
    console.log('');

    let violationCount = 0;

    for (const file of files) {
        const violations = findViolations(file);
        if (violations.length > 0) {
            const relPath = path.relative(REPO_ROOT, file);
            for (const v of violations) {
                console.log(`  ✘ ${relPath}:${v.line}: disallowed \`${v.matched}\``);
                console.log(`      ${v.text}`);
                violationCount++;
            }
        }
    }

    if (violationCount > 0) {
        console.log('');
        console.log(`✘ Found ${violationCount} disallowed column-level IF [NOT] EXISTS clause(s).`);
        console.log('  MySQL does not support IF [NOT] EXISTS on ADD/DROP/CHANGE/MODIFY COLUMN —');
        console.log('  that is a MariaDB-only extension. The version-gated upgrade runner');
        console.log('  (UpgradeService::upgradeDatabaseVersion) already guarantees a column\'s');
        console.log('  existence state at the point each script runs, so no guard is needed.');
        console.log('  See src/mysql/upgrade/6.5.0.sql for the established plain-DDL pattern.');
        process.exit(1);
    }

    console.log('✨ No MariaDB-only column-level IF [NOT] EXISTS clauses found!');
}

main();
