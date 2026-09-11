#!/usr/bin/env node

/**
 * Verifies every Cypress spec under cypress/e2e/ is reachable by at least one
 * `specPattern` in cypress/configs/*.ts.
 *
 * A spec that matches no config's specPattern is never executed by any runner
 * or CI job, but it still looks like coverage in code review — it rots
 * silently against the page it was written for. `cypress/e2e/finance/
 * deposit-search.spec.js` sat in that state for a whole release (#9728): 266
 * lines of assertions, zero runs.
 *
 * The matcher here is `minimatch`, the same library Cypress uses to resolve
 * `specPattern`, so a spec this script calls reachable really is picked up by
 * the runner.
 *
 * Run: `npm run lint:cypress-spec-coverage`
 */

const fs = require('node:fs');
const path = require('node:path');
const { minimatch } = require('minimatch');

const REPO_ROOT = path.join(__dirname, '..');
const E2E_ROOT = path.join(REPO_ROOT, 'cypress', 'e2e');
const CONFIG_ROOT = path.join(REPO_ROOT, 'cypress', 'configs');
const SPEC_EXTENSIONS = ['.spec.js', '.spec.ts'];

console.log('🔍 Cypress Spec Coverage Validation');
console.log('===================================\n');

if (!fs.existsSync(E2E_ROOT)) {
    console.log('ℹ️  cypress/e2e/ not present — skipping.');
    process.exit(0);
}

/**
 * Recursively collect every spec file under cypress/e2e/, as a repo-relative
 * POSIX path (the form Cypress matches specPattern against).
 * @param {string} dir
 * @returns {string[]}
 */
function collectSpecs(dir) {
    const found = [];
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const fullPath = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            found.push(...collectSpecs(fullPath));
        } else if (entry.isFile() && SPEC_EXTENSIONS.some((ext) => entry.name.endsWith(ext))) {
            found.push(path.relative(REPO_ROOT, fullPath).split(path.sep).join('/'));
        }
    }
    return found;
}

/**
 * Read one JS/TS string literal starting at `source[start]` (which must be a
 * quote character). Returns the unquoted value and the index just past the
 * closing quote. Backslash escapes are honoured so a quote inside the literal
 * does not end it.
 * @param {string} source
 * @param {number} start
 * @returns {{ value: string, end: number }}
 */
function readStringLiteral(source, start) {
    const quote = source[start];
    let value = '';
    let i = start + 1;
    while (i < source.length) {
        const char = source[i];
        if (char === '\\') {
            value += source[i + 1] ?? '';
            i += 2;
            continue;
        }
        if (char === quote) {
            return { value, end: i + 1 };
        }
        value += char;
        i++;
    }
    return { value, end: i };
}

/**
 * Extract the literal glob strings from a config's `specPattern` declaration.
 * Every config in cypress/configs/ declares it as a string literal or an array
 * of string literals, so a source scan avoids having to transpile + execute
 * TypeScript just to read a constant (the repo has no ts-node/tsx to do that
 * with).
 *
 * The array is consumed by tracking bracket depth rather than by a regex, with
 * string literals read via `readStringLiteral` and comments skipped, so their
 * contents are never mistaken for syntax. A glob may therefore contain a
 * bracket expression (minimatch supports `cypress/e2e/[uv]2/...`) or a quote,
 * and a `]` in a trailing comment no longer closes the array early — all three
 * truncated the match under the previous `[^\]]*` regex.
 * @param {string} source
 * @returns {string[]}
 */
function extractSpecPatterns(source) {
    const patterns = [];
    const declaration = /specPattern:\s*/g;
    let match = declaration.exec(source);
    while (match !== null) {
        let i = match.index + match[0].length;

        if (source[i] === "'" || source[i] === '"' || source[i] === '`') {
            const literal = readStringLiteral(source, i);
            patterns.push(literal.value);
            declaration.lastIndex = literal.end;
        } else if (source[i] === '[') {
            let depth = 0;
            while (i < source.length) {
                const char = source[i];
                if (char === "'" || char === '"' || char === '`') {
                    const literal = readStringLiteral(source, i);
                    patterns.push(literal.value);
                    i = literal.end;
                    continue;
                }
                if (char === '/' && source[i + 1] === '/') {
                    const lineEnd = source.indexOf('\n', i);
                    i = lineEnd === -1 ? source.length : lineEnd;
                    continue;
                }
                if (char === '/' && source[i + 1] === '*') {
                    const blockEnd = source.indexOf('*/', i + 2);
                    i = blockEnd === -1 ? source.length : blockEnd + 2;
                    continue;
                }
                if (char === '[') {
                    depth++;
                } else if (char === ']') {
                    depth--;
                    if (depth === 0) {
                        i++;
                        break;
                    }
                }
                i++;
            }
            declaration.lastIndex = i;
        }

        match = declaration.exec(source);
    }
    return patterns;
}

const specs = collectSpecs(E2E_ROOT).sort();

const configFiles = fs
    .readdirSync(CONFIG_ROOT, { withFileTypes: true })
    .filter((entry) => entry.isFile() && entry.name.endsWith('.config.ts'))
    .map((entry) => entry.name)
    .sort();

/** @type {Map<string, string[]>} pattern → configs declaring it */
const patternSources = new Map();
for (const configFile of configFiles) {
    const source = fs.readFileSync(path.join(CONFIG_ROOT, configFile), 'utf8');
    const patterns = extractSpecPatterns(source);
    if (patterns.length === 0) {
        console.error(`❌ ${configFile} declares no specPattern — cannot verify what it runs.`);
        process.exit(1);
    }
    for (const pattern of patterns) {
        if (!patternSources.has(pattern)) {
            patternSources.set(pattern, []);
        }
        patternSources.get(pattern).push(configFile);
    }
}

const allPatterns = [...patternSources.keys()];
console.log(`Configs scanned: ${configFiles.length} (${configFiles.join(', ')})`);
console.log(`Distinct specPatterns: ${allPatterns.length}`);
console.log(`Spec files found: ${specs.length}\n`);

const orphans = specs.filter((spec) => !allPatterns.some((pattern) => minimatch(spec, pattern)));

const deadPatterns = allPatterns.filter((pattern) => !specs.some((spec) => minimatch(spec, pattern)));
if (deadPatterns.length > 0) {
    console.log('⚠️  specPatterns that currently match no spec file:');
    for (const pattern of deadPatterns) {
        console.log(`   - ${pattern}  (${patternSources.get(pattern).join(', ')})`);
    }
    console.log('');
}

if (orphans.length > 0) {
    console.error('❌ Spec files matched by no config specPattern (they never run):\n');
    for (const orphan of orphans) {
        console.error(`   - ${orphan}`);
    }
    console.error('\nMove each spec into a directory an existing specPattern covers');
    console.error('(e.g. cypress/e2e/ui/ or cypress/e2e/api/), or add a specPattern');
    console.error('for its directory in cypress/configs/ and wire it into CI.');
    process.exit(1);
}

console.log('✅ Every Cypress spec is matched by at least one config specPattern.');
