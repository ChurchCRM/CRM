#!/usr/bin/env node

/**
 * Verifies every Cypress spec under cypress/e2e/ is reachable by at least one
 * `specPattern` of a config that some runner actually executes.
 *
 * A spec that matches no runner's specPattern is never executed by any CI job
 * or npm script, but it still looks like coverage in code review — it rots
 * silently against the page it was written for. `cypress/e2e/finance/
 * deposit-search.spec.js` sat in that state for a whole release (#9728): 266
 * lines of assertions, zero runs.
 *
 * Two things keep this check honest:
 *
 *  - Only RUNNER_CONFIGS are consulted. `base.config.ts` declares catch-all
 *    globs that every runner overrides, so counting it would make every spec
 *    look covered. A config file this script has not been told about is an
 *    error, so a new runner cannot slip in unclassified.
 *  - The `specPattern` declarations are read from a token stream, not the raw
 *    source, so a declaration in a `//` or `/* *\/` comment, or inside an
 *    unrelated string literal, is never mistaken for a live one. A commented-
 *    out covering glob must not make an orphan pass.
 *
 * The matcher is `minimatch`, the same library Cypress uses to resolve
 * `specPattern`, so a spec this script calls reachable really is picked up by
 * the runner.
 *
 * Run:  npm run lint:cypress-spec-coverage
 *       (runs the built-in self-test first, then scans the live tree)
 *       node scripts/validate-cypress-spec-coverage.js --self-test
 */

const fs = require('node:fs');
const path = require('node:path');
const { minimatch } = require('minimatch');

const REPO_ROOT = path.join(__dirname, '..');
const E2E_ROOT = path.join(REPO_ROOT, 'cypress', 'e2e');
const CONFIG_ROOT = path.join(REPO_ROOT, 'cypress', 'configs');
const SPEC_EXTENSIONS = ['.spec.js', '.spec.ts'];

/**
 * Configs that a CI job or npm script passes to `cypress run --config-file`.
 * Only their specPatterns count as coverage.
 */
const RUNNER_CONFIGS = [
    'docker.config.ts',
    'docker-ui.config.ts',
    'docker-admin.config.ts',
    'locale.config.ts',
    'new-system.config.ts',
    'upgrade.config.ts',
];

/**
 * Configs that only exist to be extended by a runner. Their specPatterns are
 * always overridden, so they are read for nothing.
 */
const SHARED_CONFIGS = ['base.config.ts'];

// ---- Spec discovery --------------------------------------------------------

/**
 * Recursively collect every spec file under `dir`, as a POSIX path relative to
 * `root` (the form Cypress matches specPattern against).
 * @param {string} dir
 * @param {string} root
 * @returns {string[]}
 */
function collectSpecs(dir, root) {
    const found = [];
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const fullPath = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            found.push(...collectSpecs(fullPath, root));
        } else if (entry.isFile() && SPEC_EXTENSIONS.some((ext) => entry.name.endsWith(ext))) {
            found.push(path.relative(root, fullPath).split(path.sep).join('/'));
        }
    }
    return found;
}

// ---- Config parsing --------------------------------------------------------

/**
 * Tokens after which a `/` starts a regex literal rather than a division.
 * Standard JS heuristic; the configs contain no regex literals today, but a
 * `'` inside one must not open a string.
 */
const REGEX_PRECEDING_PUNCT = new Set(['(', ',', '=', ':', '[', '!', '&', '|', '?', '{', '}', ';', '+', '-', '*', '%', '<', '>', '~', '^']);
const REGEX_PRECEDING_WORDS = new Set(['return', 'typeof', 'instanceof', 'in', 'of', 'new', 'delete', 'void', 'throw', 'case', 'do', 'else', 'yield', 'await']);

/**
 * Split JS/TS source into significant tokens. Whitespace and comments are
 * dropped; string literals are single tokens with their unquoted value, so
 * nothing inside a comment or a string can be mistaken for syntax.
 *
 * Token shapes:
 *   { type: 'string',   value }  — '…', "…", or a `…` with no ${} substitution
 *   { type: 'template', value }  — a `…` that contains ${}; not a literal
 *   { type: 'word',     value }  — identifier or keyword
 *   { type: 'number',   value }
 *   { type: 'regex',    value }
 *   { type: 'punct',    value }  — one character
 *
 * @param {string} source
 * @returns {{ type: string, value: string }[]}
 */
