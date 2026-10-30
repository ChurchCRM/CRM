#!/usr/bin/env node

/**
 * Keeps a capture that docs or the website link from being deleted or renamed.
 *
 * A Playwright capture marks itself with `usedIn: ['docs' | 'website']` in its
 * spec. Every capture marked on the base ref (default origin/master) must still
 * exist in the working tree. To retire one, fix the links first, clear its
 * `usedIn` in a merged PR, then delete it. Rules:
 * .agents/skills/churchcrm/marketing-visuals-pipeline.md "Marking used captures".
 *
 *   npm run lint
 *   CAPTURE_BASE_REF=origin/master node scripts/validate-capture-used-in.js
 */

const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const REPO_ROOT = path.join(__dirname, '..');
const SPEC_DIRS = ['playwright/workflows', 'playwright/videos', 'playwright/setup'];
const BASE_REF = process.env.CAPTURE_BASE_REF || 'origin/master';

// Capture options and the dashboards data rows are flat object literals (no nested braces).
const OBJECT_LITERAL = /\{[^{}]*\bname:\s*'([^']+)'[^{}]*\}/g;

function usedNames(source) {
    const used = new Set();
    for (const [literal, name] of source.matchAll(OBJECT_LITERAL)) {
        if (/usedIn:\s*\[\s*'/.test(literal)) {
            used.add(name);
        }
    }
    return used;
}

function allNames(source) {
    return new Set([...source.matchAll(OBJECT_LITERAL)].map((m) => m[1]));
}

function git(...args) {
    return execFileSync('git', args, { cwd: REPO_ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
}

try {
    git('rev-parse', '--verify', `${BASE_REF}^{commit}`);
} catch {
    console.log(`capture used-in check skipped: ${BASE_REF} is not available in this checkout.`);
    process.exit(0);
}

const baseUsed = new Set();
for (const file of git('ls-tree', '-r', '--name-only', BASE_REF, ...SPEC_DIRS).split('\n').filter(Boolean)) {
    for (const name of usedNames(git('show', `${BASE_REF}:${file}`))) {
        baseUsed.add(name);
    }
}

const headNames = new Set();
for (const dir of SPEC_DIRS) {
    const abs = path.join(REPO_ROOT, dir);
    if (!fs.existsSync(abs)) {
        continue;
    }
    for (const file of fs.readdirSync(abs).filter((f) => f.endsWith('.ts'))) {
        for (const name of allNames(fs.readFileSync(path.join(abs, file), 'utf8'))) {
            headNames.add(name);
        }
    }
}

const missing = [...baseUsed].filter((name) => !headNames.has(name)).sort();
if (missing.length > 0) {
    console.error(`Captures used by docs or the website were removed or renamed (vs ${BASE_REF}):`);
    for (const name of missing) {
        console.error(`  - ${name}`);
    }
    console.error('Keep them. To retire one, update the docs/website links and clear `usedIn` in an earlier merged PR.');
    process.exit(1);
}

console.log(`capture used-in check passed: ${baseUsed.size} used captures still present.`);
