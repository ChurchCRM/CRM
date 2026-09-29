#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const fullScan = process.argv.includes('--all');

console.log('🔍 PHP Syntax Validation');
console.log('========================\n');

const srcDir = path.join(__dirname, '../src');
const signaturesPath = path.join(srcDir, 'admin/data/signatures.json');

// Generate signatures.json on-the-fly if it doesn't exist yet so that the
// script works standalone without a prior full build.
if (!fs.existsSync(signaturesPath)) {
    console.log('ℹ️  signatures.json not found — generating now...\n');
    try {
        execSync(`node "${path.join(__dirname, 'generate-signatures-node.js')}"`, {
            stdio: 'inherit',
            encoding: 'utf8'
        });
    } catch (err) {
        console.error('❌ Error: Failed to generate signatures.json');
        console.error(err.message || err);
        process.exit(1);
    }
}

const signatures = JSON.parse(fs.readFileSync(signaturesPath, 'utf8'));

if (!signatures.files || !Array.isArray(signatures.files)) {
    console.error('❌ Error: Invalid signatures file format');
    process.exit(1);
}

console.log(`📋 Found ${signatures.files.length} files in signatures\n`);

let phpFiles = signatures.files
    .map((f) => (typeof f === 'string' ? f : f.filename))
    .filter((f) => f.endsWith('.php') && !f.includes('/vendor/') && !f.startsWith('vendor/'))
    .map((f) => path.join(srcDir, f.replace(/\//g, path.sep)));

// By default, only validate files that actually changed (staged, unstaged, or
// committed-but-unmerged vs the base branch). Full-repo scans belong in CI
// (`npm run build:php:validate:all`) — running php -l on 700+ files on every
// local build/commit is overkill for a diff touching a handful of files.
if (!fullScan) {
    const repoRoot = path.join(__dirname, '..');
    const runGit = (args) => {
        try {
            return execSync(`git ${args}`, { cwd: repoRoot, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] })
                .split('\n')
                .map((l) => l.trim())
                .filter(Boolean);
        } catch {
            return null;
        }
    };

    const mergeBase =
        runGit('merge-base HEAD origin/master')?.[0] || runGit('merge-base HEAD master')?.[0] || null;

    const changedLists = [
        mergeBase ? runGit(`diff --name-only ${mergeBase} HEAD`) : null,
        runGit('diff --name-only HEAD'),
        runGit('ls-files --others --exclude-standard'),
    ];

    if (changedLists.every((l) => l === null)) {
        console.log('⚠️  Not a git repo (or git unavailable) — falling back to full scan.\n');
    } else {
        const changedSet = new Set(
            changedLists
                .filter(Boolean)
                .flat()
                .map((f) => path.join(repoRoot, f))
        );
        phpFiles = phpFiles.filter((f) => changedSet.has(f));
        console.log(`🔎 Scoped run: checking ${phpFiles.length} changed PHP file(s) (use --all for a full scan)\n`);
    }
}

let errors = 0;
let validated = 0;
let notFound = 0;

if (phpFiles.length === 0) {
    console.log('✨ No changed PHP files to validate.');
    process.exit(0);
}

for (const filePath of phpFiles) {
    if (!fs.existsSync(filePath)) {
        notFound++;
        continue;
    }

    try {
        // Run php -l on the file
        execSync(`php -l "${filePath}"`, {
            stdio: 'pipe',
            encoding: 'utf8'
        });
        validated++;
        process.stdout.write('.');

        // Print progress every 50 files
        if (validated % 50 === 0) {
            console.log(` ${validated}`);
        }
    } catch (error) {
        errors++;
        const rel = path.relative(srcDir, filePath);
        console.log(`\n❌ SYNTAX ERROR: ${rel}`);
        console.log(error.stderr || error.message);
    }
}

console.log(`\n\n${'='.repeat(60)}`);
console.log(`✅ Validated: ${validated} PHP files`);
if (notFound > 0) {
    console.log(`⚠️  Not found: ${notFound} PHP files`);
}
if (errors > 0) {
    console.log(`❌ Errors: ${errors} files`);
    console.log('='.repeat(60));
    process.exit(1);
} else {
    console.log(`✨ All PHP files passed syntax validation!`);
    console.log('='.repeat(60));
}