function tokenize(source) {
    const tokens = [];
    let i = 0;

    const previous = () => tokens[tokens.length - 1];
    const regexMayStart = () => {
        const prev = previous();
        if (prev === undefined) {
            return true;
        }
        if (prev.type === 'punct') {
            return REGEX_PRECEDING_PUNCT.has(prev.value);
        }
        return prev.type === 'word' && REGEX_PRECEDING_WORDS.has(prev.value);
    };

    while (i < source.length) {
        const char = source[i];
        const next = source[i + 1];

        if (/\s/.test(char)) {
            i++;
            continue;
        }

        if (char === '/' && next === '/') {
            const lineEnd = source.indexOf('\n', i);
            i = lineEnd === -1 ? source.length : lineEnd;
            continue;
        }

        if (char === '/' && next === '*') {
            const blockEnd = source.indexOf('*/', i + 2);
            i = blockEnd === -1 ? source.length : blockEnd + 2;
            continue;
        }

        if (char === "'" || char === '"' || char === '`') {
            let value = '';
            let hasSubstitution = false;
            i++;
            while (i < source.length && source[i] !== char) {
                if (source[i] === '\\') {
                    value += source[i + 1] ?? '';
                    i += 2;
                    continue;
                }
                if (char === '`' && source[i] === '$' && source[i + 1] === '{') {
                    hasSubstitution = true;
                }
                value += source[i];
                i++;
            }
            i++; // closing quote
            tokens.push({ type: hasSubstitution ? 'template' : 'string', value });
            continue;
        }

        if (char === '/' && regexMayStart()) {
            let value = '';
            let inClass = false;
            i++;
            while (i < source.length && source[i] !== '\n' && (inClass || source[i] !== '/')) {
                if (source[i] === '\\') {
                    value += source[i] + (source[i + 1] ?? '');
                    i += 2;
                    continue;
                }
                if (source[i] === '[') {
                    inClass = true;
                } else if (source[i] === ']') {
                    inClass = false;
                }
                value += source[i];
                i++;
            }
            i++; // closing slash
            while (i < source.length && /[a-z]/i.test(source[i])) {
                i++; // flags
            }
            tokens.push({ type: 'regex', value });
            continue;
        }

        const word = /^[A-Za-z_$][\w$]*/.exec(source.slice(i, i + 200));
        if (word !== null) {
            tokens.push({ type: 'word', value: word[0] });
            i += word[0].length;
            continue;
        }

        const number = /^\d[\w.]*/.exec(source.slice(i, i + 200));
        if (number !== null) {
            tokens.push({ type: 'number', value: number[0] });
            i += number[0].length;
            continue;
        }

        tokens.push({ type: 'punct', value: char });
        i++;
    }

    return tokens;
}

/**
 * Extract the literal glob strings from every live `specPattern:` declaration
 * in a config's source. Every config in cypress/configs/ declares it as a
 * string literal or an array of string literals, so reading the token stream
 * avoids having to transpile + execute TypeScript just to read a constant
 * (the repo has no ts-node/tsx to do that with).
 *
 * Throws on anything it cannot read as a literal — a variable, a spread, a
 * template with substitutions, an empty array. A coverage check that quietly
 * reads nothing is worse than none at all (#9728).
 *
 * @param {string} source
 * @param {string} label  file name for error messages
 * @returns {string[]}
 */
