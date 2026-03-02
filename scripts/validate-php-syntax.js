#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

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

const phpFiles = signatures.files
    .map((f) => (typeof f === 'string' ? f : f.filename))
    .filter((f) => f.endsWith('.php') && !f.includes('/vendor/') && !f.startsWith('vendor/'))
    .map((f) => path.join(srcDir, f.replace(/\//g, path.sep)));

let errors = 0;
let validated = 0;
let notFound = 0;

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
