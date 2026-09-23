#!/usr/bin/env node

/**
 * Keeps cypress/support/commands.d.ts honest.
 *
 * The declaration file is the only thing under cypress/ that the root
 * tsconfig.json compiles (the 200+ specs are .js with allowJs unset, and
 * cypress/e2e/** is excluded), so nothing ever compared the declared command
 * surface against the commands actually registered with
 * Cypress.Commands.add(). It drifted: four declared commands did not exist and
 * twenty-one real ones were undeclared (issue #9731).
 *
 * This check parses both sides and fails when they disagree in either
 * direction:
 *   - declared but never registered  → a spec can be written against a command
 *                                      that does not exist
 *   - registered but not declared    → no editor/type support for a real command
 */

const fs = require('fs');
const path = require('path');

const SUPPORT_DIR = path.join(__dirname, '..', 'cypress', 'support');
const DECLARATIONS_FILE = path.join(SUPPORT_DIR, 'commands.d.ts');

console.log('🔍 Cypress Custom Command Declaration Check');
console.log('==========================================\n');

/** Command names passed to Cypress.Commands.add() across cypress/support/*.js */
function collectDefinedCommands() {
    const defined = new Set();
    const files = fs
        .readdirSync(SUPPORT_DIR, { withFileTypes: true })
        .filter((entry) => entry.isFile() && entry.name.endsWith('.js'))
        .map((entry) => path.join(SUPPORT_DIR, entry.name));

    for (const filePath of files) {
        const content = fs.readFileSync(filePath, 'utf8');
        // Cypress.Commands.add('name', ... — the name may sit on the next line.
        const pattern = /Cypress\.Commands\.add\(\s*['"]([A-Za-z0-9_$]+)['"]/g;
        let match = pattern.exec(content);
        while (match !== null) {
            defined.add(match[1]);
            match = pattern.exec(content);
        }
    }

    return defined;
}

/**
 * Body of the `interface Chainable { ... }` block, found by brace matching.
 * Returns null when the interface is missing, which the caller treats as fatal.
 */
function extractChainableBody(content) {
    const header = /\binterface\s+Chainable\b[^{]*\{/.exec(content);
    if (header === null) {
        return null;
    }

    const bodyStart = header.index + header[0].length;
    let depth = 1;
    for (let i = bodyStart; i < content.length; i++) {
        if (content[i] === '{') {
            depth++;
        } else if (content[i] === '}') {
            depth--;
            if (depth === 0) {
                return content.slice(bodyStart, i);
            }
        }
    }

    return null;
}

/** Method names declared on the Cypress.Chainable interface in commands.d.ts */
function collectDeclaredCommands() {
    const raw = fs.readFileSync(DECLARATIONS_FILE, 'utf8');
    // Strip comments first so JSDoc prose can never be mistaken for a signature.
    const content = raw.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/.*$/gm, '');

    // Only members of `interface Chainable` count. Anything outside it — a helper
    // type, an augmentation of another interface — is not a Cypress command.
    const body = extractChainableBody(content);
    if (body === null) {
        console.error('❌ Could not find `interface Chainable { ... }` in cypress/support/commands.d.ts — check the parser.');
        process.exit(1);
    }

    // Depth of every character relative to the interface body, so a method
    // shorthand nested inside an inline object type (e.g. a parameter typed
    // `{ callback(x: string): void }`) is not mistaken for a command.
    const depthAt = new Array(body.length);
    let depth = 0;
    for (let i = 0; i < body.length; i++) {
        if (body[i] === '}') {
            depth--;
        }
        depthAt[i] = depth;
        if (body[i] === '{') {
            depth++;
        }
    }

    const declared = new Set();
    // Each member declaration opens a line with `name(` at any indentation.
    const pattern = /^[ \t]*([A-Za-z_$][A-Za-z0-9_$]*)\s*\(/gm;
    let match = pattern.exec(body);
    while (match !== null) {
        if (depthAt[match.index] === 0) {
            declared.add(match[1]);
        }
        match = pattern.exec(body);
    }

    return declared;
}

const defined = collectDefinedCommands();
const declared = collectDeclaredCommands();

if (defined.size === 0) {
    console.error('❌ No Cypress.Commands.add() calls found under cypress/support/ — check the parser.');
    process.exit(1);
}

console.log(`📋 ${defined.size} command(s) registered, ${declared.size} declared in commands.d.ts\n`);

const missingDeclarations = [...defined].filter((name) => !declared.has(name)).sort();
const fictionalDeclarations = [...declared].filter((name) => !defined.has(name)).sort();

if (missingDeclarations.length > 0) {
    console.error('❌ Registered but NOT declared in cypress/support/commands.d.ts:');
    for (const name of missingDeclarations) {
        console.error(`   - cy.${name}()`);
    }
    console.error('');
}

if (fictionalDeclarations.length > 0) {
    console.error('❌ Declared in cypress/support/commands.d.ts but never registered:');
    for (const name of fictionalDeclarations) {
        console.error(`   - cy.${name}()`);
    }
    console.error('');
}

if (missingDeclarations.length > 0 || fictionalDeclarations.length > 0) {
    console.error('Fix: add or remove the declaration so commands.d.ts matches cypress/support/*.js.');
    process.exit(1);
}

console.log('✨ commands.d.ts matches the registered Cypress commands!');