function extractSpecPatterns(source, label) {
    const tokens = tokenize(source);
    const patterns = [];

    for (let i = 0; i < tokens.length - 1; i++) {
        const key = tokens[i];
        const colon = tokens[i + 1];
        const isKey = (key.type === 'word' || key.type === 'string') && key.value === 'specPattern';
        if (!isKey || colon.type !== 'punct' || colon.value !== ':') {
            continue;
        }

        const value = tokens[i + 2];
        if (value === undefined) {
            throw new Error(`${label}: specPattern has no value`);
        }

        if (value.type === 'string') {
            patterns.push(value.value);
            i += 2;
            continue;
        }

        if (value.type !== 'punct' || value.value !== '[') {
            throw new Error(`${label}: specPattern is not a string literal or an array of string literals (found ${describe(value)})`);
        }

        let j = i + 3;
        let count = 0;
        for (; j < tokens.length; j++) {
            const entry = tokens[j];
            if (entry.type === 'punct' && entry.value === ']') {
                break;
            }
            if (entry.type === 'punct' && entry.value === ',') {
                continue;
            }
            if (entry.type !== 'string') {
                throw new Error(`${label}: specPattern array holds a non-literal entry (${describe(entry)}) — only string literals can be verified`);
            }
            patterns.push(entry.value);
            count++;
        }
        if (j >= tokens.length) {
            throw new Error(`${label}: specPattern array is never closed`);
        }
        if (count === 0) {
            throw new Error(`${label}: specPattern array is empty — this runner would execute nothing`);
        }
        i = j;
    }

    return patterns;
}

/** @param {{ type: string, value: string }} token */
function describe(token) {
    return token.type === 'punct' ? `'${token.value}'` : `${token.type} ${JSON.stringify(token.value)}`;
}

// ---- Coverage --------------------------------------------------------------

/**
 * Sort the config directory's `*.config.ts` files into runners and shared
 * bases. Throws on a file in neither list, so a new config has to be
 * classified before this check will pass.
 * @param {string[]} configNames
 * @returns {string[]} runner config file names, in RUNNER_CONFIGS order
 */
function selectRunnerConfigs(configNames) {
    const unknown = configNames.filter((name) => !RUNNER_CONFIGS.includes(name) && !SHARED_CONFIGS.includes(name));
    if (unknown.length > 0) {
        throw new Error(
            `unclassified config(s) in cypress/configs/: ${unknown.join(', ')} — add each to RUNNER_CONFIGS ` +
                '(a CI job or npm script runs it) or SHARED_CONFIGS (only extended by other configs) in ' +
                path.relative(REPO_ROOT, __filename),
        );
    }
    const missing = RUNNER_CONFIGS.filter((name) => !configNames.includes(name));
    if (missing.length > 0) {
        throw new Error(`runner config(s) listed but not present in cypress/configs/: ${missing.join(', ')}`);
    }
    return RUNNER_CONFIGS.filter((name) => configNames.includes(name));
}

/**
 * @param {string[]} specs  repo-relative POSIX spec paths
 * @param {Map<string, string[]>} patternsByConfig  runner config → its specPatterns
 * @returns {{ orphans: string[], deadPatterns: { pattern: string, configs: string[] }[] }}
 */
function findOrphans(specs, patternsByConfig) {
    /** @type {Map<string, string[]>} pattern → configs declaring it */
    const patternSources = new Map();
    for (const [configFile, patterns] of patternsByConfig) {
        for (const pattern of patterns) {
            if (!patternSources.has(pattern)) {
                patternSources.set(pattern, []);
            }
            patternSources.get(pattern).push(configFile);
        }
    }
    const allPatterns = [...patternSources.keys()];

    const orphans = specs.filter((spec) => !allPatterns.some((pattern) => minimatch(spec, pattern)));
    const deadPatterns = allPatterns
        .filter((pattern) => !specs.some((spec) => minimatch(spec, pattern)))
        .map((pattern) => ({ pattern, configs: patternSources.get(pattern) }));

    return { orphans, deadPatterns };
}

// ---- Self-test -------------------------------------------------------------
//
// The repo has no unit-test runner for scripts/, so the parser and coverage
// cases this check depends on are asserted here with node:assert, the way
// scripts/locale-check.js does. It runs before every live scan so a parser
// regression fails CI on its own, without waiting for a real orphan.

