#!/usr/bin/env node

/**
 * Keeps new raw SQL out of ordinary Cypress specs (#10377).
 *
 * For every .js/.ts file under cypress/ it counts the database uses: SQL
 * statements written as string literals, references to `cy.dbQuery` or the
 * `db:query` task, and calls to local helpers that wrap them. A file may have
 * a non-zero count only when cypress/db-access.json approves it (a dedicated
 * schema spec or a fixture/reset utility, with a reason) or its baseline
 * allows that many. The baseline only goes down. Rules:
 * .agents/skills/churchcrm/cypress-testing.md "Database access".
 *
 *   npm run lint
 *   node scripts/validate-cypress-db-access.js --report   counts by category and file
 *   node scripts/validate-cypress-db-access.js --update   lower the baseline after a migration
 */

const fs = require('node:fs');
const path = require('node:path');
const ts = require('typescript');

const REPO_ROOT = path.join(__dirname, '..');
const SCAN_ROOT = 'cypress';
const ALLOWLIST_PATH = 'cypress/db-access.json';
const APPROVED_KINDS = ['schema', 'fixture'];
const DB_NAMES = new Set(['dbQuery', 'db:query', 'db:upgradeBlock']);

// Upper-case keywords only, so UI strings such as "Select a team" never match.
const SQL_STATEMENT = new RegExp(
    `^\\s*(?:${[
        String.raw`SELECT\s`,
        String.raw`(?:INSERT|REPLACE)\s+(?:IGNORE\s+)?INTO\s`,
        String.raw`UPDATE\s+[\w\x60]`,
        String.raw`DELETE\s+(?:[\w\x60]+\s+)?FROM\s`,
        String.raw`TRUNCATE\s`,
        String.raw`(?:ALTER|CREATE|DROP)\s+(?:TEMPORARY\s+)?(?:TABLE|INDEX|VIEW|TRIGGER|DATABASE)\s`,
        String.raw`SHOW\s+(?:CREATE|COLUMNS|INDEX|INDEXES|KEYS|TABLES|FULL|VARIABLES)\s`,
        String.raw`SET\s+(?:@|SESSION\s|GLOBAL\s|FOREIGN_KEY_CHECKS|NAMES\s)`,
        String.raw`CALL\s+\w+\s*\(`,
    ].join('|')})`,
);

const HOOKS = new Set(['it', 'specify', 'before', 'beforeEach', 'after', 'afterEach']);
const RESET_NAME = /clean|reset|purge|teardown|restore|remove|wipe|clear/i;
const SCHEMA_TITLE =
    /unique|foreign key|\bFK\b|cascade|on delete|set null|\benum\b|\bindex\b|constraint|schema|not null|uidx|migration/i;
const SCHEMA_SQL = /information_schema|@@|sql_mode/i;
const ASSERTS_DB_ERROR = /expect\(\s*\w+\.error\b[^;]*\.not\.(?:eq|equal)\(\s*null|\.error\.code\)\.to/;

const CATEGORIES = {
    setup: 'application setup/mutation',
    lookup: 'API-response workaround (read outside a test)',
    assertion: 'assertion (read inside a test)',
    schema: 'schema constraint',
    reset: 'fixture/reset',
};

function listFiles(relativeDir) {
    const absolute = path.join(REPO_ROOT, relativeDir);
    if (!fs.existsSync(absolute)) {
        return [];
    }
    return fs
        .readdirSync(absolute, { withFileTypes: true })
        .flatMap((entry) => {
            const child = `${relativeDir}/${entry.name}`;
            if (entry.isDirectory()) {
                return listFiles(child);
            }
            return /\.(js|ts)$/.test(entry.name) && !entry.name.endsWith('.d.ts') ? [child] : [];
        })
        .sort();
}

function literalText(node) {
    if (ts.isStringLiteral(node) || ts.isNoSubstitutionTemplateLiteral(node)) {
        return node.text;
    }
    if (ts.isTemplateExpression(node)) {
        return node.head.text + node.templateSpans.map((span) => `\${}${span.literal.text}`).join('');
    }
    return null;
}

function calleeName(call) {
    const expression = call.expression;
    if (ts.isIdentifier(expression)) {
        return expression.text;
    }
    if (ts.isPropertyAccessExpression(expression)) {
        // it.only / before.skip count as the hook itself.
        return ts.isIdentifier(expression.expression) && HOOKS.has(expression.expression.text)
            ? expression.expression.text
            : expression.name.text;
    }
    return '';
}

function functionName(node) {
    if (ts.isFunctionDeclaration(node) && node.name) {
        return node.name.text;
    }
    if (
        (ts.isArrowFunction(node) || ts.isFunctionExpression(node)) &&
        ts.isVariableDeclaration(node.parent) &&
        ts.isIdentifier(node.parent.name)
    ) {
        return node.parent.name.text;
    }
    return null;
}

/** The hook or named function a node runs in, plus the test titles above it. */
function enclosing(node) {
    const titles = [];
    let scope = null;
    for (let current = node.parent; current; current = current.parent) {
        if (ts.isCallExpression(current)) {
            const name = calleeName(current);
            const title = current.arguments[0] && literalText(current.arguments[0]);
            if ((HOOKS.has(name) || name === 'describe' || name === 'context') && title) {
                titles.push(title);
            }
            if (scope === null && HOOKS.has(name)) {
                scope = { hook: name };
            }
        }
        if (scope === null && functionName(current)) {
            scope = { fn: functionName(current) };
        }
    }
    return { scope: scope || { hook: 'module' }, titles: titles.join(' / ') };
}

/** Names of local functions that reach the database, directly or through another one. */
function databaseWrappers(functions) {
    const wrappers = new Set();
    let changed = true;
    while (changed) {
        changed = false;
        for (const [name, { touchesDb, calls }] of functions) {
            if (!wrappers.has(name) && (touchesDb || [...calls].some((callee) => wrappers.has(callee)))) {
                wrappers.add(name);
                changed = true;
            }
        }
    }
    return wrappers;
}

/** Which hooks (it, before, after, ...) each local function is reached from. */
function hooksByFunction(calls) {
    const reached = new Map();
    let changed = true;
    while (changed) {
        changed = false;
        for (const call of calls) {
            const sources = call.scope.hook ? [call.scope.hook] : [...(reached.get(call.scope.fn) || [])];
            const target = reached.get(call.name) || new Set();
            for (const hook of sources) {
                if (!target.has(hook)) {
                    target.add(hook);
                    changed = true;
                }
            }
            reached.set(call.name, target);
        }
    }
    return reached;
}

function scanFile(relativePath) {
    const source = fs.readFileSync(path.join(REPO_ROOT, relativePath), 'utf8');
    const kind = relativePath.endsWith('.ts') ? ts.ScriptKind.TS : ts.ScriptKind.JS;
    const sourceFile = ts.createSourceFile(relativePath, source, ts.ScriptTarget.Latest, true, kind);
    const statements = [];
    const calls = [];
    const functions = new Map();
    let dbRefs = 0;

    const owningFunction = (node) => {
        for (let current = node.parent; current; current = current.parent) {
            const name = functionName(current);
            if (name) {
                if (!functions.has(name)) {
                    functions.set(name, { touchesDb: false, calls: new Set() });
                }
                return functions.get(name);
            }
        }
        return null;
    };

    const visit = (node) => {
        const text = literalText(node);
        const isDbRef =
            (ts.isPropertyAccessExpression(node) && DB_NAMES.has(node.name.text)) || (text !== null && DB_NAMES.has(text));
        if (isDbRef) {
            dbRefs++;
            const owner = owningFunction(node);
            if (owner) {
                owner.touchesDb = true;
            }
        } else if (text !== null && SQL_STATEMENT.test(text) && !ts.isTemplateSpan(node.parent)) {
            const { scope, titles } = enclosing(node);
            let carrier = node;
            while (
                carrier.parent &&
                (ts.isBinaryExpression(carrier.parent) || ts.isParenthesizedExpression(carrier.parent))
            ) {
                carrier = carrier.parent;
            }
            const call = ts.isCallExpression(carrier.parent) ? carrier.parent : null;
            let statement = carrier;
            while (statement.parent && !ts.isExpressionStatement(statement) && !ts.isBlock(statement.parent)) {
                statement = statement.parent;
            }
            statements.push({
                line: sourceFile.getLineAndCharacterOfPosition(node.getStart(sourceFile)).line + 1,
                verb: text.trim().split(/\s+/)[0],
                sql: text.replace(/\s+/g, ' ').trim(),
                callee: call ? calleeName(call) : '',
                assertsError: ASSERTS_DB_ERROR.test(statement.getText(sourceFile)),
                scope,
                titles,
            });
        }
        if (ts.isCallExpression(node) && ts.isIdentifier(node.expression)) {
            calls.push({ name: node.expression.text, ...enclosing(node) });
            owningFunction(node)?.calls.add(node.expression.text);
        }
        ts.forEachChild(node, visit);
    };
    visit(sourceFile);

    const wrappers = databaseWrappers(functions);
    const wrapperCalls = calls.filter((call) => wrappers.has(call.name)).length;
    return { statements, uses: statements.length + dbRefs + wrapperCalls, hooksOf: hooksByFunction(calls) };
}

function categorize(statement, hooksOf) {
    const hooks = statement.scope.hook
        ? new Set([statement.scope.hook])
        : hooksOf.get(statement.scope.fn) || new Set();
    const inTest = hooks.has('it') || hooks.has('specify');
    const onlyTeardown = hooks.size > 0 && [...hooks].every((hook) => hook === 'after' || hook === 'afterEach');
    const read = statement.verb === 'SELECT' || statement.verb === 'SHOW';

    if (
        /reject/i.test(statement.callee) ||
        statement.assertsError ||
        SCHEMA_SQL.test(statement.sql) ||
        ['SHOW', 'ALTER', 'CREATE', 'DROP'].includes(statement.verb) ||
        (inTest && SCHEMA_TITLE.test(statement.titles))
    ) {
        return { category: 'schema', inTest };
    }
    const deletes = statement.verb === 'DELETE' || statement.verb === 'TRUNCATE';
    if (onlyTeardown || RESET_NAME.test(statement.scope.fn || '') || (deletes && !inTest)) {
        return { category: 'reset', inTest };
    }
    if (read) {
        return { category: inTest ? 'assertion' : 'lookup', inTest };
    }
    return { category: 'setup', inTest };
}

function scanTree() {
    const results = new Map();
    for (const file of listFiles(SCAN_ROOT)) {
        const { statements, uses, hooksOf } = scanFile(file);
        if (uses === 0) {
            continue;
        }
        for (const statement of statements) {
            Object.assign(statement, categorize(statement, hooksOf));
        }
        results.set(file, { statements, uses });
    }
    return results;
}

function readAllowlist() {
    const raw = JSON.parse(fs.readFileSync(path.join(REPO_ROOT, ALLOWLIST_PATH), 'utf8'));
    return { approved: raw.approved || {}, baseline: raw.baseline || {}, raw };
}

function check(results, { approved, baseline }) {
    const problems = [];
    for (const [file, entry] of Object.entries(approved)) {
        if (!APPROVED_KINDS.includes(entry.kind) || !entry.reason) {
            problems.push(`${file}: an approved entry needs "kind" (${APPROVED_KINDS.join(' or ')}) and a "reason"`);
        }
        if (baseline[file] !== undefined) {
            problems.push(`${file}: listed under both "approved" and "baseline"`);
        }
    }
    for (const [file, { uses }] of results) {
        const allowed = baseline[file] || 0;
        if (!approved[file] && uses > allowed) {
            problems.push(
                allowed === 0
                    ? `${file}: ${uses} database use(s) in a file that had none`
                    : `${file}: ${uses} database uses, the baseline allows ${allowed}`,
            );
        }
    }
    const stale = [
        ...Object.keys(approved)
            .filter((file) => !results.has(file))
            .map((file) => `${file}: approved, but it no longer uses the database`),
        ...Object.entries(baseline)
            .filter(([file, allowed]) => (results.get(file)?.uses || 0) < allowed)
            .map(([file, allowed]) => `${file}: ${results.get(file)?.uses || 0} uses, baseline ${allowed}`),
    ];
    return { problems, stale };
}

function update(results, allowlist) {
    const { problems, stale } = check(results, allowlist);
    if (problems.length > 0) {
        console.error('❌ --update only lowers the baseline. Fix these first:\n');
        for (const problem of problems) {
            console.error(`   - ${problem}`);
        }
        process.exit(1);
    }
    const baseline = {};
    for (const [file, allowed] of Object.entries(allowlist.baseline)) {
        const uses = Math.min(results.get(file)?.uses || 0, allowed);
        if (uses > 0) {
            baseline[file] = uses;
        }
    }
    for (const file of Object.keys(allowlist.approved)) {
        if (!results.has(file)) {
            delete allowlist.raw.approved[file];
        }
    }
    allowlist.raw.baseline = Object.fromEntries(Object.entries(baseline).sort(([a], [b]) => a.localeCompare(b)));
    fs.writeFileSync(path.join(REPO_ROOT, ALLOWLIST_PATH), `${JSON.stringify(allowlist.raw, null, 4)}\n`);
    console.log(`✅ ${ALLOWLIST_PATH}: ${stale.length} entr${stale.length === 1 ? 'y' : 'ies'} lowered or removed.`);
}

function report(results, { approved }) {
    const keys = Object.keys(CATEGORIES);
    const totals = Object.fromEntries(keys.map((key) => [key, 0]));
    let writesInTests = 0;
    let uses = 0;
    const rows = [];
    for (const [file, entry] of results) {
        const counts = Object.fromEntries(keys.map((key) => [key, 0]));
        for (const statement of entry.statements) {
            counts[statement.category]++;
            totals[statement.category]++;
            if (statement.inTest && statement.category === 'setup') {
                writesInTests++;
            }
        }
        uses += entry.uses;
        const status = approved[file] ? `approved: ${approved[file].kind}` : 'baseline';
        rows.push(
            `| ${file.replace(`${SCAN_ROOT}/`, '')} | ${status} | ${entry.uses} | ${entry.statements.length} | ${keys.map((key) => counts[key]).join(' | ')} |`,
        );
    }
    const statementTotal = Object.values(totals).reduce((sum, value) => sum + value, 0);
    console.log(`Files: ${results.size}. Database uses: ${uses}. SQL statements: ${statementTotal}.`);
    console.log(`INSERT/UPDATE/DELETE run inside an it(): ${writesInTests}.\n`);
    console.log('| Category | SQL statements |\n|---|---:|');
    for (const key of keys) {
        console.log(`| ${CATEGORIES[key]} | ${totals[key]} |`);
    }
    console.log(`\n| File | Status | Uses | SQL | ${keys.join(' | ')} |`);
    console.log(`|---|---|---:|---:|${keys.map(() => '---:').join('|')}|`);
    console.log(rows.join('\n'));
}

const results = scanTree();
const allowlist = readAllowlist();
const args = process.argv.slice(2);

if (args.includes('--report')) {
    report(results, allowlist);
    process.exit(0);
}
if (args.includes('--update')) {
    update(results, allowlist);
    process.exit(0);
}

console.log('🔍 Cypress database access check');
console.log('================================\n');
const { problems, stale } = check(results, allowlist);
if (problems.length > 0) {
    console.error('❌ Raw SQL or cy.dbQuery() outside the approved boundaries:\n');
    for (const problem of problems) {
        console.error(`   - ${problem}`);
    }
    console.error('\nCreate, change and read records through the API or the UI. A dedicated schema spec');
    console.error(`or a fixture/reset utility goes under "approved" in ${ALLOWLIST_PATH} with a reason.`);
    console.error('See .agents/skills/churchcrm/cypress-testing.md "Database access".\n');
    process.exit(1);
}
if (stale.length > 0) {
    console.error(`❌ ${ALLOWLIST_PATH} allows more than the code uses:\n`);
    for (const line of stale) {
        console.error(`   - ${line}`);
    }
    console.error('\nLock in the improvement: node scripts/validate-cypress-db-access.js --update\n');
    process.exit(1);
}
const baselineFiles = Object.keys(allowlist.baseline).length;
console.log(
    `✅ ${results.size} files use the database: ${Object.keys(allowlist.approved).length} approved, ${baselineFiles} on the shrinking baseline.`,
);
