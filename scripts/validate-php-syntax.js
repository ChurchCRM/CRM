#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

console.log('🔍 PHP Syntax Validation');
console.log('========================\n');

const srcDir = path.join(__dirname, '../src');

// Collect PHP files to validate.
// When signatures.json is available (post-build), use it for the canonical file list.
// Otherwise fall back to scanning src/ directly so the script works standalone
// (e.g. when invoked via `npm run build:php` without a prior full build).
let phpFiles = [];

const signaturesPath = path.join(srcDir, 'admin/data/signatures.json');

if (fs.existsSync(signaturesPath)) {
    const signatures = JSON.parse(fs.readFileSync(signaturesPath, 'utf8'));
    if (!signatures.files || !Array.isArray(signatures.files)) {
        console.error('❌ Error: Invalid signatures file format');
        process.exit(1);
    }
    console.log(`📋 Using signatures.json (${signatures.files.length} entries)\n`);
    phpFiles = signatures.files
        .map((f) => (typeof f === 'string' ? f : f.filename))
        .filter((f) => f.endsWith('.php') && !f.includes('/vendor/') && !f.startsWith('vendor/'))
        .map((f) => path.join(srcDir, f.replace(/\//g, path.sep)));
} else {
    console.log('ℹ️  signatures.json not found — scanning src/ directly\n');
    function walkPhp(dir) {
        const results = [];
        for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
            const full = path.join(dir, entry.name);
            if (entry.isDirectory()) {
                if (entry.name === 'vendor') continue;
                results.push(...walkPhp(full));
            } else if (entry.isFile() && entry.name.endsWith('.php')) {
                results.push(full);
            }
        }
        return results;
    }
    phpFiles = walkPhp(srcDir);
    console.log(`📋 Found ${phpFiles.length} PHP files in src/\n`);
}

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