function runSelfTest() {
    const assert = require('node:assert');
    const lines = [];
    let failed = false;
    const check = (name, fn) => {
        try {
            fn();
            lines.push(`  ok   ${name}`);
        } catch (e) {
            lines.push(`  FAIL ${name}\n       ${e.message}`);
            failed = true;
        }
    };

    const UI = 'cypress/e2e/ui/**/*.spec.js';
    const FINANCE = 'cypress/e2e/finance/**/*.spec.js';

    check('reads a string specPattern and an array specPattern', () => {
        assert.deepStrictEqual(extractSpecPatterns(`e2e: { specPattern: '${UI}' }`, 'f'), [UI]);
        assert.deepStrictEqual(
            extractSpecPatterns(`e2e: { specPattern: [\n 'cypress/e2e/api/**/*.spec.js',\n "${UI}"\n ], retries: 0 }`, 'f'),
            ['cypress/e2e/api/**/*.spec.js', UI],
        );
        assert.deepStrictEqual(extractSpecPatterns(`{ 'specPattern': ['${UI}'] }`, 'f'), [UI]);
    });

    // The maintainer's reproduction on #9760: a commented-out declaration
    // ahead of the live one used to be extracted too.
    check('a commented-out // specPattern is not read', () => {
        const source = `e2e: {\n  // specPattern: ['${FINANCE}'],\n  specPattern: ['${UI}'],\n}`;
        assert.deepStrictEqual(extractSpecPatterns(source, 'f'), [UI]);
    });

    check('a specPattern inside a /* block comment */ is not read', () => {
        // (a `**/` glob would end the comment early in real TS as well)
        const source = `/* specPattern: ['cypress/e2e/finance/*.spec.js'] */ e2e: { specPattern: '${UI}' }`;
        assert.deepStrictEqual(extractSpecPatterns(source, 'f'), [UI]);
    });

    check('a specPattern mentioned inside a string literal is not read', () => {
        const source = `const note = "overrides specPattern: ['${FINANCE}']";\ne2e: { specPattern: ['${UI}'] }`;
        assert.deepStrictEqual(extractSpecPatterns(source, 'f'), [UI]);
    });

    check('a commented-out entry inside the array is not read', () => {
        const source = `specPattern: [\n  '${UI}',\n  // '${FINANCE}', // temporarily disabled\n  /* 'cypress/e2e/old/**' */\n]`;
        assert.deepStrictEqual(extractSpecPatterns(source, 'f'), [UI]);
    });

    check('a bracket glob and a ] in a trailing comment do not truncate the array', () => {
        const source = `specPattern: [\n  'cypress/e2e/[uv]2/**/*.spec.js', // see [1]\n  '${UI}'\n]`;
        assert.deepStrictEqual(extractSpecPatterns(source, 'f'), ['cypress/e2e/[uv]2/**/*.spec.js', UI]);
    });

    check('escaped quotes and // inside a glob are kept', () => {
        assert.deepStrictEqual(extractSpecPatterns(`specPattern: ['a/\\'b\\'/**', "https://x//y"]`, 'f'), ["a/'b'/**", 'https://x//y']);
    });

    check('a regex literal containing a quote does not open a string', () => {
        const source = `const r = /it's/g;\nspecPattern: ['${UI}']`;
        assert.deepStrictEqual(extractSpecPatterns(source, 'f'), [UI]);
    });

    check('a non-literal specPattern throws instead of reading nothing', () => {
        assert.throws(() => extractSpecPatterns('specPattern: patterns', 'f'), /not a string literal/);
        assert.throws(() => extractSpecPatterns('specPattern: [...shared, "x/**"]', 'f'), /non-literal entry/);
        assert.throws(() => extractSpecPatterns('specPattern: [`${dir}/**/*.spec.js`]', 'f'), /non-literal entry/);
        assert.throws(() => extractSpecPatterns('specPattern: []', 'f'), /empty/);
        assert.throws(() => extractSpecPatterns("specPattern: ['x/**'", 'f'), /never closed/);
    });

    check('an unclassified config file is an error, base.config.ts is ignored', () => {
        assert.throws(() => selectRunnerConfigs([...RUNNER_CONFIGS, 'base.config.ts', 'extra.config.ts']), /unclassified config/);
        assert.deepStrictEqual(selectRunnerConfigs([...RUNNER_CONFIGS, 'base.config.ts']), RUNNER_CONFIGS);
    });

    // End to end: a spec under cypress/e2e/finance/ with the only covering
    // glob commented out must be reported, even though base.config.ts's
    // catch-alls would have matched it.
    check('an orphan is reported when its only covering glob is commented out', () => {
        const specs = ['cypress/e2e/finance/deposit-search.spec.js', 'cypress/e2e/ui/finance/deposit-search.spec.js'];
        const byConfig = new Map([
            ['docker-ui.config.ts', extractSpecPatterns(`specPattern: [\n  // '${FINANCE}',\n  '${UI}'\n]`, 'f')],
            ['docker.config.ts', extractSpecPatterns(`specPattern: ['cypress/e2e/api/**/*.spec.js', '${UI}']`, 'f')],
        ]);
        const { orphans } = findOrphans(specs, byConfig);
        assert.deepStrictEqual(orphans, ['cypress/e2e/finance/deposit-search.spec.js']);
    });

    check('a live glob covers the spec and an unmatched glob is reported dead', () => {
        const specs = ['cypress/e2e/ui/finance/deposit-search.spec.js'];
        const byConfig = new Map([['docker-ui.config.ts', [UI, FINANCE]]]);
        const { orphans, deadPatterns } = findOrphans(specs, byConfig);
        assert.deepStrictEqual(orphans, []);
        assert.deepStrictEqual(deadPatterns, [{ pattern: FINANCE, configs: ['docker-ui.config.ts'] }]);
    });

    console.log('Self-test:');
    console.log(lines.join('\n'));
    console.log('');
    return !failed;
}

