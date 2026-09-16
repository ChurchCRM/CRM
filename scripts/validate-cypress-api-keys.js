#!/usr/bin/env node

/**
 * Verifies every `*.api.key` Cypress env value in cypress/configs/*.ts is a key
 * that cypress/data/seed.sql actually seeds into user_usr.usr_ApiKey.
 *
 * A config key that matches no seeded user does not fail loudly — the API just
 * answers 401 "Invalid API key" instead of the authorization status the test
 * meant to assert, and the author has no obvious way to see why. #9730:
 * docker-admin.config.ts carried a nofinance.api.key that existed nowhere,
 * turning every would-be 403 assertion in that suite into a 401.
 *
 * Run: `npm run lint:cypress-api-keys`
 */

const fs = require('node:fs');
const path = require('node:path');

const REPO_ROOT = path.join(__dirname, '..');
const CONFIG_ROOT = path.join(REPO_ROOT, 'cypress', 'configs');
const SEED_FILE = path.join(REPO_ROOT, 'cypress', 'data', 'seed.sql');

console.log('🔍 Cypress API Key Seed Validation');
console.log('==================================\n');

if (!fs.existsSync(SEED_FILE)) {
    console.error('❌ cypress/data/seed.sql not found — cannot verify API keys.');
    process.exit(1);
}

const seedSql = fs.readFileSync(SEED_FILE, 'utf8');

/**
 * Collect every single-quoted literal appearing in a `user_usr` INSERT. The
 * API key column sits among them, so membership in this set means "some seeded
 * user carries this value" — narrower than a bare substring search over the
 * whole dump, which would also match a comment or an unrelated table.
 */
const seededUserLiterals = new Set();
const userInsert = /INSERT INTO `user_usr` VALUES([\s\S]*?);\s*$/gm;
let insertMatch = userInsert.exec(seedSql);
while (insertMatch !== null) {
    const literals = insertMatch[1].match(/'(?:[^'\\]|\\.)*'/g) || [];
    for (const literal of literals) {
        seededUserLiterals.add(literal.slice(1, -1));
    }
    insertMatch = userInsert.exec(seedSql);
}

if (seededUserLiterals.size === 0) {
    console.error('❌ No `INSERT INTO `user_usr`` rows found in seed.sql — the parser needs updating.');
    process.exit(1);
}

const configFiles = fs
    .readdirSync(CONFIG_ROOT, { withFileTypes: true })
    .filter((entry) => entry.isFile() && entry.name.endsWith('.config.ts'))
    .map((entry) => entry.name)
    .sort();

/** @type {{config: string, name: string, value: string}[]} */
const declaredKeys = [];
for (const configFile of configFiles) {
    const source = fs.readFileSync(path.join(CONFIG_ROOT, configFile), 'utf8');
    const envEntry = /['"]([\w.-]*api\.key)['"]\s*:\s*['"]([^'"]*)['"]/g;
    let match = envEntry.exec(source);
    while (match !== null) {
        declaredKeys.push({ config: configFile, name: match[1], value: match[2] });
        match = envEntry.exec(source);
    }
}

console.log(`Configs scanned: ${configFiles.length}`);
console.log(`Seeded user_usr literals: ${seededUserLiterals.size}`);
console.log(`*.api.key entries declared: ${declaredKeys.length}\n`);

const dead = declaredKeys.filter((entry) => !seededUserLiterals.has(entry.value));

// The same logical role must resolve to the same key everywhere, otherwise a
// spec asserts a different user's permissions depending on which suite runs it.
/** @type {Map<string, Map<string, string[]>>} key name → value → configs */
const byName = new Map();
for (const entry of declaredKeys) {
    if (!byName.has(entry.name)) {
        byName.set(entry.name, new Map());
    }
    const values = byName.get(entry.name);
    if (!values.has(entry.value)) {
        values.set(entry.value, []);
    }
    values.get(entry.value).push(entry.config);
}
const divergent = [...byName.entries()].filter(([, values]) => values.size > 1);

let failed = false;

if (dead.length > 0) {
    failed = true;
    console.error('❌ API keys that match no seeded user in cypress/data/seed.sql:\n');
    for (const entry of dead) {
        console.error(`   - ${entry.config}: ${entry.name} = ${entry.value}`);
    }
    console.error('\nAuthenticated requests with these keys return 401 "Invalid API key",');
    console.error('not the authorization status the test intends to assert.\n');
}

if (divergent.length > 0) {
    failed = true;
    console.error('❌ The same *.api.key resolves to different users across configs:\n');
    for (const [name, values] of divergent) {
        console.error(`   - ${name}`);
        for (const [value, configs] of values) {
            console.error(`       ${value}  (${configs.join(', ')})`);
        }
    }
    console.error('');
}

if (failed) {
    process.exit(1);
}

console.log('✅ Every Cypress *.api.key matches a seeded user and is consistent across configs.');