// ---- Main ------------------------------------------------------------------

function main() {
    console.log('🔍 Cypress Spec Coverage Validation');
    console.log('===================================\n');

    if (!runSelfTest()) {
        console.error('❌ Self-test failed — the parser cannot be trusted to read cypress/configs/.');
        process.exit(1);
    }
    if (process.argv.includes('--self-test')) {
        return;
    }

    if (!fs.existsSync(E2E_ROOT)) {
        console.log('ℹ️  cypress/e2e/ not present — skipping.');
        return;
    }

    const specs = collectSpecs(E2E_ROOT, REPO_ROOT).sort();

    const configNames = fs
        .readdirSync(CONFIG_ROOT, { withFileTypes: true })
        .filter((entry) => entry.isFile() && entry.name.endsWith('.config.ts'))
        .map((entry) => entry.name);

    let runnerConfigs;
    const patternsByConfig = new Map();
    try {
        runnerConfigs = selectRunnerConfigs(configNames);
        for (const configFile of runnerConfigs) {
            const source = fs.readFileSync(path.join(CONFIG_ROOT, configFile), 'utf8');
            const patterns = extractSpecPatterns(source, configFile);
            if (patterns.length === 0) {
                throw new Error(`${configFile} declares no specPattern — cannot verify what it runs.`);
            }
            patternsByConfig.set(configFile, patterns);
        }
    } catch (e) {
        console.error(`❌ ${e.message}`);
        process.exit(1);
    }

    console.log(`Runner configs: ${runnerConfigs.length} (${runnerConfigs.join(', ')}); ignored: ${SHARED_CONFIGS.join(', ')}`);
    for (const [configFile, patterns] of patternsByConfig) {
        console.log(`   ${configFile}: ${patterns.join(', ')}`);
    }
    console.log(`Spec files found: ${specs.length}\n`);

    const { orphans, deadPatterns } = findOrphans(specs, patternsByConfig);

    if (deadPatterns.length > 0) {
        console.log('⚠️  specPatterns that currently match no spec file:');
        for (const { pattern, configs } of deadPatterns) {
            console.log(`   - ${pattern}  (${configs.join(', ')})`);
        }
        console.log('');
    }

    if (orphans.length > 0) {
        console.error('❌ Spec files matched by no runner specPattern (they never run):\n');
        for (const orphan of orphans) {
            console.error(`   - ${orphan}`);
        }
        console.error('\nMove each spec into a directory an existing specPattern covers');
        console.error('(e.g. cypress/e2e/ui/ or cypress/e2e/api/), or add a specPattern');
        console.error('for its directory in a runner config in cypress/configs/ and wire it into CI.');
        process.exit(1);
    }

    console.log('✅ Every Cypress spec is matched by at least one runner specPattern.');
}

main();
